<?php
/**
 * Site-local (declarative) tools — Pro admin builder + runtime.
 *
 * Free: use shipped tools + PHP Tool_Base packages.
 * Pro: create site-local tools in Agentic → Tools without PHP.
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      3.3.0
 */

declare(strict_types=1);

namespace Agentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Storage, handlers, REST, and Tool_Loader integration for site-local tools.
 */
class Site_Local_Tools {

	const OPTION = 'agentic_site_local_tools';

	/**
	 * Allowlisted declarative handlers (no arbitrary code).
	 *
	 * @var array<string, array{label:string, risk:string, description:string}>
	 */
	const HANDLERS = array(
		'wp_list_posts'  => array(
			'label'       => 'List posts / pages',
			'risk'        => 'low',
			'description' => 'List posts or pages (read-only). Args: post_type, status, search, limit.',
		),
		'wp_get_post'    => array(
			'label'       => 'Get post',
			'risk'        => 'low',
			'description' => 'Fetch one post by ID (read-only). Args: post_id.',
		),
		'wp_create_post' => array(
			'label'       => 'Create draft post',
			'risk'        => 'medium',
			'description' => 'Create a draft post/page. Args: title, content, post_type (post|page).',
		),
		'wp_update_post' => array(
			'label'       => 'Update post',
			'risk'        => 'high',
			'description' => 'Update title/content/status of an existing post. Args: post_id, title?, content?, status?.',
		),
		'wp_get_option'  => array(
			'label'       => 'Get option (allowlist)',
			'risk'        => 'low',
			'description' => 'Read a wp_option key from the tool allowlist only. Args: option_key.',
		),
		'http_get'       => array(
			'label'       => 'HTTP GET (allowlist hosts)',
			'risk'        => 'medium',
			'description' => 'GET a URL on allowed hosts (defaults to this site). Args: path_or_url.',
		),
	);

	/**
	 * Bootstrap hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_filter( 'agentic_runtime_tools', array( __CLASS__, 'filter_runtime_tools' ) );
		add_filter( 'agentic_agent_tool_names', array( __CLASS__, 'filter_agent_tool_names' ), 10, 2 );
	}

	/**
	 * Whether Pro (or forced filter) enables site-local tools.
	 *
	 * @return bool
	 */
	public static function is_feature_available(): bool {
		/**
		 * Force-enable site-local tools (e.g. local QA without a license).
		 *
		 * @param bool $force Default false.
		 */
		if ( (bool) apply_filters( 'agentic_site_local_tools_force', false ) ) {
			return true;
		}
		if ( ! class_exists( License_Client::class ) ) {
			return false;
		}
		return License_Client::get_instance()->is_pro();
	}

	/**
	 * Current user may manage site-local tool definitions.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage(): bool {
		return current_user_can( 'manage_options' ) && self::is_feature_available();
	}

	/**
	 * All stored definitions (enabled or not).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all(): array {
		$raw = get_option( self::OPTION, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $row ) {
			if ( is_array( $row ) && ! empty( $row['name'] ) ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * Find one definition by name.
	 *
	 * @param string $name Tool slug.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $name ): ?array {
		foreach ( self::all() as $row ) {
			if ( ( $row['name'] ?? '' ) === $name ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * Persist full list.
	 *
	 * @param array<int, array<string, mixed>> $tools Tools.
	 * @return void
	 */
	private static function save_all( array $tools ): void {
		update_option( self::OPTION, array_values( $tools ), false );
		delete_transient( 'agentic_tool_paths' );
		if ( class_exists( Tools_Registry::class ) ) {
			Tools_Registry::bust_cache();
		}
	}

	/**
	 * Sanitize and validate a definition payload.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @param string|null          $existing_name When updating, previous name.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function sanitize_definition( array $input, ?string $existing_name = null ) {
		$name = sanitize_key( (string) ( $input['name'] ?? '' ) );
		if ( '' === $name || ! preg_match( '/^[a-z][a-z0-9_]{1,62}$/', $name ) ) {
			return new \WP_Error( 'invalid_name', __( 'Tool name must be snake_case (a-z, 0-9, underscore), 2–63 chars.', 'agent-builder' ) );
		}

		// Reserved: don't shadow core tools by same slug if already registered from core.
		if ( null === $existing_name || $existing_name !== $name ) {
			if ( self::get( $name ) ) {
				return new \WP_Error( 'duplicate', __( 'A site-local tool with this name already exists.', 'agent-builder' ) );
			}
			if ( class_exists( Tools_Registry::class ) ) {
				$existing = Tools_Registry::get( $name );
				if ( $existing && ( $existing['source'] ?? '' ) !== 'site' ) {
					return new \WP_Error( 'reserved', __( 'That tool name is already used by a core or agent tool.', 'agent-builder' ) );
				}
			}
		}

		$handler = sanitize_key( (string) ( $input['handler'] ?? '' ) );
		if ( ! isset( self::HANDLERS[ $handler ] ) ) {
			return new \WP_Error( 'invalid_handler', __( 'Unknown or disallowed handler.', 'agent-builder' ) );
		}

		$label = sanitize_text_field( (string) ( $input['label'] ?? $name ) );
		$desc  = sanitize_textarea_field( (string) ( $input['description'] ?? '' ) );
		if ( '' === trim( $desc ) ) {
			return new \WP_Error( 'invalid_description', __( 'Description is required (the LLM uses it to decide when to call the tool).', 'agent-builder' ) );
		}

		$category = sanitize_key( (string) ( $input['category'] ?? 'custom' ) );
		if ( '' === $category ) {
			$category = 'custom';
		}

		$risk = sanitize_key( (string) ( $input['risk_level'] ?? self::HANDLERS[ $handler ]['risk'] ) );
		if ( ! in_array( $risk, array( 'none', 'low', 'medium', 'high', 'extreme' ), true ) ) {
			$risk = self::HANDLERS[ $handler ]['risk'];
		}
		// Free builder never allows extreme via UI default; still block extreme handlers we don't ship.
		if ( 'extreme' === $risk ) {
			$risk = 'high';
		}

		$parameters = $input['parameters'] ?? null;
		if ( ! is_array( $parameters ) ) {
			$parameters = self::default_parameters_for_handler( $handler );
		} else {
			$parameters = self::sanitize_parameters_schema( $parameters );
		}

		$config = is_array( $input['config'] ?? null ) ? $input['config'] : array();
		$config = self::sanitize_config( $handler, $config );

		$agents = array();
		if ( ! empty( $input['agent_slugs'] ) && is_array( $input['agent_slugs'] ) ) {
			foreach ( $input['agent_slugs'] as $slug ) {
				$slug = sanitize_key( (string) $slug );
				if ( $slug ) {
					$agents[] = $slug;
				}
			}
		}
		$agents = array_values( array_unique( $agents ) );

		$enabled = array_key_exists( 'enabled', $input )
			? (bool) $input['enabled']
			: true;

		$now = gmdate( 'Y-m-d H:i:s' );
		$prev = $existing_name ? self::get( $existing_name ) : null;

		return array(
			'name'        => $name,
			'label'       => $label ?: $name,
			'description' => $desc,
			'category'    => $category,
			'handler'     => $handler,
			'risk_level'  => $risk,
			'parameters'  => $parameters,
			'config'      => $config,
			'agent_slugs' => $agents, // Empty = all active agents.
			'enabled'     => $enabled,
			'created_at'  => $prev['created_at'] ?? $now,
			'updated_at'  => $now,
		);
	}

	/**
	 * Default JSON schema per handler.
	 *
	 * @param string $handler Handler id.
	 * @return array<string, mixed>
	 */
	public static function default_parameters_for_handler( string $handler ): array {
		switch ( $handler ) {
			case 'wp_list_posts':
				return array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array(
							'type'        => 'string',
							'description' => 'post or page (default post).',
						),
						'status'    => array(
							'type'        => 'string',
							'description' => 'publish, draft, any (default any).',
						),
						'search'    => array(
							'type'        => 'string',
							'description' => 'Optional search string.',
						),
						'limit'     => array(
							'type'        => 'integer',
							'description' => 'Max results 1–20 (default 10).',
						),
					),
				);
			case 'wp_get_post':
				return array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress post ID.',
						),
					),
					'required'   => array( 'post_id' ),
				);
			case 'wp_create_post':
				return array(
					'type'       => 'object',
					'properties' => array(
						'title'     => array(
							'type'        => 'string',
							'description' => 'Post title.',
						),
						'content'   => array(
							'type'        => 'string',
							'description' => 'Post content HTML/text.',
						),
						'post_type' => array(
							'type'        => 'string',
							'description' => 'post or page (default post).',
						),
					),
					'required'   => array( 'title' ),
				);
			case 'wp_update_post':
				return array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'Post ID to update.',
						),
						'title'   => array(
							'type'        => 'string',
							'description' => 'New title (optional).',
						),
						'content' => array(
							'type'        => 'string',
							'description' => 'New content (optional).',
						),
						'status'  => array(
							'type'        => 'string',
							'description' => 'draft, pending, private, or publish (optional).',
						),
					),
					'required'   => array( 'post_id' ),
				);
			case 'wp_get_option':
				return array(
					'type'       => 'object',
					'properties' => array(
						'option_key' => array(
							'type'        => 'string',
							'description' => 'Option key (must be on this tool allowlist).',
						),
					),
					'required'   => array( 'option_key' ),
				);
			case 'http_get':
				return array(
					'type'       => 'object',
					'properties' => array(
						'path_or_url' => array(
							'type'        => 'string',
							'description' => 'Site-relative path or full URL on an allowed host.',
						),
					),
					'required'   => array( 'path_or_url' ),
				);
			default:
				return array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				);
		}
	}

	/**
	 * Light sanitize of schema (names + types only).
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @return array<string, mixed>
	 */
	private static function sanitize_parameters_schema( array $schema ): array {
		$out = array(
			'type'       => 'object',
			'properties' => array(),
		);
		$props = $schema['properties'] ?? array();
		if ( is_array( $props ) ) {
			foreach ( $props as $key => $prop ) {
				$key = sanitize_key( (string) $key );
				if ( '' === $key || ! is_array( $prop ) ) {
					continue;
				}
				$type = sanitize_key( (string) ( $prop['type'] ?? 'string' ) );
				if ( ! in_array( $type, array( 'string', 'integer', 'number', 'boolean', 'array', 'object' ), true ) ) {
					$type = 'string';
				}
				$out['properties'][ $key ] = array(
					'type'        => $type,
					'description' => sanitize_text_field( (string) ( $prop['description'] ?? '' ) ),
				);
			}
		}
		if ( ! empty( $schema['required'] ) && is_array( $schema['required'] ) ) {
			$req = array();
			foreach ( $schema['required'] as $r ) {
				$r = sanitize_key( (string) $r );
				if ( $r && isset( $out['properties'][ $r ] ) ) {
					$req[] = $r;
				}
			}
			if ( $req ) {
				$out['required'] = $req;
			}
		}
		if ( array() === $out['properties'] ) {
			$out['properties'] = new \stdClass();
		}
		return $out;
	}

	/**
	 * Sanitize handler-specific config.
	 *
	 * @param string               $handler Handler.
	 * @param array<string, mixed> $config  Config.
	 * @return array<string, mixed>
	 */
	private static function sanitize_config( string $handler, array $config ): array {
		$out = array();
		if ( 'wp_get_option' === $handler ) {
			$keys = $config['allowed_options'] ?? array();
			if ( is_string( $keys ) ) {
				$keys = preg_split( '/[\s,]+/', $keys ) ?: array();
			}
			$deny  = array( 'siteurl', 'home', 'admin_email', 'users_can_register', 'active_plugins', 'cron', 'recently_activated', 'rewrite_rules' );
			$clean = array();
			if ( is_array( $keys ) ) {
				foreach ( $keys as $k ) {
					$k = preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) ?? '';
					if ( ! $k || in_array( $k, $deny, true ) ) {
						continue;
					}
					if ( preg_match( '/(secret|password|salt|private_key|auth_key|logged_in_key|nonce_key|token)/i', $k ) ) {
						continue;
					}
					$clean[] = $k;
				}
			}
			$out['allowed_options'] = array_values( array_unique( $clean ) );
		}
		if ( 'http_get' === $handler ) {
			$hosts = $config['allowed_hosts'] ?? array();
			if ( is_string( $hosts ) ) {
				$hosts = preg_split( '/[\s,]+/', $hosts ) ?: array();
			}
			$clean = array();
			$site  = wp_parse_url( home_url(), PHP_URL_HOST );
			if ( is_string( $site ) && $site ) {
				$clean[] = strtolower( $site );
			}
			if ( is_array( $hosts ) ) {
				foreach ( $hosts as $h ) {
					$h = strtolower( sanitize_text_field( (string) $h ) );
					$h = preg_replace( '#^https?://#', '', $h ) ?? '';
					$h = explode( '/', $h )[0];
					if ( $h && preg_match( '/^[a-z0-9.\-]+$/', $h ) ) {
						$clean[] = $h;
					}
				}
			}
			$out['allowed_hosts'] = array_values( array_unique( $clean ) );
			$out['max_bytes']     = min( 100000, max( 1000, absint( $config['max_bytes'] ?? 50000 ) ) );
		}
		return $out;
	}

	/**
	 * Create or update a tool.
	 *
	 * @param array<string, mixed> $input Input.
	 * @param string|null          $rename_from Previous name when renaming.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function upsert( array $input, ?string $rename_from = null ) {
		if ( ! self::current_user_can_manage() ) {
			return new \WP_Error( 'forbidden', __( 'Site-local tool builder requires Agent Builder Pro and manage_options.', 'agent-builder' ), array( 'status' => 403 ) );
		}

		$existing_name = $rename_from ?: ( isset( $input['name'] ) ? sanitize_key( (string) $input['name'] ) : null );
		$is_update     = $existing_name && self::get( $existing_name );

		$def = self::sanitize_definition( $input, $is_update ? $existing_name : null );
		if ( is_wp_error( $def ) ) {
			return $def;
		}

		$all = self::all();
		if ( $is_update ) {
			$all = array_values(
				array_filter(
					$all,
					static function ( $row ) use ( $existing_name ) {
						return ( $row['name'] ?? '' ) !== $existing_name;
					}
				)
			);
			// If renamed, drop old registry row.
			if ( $existing_name !== $def['name'] && class_exists( Tools_Registry::class ) ) {
				Tools_Registry::unregister( $existing_name );
			}
		}

		$all[] = $def;
		self::save_all( $all );
		self::sync_registry_row( $def );

		if ( class_exists( Audit_Log::class ) ) {
			Audit_Log::log_admin(
				$is_update ? 'site_tool_updated' : 'site_tool_created',
				'tool',
				array(
					'id'         => $def['name'],
					'name'       => $def['name'],
					'handler'    => $def['handler'] ?? '',
					'risk_level' => $def['risk_level'] ?? '',
					'enabled'    => ! empty( $def['enabled'] ),
				)
			);
		}
		if ( class_exists( Security_Log::class ) ) {
			Security_Log::log_system(
				$is_update ? 'site_tool_updated' : 'site_tool_created',
				'tools',
				array(
					'name'    => $def['name'],
					'handler' => $def['handler'] ?? '',
				)
			);
		}

		return $def;
	}

	/**
	 * Delete a site-local tool.
	 *
	 * @param string $name Name.
	 * @return true|\WP_Error
	 */
	public static function delete( string $name ) {
		if ( ! self::current_user_can_manage() ) {
			return new \WP_Error( 'forbidden', __( 'Site-local tool builder requires Agent Builder Pro.', 'agent-builder' ), array( 'status' => 403 ) );
		}
		$name = sanitize_key( $name );
		$all  = array_values(
			array_filter(
				self::all(),
				static function ( $row ) use ( $name ) {
					return ( $row['name'] ?? '' ) !== $name;
				}
			)
		);
		self::save_all( $all );
		if ( class_exists( Tools_Registry::class ) ) {
			Tools_Registry::unregister( $name );
		}
		if ( class_exists( Audit_Log::class ) ) {
			Audit_Log::log_admin(
				'site_tool_deleted',
				'tool',
				array(
					'id'   => $name,
					'name' => $name,
				)
			);
		}
		if ( class_exists( Security_Log::class ) ) {
			Security_Log::log_system( 'site_tool_deleted', 'tools', array( 'name' => $name ) );
		}
		return true;
	}

	/**
	 * Sync one definition into Tools_Registry.
	 *
	 * @param array<string, mixed> $def Definition.
	 * @return void
	 */
	public static function sync_registry_row( array $def ): void {
		if ( ! class_exists( Tools_Registry::class ) ) {
			return;
		}
		Tools_Registry::register(
			array(
				'name'        => $def['name'],
				'description' => $def['description'],
				'category'    => $def['category'] ?? 'custom',
				'source'      => 'site',
				'enabled'     => ! empty( $def['enabled'] ),
				'risk_level'  => $def['risk_level'] ?? 'low',
				'parameters'  => $def['parameters'] ?? array(),
			)
		);
	}

	/**
	 * Sync all site tools into registry (e.g. after Pro activates).
	 *
	 * @return void
	 */
	public static function sync_all_to_registry(): void {
		foreach ( self::all() as $def ) {
			self::sync_registry_row( $def );
		}
	}

	/**
	 * Runtime Tool_Base instances for the loader.
	 *
	 * @param array<int, Tool_Base> $tools Existing.
	 * @return array<int, Tool_Base>
	 */
	public static function filter_runtime_tools( array $tools ): array {
		if ( ! self::is_feature_available() ) {
			return $tools;
		}
		foreach ( self::all() as $def ) {
			if ( empty( $def['enabled'] ) ) {
				continue;
			}
			$tools[] = new Site_Local_Tool( $def );
		}
		return $tools;
	}

	/**
	 * Attach site-local tools to agents.
	 *
	 * @param string[] $names    Tool names.
	 * @param string   $agent_id Agent id.
	 * @return string[]
	 */
	public static function filter_agent_tool_names( array $names, string $agent_id ): array {
		if ( ! self::is_feature_available() ) {
			return $names;
		}
		foreach ( self::all() as $def ) {
			if ( empty( $def['enabled'] ) ) {
				continue;
			}
			$slug = (string) ( $def['name'] ?? '' );
			if ( '' === $slug ) {
				continue;
			}
			$agents = $def['agent_slugs'] ?? array();
			if ( ! is_array( $agents ) || array() === $agents || in_array( $agent_id, $agents, true ) ) {
				$names[] = $slug;
			}
		}
		return array_values( array_unique( $names ) );
	}

	/**
	 * Execute a handler.
	 *
	 * @param array<string, mixed> $def       Definition.
	 * @param array<string, mixed> $arguments Args.
	 * @return array<string, mixed>
	 */
	public static function execute_handler( array $def, array $arguments ): array {
		if ( ! self::is_feature_available() ) {
			return array(
				'success' => false,
				'error'   => 'Site-local tools require Agent Builder Pro.',
			);
		}
		if ( empty( $def['enabled'] ) ) {
			return array(
				'success' => false,
				'error'   => 'This site-local tool is disabled.',
			);
		}

		$handler = (string) ( $def['handler'] ?? '' );
		$config  = is_array( $def['config'] ?? null ) ? $def['config'] : array();

		switch ( $handler ) {
			case 'wp_list_posts':
				return self::handler_list_posts( $arguments );
			case 'wp_get_post':
				return self::handler_get_post( $arguments );
			case 'wp_create_post':
				return self::handler_create_post( $arguments );
			case 'wp_update_post':
				return self::handler_update_post( $arguments );
			case 'wp_get_option':
				return self::handler_get_option( $arguments, $config );
			case 'http_get':
				return self::handler_http_get( $arguments, $config );
			default:
				return array(
					'success' => false,
					'error'   => 'Unknown handler.',
				);
		}
	}

	/**
	 * @param array<string, mixed> $args Args.
	 * @return array<string, mixed>
	 */
	private static function handler_list_posts( array $args ): array {
		$post_type = sanitize_key( (string) ( $args['post_type'] ?? 'post' ) );
		if ( ! in_array( $post_type, array( 'post', 'page' ), true ) ) {
			$post_type = 'post';
		}
		$status = sanitize_key( (string) ( $args['status'] ?? 'any' ) );
		$limit  = min( 20, max( 1, absint( $args['limit'] ?? 10 ) ) );
		$search = sanitize_text_field( (string) ( $args['search'] ?? '' ) );

		$query = array(
			'post_type'              => $post_type,
			'post_status'            => $status,
			'posts_per_page'         => $limit,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		if ( $search ) {
			$query['s'] = $search;
		}

		$posts = get_posts( $query );
		$items = array();
		foreach ( $posts as $p ) {
			$items[] = array(
				'id'     => $p->ID,
				'title'  => $p->post_title,
				'status' => $p->post_status,
				'type'   => $p->post_type,
				'date'   => $p->post_date,
			);
		}
		return array(
			'success' => true,
			'count'   => count( $items ),
			'posts'   => $items,
		);
	}

	/**
	 * @param array<string, mixed> $args Args.
	 * @return array<string, mixed>
	 */
	private static function handler_get_post( array $args ): array {
		$id = absint( $args['post_id'] ?? 0 );
		if ( $id < 1 ) {
			return array( 'success' => false, 'error' => 'post_id required' );
		}
		$p = get_post( $id );
		if ( ! $p ) {
			return array( 'success' => false, 'error' => 'Post not found' );
		}
		return array(
			'success' => true,
			'post'    => array(
				'id'      => $p->ID,
				'title'   => $p->post_title,
				'status'  => $p->post_status,
				'type'    => $p->post_type,
				'content' => $p->post_content,
				'excerpt' => $p->post_excerpt,
			),
		);
	}

	/**
	 * @param array<string, mixed> $args Args.
	 * @return array<string, mixed>
	 */
	private static function handler_create_post( array $args ): array {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return array( 'success' => false, 'error' => 'Permission denied' );
		}
		$title     = sanitize_text_field( (string) ( $args['title'] ?? '' ) );
		$content   = wp_kses_post( (string) ( $args['content'] ?? '' ) );
		$post_type = sanitize_key( (string) ( $args['post_type'] ?? 'post' ) );
		if ( ! in_array( $post_type, array( 'post', 'page' ), true ) ) {
			$post_type = 'post';
		}
		if ( '' === $title ) {
			return array( 'success' => false, 'error' => 'title required' );
		}
		$id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'draft',
				'post_type'    => $post_type,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return array( 'success' => false, 'error' => $id->get_error_message() );
		}
		return array(
			'success' => true,
			'post_id' => (int) $id,
			'status'  => 'draft',
			'edit'    => get_edit_post_link( (int) $id, 'raw' ),
		);
	}

	/**
	 * @param array<string, mixed> $args Args.
	 * @return array<string, mixed>
	 */
	private static function handler_update_post( array $args ): array {
		$id = absint( $args['post_id'] ?? 0 );
		if ( $id < 1 ) {
			return array( 'success' => false, 'error' => 'post_id required' );
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return array( 'success' => false, 'error' => 'Permission denied' );
		}
		$update = array( 'ID' => $id );
		if ( isset( $args['title'] ) ) {
			$update['post_title'] = sanitize_text_field( (string) $args['title'] );
		}
		if ( isset( $args['content'] ) ) {
			$update['post_content'] = wp_kses_post( (string) $args['content'] );
		}
		if ( isset( $args['status'] ) ) {
			$status = sanitize_key( (string) $args['status'] );
			if ( in_array( $status, array( 'draft', 'pending', 'private', 'publish' ), true ) ) {
				$update['post_status'] = $status;
			}
		}
		if ( count( $update ) < 2 ) {
			return array( 'success' => false, 'error' => 'Nothing to update' );
		}
		$r = wp_update_post( $update, true );
		if ( is_wp_error( $r ) ) {
			return array( 'success' => false, 'error' => $r->get_error_message() );
		}
		return array(
			'success' => true,
			'post_id' => $id,
		);
	}

	/**
	 * @param array<string, mixed> $args   Args.
	 * @param array<string, mixed> $config Config.
	 * @return array<string, mixed>
	 */
	private static function handler_get_option( array $args, array $config ): array {
		$key = sanitize_text_field( (string) ( $args['option_key'] ?? '' ) );
		$key = preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) ?? '';
		$allowed = $config['allowed_options'] ?? array();
		if ( ! is_array( $allowed ) || ! in_array( $key, $allowed, true ) ) {
			return array(
				'success' => false,
				'error'   => 'Option key not on this tool allowlist.',
			);
		}
		return array(
			'success' => true,
			'key'     => $key,
			'value'   => get_option( $key ),
		);
	}

	/**
	 * @param array<string, mixed> $args   Args.
	 * @param array<string, mixed> $config Config.
	 * @return array<string, mixed>
	 */
	private static function handler_http_get( array $args, array $config ): array {
		$raw = trim( (string) ( $args['path_or_url'] ?? '' ) );
		if ( '' === $raw ) {
			return array( 'success' => false, 'error' => 'path_or_url required' );
		}
		if ( str_starts_with( $raw, '/' ) ) {
			$url = home_url( $raw );
		} else {
			$url = esc_url_raw( $raw );
		}
		if ( ! $url || ! preg_match( '#^https?://#i', $url ) ) {
			return array( 'success' => false, 'error' => 'Invalid URL' );
		}
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$allowed = $config['allowed_hosts'] ?? array();
		if ( ! is_array( $allowed ) || ! in_array( $host, array_map( 'strtolower', $allowed ), true ) ) {
			return array( 'success' => false, 'error' => 'Host not allowed for this tool.' );
		}
		$max = absint( $config['max_bytes'] ?? 50000 );
		$res = wp_remote_get(
			$url,
			array(
				'timeout'     => 15,
				'redirection' => 2,
				'limit_response_size' => $max,
			)
		);
		if ( is_wp_error( $res ) ) {
			return array( 'success' => false, 'error' => $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = (string) wp_remote_retrieve_body( $res );
		if ( strlen( $body ) > $max ) {
			$body = substr( $body, 0, $max ) . '…';
		}
		return array(
			'success'     => $code >= 200 && $code < 400,
			'status_code' => $code,
			'body'        => $body,
			'url'         => $url,
		);
	}

	/**
	 * REST routes for Pro admin.
	 *
	 * @return void
	 */
	public static function register_rest_routes(): void {
		register_rest_route(
			'agentic/v1',
			'/site-local-tools',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'rest_list' ),
					'permission_callback' => static function () {
						return current_user_can( 'manage_options' );
					},
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'rest_create' ),
					'permission_callback' => static function () {
						return current_user_can( 'manage_options' );
					},
				),
			)
		);
		register_rest_route(
			'agentic/v1',
			'/site-local-tools/(?P<name>[a-z0-9_]+)',
			array(
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( __CLASS__, 'rest_update' ),
					'permission_callback' => static function () {
						return current_user_can( 'manage_options' );
					},
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'rest_delete' ),
					'permission_callback' => static function () {
						return current_user_can( 'manage_options' );
					},
				),
			)
		);
		register_rest_route(
			'agentic/v1',
			'/site-local-tools/(?P<name>[a-z0-9_]+)/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_test' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_list( \WP_REST_Request $request ) {
		return new \WP_REST_Response(
			array(
				'pro'      => self::is_feature_available(),
				'handlers' => self::HANDLERS,
				'tools'    => self::all(),
			),
			200
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_create( \WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}
		$result = self::upsert( is_array( $params ) ? $params : array() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new \WP_REST_Response( array( 'ok' => true, 'tool' => $result ), 201 );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_update( \WP_REST_Request $request ) {
		$name   = sanitize_key( (string) $request->get_param( 'name' ) );
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		if ( empty( $params['name'] ) ) {
			$params['name'] = $name;
		}
		$result = self::upsert( $params, $name );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new \WP_REST_Response( array( 'ok' => true, 'tool' => $result ), 200 );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_delete( \WP_REST_Request $request ) {
		$name   = sanitize_key( (string) $request->get_param( 'name' ) );
		$result = self::delete( $name );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new \WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_test( \WP_REST_Request $request ) {
		if ( ! self::current_user_can_manage() ) {
			return new \WP_Error( 'forbidden', __( 'Pro required.', 'agent-builder' ), array( 'status' => 403 ) );
		}
		$name = sanitize_key( (string) $request->get_param( 'name' ) );
		$def  = self::get( $name );
		if ( ! $def ) {
			return new \WP_Error( 'not_found', __( 'Tool not found.', 'agent-builder' ), array( 'status' => 404 ) );
		}
		$params = $request->get_json_params();
		$args   = is_array( $params ) && isset( $params['arguments'] ) && is_array( $params['arguments'] )
			? $params['arguments']
			: array();
		// Force enabled for test of draft tools.
		$def['enabled'] = true;
		$result         = self::execute_handler( $def, $args );
		return new \WP_REST_Response( array( 'ok' => true, 'result' => $result ), 200 );
	}

	/**
	 * Payload fragment for Tools admin page.
	 *
	 * @return array<string, mixed>
	 */
	public static function admin_payload(): array {
		$agents = array();
		if ( class_exists( '\Agentic_Agent_Registry' ) ) {
			foreach ( \Agentic_Agent_Registry::get_instance()->get_all_instances() as $agent ) {
				$agents[] = array(
					'id'   => $agent->get_id(),
					'name' => $agent->get_name(),
				);
			}
		}
		$handlers = array();
		foreach ( self::HANDLERS as $id => $meta ) {
			$handlers[] = array_merge( array( 'id' => $id ), $meta );
		}
		return array(
			'pro'              => self::is_feature_available(),
			'can_manage'       => self::current_user_can_manage(),
			'handlers'         => $handlers,
			'site_tools'       => self::all(),
			'agents'           => $agents,
			'upgrade_url'      => class_exists( Distribution::class )
				? Distribution::free_pro_promo_url()
				: 'https://agentic-plugin.com/licensing-and-pricing/',
			'docs_url'         => 'https://agentic-plugin.com/agent-tools/',
		);
	}
}
