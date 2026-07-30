<?php
/**
 * Admin pages REST — bootstrap + actions for React admin surfaces
 * (tools, skills list, approvals, activity logs, deployment, upgrade).
 *
 * Agents list intentionally stays PHP (WordPress plugins-style UI later).
 *
 * @package    Agent_Builder
 * @subpackage Includes
 * @since      3.3.0
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST for non-settings admin React pages.
 */
class Admin_Pages_REST {

	/**
	 * Boot.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'agentic/v1',
			'/admin-page',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_page' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'args'                => array(
						'page' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
						'tab'  => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'post_action' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * Permission check for the shared admin-page REST endpoint.
	 *
	 * This single endpoint serves several distinct React admin surfaces
	 * (tools, skills, approvals, logs, deployment, train-data, upgrade-pro),
	 * each already gated behind its own `agentic_*` capability at the
	 * wp-admin menu level (see Admin_Menu_Handler::register()). The
	 * capability required here must match that per-page grant, otherwise a
	 * role granted e.g. `agentic_manage_tools` can see the menu item and
	 * the empty page shell but every data request 403s.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function can_manage( \WP_REST_Request $request ): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$page   = sanitize_key( (string) $request->get_param( 'page' ) );
		$action = sanitize_key( (string) $request->get_param( 'action_name' ) );

		$tools_actions  = array( 'toggle_tool', 'apply_tools_profile', 'delete_skill' );
		$agents_actions = array( 'save_approval_prefs', 'approval_decide' );

		if ( 'tools' === $page || 'skills' === $page || in_array( $action, $tools_actions, true ) ) {
			return current_user_can( 'agentic_manage_tools' );
		}

		if ( 'approvals' === $page || 'deployment' === $page || in_array( $action, $agents_actions, true ) ) {
			return current_user_can( 'agentic_manage_agents' );
		}

		if ( 'logs' === $page ) {
			return current_user_can( 'agentic_view_audit_log' );
		}

		// train-data, upgrade-pro, and anything unmapped stay behind the
		// broadest admin-settings privilege as a safe default.
		return current_user_can( 'agentic_manage_settings' );
	}

	/**
	 * GET page payload.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_page( \WP_REST_Request $request ) {
		$page = sanitize_key( (string) $request->get_param( 'page' ) );
		$tab  = sanitize_key( (string) $request->get_param( 'tab' ) );

		switch ( $page ) {
			case 'tools':
				return new \WP_REST_Response( self::tools_payload( $tab ?: 'all' ), 200 );
			case 'skills':
				return new \WP_REST_Response( self::skills_payload(), 200 );
			case 'approvals':
				return new \WP_REST_Response( self::approvals_payload( $tab ?: 'approvals' ), 200 );
			case 'logs':
				$period = sanitize_key( (string) $request->get_param( 'period' ) );
				if ( ! in_array( $period, array( 'day', 'week', 'month' ), true ) ) {
					$period = 'week';
				}
				return new \WP_REST_Response( self::logs_payload( $tab ?: 'audit', $period ), 200 );
			case 'deployment':
				return new \WP_REST_Response( self::deployment_payload(), 200 );
			case 'upgrade-pro':
				return new \WP_REST_Response( self::upgrade_payload(), 200 );
			case 'train-data':
				return new \WP_REST_Response( self::train_payload( $tab ?: 'wiki' ), 200 );
			default:
				return new \WP_Error( 'unknown_page', __( 'Unknown admin page.', 'agent-builder' ), array( 'status' => 404 ) );
		}
	}

	/**
	 * POST action.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function post_action( \WP_REST_Request $request ) {
		$action = sanitize_key( (string) $request->get_param( 'action_name' ) );

		if ( 'toggle_tool' === $action ) {
			$name    = sanitize_text_field( (string) $request->get_param( 'name' ) );
			$enabled = rest_sanitize_boolean( $request->get_param( 'enabled' ) );
			if ( '' === $name || ! class_exists( Tools_Registry::class ) ) {
				return new \WP_Error( 'invalid', __( 'Invalid tool.', 'agent-builder' ), array( 'status' => 400 ) );
			}
			$ok = Tools_Registry::set_enabled( $name, $enabled );
			// Manual toggle leaves basic profile as custom.
			update_option( 'agentic_tools_ability_profile', 'custom', false );
			if ( $ok && class_exists( Audit_Log::class ) ) {
				Audit_Log::log_admin(
					$enabled ? 'tool_enabled' : 'tool_disabled',
					'tool',
					array(
						'id'      => $name,
						'name'    => $name,
						'enabled' => $enabled,
					)
				);
			}
			if ( class_exists( Security_Log::class ) ) {
				Security_Log::log_system( $enabled ? 'tool_enabled' : 'tool_disabled', $name );
			}
			return new \WP_REST_Response(
				array(
					'ok'      => (bool) $ok,
					'name'    => $name,
					'enabled' => $enabled,
				),
				$ok ? 200 : 500
			);
		}

		if ( 'apply_tools_profile' === $action ) {
			$profile_id = sanitize_key( (string) $request->get_param( 'profile' ) );
			$profiles   = self::tools_ability_profiles();
			if ( ! isset( $profiles[ $profile_id ] ) || 'custom' === $profile_id ) {
				return new \WP_Error( 'invalid_profile', __( 'Unknown ability profile.', 'agent-builder' ), array( 'status' => 400 ) );
			}
			if ( ! class_exists( Tools_Registry::class ) ) {
				return new \WP_Error( 'unavailable', __( 'Tools registry unavailable.', 'agent-builder' ), array( 'status' => 500 ) );
			}
			$max    = (string) $profiles[ $profile_id ]['max_risk'];
			$result = Tools_Registry::apply_max_risk_level( $max );
			update_option( 'agentic_tools_ability_profile', $profile_id, false );
			if ( class_exists( Audit_Log::class ) ) {
				Audit_Log::log_admin(
					'tools_profile_applied',
					'tools',
					array(
						'id'        => $profile_id,
						'profile'   => $profile_id,
						'max_risk'  => $max,
						'enabled'   => $result['enabled'] ?? 0,
						'disabled'  => $result['disabled'] ?? 0,
					),
					(string) ( $profiles[ $profile_id ]['label'] ?? $profile_id )
				);
			}
			if ( class_exists( Security_Log::class ) ) {
				Security_Log::log_system(
					'tools_profile_applied',
					'tools',
					array(
						'profile'  => $profile_id,
						'max_risk' => $max,
					)
				);
			}
			return new \WP_REST_Response(
				array(
					'ok'      => true,
					'profile' => $profile_id,
					'result'  => $result,
				),
				200
			);
		}

		if ( 'save_approval_prefs' === $action ) {
			return self::save_approval_prefs( $request );
		}

		if ( 'approval_decide' === $action ) {
			return self::approval_decide( $request );
		}

		if ( 'delete_skill' === $action ) {
			$id = absint( $request->get_param( 'id' ) );
			if ( $id < 1 || ! class_exists( Skills_Registry::class ) ) {
				return new \WP_Error( 'invalid', __( 'Invalid skill.', 'agent-builder' ), array( 'status' => 400 ) );
			}
			Skills_Registry::delete( $id );
			return new \WP_REST_Response( array( 'ok' => true, 'id' => $id ), 200 );
		}

		return new \WP_Error( 'unknown_action', __( 'Unknown action.', 'agent-builder' ), array( 'status' => 400 ) );
	}

	/**
	 * Known tool category slugs → admin labels.
	 *
	 * @return array<string, string>
	 */
	private static function tool_category_labels(): array {
		return array(
			'all'                      => __( 'All', 'agent-builder' ),
			'agents'                   => __( 'Agents', 'agent-builder' ),
			'ai-visibility'            => __( 'AI Visibility', 'agent-builder' ),
			'analytics'                => __( 'Analytics', 'agent-builder' ),
			'assistant-trainer'        => __( 'Agents', 'agent-builder' ),
			'caching'                  => __( 'Caching', 'agent-builder' ),
			'cli'                      => __( 'CLI', 'agent-builder' ),
			'communication'            => __( 'Communication', 'agent-builder' ),
			'content'                  => __( 'Content', 'agent-builder' ),
			'custom'                   => __( 'Custom', 'agent-builder' ),
			'crm'                      => __( 'CRM', 'agent-builder' ),
			'database'                 => __( 'Database', 'agent-builder' ),
			'dataforseo'               => __( 'DataForSEO', 'agent-builder' ),
			'ecommerce'                => __( 'Ecommerce', 'agent-builder' ),
			'email'                    => __( 'Email', 'agent-builder' ),
			'files'                    => __( 'Files', 'agent-builder' ),
			'forms'                    => __( 'Forms', 'agent-builder' ),
			'gbp'                      => __( 'Google Business', 'agent-builder' ),
			'git'                      => __( 'Git', 'agent-builder' ),
			'google-search-marketing'  => __( 'Search Marketing', 'agent-builder' ),
			'google-workspace'         => __( 'Google Workspace', 'agent-builder' ),
			'maintenance'              => __( 'Maintenance', 'agent-builder' ),
			'media'                    => __( 'Media', 'agent-builder' ),
			'orchestration'            => __( 'Orchestration', 'agent-builder' ),
			'plugins'                  => __( 'Plugins', 'agent-builder' ),
			'security'                 => __( 'Security', 'agent-builder' ),
			'seo'                      => __( 'SEO', 'agent-builder' ),
			'site-audit'               => __( 'Site Audit', 'agent-builder' ),
			'site-health'              => __( 'Site Health', 'agent-builder' ),
			'themes'                   => __( 'Themes', 'agent-builder' ),
			'users'                    => __( 'Users', 'agent-builder' ),
			'utility'                  => __( 'Utility', 'agent-builder' ),
			'web'                      => __( 'Web', 'agent-builder' ),
			'wordpress'                => __( 'WordPress', 'agent-builder' ),
		);
	}

	/**
	 * Human label for a tool category slug.
	 *
	 * @param string $slug Category slug.
	 * @return string
	 */
	private static function tool_category_label( string $slug ): string {
		$slug  = strtolower( $slug );
		$known = self::tool_category_labels();
		if ( isset( $known[ $slug ] ) ) {
			return $known[ $slug ];
		}

		return ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );
	}

	/**
	 * Normalize category slug for grouping (e.g. WordPress → wordpress).
	 *
	 * @param string $category Raw category.
	 * @return string
	 */
	private static function normalize_tool_category( string $category ): string {
		$category = strtolower( sanitize_key( $category ) );
		if ( '' === $category ) {
			return 'general';
		}
		// Collapse synonym.
		if ( 'agents' === $category ) {
			return 'assistant-trainer';
		}

		return $category;
	}

	/**
	 * @param string $tab Active category tab (all | category slug).
	 * @return array<string,mixed>
	 */
	private static function tools_payload( string $tab = 'all' ): array {
		// Keep registry in sync (same as tools.php).
		if ( class_exists( Tool_Loader::class ) ) {
			Tool_Loader::get_instance()->sync_to_registry();
			Tool_Loader::get_instance()->load();
		}
		if ( class_exists( Tools_Registry::class ) ) {
			Tools_Registry::seed_core_tools(
				array(
					'db_update_option' => 'database',
					'db_create_post'   => 'database',
					'db_update_post'   => 'database',
					'db_delete_post'   => 'database',
					'run_wp_cli'       => 'cli',
				)
			);
		}

		// Also sync tools claimed by active agents (category + presence).
		if ( class_exists( '\Agentic_Agent_Registry' ) && class_exists( Tool_Loader::class ) && class_exists( Tools_Registry::class ) ) {
			$loader    = Tool_Loader::get_instance();
			$instances = \Agentic_Agent_Registry::get_instance()->get_all_instances();
			foreach ( $instances as $agent ) {
				$names = $agent->get_tool_names();
				$defs  = $loader->get_definitions_for( $names );
				Tools_Registry::sync_agent_tools( $agent->get_id(), $defs );
			}
		}

		// Pro site-local tools → registry (source = site).
		if ( class_exists( Site_Local_Tools::class ) ) {
			Site_Local_Tools::sync_all_to_registry();
		}

		$tools = class_exists( Tools_Registry::class ) ? Tools_Registry::get_all() : array();
		$tab   = self::normalize_tool_category( $tab );
		if ( 'general' === $tab ) {
			$tab = 'all';
		}
		// Special tabs: site-local custom tools.
		$is_site_tab = ( 'custom' === $tab || 'site' === $tab );
		if ( $is_site_tab ) {
			$tab = 'custom';
		}

		// Counts per normalized category.
		$counts = array( 'all' => 0 );
		$rows_all = array();
		foreach ( $tools as $tool ) {
			$name = (string) ( $tool['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$cat = self::normalize_tool_category( (string) ( $tool['category'] ?? 'general' ) );
			if ( ! isset( $counts[ $cat ] ) ) {
				$counts[ $cat ] = 0;
			}
			++$counts[ $cat ];
			++$counts['all'];

			$rows_all[] = array(
				'id'          => $name,
				'title'       => $name,
				'subtitle'    => (string) ( $tool['description'] ?? '' ),
				'category'    => $cat,
				'category_label' => self::tool_category_label( $cat ),
				'enabled'     => ! empty( $tool['enabled'] ),
				'source'      => (string) ( $tool['source'] ?? 'core' ),
				'risk_level'  => (string) ( $tool['risk_level'] ?? '' ),
			);
		}

		// Build tabs: All first, then Custom (site-local), then categories A–Z.
		$cat_slugs = array_keys( $counts );
		$cat_slugs = array_values(
			array_filter(
				$cat_slugs,
				static function ( $slug ) use ( $counts ) {
					return 'all' !== $slug && 'custom' !== $slug && ( $counts[ $slug ] ?? 0 ) > 0;
				}
			)
		);
		usort(
			$cat_slugs,
			static function ( $a, $b ) {
				return strcasecmp( self::tool_category_label( $a ), self::tool_category_label( $b ) );
			}
		);

		$site_count = 0;
		foreach ( $rows_all as $row ) {
			if ( 'site' === ( $row['source'] ?? '' ) || 'custom' === ( $row['category'] ?? '' ) ) {
				++$site_count;
			}
		}
		if ( class_exists( Site_Local_Tools::class ) ) {
			$site_count = max( $site_count, count( Site_Local_Tools::all() ) );
		}

		if ( ! $is_site_tab && 'all' !== $tab && ! in_array( $tab, $cat_slugs, true ) ) {
			$tab = 'all';
		}

		$tabs   = array();
		$tabs[] = array(
			'id'    => 'all',
			'label' => sprintf(
				/* translators: %d: tool count */
				__( 'All (%d)', 'agent-builder' ),
				(int) ( $counts['all'] ?? 0 )
			),
			'url'   => admin_url( 'admin.php?page=agentic-tools&tab=all' ),
		);
		$tabs[] = array(
			'id'    => 'custom',
			'label' => sprintf(
				/* translators: %d: site-local tool count */
				__( 'Custom (%d)', 'agent-builder' ),
				$site_count
			),
			'url'   => admin_url( 'admin.php?page=agentic-tools&tab=custom' ),
		);
		foreach ( $cat_slugs as $slug ) {
			$tabs[] = array(
				'id'    => $slug,
				'label' => sprintf(
					'%s (%d)',
					self::tool_category_label( $slug ),
					(int) $counts[ $slug ]
				),
				'url'   => admin_url( 'admin.php?page=agentic-tools&tab=' . rawurlencode( $slug ) ),
			);
		}

		$rows = $rows_all;
		if ( $is_site_tab ) {
			$rows = array_values(
				array_filter(
					$rows_all,
					static function ( $row ) {
						return 'site' === ( $row['source'] ?? '' ) || 'custom' === ( $row['category'] ?? '' );
					}
				)
			);
		} elseif ( 'all' !== $tab ) {
			$rows = array_values(
				array_filter(
					$rows_all,
					static function ( $row ) use ( $tab ) {
						return ( $row['category'] ?? '' ) === $tab;
					}
				)
			);
		}

		$panel_title = 'all' === $tab
			? __( 'All tools', 'agent-builder' )
			: ( $is_site_tab ? __( 'Site-local tools', 'agent-builder' ) : self::tool_category_label( $tab ) );

		$site_local = class_exists( Site_Local_Tools::class )
			? Site_Local_Tools::admin_payload()
			: array( 'pro' => false, 'can_manage' => false, 'handlers' => array(), 'site_tools' => array(), 'agents' => array() );

		$is_advanced = class_exists( Admin_Menu_Handler::class )
			? Admin_Menu_Handler::is_advanced_mode()
			: ( 'advanced' === get_option( 'agentic_ui_mode', 'basic' ) );

		$enabled_count  = 0;
		$disabled_count = 0;
		foreach ( $rows_all as $row ) {
			if ( ! empty( $row['enabled'] ) ) {
				++$enabled_count;
			} else {
				++$disabled_count;
			}
		}

		$active_profile = self::resolve_active_tools_profile();
		$profile_cards  = array();
		foreach ( self::tools_ability_profiles() as $id => $p ) {
			if ( 'custom' === $id ) {
				continue;
			}
			$profile_cards[] = array(
				'id'          => $id,
				'label'       => $p['label'],
				'summary'     => $p['summary'],
				'detail'      => $p['detail'],
				'max_risk'    => $p['max_risk'],
				'icon'        => $p['icon'],
				'active'      => $id === $active_profile,
			);
		}

		return array(
			'page'              => 'tools',
			'tab'               => $tab,
			'site_local'        => $site_local,
			'title'             => __( 'Tools', 'agent-builder' ),
			'panel_title'       => $is_advanced
				? $panel_title
				: __( 'What may assistants do?', 'agent-builder' ),
			'description'       => $is_advanced
				? __( 'Enable or disable tools assistants can use. Group by category using the tabs.', 'agent-builder' )
				: __( 'Pick a simple safety profile. We turn tools on or off to match — no need to manage hundreds of tools one by one.', 'agent-builder' ),
			'rows'              => $rows,
			'tabs'              => $tabs,
			'counts'            => $counts,
			'is_advanced'       => $is_advanced,
			'ui_mode'           => $is_advanced ? 'advanced' : 'basic',
			'interface_url'     => admin_url( 'admin.php?page=agentic-settings&tab=interface' ),
			'active_profile'    => $active_profile,
			'profiles'          => $profile_cards,
			'enabled_count'     => $enabled_count,
			'disabled_count'    => $disabled_count,
			'enabled_max_risk'  => class_exists( Tools_Registry::class )
				? Tools_Registry::enabled_max_risk_level()
				: 'none',
			'docs_url'          => 'https://agentic-plugin.com/agent-tools/',
			'footer_policy'     => __(
				'Assistants only use the tools you allow. Higher-risk actions still follow Approvals and your safety settings. Provider processing of chat content is covered by our Privacy Policy.',
				'agent-builder'
			),
		);
	}

	/**
	 * Named ability profiles for Basic Tools UI.
	 *
	 * @return array<string, array{label:string,summary:string,detail:string,max_risk:string,icon:string}>
	 */
	private static function tools_ability_profiles(): array {
		return array(
			'browse'  => array(
				'label'    => __( 'Browse & answer', 'agent-builder' ),
				'summary'  => __( 'Safest — read-only help', 'agent-builder' ),
				'detail'   => __( 'Assistants can look things up and answer questions. They cannot change posts, settings, or your site.', 'agent-builder' ),
				'max_risk' => 'low',
				'icon'     => '👀',
			),
			'assist'  => array(
				'label'    => __( 'Help with drafts', 'agent-builder' ),
				'summary'  => __( 'Balanced — create drafts with care', 'agent-builder' ),
				'detail'   => __( 'Read plus everyday writing (drafts and light edits). Riskier changes still ask for confirmation.', 'agent-builder' ),
				'max_risk' => 'medium',
				'icon'     => '✍️',
			),
			'manage'  => array(
				'label'    => __( 'Manage my site', 'agent-builder' ),
				'summary'  => __( 'Full productivity — approvals for big changes', 'agent-builder' ),
				'detail'   => __( 'Most tools on, including significant updates. High-risk actions go through the Approvals queue. Extreme tools stay off.', 'agent-builder' ),
				'max_risk' => 'high',
				'icon'     => '🛠️',
			),
			'custom'  => array(
				'label'    => __( 'Custom mix', 'agent-builder' ),
				'summary'  => __( 'You mixed tools manually', 'agent-builder' ),
				'detail'   => __( 'Individual tools were toggled outside a profile.', 'agent-builder' ),
				'max_risk' => 'high',
				'icon'     => '⚙️',
			),
		);
	}

	/**
	 * Active profile id (stored or inferred).
	 *
	 * @return string
	 */
	private static function resolve_active_tools_profile(): string {
		$stored = sanitize_key( (string) get_option( 'agentic_tools_ability_profile', '' ) );
		$profiles = self::tools_ability_profiles();
		if ( $stored && isset( $profiles[ $stored ] ) && 'custom' !== $stored ) {
			return $stored;
		}
		if ( 'custom' === $stored ) {
			return 'custom';
		}
		// Infer from current max enabled risk.
		if ( ! class_exists( Tools_Registry::class ) ) {
			return 'browse';
		}
		$max = Tools_Registry::enabled_max_risk_level();
		$w   = Risk_Level::weight( $max );
		if ( $w <= Risk_Level::weight( Risk_Level::LOW ) ) {
			return 'browse';
		}
		if ( $w <= Risk_Level::weight( Risk_Level::MEDIUM ) ) {
			return 'assist';
		}
		return 'manage';
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function skills_payload(): array {
		$skills = class_exists( Skills_Registry::class ) ? Skills_Registry::get_all() : array();
		$rows   = array();
		foreach ( $skills as $skill ) {
			$id = (int) ( $skill['id'] ?? 0 );
			$rows[] = array(
				'id'          => (string) $id,
				'title'       => (string) ( $skill['name'] ?? '' ),
				'subtitle'    => (string) ( $skill['description'] ?? '' ),
				'agent'       => (string) ( $skill['agent_slug'] ?? '' ),
				'enabled'     => ! empty( $skill['enabled'] ),
				'version'     => (string) ( $skill['version'] ?? '' ),
				'edit_url'    => admin_url( 'admin.php?page=agentic-skills&skill_view=edit&skill_id=' . $id ),
				'delete_id'   => $id,
			);
		}

		return array(
			'page'        => 'skills',
			'title'       => __( 'Skills', 'agent-builder' ),
			'description' => __( 'Instructions that teach agents when and how to use tools.', 'agent-builder' ),
			'rows'        => $rows,
			'actions'     => array(
				array(
					'label' => __( 'Create Skill', 'agent-builder' ),
					'url'   => admin_url( 'admin.php?page=agentic-skills&skill_view=new' ),
					'primary' => true,
				),
				array(
					'label' => __( 'Browse Community', 'agent-builder' ),
					'url'   => admin_url( 'admin.php?page=agentic-skills&skill_view=hub' ),
				),
			),
		);
	}

	/**
	 * @param string $tab Tab.
	 * @return array<string,mixed>
	 */
	private static function approvals_payload( string $tab ): array {
		$queue   = new Approval_Queue();
		$pending = $queue->get_pending();
		$rows    = array();
		foreach ( $pending as $item ) {
			$action = (string) ( $item['tool_name'] ?? $item['action'] ?? 'action' );
			$params = $item['params'] ?? $item['data'] ?? array();
			if ( is_string( $params ) ) {
				$decoded = json_decode( $params, true );
				$params  = is_array( $decoded ) ? $decoded : array();
			}
			$summary = '';
			if ( ! empty( $item['reasoning'] ) ) {
				$summary = (string) $item['reasoning'];
			} elseif ( ! empty( $params['file_path'] ) ) {
				$summary = (string) $params['file_path'];
			} elseif ( ! empty( $params['title'] ) ) {
				$summary = (string) $params['title'];
			} else {
				$summary = wp_json_encode( $params );
			}

			$rows[] = array(
				'id'         => (string) ( $item['id'] ?? '' ),
				'title'      => str_replace( '_', ' ', $action ),
				'action'     => $action,
				'subtitle'   => (string) ( $item['agent_id'] ?? '' ),
				'risk_level' => (string) ( $item['risk_level'] ?? 'high' ),
				'created_at' => (string) ( $item['created_at'] ?? '' ),
				'summary'    => $summary,
			);
		}

		$is_advanced = class_exists( Admin_Menu_Handler::class )
			? Admin_Menu_Handler::is_advanced_mode()
			: ( 'advanced' === get_option( 'agentic_ui_mode', 'basic' ) );

		$prefs = self::get_approval_prefs();

		return array(
			'page'           => 'approvals',
			'tab'            => $tab,
			'title'          => __( 'Approvals', 'agent-builder' ),
			'panel_title'    => __( 'Things waiting for your OK', 'agent-builder' ),
			'description'    => __( 'When an assistant wants to change something important, it waits here until you approve or reject it.', 'agent-builder' ),
			'pending_count'  => count( $rows ),
			'rows'           => $rows,
			'tabs'           => array(
				array(
					'id'    => 'approvals',
					'label' => __( 'Approvals', 'agent-builder' ),
					'url'   => admin_url( 'admin.php?page=agentic-approvals&tab=approvals' ),
				),
				array(
					'id'    => 'backups',
					'label' => __( 'Backups', 'agent-builder' ),
					'url'   => admin_url( 'admin.php?page=agentic-approvals&tab=backups' ),
				),
			),
			'agent_mode'     => (string) get_option( 'agentic_agent_mode', 'supervised' ),
			'is_advanced'    => $is_advanced,
			'interface_url'  => admin_url( 'admin.php?page=agentic-settings&tab=interface' ),
			'prefs'          => $prefs,
			'comfort_profiles' => self::approval_comfort_profiles(),
			'docs_url'       => 'https://agentic-plugin.com/approval-queue/',
			'footer_policy'  => __(
				'Approvals keep high-risk assistant actions under human control. Email alerts use your admin address and never include passwords. See Privacy Policy for how providers process chat.',
				'agent-builder'
			),
			'classic_url'    => admin_url( 'admin.php?page=agentic-approvals&tab=approvals&classic=1' ),
		);
	}

	/**
	 * Comfort profiles for non-technical Approvals preferences.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function approval_comfort_profiles(): array {
		$active = sanitize_key( (string) get_option( 'agentic_approval_comfort', 'careful' ) );
		$cards  = array(
			array(
				'id'       => 'careful',
				'icon'     => '🛡️',
				'label'    => __( 'Always ask me', 'agent-builder' ),
				'summary'  => __( 'Safest default', 'agent-builder' ),
				'detail'   => __( 'Important or writing actions wait for you. Best when you want full control.', 'agent-builder' ),
				'risk_note'=> __( 'No automatic approvals beyond the safest reads.', 'agent-builder' ),
				'auto_max' => 'none',
				'mode'     => 'supervised',
				'needs_ack'=> false,
			),
			array(
				'id'       => 'balanced',
				'icon'     => '⚖️',
				'label'    => __( 'Auto-approve low risk', 'agent-builder' ),
				'summary'  => __( 'Recommended for most sites', 'agent-builder' ),
				'detail'   => __( 'Simple look-ups run freely. Drafts and bigger changes still pause for confirmation or this queue.', 'agent-builder' ),
				'risk_note'=> __( 'You accept that low-risk tools may run without a separate approval email.', 'agent-builder' ),
				'auto_max' => 'low',
				'mode'     => 'supervised',
				'needs_ack'=> false,
			),
			array(
				'id'       => 'hands_off',
				'icon'     => '⚡',
				'label'    => __( 'Trust more (higher risk)', 'agent-builder' ),
				'summary'  => __( 'Faster — use with care', 'agent-builder' ),
				'detail'   => __( 'Assistants work with less interruption (autonomous mode). You can still review history. Extreme tools stay blocked.', 'agent-builder' ),
				'risk_note'=> __( 'I understand assistants may change content without waiting in this queue, and I accept that increased risk.', 'agent-builder' ),
				'auto_max' => 'low',
				'mode'     => 'autonomous',
				'needs_ack'=> true,
			),
		);
		foreach ( $cards as &$c ) {
			$c['active'] = ( $c['id'] === $active );
		}
		unset( $c );
		return $cards;
	}

	/**
	 * Current approval notification / comfort prefs.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_approval_prefs(): array {
		$email = sanitize_email( (string) get_option( 'agentic_approval_email_to', '' ) );
		if ( ! is_email( $email ) ) {
			$email = (string) get_option( 'admin_email' );
		}
		return array(
			'email_notify' => (bool) get_option( 'agentic_approval_email_notify', false ),
			'email_to'     => $email,
			'comfort'      => sanitize_key( (string) get_option( 'agentic_approval_comfort', 'careful' ) ),
			'auto_max_risk'=> sanitize_key( (string) get_option( 'agentic_approval_auto_max_risk', 'none' ) ),
			'risk_ack'     => (bool) get_option( 'agentic_approval_risk_ack', false ),
			'agent_mode'   => (string) get_option( 'agentic_agent_mode', 'supervised' ),
		);
	}

	/**
	 * Save Approvals preferences from React UI.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private static function save_approval_prefs( \WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', __( 'Permission denied.', 'agent-builder' ), array( 'status' => 403 ) );
		}

		$email_notify = rest_sanitize_boolean( $request->get_param( 'email_notify' ) );
		$email_to     = sanitize_email( (string) $request->get_param( 'email_to' ) );
		$comfort      = sanitize_key( (string) $request->get_param( 'comfort' ) );
		$risk_ack     = rest_sanitize_boolean( $request->get_param( 'risk_ack' ) );

		$profiles = array();
		foreach ( self::approval_comfort_profiles() as $p ) {
			$profiles[ $p['id'] ] = $p;
		}
		if ( ! isset( $profiles[ $comfort ] ) ) {
			$comfort = 'careful';
		}

		if ( ! empty( $profiles[ $comfort ]['needs_ack'] ) && ! $risk_ack ) {
			return new \WP_Error(
				'ack_required',
				__( 'Please confirm you accept the increased risk for “Trust more”.', 'agent-builder' ),
				array( 'status' => 400 )
			);
		}

		if ( $email_notify && $email_to && ! is_email( $email_to ) ) {
			return new \WP_Error( 'invalid_email', __( 'Enter a valid email address.', 'agent-builder' ), array( 'status' => 400 ) );
		}

		$prev = self::get_approval_prefs();

		update_option( 'agentic_approval_email_notify', $email_notify ? 1 : 0, false );
		if ( is_email( $email_to ) ) {
			update_option( 'agentic_approval_email_to', $email_to, false );
		}

		$auto_max = (string) ( $profiles[ $comfort ]['auto_max'] ?? 'none' );
		$mode     = (string) ( $profiles[ $comfort ]['mode'] ?? 'supervised' );

		update_option( 'agentic_approval_comfort', $comfort, false );
		update_option( 'agentic_approval_auto_max_risk', $auto_max, false );
		update_option( 'agentic_agent_mode', $mode, false );
		update_option( 'agentic_approval_risk_ack', ( ! empty( $profiles[ $comfort ]['needs_ack'] ) && $risk_ack ) ? 1 : 0, false );

		if ( class_exists( Audit_Log::class ) ) {
			Audit_Log::log_admin(
				'approval_prefs_saved',
				'approvals',
				array(
					'id'           => $comfort,
					'comfort'      => $comfort,
					'auto_max'     => $auto_max,
					'agent_mode'   => $mode,
					'email_notify' => $email_notify,
					'previous'     => $prev,
				)
			);
		}
		if ( class_exists( Security_Log::class ) ) {
			Security_Log::log_system(
				'approval_prefs_saved',
				'approvals',
				array(
					'comfort'    => $comfort,
					'agent_mode' => $mode,
					'auto_max'   => $auto_max,
				)
			);
		}

		return new \WP_REST_Response(
			array(
				'ok'    => true,
				'prefs' => self::get_approval_prefs(),
			),
			200
		);
	}

	/**
	 * Approve or reject a queue item from the admin UI.
	 *
	 * Prefer the dedicated REST route which also executes on approve; this
	 * action is a thin fallback for the React admin-page POST channel.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private static function approval_decide( \WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', __( 'Permission denied.', 'agent-builder' ), array( 'status' => 403 ) );
		}
		$id     = absint( $request->get_param( 'id' ) );
		$decide = sanitize_key( (string) $request->get_param( 'decide' ) );
		if ( $id < 1 || ! in_array( $decide, array( 'approve', 'reject' ), true ) ) {
			return new \WP_Error( 'invalid', __( 'Invalid approval request.', 'agent-builder' ), array( 'status' => 400 ) );
		}

		// Dispatch through the main REST approval handler so approve also runs the tool.
		$rest_req = new \WP_REST_Request( 'POST', '/agentic/v1/approvals/' . $id );
		$rest_req->set_param( 'id', $id );
		$rest_req->set_param( 'action', $decide );
		$response = rest_do_request( $rest_req );
		if ( $response->is_error() ) {
			return $response->as_error();
		}

		// Flatten nested api_success envelope for the React admin UI.
		$body = $response->get_data();
		$core = is_array( $body ) ? $body : array();
		if ( isset( $core['data'] ) && is_array( $core['data'] ) ) {
			$core = $core['data'];
		}

		return new \WP_REST_Response(
			array_merge(
				array(
					'ok'     => true,
					'id'     => $id,
					'decide' => $decide,
				),
				is_array( $core ) ? $core : array()
			),
			200
		);
	}

	/**
	 * @param string $tab Tab.
	 * @return array<string,mixed>
	 */
	private static function logs_payload( string $tab, string $period = 'week' ): array {
		$rows          = array();
		$stats         = array(
			'total'      => 0,
			'chats'      => 0,
			'tools'      => 0,
			'approvals'  => 0,
			'security'   => 0,
			'tokens'     => 0,
		);
		$is_advanced   = class_exists( Admin_Menu_Handler::class )
			? Admin_Menu_Handler::is_advanced_mode()
			: ( 'advanced' === get_option( 'agentic_ui_mode', 'basic' ) );
		$period_limits = array(
			'day'   => 200,
			'week'  => 500,
			'month' => 1500,
		);
		$limit = $period_limits[ $period ] ?? 500;

		if ( 'audit' === $tab && class_exists( Audit_Log::class ) ) {
			$log = new Audit_Log();
			// Hide noisy chat_start/complete by default (same as classic audit page).
			$exclude = array( 'chat_start', 'chat_complete' );
			$items   = $log->get_recent( $limit, null, null, $period, $exclude );
			foreach ( (array) $items as $item ) {
				$row    = self::humanize_audit_row( is_array( $item ) ? $item : (array) $item );
				$rows[] = $row;
				++$stats['total'];
				$kind = $row['kind'] ?? 'other';
				if ( 'chat' === $kind ) {
					++$stats['chats'];
				} elseif ( 'tool' === $kind ) {
					++$stats['tools'];
				} elseif ( 'approval' === $kind ) {
					++$stats['approvals'];
				} elseif ( 'security' === $kind ) {
					++$stats['security'];
				}
				$stats['tokens'] += (int) ( $row['tokens'] ?? 0 );
			}
		} elseif ( 'conversations' === $tab ) {
			global $wpdb;
			$table = $wpdb->prefix . 'agentic_conversations';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $exists ) {
				$days = match ( $period ) {
					'week'  => 7,
					'month' => 30,
					default => 1,
				};
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; %i quotes table name.
				$items = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT id, agent_id, user_id, updated_at, created_at FROM %i
						WHERE updated_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
						ORDER BY updated_at DESC LIMIT %d',
						$table,
						$days,
						$limit
					),
					ARRAY_A
				);
				foreach ( (array) $items as $item ) {
					$agent = (string) ( $item['agent_id'] ?? '' );
					$user  = absint( $item['user_id'] ?? 0 );
					$uname = $user ? ( get_userdata( $user )->display_name ?? ( 'User #' . $user ) ) : __( 'Guest', 'agent-builder' );
					$rows[] = array(
						'id'         => (string) ( $item['id'] ?? '' ),
						'title'      => sprintf(
							/* translators: 1: agent name, 2: conversation id */
							__( 'Chat with %1$s (#%2$s)', 'agent-builder' ),
							self::friendly_agent_name( $agent ),
							(string) ( $item['id'] ?? '' )
						),
						'subtitle'   => $uname,
						'when'       => (string) ( $item['updated_at'] ?? $item['created_at'] ?? '' ),
						'when_human' => self::human_time_label( (string) ( $item['updated_at'] ?? $item['created_at'] ?? '' ) ),
						'detail'     => '',
						'kind'       => 'chat',
						'icon'       => '💬',
						'raw_action' => 'conversation',
						'tokens'     => 0,
					);
					++$stats['total'];
					++$stats['chats'];
				}
			}
		} elseif ( 'security' === $tab && class_exists( Security_Log::class ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'agentic_security_log';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $exists ) {
				$days = match ( $period ) {
					'week'  => 7,
					'month' => 30,
					default => 1,
				};
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; %i quotes table name.
				$items = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT * FROM %i
						WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
						ORDER BY id DESC LIMIT %d',
						$table,
						$days,
						$limit
					),
					ARRAY_A
				);
				foreach ( (array) $items as $item ) {
					$event = (string) ( $item['event'] ?? $item['action'] ?? 'security' );
					$rows[] = array(
						'id'         => (string) ( $item['id'] ?? '' ),
						'title'      => self::friendly_security_title( $event ),
						'subtitle'   => (string) ( $item['context'] ?? $item['source'] ?? '' ),
						'when'       => (string) ( $item['created_at'] ?? '' ),
						'when_human' => self::human_time_label( (string) ( $item['created_at'] ?? '' ) ),
						'detail'     => self::friendly_detail_string( (string) ( $item['message'] ?? $item['details'] ?? '' ) ),
						'kind'       => 'security',
						'icon'       => '🔒',
						'raw_action' => $event,
						'tokens'     => 0,
					);
					++$stats['total'];
					++$stats['security'];
				}
			}
		}

		$period_labels = array(
			'day'   => __( 'Today', 'agent-builder' ),
			'week'  => __( 'Last 7 days', 'agent-builder' ),
			'month' => __( 'Last 30 days', 'agent-builder' ),
		);

		return array(
			'page'           => 'logs',
			'tab'            => $tab,
			'title'          => __( 'Activity', 'agent-builder' ),
			'panel_title'    => match ( $tab ) {
				'conversations' => __( 'Recent conversations', 'agent-builder' ),
				'security'      => __( 'Security events', 'agent-builder' ),
				default         => __( 'What your assistants have been doing', 'agent-builder' ),
			},
			'description'    => match ( $tab ) {
				'conversations' => __( 'Chats between people and assistants on this site.', 'agent-builder' ),
				'security'      => __( 'Security-related events. Most sites stay quiet here.', 'agent-builder' ),
				default         => __( 'A simple timeline of assistant work — tools used, approvals, and important system events. No technical jargon required.', 'agent-builder' ),
			},
			'rows'           => $rows,
			'stats'          => $stats,
			'period'         => $period,
			'period_options' => array(
				array( 'id' => 'day', 'label' => $period_labels['day'] ),
				array( 'id' => 'week', 'label' => $period_labels['week'] ),
				array( 'id' => 'month', 'label' => $period_labels['month'] ),
			),
			'kind_filters'   => array(
				array( 'id' => 'all', 'label' => __( 'All', 'agent-builder' ) ),
				array( 'id' => 'tool', 'label' => __( 'Tools used', 'agent-builder' ) ),
				array( 'id' => 'approval', 'label' => __( 'Approvals', 'agent-builder' ) ),
				array( 'id' => 'chat', 'label' => __( 'Chats', 'agent-builder' ) ),
				array( 'id' => 'settings', 'label' => __( 'Settings', 'agent-builder' ) ),
				array( 'id' => 'other', 'label' => __( 'Other', 'agent-builder' ) ),
			),
			'is_advanced'    => $is_advanced,
			'interface_url'  => admin_url( 'admin.php?page=agentic-settings&tab=interface' ),
			'docs_url'       => 'https://agentic-plugin.com/audit-log/',
			'footer_policy'  => __(
				'Activity helps you understand what assistants did on your site. Logs are stored locally and purged according to your retention settings. Chat content lives under Conversations.',
				'agent-builder'
			),
			'tabs'           => array(
				array(
					'id'    => 'audit',
					'label' => __( 'Timeline', 'agent-builder' ),
					'url'   => admin_url( 'admin.php?page=agentic-audit-log&tab=audit&period=' . rawurlencode( $period ) ),
				),
				array(
					'id'    => 'conversations',
					'label' => __( 'Conversations', 'agent-builder' ),
					'url'   => admin_url( 'admin.php?page=agentic-audit-log&tab=conversations&period=' . rawurlencode( $period ) ),
				),
				array(
					'id'    => 'security',
					'label' => __( 'Security', 'agent-builder' ),
					'url'   => admin_url( 'admin.php?page=agentic-audit-log&tab=security&period=' . rawurlencode( $period ) ),
				),
			),
		);
	}

	/**
	 * Turn a raw audit row into a friendly timeline item.
	 *
	 * @param array<string,mixed> $item DB row.
	 * @return array<string,mixed>
	 */
	private static function humanize_audit_row( array $item ): array {
		$action  = (string) ( $item['action'] ?? 'event' );
		$agent   = (string) ( $item['agent_id'] ?? '' );
		$target  = (string) ( $item['target_type'] ?? '' );
		$when    = (string) ( $item['created_at'] ?? '' );
		$details = $item['details'] ?? '';
		if ( is_string( $details ) ) {
			$decoded = json_decode( $details, true );
			$details = is_array( $decoded ) ? $decoded : array( 'raw' => $details );
		}
		if ( ! is_array( $details ) ) {
			$details = array();
		}

		$kind  = 'other';
		$icon  = '•';
		$title = self::friendly_action_title( $action, $target, $details );

		if ( str_starts_with( $action, 'chat_' ) || 'conversation' === $target ) {
			$kind = 'chat';
			$icon = '💬';
		} elseif ( str_contains( $action, 'tool' ) || 'tool_call' === $action || 'tool_choice' === $action || 'tool_executed_on_approval' === $action ) {
			$kind = 'tool';
			$icon = '🔧';
			if ( $target && 'conversation' !== $target && 'tool_call' === $action ) {
				$title = sprintf(
					/* translators: %s: tool name */
					__( 'Used tool: %s', 'agent-builder' ),
					str_replace( '_', ' ', $target )
				);
			}
		} elseif ( str_contains( $action, 'approval' ) || str_contains( $action, 'approve' ) || str_contains( $action, 'reject' ) ) {
			$kind = 'approval';
			$icon = '✅';
		} elseif (
			str_contains( $action, 'settings' )
			|| str_contains( $action, 'mode' )
			|| str_contains( $action, 'profile' )
			|| str_contains( $action, 'deployment' )
			|| str_contains( $action, 'tool_enabled' )
			|| str_contains( $action, 'tool_disabled' )
			|| str_contains( $action, 'site_tool' )
			|| str_contains( $action, 'prefs' )
		) {
			$kind = 'settings';
			$icon = '⚙️';
		}

		$detail_bits = array();
		if ( ! empty( $item['reasoning'] ) ) {
			$detail_bits[] = (string) $item['reasoning'];
		}
		if ( ! empty( $details['message'] ) && is_string( $details['message'] ) ) {
			$detail_bits[] = $details['message'];
		}
		if ( ! empty( $details['error'] ) && is_string( $details['error'] ) ) {
			$detail_bits[] = $details['error'];
		}
		$tokens = (int) ( $item['tokens_used'] ?? 0 );
		if ( $tokens > 0 ) {
			$detail_bits[] = sprintf(
				/* translators: %s: token count */
				__( '%s tokens', 'agent-builder' ),
				number_format_i18n( $tokens )
			);
		}

		return array(
			'id'         => (string) ( $item['id'] ?? '' ),
			'title'      => $title,
			'subtitle'   => self::friendly_agent_name( $agent ),
			'when'       => $when,
			'when_human' => self::human_time_label( $when ),
			'detail'     => implode( ' · ', array_filter( $detail_bits ) ),
			'kind'       => $kind,
			'icon'       => $icon,
			'raw_action' => $action,
			'tokens'     => $tokens,
			'cost'       => (float) ( $item['cost'] ?? 0 ),
		);
	}

	/**
	 * @param string               $action  Action slug.
	 * @param string               $target  Target type.
	 * @param array<string,mixed>  $details Details.
	 * @return string
	 */
	private static function friendly_action_title( string $action, string $target, array $details ): string {
		$map = array(
			'chat_start'                  => __( 'Started a chat', 'agent-builder' ),
			'chat_complete'               => __( 'Finished a chat', 'agent-builder' ),
			'tool_call'                   => __( 'Used a tool', 'agent-builder' ),
			'tool_choice'                 => __( 'Chose tools for a reply', 'agent-builder' ),
			'tool_executed_on_approval'   => __( 'Ran an approved action', 'agent-builder' ),
			'tool_enabled'                => __( 'Tool turned on', 'agent-builder' ),
			'tool_disabled'               => __( 'Tool turned off', 'agent-builder' ),
			'tools_profile_applied'       => __( 'Tools safety profile applied', 'agent-builder' ),
			'site_tool_created'           => __( 'Site-local tool created', 'agent-builder' ),
			'site_tool_updated'           => __( 'Site-local tool updated', 'agent-builder' ),
			'site_tool_deleted'           => __( 'Site-local tool deleted', 'agent-builder' ),
			'approval_queued'             => __( 'Action waiting for approval', 'agent-builder' ),
			'approval_approved'           => __( 'You approved an action', 'agent-builder' ),
			'approval_rejected'           => __( 'You rejected an action', 'agent-builder' ),
			'action_approved'             => __( 'You approved an action', 'agent-builder' ),
			'action_rejected'             => __( 'You rejected an action', 'agent-builder' ),
			'approval_prefs_saved'        => __( 'Approval preferences saved', 'agent-builder' ),
			'ui_mode_changed'             => __( 'Interface mode changed', 'agent-builder' ),
			'default_agent_mode_changed'  => __( 'Default assistant mode changed', 'agent-builder' ),
			'deployment_created'          => __( 'Deployment created', 'agent-builder' ),
			'deployment_updated'          => __( 'Deployment updated', 'agent-builder' ),
			'deployment_enabled'          => __( 'Deployment enabled', 'agent-builder' ),
			'deployment_disabled'         => __( 'Deployment disabled', 'agent-builder' ),
			'deployment_deleted'          => __( 'Deployment deleted', 'agent-builder' ),
			'settings_changed'            => __( 'Settings changed', 'agent-builder' ),
			'agent_activated'             => __( 'Assistant activated', 'agent-builder' ),
			'agent_deactivated'           => __( 'Assistant deactivated', 'agent-builder' ),
		);
		if ( isset( $map[ $action ] ) ) {
			return $map[ $action ];
		}
		return ucwords( str_replace( array( '_', '-' ), ' ', $action ) );
	}

	/**
	 * @param string $slug Agent slug.
	 * @return string
	 */
	private static function friendly_agent_name( string $slug ): string {
		if ( '' === $slug || 'human' === $slug || 'system' === $slug ) {
			return __( 'You / system', 'agent-builder' );
		}
		if ( class_exists( '\Agentic_Agent_Registry' ) ) {
			$agent = \Agentic_Agent_Registry::get_instance()->get_agent_instance( $slug );
			if ( $agent && method_exists( $agent, 'get_name' ) ) {
				return $agent->get_name();
			}
		}
		return ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );
	}

	/**
	 * @param string $event Security event.
	 * @return string
	 */
	private static function friendly_security_title( string $event ): string {
		$map = array(
			'approval_execution_exception' => __( 'Approved action failed while running', 'agent-builder' ),
			'chat_exception'               => __( 'Chat hit an error', 'agent-builder' ),
			'chat_error'                   => __( 'Chat error recorded', 'agent-builder' ),
		);
		return $map[ $event ] ?? ucwords( str_replace( array( '_', '-' ), ' ', $event ) );
	}

	/**
	 * @param string $raw Raw detail.
	 * @return string
	 */
	private static function friendly_detail_string( string $raw ): string {
		$raw = trim( wp_strip_all_tags( $raw ) );
		if ( '' === $raw ) {
			return '';
		}
		if ( strlen( $raw ) > 160 ) {
			return substr( $raw, 0, 160 ) . '…';
		}
		return $raw;
	}

	/**
	 * @param string $mysql_utc MySQL datetime (UTC-ish).
	 * @return string
	 */
	private static function human_time_label( string $mysql_utc ): string {
		if ( '' === $mysql_utc ) {
			return '';
		}
		$ts = strtotime( $mysql_utc . ' UTC' );
		if ( ! $ts ) {
			$ts = strtotime( $mysql_utc );
		}
		if ( ! $ts ) {
			return $mysql_utc;
		}
		return sprintf(
			/* translators: %s: human time diff */
			__( '%s ago', 'agent-builder' ),
			human_time_diff( $ts, time() )
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function deployment_payload(): array {
		return array(
			'page'        => 'deployment',
			'title'       => __( 'Publish', 'agent-builder' ),
			'description' => __( 'Deploy assistants to shortcodes, blocks, and channels.', 'agent-builder' ),
			'links'       => array(
				array(
					'label' => __( 'Shortcodes', 'agent-builder' ),
					'url'   => admin_url( 'admin.php?page=agentic-deployment' ),
					'hint'  => __( 'Embed chat on any page', 'agent-builder' ),
				),
				array(
					'label' => __( 'Open Agent Chat', 'agent-builder' ),
					'url'   => admin_url( 'admin.php?page=agentic-chat' ),
					'hint'  => __( 'Test assistants in wp-admin', 'agent-builder' ),
				),
				array(
					'label' => __( 'Train an Agent', 'agent-builder' ),
					'url'   => admin_url( 'admin.php?page=agentic-agent-wizard' ),
					'hint'  => __( 'Wizard to create a new assistant', 'agent-builder' ),
				),
			),
			'legacy_note' => __( 'Full deployment editor (shortcode builder, CLI, modals) remains available when you open a deployment action from here.', 'agent-builder' ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function upgrade_payload(): array {
		$is_pro = class_exists( License_Client::class ) && License_Client::get_instance()->is_pro();
		$pricing = class_exists( Distribution::class )
			? Distribution::PRICING_URL
			: 'https://agentic-plugin.com/licensing-and-pricing/';
		return array(
			'page'        => 'upgrade-pro',
			'title'       => $is_pro ? __( 'Pro', 'agent-builder' ) : __( 'Upgrade to Pro', 'agent-builder' ),
			'description' => $is_pro
				? __( 'Pro is active on this site.', 'agent-builder' )
				: __( 'Unlock vector RAG, connectors, channels, and advanced metering.', 'agent-builder' ),
			'is_pro'      => $is_pro,
			'pricing_url' => $pricing,
			'features'    => array(
				__( 'Hosted vector store / RAG', 'agent-builder' ),
				__( 'MCP connectors & channels', 'agent-builder' ),
				__( 'Usage analytics & costs', 'agent-builder' ),
				__( 'Priority support', 'agent-builder' ),
			),
		);
	}

	/**
	 * @param string $tab Tab.
	 * @return array<string,mixed>
	 */
	private static function train_payload( string $tab ): array {
		$concepts = array();
		if ( class_exists( Okf_Store::class ) ) {
			foreach ( Okf_Store::list_concepts( '', true ) as $c ) {
				$concepts[] = array(
					'id'      => (string) ( $c['id'] ?? '' ),
					'title'   => (string) ( $c['title'] ?? $c['id'] ?? '' ),
					'type'    => (string) ( $c['type'] ?? '' ),
					'example' => ! empty( $c['example'] ),
					'status'  => (string) ( $c['status'] ?? '' ),
				);
			}
		}
		$is_pro = class_exists( License_Client::class ) && License_Client::get_instance()->is_pro();

		return array(
			'page'        => 'train-data',
			'tab'         => $tab,
			'title'       => __( 'Knowledge', 'agent-builder' ),
			'description' => __( 'Wiki concepts (OKF) and optional Pro vector store.', 'agent-builder' ),
			'is_pro'      => $is_pro,
			'concepts'    => $concepts,
			'tabs'        => array_values(
				array_filter(
					array(
						array(
							'id'    => 'wiki',
							'label' => __( 'Knowledge Wiki', 'agent-builder' ),
							'url'   => admin_url( 'admin.php?page=agentic-train-data&tab=wiki' ),
						),
						$is_pro ? array(
							'id'    => 'vector',
							'label' => __( 'Vector Store', 'agent-builder' ),
							'url'   => admin_url( 'admin.php?page=agentic-train-data&tab=vector' ),
						) : null,
					)
				)
			),
			'manage_url'  => admin_url( 'admin.php?page=agentic-train-data&tab=wiki' ),
		);
	}
}
