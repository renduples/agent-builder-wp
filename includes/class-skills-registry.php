<?php
/**
 * Skills Registry — database-backed CRUD for agent skills.
 *
 * Skills contain SKILL.md instructions that teach agents when and how
 * to use channel-specific tools. They live above channels and below agents
 * in the stack: agent → skill → channel → tool.
 *
 * @package    Agent_Builder
 * @subpackage Includes
 * @since      2.5.2
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Skills_Registry
 *
 * @since 2.5.2
 */
final class Skills_Registry {

	/**
	 * Runtime cache (null = not yet loaded).
	 *
	 * @var array<int, array<string, mixed>>|null
	 */
	private static ?array $cache = null;

	// ── Cache ─────────────────────────────────────────────────────────────

	/**
	 * Clear both static and persistent caches.
	 *
	 * @return void
	 */
	public static function bust_cache(): void {
		self::$cache = null;
		wp_cache_delete( 'agentic_skills_all', 'agentic' );
	}

	// ── Read ──────────────────────────────────────────────────────────────

	/**
	 * Get all skills from the database.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$cached = wp_cache_get( 'agentic_skills_all', 'agentic' );
		if ( is_array( $cached ) ) {
			self::$cache = $cached;
			return self::$cache;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'agentic_skills';

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, manual cache.
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A );

		self::$cache = is_array( $rows ) ? $rows : array();

		wp_cache_set( 'agentic_skills_all', self::$cache, 'agentic', HOUR_IN_SECONDS );

		return self::$cache;
	}

	/**
	 * Get a single skill by ID.
	 *
	 * @param int $id Skill ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'agentic_skills';

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get a single skill by slug.
	 *
	 * @param string $slug Skill slug.
	 * @return array<string, mixed>|null
	 */
	public static function get_by_slug( string $slug ): ?array {
		foreach ( self::get_all() as $skill ) {
			if ( $slug === $skill['slug'] ) {
				return $skill;
			}
		}
		return null;
	}

	/**
	 * Get skills assigned to a specific agent.
	 *
	 * @param string $agent_slug Agent slug.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_for_agent( string $agent_slug ): array {
		return array_values(
			array_filter(
				self::get_all(),
				static fn( array $s ): bool => '1' === ( $s['enabled'] ?? '0' )
					&& ( '' === $s['agent_slug'] || $agent_slug === $s['agent_slug'] )
			)
		);
	}

	/**
	 * Count skills grouped by source.
	 *
	 * @return array<string, int>
	 */
	public static function count_by_source(): array {
		$counts = array();
		foreach ( self::get_all() as $skill ) {
			$src            = $skill['source'] ?? 'local';
			$counts[ $src ] = ( $counts[ $src ] ?? 0 ) + 1;
		}
		return $counts;
	}

	// ── Write ─────────────────────────────────────────────────────────────

	/**
	 * Create a new skill.
	 *
	 * @param array<string, mixed> $data Skill data.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function create( array $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'agentic_skills';
		$now   = current_time( 'mysql', true );

		$slug = sanitize_title( $data['name'] ?? 'skill' );

		// Ensure unique slug.
		$existing = self::get_by_slug( $slug );
		if ( $existing ) {
			$slug .= '-' . wp_generate_password( 4, false );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
		$result = $wpdb->insert(
			$table,
			array(
				'name'        => sanitize_text_field( $data['name'] ?? '' ),
				'slug'        => $slug,
				'description' => sanitize_text_field( $data['description'] ?? '' ),
				'content'     => $data['content'] ?? '',
				'agent_slug'  => sanitize_key( $data['agent_slug'] ?? '' ),
				'source'      => sanitize_key( $data['source'] ?? 'local' ),
				'source_id'   => sanitize_text_field( $data['source_id'] ?? '' ),
				'version'     => sanitize_text_field( $data['version'] ?? '1.0.0' ),
				'author'      => sanitize_text_field( $data['author'] ?? '' ),
				'enabled'     => ! empty( $data['enabled'] ) ? 1 : 0,
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		self::bust_cache();
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing skill.
	 *
	 * @param int                  $id   Skill ID.
	 * @param array<string, mixed> $data Fields to update.
	 * @return bool
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'agentic_skills';

		$fields = array();
		$format = array();

		$allowed = array(
			'name'        => '%s',
			'description' => '%s',
			'content'     => '%s',
			'agent_slug'  => '%s',
			'version'     => '%s',
			'author'      => '%s',
			'enabled'     => '%d',
		);

		foreach ( $allowed as $key => $fmt ) {
			if ( array_key_exists( $key, $data ) ) {
				if ( 'content' === $key ) {
					$fields[ $key ] = $data[ $key ];
				} elseif ( 'enabled' === $key ) {
					$fields[ $key ] = ! empty( $data[ $key ] ) ? 1 : 0;
				} elseif ( 'agent_slug' === $key ) {
					$fields[ $key ] = sanitize_key( $data[ $key ] );
				} else {
					$fields[ $key ] = sanitize_text_field( $data[ $key ] );
				}
				$format[] = $fmt;
			}
		}

		if ( empty( $fields ) ) {
			return false;
		}

		$fields['updated_at'] = current_time( 'mysql', true );
		$format[]             = '%s';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
		$result = $wpdb->update( $table, $fields, array( 'id' => $id ), $format, array( '%d' ) );

		self::bust_cache();

		return false !== $result;
	}

	/**
	 * Delete a skill.
	 *
	 * @param int $id Skill ID.
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'agentic_skills';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		self::bust_cache();

		return false !== $result && $result > 0;
	}

	/**
	 * Seed bundled core skills from library/skills/{slug}/SKILL.md files.
	 *
	 * Only inserts skills that do not already exist (checked by slug).
	 * Existing skills — including those imported from ClawHub — are left
	 * untouched so user edits are preserved.
	 *
	 * @return int Number of newly seeded skills.
	 */
	public static function seed_core_skills(): int {
		$core_dir = defined( 'AGENT_BUILDER_DIR' ) ? AGENT_BUILDER_DIR . 'library/skills/' : '';

		$dirs = array();
		if ( '' !== $core_dir && is_dir( $core_dir ) ) {
			$dirs[] = $core_dir;
		}

		// Allow Pro (and other extensions) to register additional skill directories.
		$dirs = apply_filters( 'agentic_skill_dirs', $dirs );
		$dirs = array_unique( array_filter( $dirs, 'is_dir' ) );

		if ( empty( $dirs ) ) {
			return 0;
		}

		$seeded = 0;

		foreach ( $dirs as $skills_dir ) {
			$skill_files = glob( rtrim( $skills_dir, '/' ) . '/*/SKILL.md' );
			foreach ( ( $skill_files ? $skill_files : array() ) as $skill_file ) {
				$slug = basename( dirname( $skill_file ) );

				if ( self::get_by_slug( $slug ) ) {
					continue;
				}

				$raw = file_get_contents( $skill_file );
				if ( false === $raw || '' === $raw ) {
					continue;
				}

				$name        = ucwords( str_replace( '-', ' ', $slug ) );
				$description = '';

				if ( preg_match( '/^---\s*\n(.*?)\n---\s*\n/s', $raw, $m ) ) {
					if ( preg_match( '/^name:\s*(.+)$/m', $m[1], $nm ) ) {
						$name = trim( $nm[1], " \t\"'" );
					}
					if ( preg_match( '/^description:\s*["\']?(.*?)["\']?\s*$/m', $m[1], $dm ) ) {
						$description = trim( $dm[1], " \t\"'" );
					}
				}

				self::create(
					array(
						'name'        => $name,
						'description' => $description,
						'content'     => $raw,
						'agent_slug'  => '',
						'source'      => 'core',
						'source_id'   => $slug,
						'version'     => '1.0.0',
						'author'      => 'Agentic',
						'enabled'     => true,
					)
				);

				++$seeded;
			}
		}

		return $seeded;
	}

	/**
	 * Extract the `tools:` list from SKILL.md YAML front matter.
	 *
	 * Parses inline YAML arrays in the form `tools: [foo, bar]` or block
	 * sequences `tools:\n  - foo\n  - bar`. Returns an empty array when no
	 * `tools:` key is present — absence is not an error.
	 *
	 * @param string $content Raw SKILL.md content.
	 * @return string[] Tool name list, or empty array.
	 */
	public static function parse_front_matter_tools( string $content ): array {
		if ( ! preg_match( '/^---\s*\n(.*?)\n---\s*\n/s', $content, $m ) ) {
			return array();
		}
		$front_matter = $m[1];

		// Inline array: tools: [foo, bar, baz].
		if ( preg_match( '/^tools:\s*\[([^\]]*)\]/m', $front_matter, $inline ) ) {
			$names = array_map( 'trim', explode( ',', $inline[1] ) );
			return array_values( array_filter( $names ) );
		}

		// Block sequence:
		// tools:
		// - foo
		// - bar.
		if ( preg_match( '/^tools:\s*\n((?:\s+-\s+\S+\n?)+)/m', $front_matter, $block ) ) {
			preg_match_all( '/^\s+-\s+(\S+)/m', $block[1], $items );
			return array_values( array_filter( $items[1] ) );
		}

		return array();
	}

	/**
	 * Validate all skills with declared tools against the live Tools_Registry.
	 *
	 * Only skills that include a `tools:` list in their SKILL.md front matter
	 * are checked — absence of the key is not treated as an error, allowing
	 * incremental adoption.
	 *
	 * @return array<string, string[]> Map of skill_slug => unknown tool names.
	 */
	public static function validate(): array {
		$errors      = array();
		$known_tools = array_keys( Tools_Registry::get_all() );

		foreach ( self::get_all() as $skill ) {
			$declared = self::parse_front_matter_tools( $skill['content'] ?? '' );
			if ( empty( $declared ) ) {
				continue;
			}

			$unknown = array_values( array_diff( $declared, $known_tools ) );
			if ( ! empty( $unknown ) ) {
				$errors[ $skill['slug'] ] = $unknown;
			}
		}

		return $errors;
	}

	/**
	 * Import a skill from ClawHub data.
	 *
	 * @param array<string, mixed> $hub_data Data from ClawHub API.
	 * @return int|false Inserted ID or false.
	 */
	public static function import_from_hub( array $hub_data ) {
		// ClawHub uses slug as stable identifier; also accept _id or id.
		$source_id = $hub_data['slug'] ?? ( $hub_data['_id'] ?? ( $hub_data['id'] ?? '' ) );

		// Normalise ClawHub field names.
		if ( empty( $hub_data['name'] ) && ! empty( $hub_data['displayName'] ) ) {
			$hub_data['name'] = $hub_data['displayName'];
		}
		if ( empty( $hub_data['description'] ) && ! empty( $hub_data['summary'] ) ) {
			$hub_data['description'] = $hub_data['summary'];
		}

		// Check if already imported.
		if ( $source_id ) {
			foreach ( self::get_all() as $existing ) {
				if ( in_array( $existing['source'], array( 'clawhub', 'agentic' ), true ) && $source_id === $existing['source_id'] ) {
					// Update existing import.
					self::update(
						(int) $existing['id'],
						array(
							'name'        => $hub_data['name'] ?? $existing['name'],
							'description' => $hub_data['description'] ?? $existing['description'],
							'content'     => $hub_data['content'] ?? ( $hub_data['skill_md'] ?? $existing['content'] ),
							'version'     => $hub_data['version'] ?? $existing['version'],
							'author'      => $hub_data['author'] ?? $existing['author'],
						)
					);
					return (int) $existing['id'];
				}
			}
		}

		$source = sanitize_key( $hub_data['source'] ?? 'clawhub' );
		if ( ! in_array( $source, array( 'agentic', 'clawhub' ), true ) ) {
			$source = 'clawhub';
		}

		return self::create(
			array(
				'name'        => $hub_data['name'] ?? 'Imported Skill',
				'description' => $hub_data['description'] ?? '',
				'content'     => $hub_data['content'] ?? ( $hub_data['skill_md'] ?? '' ),
				'agent_slug'  => '',
				'source'      => $source,
				'source_id'   => (string) $source_id,
				'version'     => $hub_data['version'] ?? '1.0.0',
				'author'      => $hub_data['author'] ?? '',
				'enabled'     => true,
			)
		);
	}
}
