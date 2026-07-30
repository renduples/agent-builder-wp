<?php
/**
 * Agent Builder — Relay Connect
 *
 * Two responsibilities:
 *   1. GET /wp-json/agentic/relay/ping  (public) — signals Agent Builder is installed
 *   2. ?agentic_relay_connect=1         (page)   — approval screen for the relay site-connect flow
 *
 * The relay probes (1) to detect this plugin.
 * On detection it redirects the user's browser to (2) with a signed relay_state token.
 * The user approves, we generate an Application Password and POST to the relay callback.
 *
 * @package Agentic
 */

defined( 'ABSPATH' ) || exit;

use Agentic\WP_Optional_API;

/**
 * Connects this site to the Agentic MCP relay (mcp.agentic-plugin.com).
 *
 * Lives in the global namespace so it can be loaded before the autoloader.
 */
class Agentic_Relay_Connect {

	const RELAY_BASE        = 'https://mcp.agentic-plugin.com';
	const VERIFY_STATE_URL  = 'https://mcp.agentic-plugin.com/api/verify-state';
	const ALLOWED_CALLBACK  = 'https://mcp.agentic-plugin.com/oauth2/relay-callback';
	const APP_PASS_NAME     = 'Agent Builder Relay';
	const CONNECTORS_OPTION = 'agentic_active_connectors';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_ping' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_mcp' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_connect' ), 1 );
	}

	// ---------- 1. Ping endpoint ----------

	/**
	 * Register the public detection ping route.
	 */
	public static function register_ping(): void {
		register_rest_route(
			'agentic/relay',
			'/ping',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_ping' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Answer the relay's detection probe with plugin version + agent slugs.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_ping(): WP_REST_Response {
		$agents = array();

		// Collect registered agent slugs from the filesystem.
		$agents_dir = defined( 'AGENT_BUILDER_DIR' ) ? AGENT_BUILDER_DIR . 'library/agents/' : '';
		if ( $agents_dir && is_dir( $agents_dir ) ) {
			foreach ( glob( $agents_dir . '*/agent.php' ) as $file ) {
				$slug = basename( dirname( $file ) );
				if ( $slug ) {
					$agents[] = $slug;
				}
			}
		}

		return new WP_REST_Response(
			array(
				'relay_ready' => true,
				'version'     => defined( 'AGENT_BUILDER_VERSION' ) ? AGENT_BUILDER_VERSION : '1.0.0',
				'agents'      => $agents,
			),
			200
		);
	}

	// ---------- 3. MCP JSON-RPC endpoint ----------

	/**
	 * Register the per-agent MCP JSON-RPC route.
	 */
	public static function register_mcp(): void {
		register_rest_route(
			'agentic',
			'/(?P<slug>[a-z0-9_-]+)/mcp',
			array(
				'methods'             => array( 'POST', 'GET', 'DELETE' ),
				'callback'            => array( __CLASS__, 'handle_mcp' ),
				'permission_callback' => array( __CLASS__, 'check_mcp_permission' ),
			)
		);
	}

	/**
	 * MCP route permission: authenticated user + Pro license or active connector.
	 *
	 * Individual abilities enforce their own permission checks on execute.
	 *
	 * @return bool|\WP_Error
	 */
	public static function check_mcp_permission(): bool|\WP_Error {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Authentication required.', 'agent-builder' ),
				array( 'status' => 401 )
			);
		}

		if ( class_exists( 'Agentic\\License_Client' ) ) {
			$license = \Agentic\License_Client::get_instance();
			if ( ! $license->can_use_mcp() ) {
				return new \WP_Error(
					'rest_forbidden',
					__( 'MCP requires an active connector or Pro license.', 'agent-builder' ),
					array( 'status' => 403 )
				);
			}
		}

		return true;
	}

	/**
	 * Handle an MCP JSON-RPC request.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	public static function handle_mcp( \WP_REST_Request $request ): \WP_REST_Response {
		// DELETE = session termination (MCP spec) — acknowledge and exit.
		if ( 'DELETE' === $request->get_method() ) {
			return new \WP_REST_Response( null, 200 );
		}

		$body   = $request->get_json_params();
		$body   = is_array( $body ) ? $body : array();
		$method = $body['method'] ?? '';
		$id     = $body['id'] ?? null;

		switch ( $method ) {
			case 'initialize':
				return new \WP_REST_Response(
					self::mcp_result(
						$id,
						array(
							'protocolVersion' => '2024-11-05',
							'capabilities'    => array( 'tools' => new \stdClass() ),
							'serverInfo'      => array(
								'name'    => 'Agent Builder MCP',
								'version' => defined( 'AGENT_BUILDER_VERSION' ) ? AGENT_BUILDER_VERSION : '1.0.0',
							),
						)
					),
					200
				);

			case 'notifications/initialized':
				return new \WP_REST_Response( self::mcp_result( $id, null ), 200 );

			case 'tools/list':
				return new \WP_REST_Response(
					self::mcp_result( $id, array( 'tools' => self::get_mcp_tools() ) ),
					200
				);

			case 'tools/call':
				return new \WP_REST_Response(
					self::handle_tool_call( $id, $body['params'] ?? array() ),
					200
				);

			default:
				return new \WP_REST_Response(
					self::mcp_error( $id, -32601, "Method not found: $method" ),
					200
				);
		}
	}

	/**
	 * Build the MCP tools list from registered WordPress abilities.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_mcp_tools(): array {
		if ( ! WP_Optional_API::has( 'wp_get_abilities' ) ) {
			return array();
		}

		$tools = array();
		foreach ( WP_Optional_API::get_abilities() as $ability ) {
			$name = $ability->get_name();

			// Skip mcp-adapter meta-tools — they are not useful direct tools for the relay.
			if ( str_starts_with( $name, 'mcp-adapter/' ) ) {
				continue;
			}

			$schema = $ability->get_input_schema();
			if ( empty( $schema ) || ! is_array( $schema ) ) {
				$schema = array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				);
			}

			$label       = $ability->get_label();
			$label       = ( is_string( $label ) && '' !== $label ) ? $label : $name;
			$description = $ability->get_description();
			$description = ( is_string( $description ) && '' !== $description ) ? $description : $label;

			$tools[] = array(
				'name'        => self::ability_to_mcp_name( $name ),
				'description' => $description,
				'inputSchema' => $schema,
				'annotations' => array( 'title' => $label ),
			);
		}

		return $tools;
	}

	/**
	 * Execute an ability via MCP tools/call.
	 *
	 * @param mixed $id     JSON-RPC request id.
	 * @param array $params JSON-RPC params (name + arguments).
	 * @return array JSON-RPC response payload.
	 */
	private static function handle_tool_call( mixed $id, array $params ): array {
		$mcp_name  = $params['name'] ?? '';
		$arguments = $params['arguments'] ?? array();

		if ( ! $mcp_name ) {
			return self::mcp_error( $id, -32602, 'Missing tool name.' );
		}

		if ( ! WP_Optional_API::has( 'wp_get_ability' ) ) {
			return self::mcp_error( $id, -32603, 'Abilities API not available.' );
		}

		// Convert MCP name → ability name and look up.
		$ability = WP_Optional_API::get_ability( self::mcp_name_to_ability( $mcp_name ) );
		if ( ! $ability ) {
			// Fallback: scan all abilities for a matching MCP name.
			$ability = self::find_ability_by_mcp_name( $mcp_name );
		}

		if ( ! $ability ) {
			return self::mcp_error( $id, -32602, "Unknown tool: $mcp_name" );
		}

		try {
			$result = $ability->execute( $arguments );
			$text   = is_string( $result ) ? $result : (string) wp_json_encode( $result );

			return self::mcp_result(
				$id,
				array(
					'content' => array(
						array(
							'type' => 'text',
							'text' => $text,
						),
					),
				)
			);
		} catch ( \Throwable $e ) {
			return self::mcp_result(
				$id,
				array(
					'content' => array(
						array(
							'type' => 'text',
							'text' => 'Error: ' . $e->getMessage(),
						),
					),
					'isError' => true,
				)
			);
		}
	}

	/**
	 * Convert an ability name to an MCP-safe tool name.
	 *
	 * @param string $name Ability name, e.g. "agent-builder/create-post".
	 * @return string MCP tool name.
	 */
	private static function ability_to_mcp_name( string $name ): string {
		// Replace namespace separator `/` with `__` (double underscore) — reversible.
		return str_replace( '/', '__', $name );
	}

	/**
	 * Convert an MCP tool name back to an ability name.
	 *
	 * @param string $mcp_name MCP tool name.
	 * @return string Ability name.
	 */
	private static function mcp_name_to_ability( string $mcp_name ): string {
		return str_replace( '__', '/', $mcp_name );
	}

	/**
	 * Fallback lookup: scan all abilities for a matching MCP name.
	 *
	 * @param string $mcp_name MCP tool name.
	 * @return \WP_Ability|null
	 */
	private static function find_ability_by_mcp_name( string $mcp_name ): ?\WP_Ability {
		foreach ( WP_Optional_API::get_abilities() as $ability ) {
			if ( self::ability_to_mcp_name( $ability->get_name() ) === $mcp_name ) {
				return $ability;
			}
		}
		return null;
	}

	/**
	 * Build a JSON-RPC success envelope.
	 *
	 * @param mixed $id     JSON-RPC request id.
	 * @param mixed $result Result payload.
	 * @return array
	 */
	private static function mcp_result( mixed $id, mixed $result ): array {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		);
	}

	/**
	 * Build a JSON-RPC error envelope.
	 *
	 * @param mixed  $id      JSON-RPC request id.
	 * @param int    $code    JSON-RPC error code.
	 * @param string $message Error message.
	 * @return array
	 */
	private static function mcp_error( mixed $id, int $code, string $message ): array {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => array(
				'code'    => $code,
				'message' => $message,
			),
		);
	}

	// ---------- 2. Connect approval flow ----------

	/**
	 * Route ?agentic_relay_connect=1 requests to the approval flow.
	 */
	public static function maybe_handle_connect(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['agentic_relay_connect'] ) ) {
			return;
		}

		self::handle_connect_page();
	}

	/**
	 * Validate the connect request, then show or process the approval screen.
	 */
	public static function handle_connect_page(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only entry point; the state token is verified server-side with the relay and the approval POST is nonce-protected.
		$raw_state = sanitize_text_field( wp_unslash( $_GET['relay_state'] ?? '' ) );
		$provider  = sanitize_key( wp_unslash( $_GET['provider'] ?? 'anthropic' ) );
		$callback  = esc_url_raw( rawurldecode( sanitize_text_field( wp_unslash( $_GET['callback'] ?? '' ) ) ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Validate callback origin — must be our relay's exact callback endpoint.
		if ( ! self::is_allowed_callback( $callback ) ) {
			wp_die( esc_html__( 'Invalid connector callback URL.', 'agent-builder' ), 400 );
		}

		// Verify relay_state with the relay.
		if ( ! $raw_state || ! self::verify_relay_state( $raw_state ) ) {
			wp_die( esc_html__( 'This connection link has expired or is invalid. Please start again from Claude.ai.', 'agent-builder' ), 400 );
		}

		// Require WP login.
		if ( ! is_user_logged_in() ) {
			$return = add_query_arg( self::connect_query_args(), home_url( '/' ) );
			wp_safe_redirect( wp_login_url( $return ) );
			exit;
		}

		/**
		 * Filters the capability required to approve the relay connector.
		 *
		 * Connecting mints an Application Password and exposes agent tools to
		 * the relay, so this defaults to site administrators.
		 *
		 * @param string $capability Required capability. Default 'manage_options'.
		 */
		$required_cap = apply_filters( 'agentic_relay_connect_capability', 'manage_options' );
		if ( ! current_user_can( $required_cap ) ) {
			wp_die( esc_html__( 'Sorry, you need administrator permissions to connect this site. Please ask your site administrator to approve the connection.', 'agent-builder' ), 403 );
		}

		// Handle POST (approval decision).
		$request_method = strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) );
		if ( 'POST' === $request_method ) {
			self::process_approval( $raw_state, $provider, $callback );
			return;
		}

		// Show approval screen.
		self::render_approval_screen( $raw_state, $provider, $callback );
	}

	/**
	 * Process the admin's approve/deny decision and notify the relay.
	 *
	 * @param string $raw_state Relay state token (already verified).
	 * @param string $provider  Connector provider slug.
	 * @param string $callback  Relay callback URL (already validated).
	 */
	private static function process_approval( string $raw_state, string $provider, string $callback ): void {
		$nonce_action = 'agentic_relay_connect_' . substr( $raw_state, 0, 16 );

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			wp_die( esc_html__( 'Security check failed.', 'agent-builder' ), 403 );
		}

		if ( sanitize_key( wp_unslash( $_POST['decision'] ?? '' ) ) !== 'approve' ) {
			self::render_denied();
			return;
		}

		$user_id = get_current_user_id();

		// Generate Application Password — WP 5.6+.
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			wp_die( esc_html__( 'Application Passwords are not available on this site.', 'agent-builder' ), 500 );
		}

		$result = WP_Application_Passwords::create_new_application_password( $user_id, array( 'name' => self::APP_PASS_NAME ) );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), 500 );
		}
		[ $app_pass, ] = $result;

		$user = wp_get_current_user();

		// Base64-encode for relay storage: "login:password".
		$app_pass_b64 = base64_encode( $user->user_login . ':' . $app_pass ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		// Record that the connector is active.
		$connectors   = (array) get_option( self::CONNECTORS_OPTION, array() );
		$connectors[] = $provider;
		update_option( self::CONNECTORS_OPTION, array_unique( $connectors ) );

		// Collect available agent slugs.
		$agent_slugs = array();
		$agents_dir  = defined( 'AGENT_BUILDER_DIR' ) ? AGENT_BUILDER_DIR . 'library/agents/' : '';
		if ( $agents_dir && is_dir( $agents_dir ) ) {
			foreach ( glob( $agents_dir . '*/agent.php' ) as $file ) {
				$agent_slugs[] = basename( dirname( $file ) );
			}
		}

		// POST to relay callback.
		$response = wp_remote_post(
			$callback,
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'relay_state'      => $raw_state,
						'site_url'         => home_url(),
						'wp_user_email'    => $user->user_email,
						'wp_user_login'    => $user->user_login,
						'app_password_b64' => $app_pass_b64,
						'agent_slugs'      => $agent_slugs,
						'provider'         => $provider,
					)
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_die( esc_html__( 'Could not reach the relay. Please try again.', 'agent-builder' ), 500 );
		}

		// The relay responds with a 302 redirect location in the Location header.
		$location = wp_remote_retrieve_header( $response, 'location' );
		if ( $location ) {
			wp_safe_redirect( $location );
			exit;
		}

		// Fallback — relay redirected the PHP request server-side; show success.
		self::render_success( $provider );
	}

	// ---------- Validation helpers ----------

	/**
	 * Strictly validate the relay callback URL: exact scheme, host, and path
	 * must match ALLOWED_CALLBACK (query string may vary).
	 *
	 * @param string $callback Callback URL supplied by the relay redirect.
	 * @return bool
	 */
	private static function is_allowed_callback( string $callback ): bool {
		$allowed = wp_parse_url( self::ALLOWED_CALLBACK );
		$given   = wp_parse_url( $callback );

		if ( ! is_array( $given ) || ! is_array( $allowed ) ) {
			return false;
		}

		return ( $given['scheme'] ?? '' ) === $allowed['scheme']
			&& ( $given['host'] ?? '' ) === $allowed['host']
			&& ( $given['path'] ?? '' ) === $allowed['path']
			&& empty( $given['port'] )
			&& empty( $given['user'] );
	}

	/**
	 * The connect-flow query args, sanitized, for rebuilding the login redirect.
	 *
	 * @return array<string,string>
	 */
	private static function connect_query_args(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Values are only echoed back into the login redirect URL.
		return array(
			'agentic_relay_connect' => '1',
			'relay_state'           => sanitize_text_field( wp_unslash( $_GET['relay_state'] ?? '' ) ),
			'provider'              => sanitize_key( wp_unslash( $_GET['provider'] ?? 'anthropic' ) ),
			'callback'              => sanitize_text_field( wp_unslash( $_GET['callback'] ?? '' ) ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	// ---------- Relay state verification ----------

	/**
	 * Verify the relay state token server-side with the relay.
	 *
	 * @param string $raw_state Relay state token.
	 * @return bool
	 */
	private static function verify_relay_state( string $raw_state ): bool {
		$response = wp_remote_get(
			add_query_arg( 'state', rawurlencode( $raw_state ), self::VERIFY_STATE_URL ),
			array( 'timeout' => 8 )
		);
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return false;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return ! empty( $data['valid'] );
	}

	// ---------- Templates ----------

	/**
	 * Render the connector approval screen.
	 *
	 * @param string $raw_state Relay state token.
	 * @param string $provider  Connector provider slug.
	 * @param string $callback  Relay callback URL.
	 */
	private static function render_approval_screen( string $raw_state, string $provider, string $callback ): void {
		$nonce_action   = 'agentic_relay_connect_' . substr( $raw_state, 0, 16 );
		$nonce          = wp_create_nonce( $nonce_action );
		$current_user   = wp_get_current_user();
		$provider_label = 'anthropic' === $provider ? 'Claude (Anthropic)' : ucfirst( $provider );

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );

		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php esc_html_e( 'Allow Agent Builder Connector', 'agent-builder' ); ?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f0f0f1;color:#1d2327;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px}
.card{background:#fff;border-radius:8px;box-shadow:0 2px 16px rgba(0,0,0,.12);max-width:440px;width:100%;padding:40px 36px 32px}
.logo{text-align:center;margin-bottom:24px}
.logo svg{width:48px;height:48px;color:#2271b1}
h1{font-size:20px;font-weight:700;text-align:center;margin-bottom:8px}
.sub{font-size:14px;color:#646970;text-align:center;margin-bottom:24px;line-height:1.5}
.user-row{display:flex;align-items:center;gap:10px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px;padding:10px 14px;margin-bottom:20px;font-size:14px}
.user-row strong{display:block;color:#1d2327}
.user-row span{color:#646970;font-size:13px}
.label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#646970;margin-bottom:8px}
.scope{background:#f0f6fc;border:1px solid #c3d9f5;border-left:3px solid #2271b1;border-radius:4px;padding:12px 14px;font-size:14px;margin-bottom:20px;line-height:1.5}
.notice{font-size:12px;color:#646970;margin-bottom:20px;line-height:1.5}
.actions{display:flex;gap:12px}
.btn{flex:1;padding:10px 16px;font-size:14px;font-weight:600;border-radius:4px;border:1px solid transparent;cursor:pointer}
.btn-primary{background:#2271b1;color:#fff;border-color:#2271b1}
.btn-primary:hover{background:#135e96}
.btn-secondary{background:#fff;color:#2271b1;border-color:#2271b1}
.btn-secondary:hover{background:#f0f6fc}
</style>
</head>
<body>
<div class="card">
	<div class="logo">
		<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
			<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
		</svg>
	</div>

	<h1>
		<?php
		/* translators: %s: AI provider name, e.g. "Claude (Anthropic)". */
		printf( esc_html__( 'Allow %s to access this site?', 'agent-builder' ), esc_html( $provider_label ) );
		?>
	</h1>
	<p class="sub">
		<?php
		printf(
			/* translators: 1: AI provider name, 2: site URL. */
			esc_html__( 'The Agent Builder Connector will allow %1$s to read and use your AI agents on %2$s.', 'agent-builder' ),
			esc_html( $provider_label ),
			'<strong>' . esc_html( home_url() ) . '</strong>'
		);
		?>
	</p>

	<div class="label"><?php esc_html_e( 'Signed in as', 'agent-builder' ); ?></div>
	<div class="user-row">
		<?php echo get_avatar( $current_user->ID, 32 ); ?>
		<div>
			<strong><?php echo esc_html( $current_user->display_name ); ?></strong>
			<span><?php echo esc_html( $current_user->user_email ); ?></span>
		</div>
	</div>

	<div class="label"><?php esc_html_e( 'What will be shared', 'agent-builder' ); ?></div>
	<div class="scope">
		<?php esc_html_e( 'An Application Password will be created so the relay can call your Agent Builder tools on behalf of Claude. You can revoke it at any time under Users → Profile → Application Passwords.', 'agent-builder' ); ?>
	</div>

	<p class="notice"><?php esc_html_e( 'Only your Agent Builder tools are exposed — no other site data.', 'agent-builder' ); ?></p>

	<form method="POST">
		<?php wp_nonce_field( $nonce_action ); ?>
		<input type="hidden" name="relay_state" value="<?php echo esc_attr( $raw_state ); ?>">
		<input type="hidden" name="provider"    value="<?php echo esc_attr( $provider ); ?>">
		<input type="hidden" name="callback"    value="<?php echo esc_attr( $callback ); ?>">
		<div class="actions">
			<button type="submit" name="decision" value="deny"    class="btn btn-secondary"><?php esc_html_e( 'Deny', 'agent-builder' ); ?></button>
			<button type="submit" name="decision" value="approve" class="btn btn-primary"><?php esc_html_e( 'Allow Access', 'agent-builder' ); ?></button>
		</div>
	</form>
</div>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * Render the success screen after connecting.
	 *
	 * @param string $provider Connector provider slug.
	 */
	private static function render_success( string $provider ): void {
		$label = 'anthropic' === $provider ? 'Claude' : ucfirst( $provider );
		status_header( 200 );
		header( 'Content-Type: text/html; charset=UTF-8' );
		?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Connected</title>
<style>body{font-family:-apple-system,sans-serif;background:#f0f0f1;display:flex;align-items:center;justify-content:center;min-height:100vh}.card{background:#fff;border-radius:8px;padding:48px 36px;text-align:center;box-shadow:0 2px 16px rgba(0,0,0,.12);max-width:400px}.icon{font-size:48px;margin-bottom:16px}h1{font-size:20px;font-weight:700;margin-bottom:8px}p{color:#646970;font-size:14px;line-height:1.6}</style>
</head><body><div class="card">
<div class="icon">✅</div>
<h1>
		<?php
		/* translators: %s: AI provider name, e.g. "Claude". */
		printf( esc_html__( 'Connected to %s!', 'agent-builder' ), esc_html( $label ) );
		?>
</h1>
<p><?php esc_html_e( 'Your Agent Builder tools are now available. You can return to Claude.ai and start using them.', 'agent-builder' ); ?></p>
</div></body></html>
		<?php
		exit;
	}

	/**
	 * Render the denied screen.
	 */
	private static function render_denied(): void {
		status_header( 200 );
		header( 'Content-Type: text/html; charset=UTF-8' );
		?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Denied</title>
<style>body{font-family:-apple-system,sans-serif;background:#f0f0f1;display:flex;align-items:center;justify-content:center;min-height:100vh}.card{background:#fff;border-radius:8px;padding:48px 36px;text-align:center;box-shadow:0 2px 16px rgba(0,0,0,.12);max-width:400px}h1{font-size:20px;font-weight:700;margin-bottom:8px}p{color:#646970;font-size:14px}</style>
</head><body><div class="card">
<h1><?php esc_html_e( 'Access denied', 'agent-builder' ); ?></h1>
<p><?php esc_html_e( 'You chose not to connect this site. You can close this window.', 'agent-builder' ); ?></p>
</div></body></html>
		<?php
		exit;
	}
}
