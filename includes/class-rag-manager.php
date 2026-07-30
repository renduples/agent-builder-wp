<?php
/**
 * RAG Manager — Vector Store & Train on Data handlers
 *
 * Extracted from the main Plugin class to reduce file size.
 * Handles all RAG API communication and AJAX endpoints for the
 * Train on Data admin page and credits/pricing on the Costs page.
 *
 * @package    Agent_Builder
 * @subpackage Includes
 * @since      2.5.0
 */

declare(strict_types=1);

namespace Agentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages RAG (Retrieval-Augmented Generation) API calls and admin AJAX.
 */
class RAG_Manager {

	/**
	 * Return the RAG API base URL from the service registry.
	 */
	private static function api_base(): string {
		return Service_Registry::url( 'agentic-rag' );
	}

	// ── RAG API helpers ──────────────────────────────────────────────────────

	/**
	 * Whether credentials needed for the RAG API are configured.
	 */
	public static function has_credentials(): bool {
		$secret = get_option( 'agentic_rag_api_secret', '' );
		if ( ! $secret ) {
			$secret = Provider_Registry::get( 'agentic' )['api_key'] ?? '';
		}
		if ( empty( $secret ) ) {
			return false;
		}
		$license = get_option( License_Client::OPTION_LICENSE_KEY, '' );
		return ! empty( $license );
	}

	/**
	 * License key used as RAG user_id, without terminating the request.
	 *
	 * @return string Empty when unset.
	 */
	public static function get_user_id_safe(): string {
		return (string) get_option( License_Client::OPTION_LICENSE_KEY, '' );
	}

	/**
	 * Call the RAG API.
	 *
	 * @param  string               $endpoint Endpoint path (e.g. '/sources').
	 * @param  string               $method   HTTP method.
	 * @param  array<string, mixed> $body     Request body (for POST/DELETE) or query params (GET).
	 * @param  array<string, mixed> $options  Optional: timeout (int seconds).
	 * @return array|\WP_Error  Decoded response or WP_Error.
	 */
	public static function api_request( string $endpoint, string $method = 'GET', array $body = array(), array $options = array() ) {
		$secret = get_option( 'agentic_rag_api_secret', '' );
		if ( ! $secret ) {
			$secret = Provider_Registry::get( 'agentic' )['api_key'] ?? '';
		}

		if ( empty( $secret ) ) {
			return new \WP_Error( 'no_api_secret', __( 'RAG API secret is not configured.', 'agent-builder' ) );
		}

		$timeout = isset( $options['timeout'] ) ? max( 3, (int) $options['timeout'] ) : 60;

		$args = array(
			'method'  => $method,
			'timeout' => $timeout,
			'headers' => array(
				'X-API-Key'        => $secret,
				'Content-Type'     => 'application/json',
				'X-Plugin-Version' => AGENT_BUILDER_VERSION,
			),
		);

		if ( ! empty( $body ) && in_array( $method, array( 'POST', 'DELETE' ), true ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$url = self::api_base() . $endpoint;
		if ( 'GET' === $method && ! empty( $body ) ) {
			$url = add_query_arg( $body, $url );
			unset( $args['body'] );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			return new \WP_Error( 'rag_api_error', self::format_api_error( $data, $code ) );
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Normalize RAG API error payloads (string detail or FastAPI list).
	 *
	 * @param mixed $data Response body.
	 * @param int   $code HTTP status.
	 */
	private static function format_api_error( $data, int $code ): string {
		if ( is_array( $data ) && isset( $data['detail'] ) ) {
			$detail = $data['detail'];
			if ( is_string( $detail ) ) {
				return $detail;
			}
			if ( is_array( $detail ) ) {
				$parts = array();
				foreach ( $detail as $item ) {
					if ( is_string( $item ) ) {
						$parts[] = $item;
					} elseif ( is_array( $item ) && isset( $item['msg'] ) ) {
						$parts[] = (string) $item['msg'];
					}
				}
				if ( $parts ) {
					return implode( '; ', $parts );
				}
			}
		}
		return 'RAG API error (HTTP ' . $code . ')';
	}

	/**
	 * Call the RAG API with a multipart file upload.
	 *
	 * @param  string $endpoint Endpoint path.
	 * @param  array  $file     File array from $_FILES.
	 * @param  array  $fields   Extra form fields.
	 * @return array|\WP_Error  Decoded response or WP_Error.
	 */
	public static function api_upload( string $endpoint, array $file, array $fields = array() ) {
		$secret = get_option( 'agentic_rag_api_secret', '' );
		if ( ! $secret ) {
			$secret = Provider_Registry::get( 'agentic' )['api_key'] ?? '';
		}

		if ( empty( $secret ) ) {
			return new \WP_Error( 'no_api_secret', __( 'RAG API secret is not configured.', 'agent-builder' ) );
		}

		$boundary = wp_generate_password( 24, false );
		$body     = '';

		foreach ( $fields as $key => $val ) {
			$body .= '--' . $boundary . "\r\n";
			$body .= 'Content-Disposition: form-data; name="' . $key . '"' . "\r\n\r\n";
			$body .= $val . "\r\n";
		}

		// File part.
		$body .= '--' . $boundary . "\r\n";
		$body .= 'Content-Disposition: form-data; name="file"; filename="' . basename( $file['name'] ) . '"' . "\r\n";
		$body .= 'Content-Type: ' . ( ! empty( $file['type'] ) ? $file['type'] : 'application/octet-stream' ) . "\r\n\r\n";

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading uploaded temp file.
		$body .= file_get_contents( $file['tmp_name'] ) . "\r\n";
		$body .= '--' . $boundary . '--' . "\r\n";

		$response = wp_remote_post(
			self::api_base() . $endpoint,
			array(
				'method'  => 'POST',
				'timeout' => 120,
				'headers' => array(
					'X-API-Key'        => $secret,
					'Content-Type'     => 'multipart/form-data; boundary=' . $boundary,
					'X-Plugin-Version' => AGENT_BUILDER_VERSION,
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			return new \WP_Error( 'rag_api_error', self::format_api_error( $data, $code ) );
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Get the license key used as user ID for the RAG service (AJAX only).
	 *
	 * Terminates with wp_send_json_error when missing — safe only inside AJAX handlers.
	 * Prefer get_user_id_safe() from CLI / chat retrieval.
	 *
	 * @return string
	 */
	public static function get_user_id(): string {
		$license_key = self::get_user_id_safe();
		if ( empty( $license_key ) ) {
			wp_send_json_error( 'No license key configured. Please activate your license first.', 403 );
		}
		return $license_key;
	}

	/**
	 * Capability check for Vector Store admin actions.
	 */
	public static function current_user_can_manage(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'agentic_manage_settings' );
	}

	// ── AJAX handlers ────────────────────────────────────────────────────────

	/**
	 * Vector Store AJAX requires Agent Builder Pro (hosted RAG is a Pro feature).
	 *
	 * Free Knowledge Wiki (OKF) does not use these endpoints. Callers that hit
	 * them without Pro receive a clear upgrade message — free features stay intact.
	 *
	 * @return void
	 */
	private static function require_pro(): void {
		if ( class_exists( '\Agentic\License_Client' ) && License_Client::get_instance()->is_pro() ) {
			return;
		}
		wp_send_json_error(
			__( 'The hosted Vector Store is available in Agent Builder Pro. Use the free Knowledge Wiki (OKF) tab for local curated knowledge, or upgrade at agentic-plugin.com/pricing/.', 'agent-builder' ),
			403
		);
	}

	/**
	 * Query the hosted vector store (Pro). Used by chat retrieval, CLI, and tools.
	 *
	 * Matches agentic-services POST /query:
	 * body { user_id, query, top_k } → { results: [{ id, distance, text }], count, credits_used }.
	 *
	 * @param string               $query   Search query.
	 * @param int                  $limit   Max chunks (1–20).
	 * @param array<string, mixed> $options Optional: timeout (int seconds).
	 * @return array|\WP_Error Decoded hits or error.
	 */
	public static function search( string $query, int $limit = 5, array $options = array() ) {
		if ( ! class_exists( '\Agentic\License_Client' ) || ! License_Client::get_instance()->is_pro() ) {
			return new \WP_Error( 'pro_required', __( 'Vector search requires Agent Builder Pro.', 'agent-builder' ) );
		}

		$query = trim( wp_strip_all_tags( $query ) );
		if ( '' === $query ) {
			return new \WP_Error( 'empty_query', __( 'Search query is empty.', 'agent-builder' ) );
		}

		if ( ! self::has_credentials() ) {
			return new \WP_Error( 'no_credentials', __( 'RAG API secret or license key is not configured.', 'agent-builder' ) );
		}

		$user_id = self::get_user_id_safe();
		$top_k   = max( 1, min( 20, $limit ) );

		return self::api_request(
			'/query',
			'POST',
			array(
				'user_id' => $user_id,
				'query'   => $query,
				'top_k'   => $top_k,
			),
			$options
		);
	}

	/**
	 * AJAX: Get balance bar overview (credits + source count).
	 *
	 * @return void
	 */
	public static function ajax_get_overview(): void {
		check_ajax_referer( 'agentic_train_data' );
		if ( ! self::current_user_can_manage() ) {
			wp_send_json_error( __( 'Permission denied.', 'agent-builder' ) );
		}
		self::require_pro();

		$user_id = self::get_user_id();

		$credits = self::api_request( '/credits', 'GET', array( 'user_id' => $user_id ) );
		$sources = self::api_request( '/sources', 'GET', array( 'user_id' => $user_id ) );

		$balance      = 0;
		$source_count = 0;
		$vector_count = 0;

		if ( ! is_wp_error( $credits ) ) {
			$balance = $credits['balance'] ?? 0;
		}

		if ( ! is_wp_error( $sources ) && ! empty( $sources['sources'] ) ) {
			$source_count = count( $sources['sources'] );
			foreach ( $sources['sources'] as $src ) {
				$vector_count += (int) ( $src['chunk_count'] ?? $src['chunks'] ?? 0 );
			}
		}

		// Persist for Getting Started / has_active_knowledge (cheap, no API on dashboard).
		update_option( 'agentic_vector_source_count', (int) $source_count, false );
		if ( $source_count > 0 ) {
			update_option( 'agentic_has_knowledge', '1', false );
		}

		wp_send_json_success(
			array(
				'balance'      => $balance,
				'source_count' => $source_count,
				'vector_count' => $vector_count,
			)
		);
	}

	/**
	 * AJAX: Scan website content — returns list of published posts/pages.
	 *
	 * @return void
	 */
	public static function ajax_scan_content(): void {
		check_ajax_referer( 'agentic_train_data' );
		if ( ! self::current_user_can_manage() ) {
			wp_send_json_error( __( 'Permission denied.', 'agent-builder' ) );
		}
		self::require_pro();

		$post_type = sanitize_key( wp_unslash( $_POST['post_type'] ?? 'pages' ) );
		$limit     = absint( wp_unslash( $_POST['limit'] ?? 25 ) );
		$category  = absint( wp_unslash( $_POST['category'] ?? 0 ) );

		$types = array( 'page' );
		if ( 'posts' === $post_type ) {
			$types = array( 'post' );
		} elseif ( 'both' === $post_type ) {
			$types = array( 'page', 'post' );
		}

		$args = array(
			'post_type'      => $types,
			'post_status'    => 'publish',
			'posts_per_page' => $limit > 0 ? $limit : -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( $category > 0 ) {
			$args['cat'] = $category;
		}

		// Get trained sources to mark items.
		$user_id         = self::get_user_id();
		$sources         = self::api_request( '/sources', 'GET', array( 'user_id' => $user_id ) );
		$trained_sources = array();
		if ( ! is_wp_error( $sources ) && ! empty( $sources['sources'] ) ) {
			foreach ( $sources['sources'] as $src ) {
				$trained_sources[] = $src['source'] ?? $src['source_name'] ?? '';
			}
		}

		$query = new \WP_Query( $args );
		$items = array();

		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id     = get_the_ID();
			$content     = wp_strip_all_tags( get_the_content() );
			$source_name = 'wp-' . get_post_type() . '-' . $post_id;

			$items[] = array(
				'id'         => $post_id,
				'title'      => get_the_title(),
				'type'       => get_post_type(),
				'word_count' => str_word_count( $content ),
				'trained'    => in_array( $source_name, $trained_sources, true ),
			);
		}
		wp_reset_postdata();

		wp_send_json_success( array( 'items' => $items ) );
	}

	/**
	 * AJAX: Train a single post — sends content to RAG /train/text.
	 *
	 * @return void
	 */
	public static function ajax_train_post(): void {
		check_ajax_referer( 'agentic_train_data' );
		if ( ! self::current_user_can_manage() ) {
			wp_send_json_error( __( 'Permission denied.', 'agent-builder' ) );
		}
		self::require_pro();

		$post_id = absint( wp_unslash( $_POST['post_id'] ?? 0 ) );
		if ( $post_id <= 0 ) {
			wp_send_json_error( __( 'Invalid post ID.', 'agent-builder' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			wp_send_json_error( __( 'Post not found or not published.', 'agent-builder' ) );
		}

		$content = wp_strip_all_tags( apply_filters( 'the_content', $post->post_content ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress filter.
		if ( empty( trim( $content ) ) ) {
			wp_send_json_error( __( 'Post has no text content to train.', 'agent-builder' ) );
		}

		$user_id     = self::get_user_id();
		$source_name = 'wp-' . $post->post_type . '-' . $post_id;

		$result = self::api_request(
			'/train/text',
			'POST',
			array(
				'user_id'       => $user_id,
				'site_url'      => site_url(),
				'source'        => $source_name,
				'source_type'   => $post->post_type, // Must match RAG DB enum: file, page, post, taxonomy, text.
				'text'          => $content,
				'chunk_size'    => 500,
				'chunk_overlap' => 50,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Upload a file for training via RAG /train.
	 *
	 * @return void
	 */
	public static function ajax_upload_file(): void {
		check_ajax_referer( 'agentic_train_data' );
		if ( ! self::current_user_can_manage() ) {
			wp_send_json_error( __( 'Permission denied.', 'agent-builder' ) );
		}
		self::require_pro();

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( __( 'No file uploaded.', 'agent-builder' ) );
		}

		$file = $_FILES['file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File handled by wp_handle_upload(). File info validated below.

		// Validate file type.
		$allowed = array( 'application/pdf', 'text/plain' );
		$finfo   = finfo_open( FILEINFO_MIME_TYPE );
		$mime    = finfo_file( $finfo, $file['tmp_name'] );
		// Note: Explicit finfo_close() is deprecated; resource is released automatically.

		if ( ! in_array( $mime, $allowed, true ) ) {
			wp_send_json_error( __( 'Only PDF and text files are allowed.', 'agent-builder' ) );
		}

		// Validate file size (10 MB).
		if ( $file['size'] > 10 * 1024 * 1024 ) {
			wp_send_json_error( __( 'File exceeds 10 MB limit.', 'agent-builder' ) );
		}

		$user_id = self::get_user_id();

		$result = self::api_upload(
			'/train',
			$file,
			array(
				'user_id'  => $user_id,
				'site_url' => site_url(),
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Get training sources from RAG API.
	 *
	 * @return void
	 */
	public static function ajax_get_sources(): void {
		check_ajax_referer( 'agentic_train_data' );
		if ( ! self::current_user_can_manage() ) {
			wp_send_json_error( __( 'Permission denied.', 'agent-builder' ) );
		}
		self::require_pro();

		$user_id = self::get_user_id();
		$result  = self::api_request( '/sources', 'GET', array( 'user_id' => $user_id ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Delete a training source.
	 *
	 * @return void
	 */
	public static function ajax_delete_source(): void {
		check_ajax_referer( 'agentic_train_data' );
		if ( ! self::current_user_can_manage() ) {
			wp_send_json_error( __( 'Permission denied.', 'agent-builder' ) );
		}
		self::require_pro();

		$source = sanitize_text_field( wp_unslash( $_POST['source'] ?? '' ) );
		if ( empty( $source ) ) {
			wp_send_json_error( __( 'Missing source name.', 'agent-builder' ) );
		}

		$user_id = self::get_user_id();
		$result  = self::api_request(
			'/source',
			'DELETE',
			array(
				'user_id' => $user_id,
				'source'  => $source,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Get credit balance.
	 *
	 * @return void
	 */
	public static function ajax_get_credits(): void {
		check_ajax_referer( 'agentic_train_data' );
		if ( ! self::current_user_can_manage() ) {
			wp_send_json_error( __( 'Permission denied.', 'agent-builder' ) );
		}
		self::require_pro();

		$user_id = self::get_user_id();
		$result  = self::api_request( '/credits/summary', 'GET', array( 'user_id' => $user_id ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		// Add credit_value from pricing.
		$pricing                = get_option( 'agentic_rag_pricing', array() );
		$result['credit_value'] = $pricing['credit_value'] ?? 0.01;

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Get pricing info.
	 *
	 * @return void
	 */
	public static function ajax_get_pricing(): void {
		check_ajax_referer( 'agentic_train_data' );
		if ( ! self::current_user_can_manage() ) {
			wp_send_json_error( __( 'Permission denied.', 'agent-builder' ) );
		}
		self::require_pro();

		$local  = get_option( 'agentic_rag_pricing', array() );
		$remote = self::api_request( '/pricing', 'GET' );
		$ops    = ( ! is_wp_error( $remote ) && isset( $remote['operations'] ) ) ? $remote['operations'] : array();

		// embed_cost and query_cost are owned by wp_marketplace_pricing (via /pricing API).
		// Fall back to hardcoded defaults only — not the legacy option, which is stale.
		$embed_cost = (float) ( $ops['rag_embed']['cost_credits'] ?? 0.7 );
		$query_cost = (float) ( $ops['rag_query']['cost_credits'] ?? 1.0 );

		wp_send_json_success(
			array(
				'credit_value' => $local['credit_value'] ?? 0.01,
				'embed_cost'   => $embed_cost,
				'query_cost'   => $query_cost,
				'minimum_usd'  => $local['minimum_usd'] ?? 10,
				'operations'   => $ops,
			)
		);
	}

	/**
	 * AJAX: Get credit transactions.
	 *
	 * @return void
	 */
	public static function ajax_get_transactions(): void {
		check_ajax_referer( 'agentic_train_data' );
		if ( ! self::current_user_can_manage() ) {
			wp_send_json_error( __( 'Permission denied.', 'agent-builder' ) );
		}
		self::require_pro();

		$primary_user_id = self::get_user_id();
		$limit           = absint( wp_unslash( $_POST['limit'] ?? 20 ) );
		$offset          = absint( wp_unslash( $_POST['offset'] ?? 0 ) );

		// Collect all license keys for the current WP user so we aggregate their full history.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table.
		$all_keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT license_key FROM {$wpdb->prefix}marketplace_licenses WHERE user_id = %d AND status = 'active'",
				get_current_user_id()
			)
		);
		if ( ! in_array( $primary_user_id, $all_keys, true ) ) {
			$all_keys[] = $primary_user_id;
		}
		$user_id = implode( ',', array_unique( array_filter( $all_keys ) ) );

		$result = self::api_request(
			'/credits/transactions',
			'GET',
			array(
				'user_id' => $user_id,
				'limit'   => min( $limit, 100 ),
				'offset'  => $offset,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}
}
