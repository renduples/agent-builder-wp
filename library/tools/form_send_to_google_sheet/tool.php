<?php
/**
 * Tool: form_send_to_google_sheet
 *
 * Send native form entries to a Google Sheet via the Sheets API v4.
 * Authenticates using a Google service account JSON credential file
 * and appends rows including a header row.
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.9.0
 *
 * php version 8.1
 */

declare( strict_types=1 );

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Send form entries to a Google Sheet via the Sheets API.
 */
class Form_Send_To_Google_Sheet extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'form_send_to_google_sheet';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function is_pro_only(): bool {
		return true;
	}

	public function get_description(): string {
		return 'Send all entries from a native form to a Google Sheet. ' .
			'Uses the Google Sheets channel credentials configured in Settings → Channels → Google Sheets. ' .
			'Override with credentials_json and spreadsheet_id if needed. ' .
			'Appends a header row followed by one row per entry.';
	}

	/**
	 * Get the tool category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'forms';
	}

	/**
	 * Get the parameter schema.
	 *
	 * @return array
	 */
	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'form_id'          => array(
					'type'        => 'integer',
					'description' => 'The ID of the native form whose entries will be sent.',
				),
				'spreadsheet_id'   => array(
					'type'        => 'string',
					'description' => 'The Google Spreadsheet ID (from the sheet URL).',
				),
				'sheet_name'       => array(
					'type'        => 'string',
					'description' => 'The name of the sheet tab. Defaults to "Sheet1".',
					'default'     => 'Sheet1',
				),
				'credentials_json' => array(
					'type'        => 'string',
					'description' => 'Optional. Path to a service account JSON file or raw JSON. If omitted, uses credentials from Settings > Channels > Google Sheets.',
				),
			),
			'required'   => array( 'form_id' ),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	public function execute( array $arguments ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'error' => 'You do not have permission to export form data.' );
		}

		if ( ! class_exists( '\Agentic\Channels\Google_Sheets_Channel' ) ) {
			return array( 'error' => 'Google Sheets integration requires Agent Builder Pro.' );
		}

		$form_id          = absint( $arguments['form_id'] ?? 0 );
		$spreadsheet_id   = sanitize_text_field( $arguments['spreadsheet_id'] ?? '' );
		$sheet_name       = sanitize_text_field( $arguments['sheet_name'] ?? 'Sheet1' );
		$credentials_json = $arguments['credentials_json'] ?? '';

		if ( 0 === $form_id ) {
			return array( 'error' => 'A form_id is required.' );
		}

		// Verify the form exists.
		$post = get_post( $form_id );
		if ( ! $post || \Agentic_Native_Forms::FORM_CPT !== $post->post_type ) {
			return array( 'error' => 'Native form with ID ' . $form_id . ' was not found.' );
		}

		// Resolve spreadsheet ID: explicit arg → per-form meta → channel default.
		if ( '' === $spreadsheet_id ) {
			$spreadsheet_id = (string) get_post_meta( $form_id, '_agentic_gs_sheet_id', true );
		}
		if ( '' === $spreadsheet_id ) {
			$spreadsheet_id = \Agentic\Channels\Google_Sheets_Channel::get_setting( 'default_sheet_id' );
		}
		if ( '' === $spreadsheet_id ) {
			return array( 'error' => 'A spreadsheet_id is required. Set one per-form or as default in Settings → Channels → Google Sheets.' );
		}

		// Resolve sheet tab: explicit arg → per-form meta → "Sheet1".
		if ( 'Sheet1' === $sheet_name ) {
			$form_tab = (string) get_post_meta( $form_id, '_agentic_gs_sheet_tab', true );
			if ( '' !== $form_tab ) {
				$sheet_name = $form_tab;
			}
		}

		// Load credentials: explicit argument → channel settings → error.
		if ( '' !== $credentials_json ) {
			$credentials = $this->load_credentials( $credentials_json );
			if ( is_string( $credentials ) ) {
				return array( 'error' => $credentials );
			}
		} else {
			$credentials = \Agentic\Channels\Google_Sheets_Channel::get_credentials_array();
			if ( null === $credentials ) {
				return array( 'error' => 'No Google credentials configured. Set them up in Settings → Channels → Google Sheets, or pass credentials_json directly.' );
			}
		}

		// Get access token.
		$token = $this->get_access_token( $credentials );
		if ( is_string( $token ) ) {
			return array( 'error' => $token );
		}

		// Fetch all entries.
		$engine     = \Agentic_Native_Forms::get_instance();
		$definition = $engine->get_definition( $form_id );
		$fields     = $definition['fields'] ?? array();

		// Build header row from field labels.
		$headers = array( 'Entry ID', 'Submitted' );
		foreach ( $fields as $field ) {
			$headers[] = $field['label'] ?? $field['name'] ?? 'Field';
		}

		// Collect all entry rows.
		$rows       = array( $headers );
		$page       = 1;
		$per_page   = 100;
		$total_sent = 0;

		do {
			$result  = $engine->get_entries( $form_id, $per_page, $page );
			$entries = $result['entries'] ?? array();

			foreach ( $entries as $entry ) {
				$row   = array();
				$row[] = (string) ( $entry['entry_id'] ?? '' );
				$row[] = $entry['submitted'] ?? '';

				foreach ( $fields as $field ) {
					$name = sanitize_key( $field['name'] ?? '' );
					$val  = $entry['fields'][ $name ] ?? array();
					// Payload stores { label, value } arrays.
					$value = is_array( $val ) && isset( $val['value'] ) ? $val['value'] : ( is_array( $val ) ? implode( ', ', $val ) : (string) $val );
					$row[] = (string) $value;
				}

				$rows[] = $row;
				++$total_sent;
			}

			$entries_count = count( $entries );
			++$page;
		} while ( $entries_count === $per_page );

		if ( $total_sent < 1 ) {
			return array(
				'form_id'        => $form_id,
				'rows_sent'      => 0,
				'spreadsheet_id' => $spreadsheet_id,
				'sheet_name'     => $sheet_name,
				'message'        => 'No entries found for this form.',
			);
		}

		// Append rows to Google Sheet.
		$range   = $sheet_name . '!A1';
		$api_url = sprintf(
			'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s:append?valueInputOption=USER_ENTERED',
			rawurlencode( $spreadsheet_id ),
			rawurlencode( $range )
		);

		$response = wp_remote_post(
			$api_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token['access_token'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'values' => $rows ) ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'error' => 'Google Sheets API request failed: ' . $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status < 200 || $status >= 300 ) {
			$api_error = $body['error']['message'] ?? 'Unknown error (HTTP ' . $status . ')';
			return array( 'error' => 'Google Sheets API error: ' . $api_error );
		}

		return array(
			'rows_sent'      => $total_sent,
			'spreadsheet_id' => $spreadsheet_id,
			'sheet_name'     => $sheet_name,
			'message'        => $total_sent . ' entries (plus header) appended to the Google Sheet.',
		);
	}

	/**
	 * Load service account credentials from a file path or raw JSON string.
	 *
	 * @param string $input File path or JSON string.
	 * @return array|string Credentials array or error message string.
	 */
	private function load_credentials( string $input ) {
		$json = $input;

		// If it looks like a file path, try to read it.
		if ( ! str_starts_with( trim( $input ), '{' ) ) {
			if ( ! file_exists( $input ) || ! is_readable( $input ) ) {
				return 'Credentials file not found or not readable: ' . $input;
			}
			$json = file_get_contents( $input ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}

		$credentials = json_decode( $json, true );
		if ( ! is_array( $credentials ) || empty( $credentials['client_email'] ) || empty( $credentials['private_key'] ) ) {
			return 'Invalid service account credentials. Ensure the JSON contains client_email and private_key.';
		}

		return $credentials;
	}

	/**
	 * Exchange service account credentials for an OAuth2 access token via JWT.
	 *
	 * @param array $credentials Service account credentials.
	 * @return array|string Token array with access_token key, or error message string.
	 */
	private function get_access_token( array $credentials ) {
		$now    = time();
		$header = wp_json_encode(
			array(
				'alg' => 'RS256',
				'typ' => 'JWT',
			)
		);
		$claims = wp_json_encode(
			array(
				'iss'   => $credentials['client_email'],
				'scope' => 'https://www.googleapis.com/auth/spreadsheets',
				'aud'   => 'https://oauth2.googleapis.com/token',
				'iat'   => $now,
				'exp'   => $now + 3600,
			)
		);

		$segments   = array();
		$segments[] = $this->base64url_encode( $header );
		$segments[] = $this->base64url_encode( $claims );
		$signing    = implode( '.', $segments );

		$signature = '';
		$success   = openssl_sign( $signing, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256 );
		if ( ! $success ) {
			return 'Failed to sign JWT with the provided private key.';
		}

		$segments[] = $this->base64url_encode( $signature );
		$jwt        = implode( '.', $segments );

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return 'Token exchange failed: ' . $response->get_error_message();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$err = $body['error_description'] ?? $body['error'] ?? 'Unknown token exchange error';
			return 'Token exchange failed: ' . $err;
		}

		return $body;
	}

	/**
	 * Base64url-encode a string (no padding).
	 *
	 * @param string $data Raw data.
	 * @return string
	 */
	private function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}
}

return new Form_Send_To_Google_Sheet();
