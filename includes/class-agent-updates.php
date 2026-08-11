<?php
/**
 * Agent Updates
 *
 * Checks for newer versions of AI agents
 * and provides the one-click update installation logic
 *
 * @package    Agent_Builder
 * @subpackage Includes
 * @since      2.4.0
 */

namespace Agentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agent_Updates class.
 */
class Agent_Updates {

	const TRANSIENT     = 'agentic_agent_updates';
	const TTL           = 12 * HOUR_IN_SECONDS;
	const OPT_IN_OPTION = 'agentic_agent_updates_optin';

	/**
	 * Marketplace URL for discovering / installing community agents.
	 *
	 * Free and WordPress.org builds no longer phone home for agent update
	 * checks. Users browse and install from the public marketplace instead.
	 */
	const MARKETPLACE_URL = 'https://agentic-plugin.com/community-agents/';

	/**
	 * Whether remote agent-update checks are available on this site.
	 *
	 * Free / WordPress.org builds never call the store for update checks
	 * (Guideline 7/8): there is no in-plugin install path without Pro, and
	 * free users are directed to the Community Agents marketplace instead.
	 * Pro-licensed sites may still opt in to in-dashboard update notices.
	 */
	public static function is_remote_check_available(): bool {
		return class_exists( License_Client::class )
			&& License_Client::get_instance()->is_pro();
	}

	/**
	 * Whether the site administrator has opted in to agent update checks.
	 *
	 * Returns true only when Pro is active and the option is explicitly 'yes'.
	 * Free builds always return false (no phone-home).
	 */
	public static function is_opted_in(): bool {
		if ( ! self::is_remote_check_available() ) {
			return false;
		}
		return 'yes' === get_option( self::OPT_IN_OPTION, '' );
	}

	/**
	 * Whether the consent prompt still needs to be shown (choice not yet made).
	 *
	 * Free builds never show the consent wall — use the marketplace link instead.
	 */
	public static function needs_consent(): bool {
		if ( ! self::is_remote_check_available() ) {
			return false;
		}
		return '' === get_option( self::OPT_IN_OPTION, '' );
	}

	/**
	 * Check for available agent updates and cache the results.
	 *
	 * Only caches a result on success so that transient expiry triggers a fresh
	 * check — failed checks are retried on the next page load.
	 * Does nothing if the administrator has not opted in (or free / non-Pro).
	 *
	 * @param array $installed Map of slug => agent_data from get_installed_agents().
	 */
	public static function check( array $installed ): void {
		if ( ! self::is_opted_in() ) {
			return;
		}
		$registry = \Agentic_Agent_Registry::get_instance();

		$agents = array();
		foreach ( $installed as $slug => $agent ) {
			if ( empty( $agent['version'] ) ) {
				continue;
			}

			// A slug that a plugin bundles belongs to that plugin. Asking the
			// store about it invites an answer we must not act on: installing it
			// would write a copy into agents_dir that displaces the bundled agent
			// for good, and no plugin update would ever reach it again.
			if ( $registry->is_bundled_slug( $slug ) ) {
				continue;
			}

			$agents[] = array(
				'slug'    => $slug,
				'version' => $agent['version'],
			);
		}

		if ( empty( $agents ) ) {
			set_transient( self::TRANSIENT, array(), self::TTL );
			return;
		}

		$response = wp_remote_post(
			Service_Registry::url( 'agentic-api', '/wp-json/agentic/v1/agents/check-updates' ),
			array(
				'body'    => wp_json_encode( array( 'agents' => $agents ) ),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// Fail-open: don't cache errors so the next page load triggers a retry.
			return;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		set_transient( self::TRANSIENT, is_array( $data ) ? $data : array(), self::TTL );
	}

	/**
	 * Get cached update data.
	 *
	 * @return array<string, array{name: string, version: string, package: string, url: string}>
	 */
	public static function get(): array {
		$data = get_transient( self::TRANSIENT );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Return the number of available updates.
	 */
	public static function count(): int {
		return count( self::get() );
	}

	/**
	 * Bust the updates cache so the next page load triggers a fresh check.
	 */
	public static function bust(): void {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * Admin_init handler: process consent form and trigger update check on the Agents page.
	 *
	 * No-ops on all other admin pages. Handles the consent POST before any output
	 * is sent, then triggers a fresh remote check when the transient is stale.
	 *
	 * @return void
	 */
	public static function maybe_check_on_agents_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || 'agentic-agents' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// Free builds: no consent form, no remote update checks.
		if ( ! self::is_remote_check_available() ) {
			return;
		}

		// Handle consent form POST before any output is sent (Pro only).
		if (
			isset( $_POST['agentic_updates_consent'], $_POST['_wpnonce_updates_consent'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce_updates_consent'] ) ), 'agentic_updates_consent' ) &&
			current_user_can( 'manage_options' )
		) {
			$agentic_consent_value = 'enable' === sanitize_key( wp_unslash( $_POST['agentic_updates_consent'] ) ) ? 'yes' : 'no';
			update_option( self::OPT_IN_OPTION, $agentic_consent_value );
			wp_safe_redirect( admin_url( 'admin.php?page=agentic-agents' ) );
			exit;
		}

		// Trigger a fresh update check when the cache is stale.
		if ( false !== get_transient( self::TRANSIENT ) ) {
			return;
		}

		self::check( \Agentic_Agent_Registry::get_instance()->get_installed_agents( true ) );
	}

	/**
	 * Download and install an update for a given agent.
	 *
	 * Replicates the zip-upload flow from admin/agents.php but triggered
	 * programmatically. The agent's previous directory is moved aside rather than
	 * deleted, and any entitlement files the new zip does not carry (.license,
	 * .activation) are copied forward from it — losing those would leave a
	 * purchased agent unable to run.
	 *
	 * @param string $slug    Agent slug.
	 * @param string $zip_url Direct download URL for the new-version zip.
	 * @return true|\WP_Error
	 */
	public static function do_update( string $slug, string $zip_url ): bool|\WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', __( 'Insufficient permissions.', 'agent-builder' ) );
		}

		// The plugin that bundles a slug owns it. Installing a store copy here
		// would shadow the bundled agent permanently. The store should never
		// offer one; refuse even if it does.
		if ( \Agentic_Agent_Registry::get_instance()->is_bundled_slug( $slug ) ) {
			\Agentic\Security_Log::log_system(
				'agent_update_blocked',
				'agents',
				array(
					'slug'   => $slug,
					'reason' => 'bundled_slug',
				)
			);
			return new \WP_Error(
				'bundled_agent',
				__( 'This agent ships with a plugin and is updated when that plugin updates.', 'agent-builder' )
			);
		}

		// Installing/updating agent packages from a remote archive is an Agent
		// Builder Pro feature. The free plugin never downloads and installs
		// executable agent code (WordPress.org Guideline 8).
		if ( ! class_exists( '\Agentic\License_Client' ) || ! \Agentic\License_Client::get_instance()->is_pro() ) {
			return new \WP_Error( 'pro_required', __( 'Updating installed agent packages requires Agent Builder Pro.', 'agent-builder' ) );
		}

		// SSRF guard — only allow downloads from our host.
		$allowed_host = 'agentic-plugin.com';
		$url_host     = wp_parse_url( $zip_url, PHP_URL_HOST );
		if ( $url_host !== $allowed_host ) {
			return new \WP_Error( 'invalid_url', __( 'Invalid update source.', 'agent-builder' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		$agents_dir = AGENTIC_AGENTS_DIR;

		if ( ! is_dir( $agents_dir ) ) {
			wp_mkdir_p( $agents_dir );
		}

		// Log that the update process is beginning (before any I/O that can fail).
		\Agentic\Security_Log::log_system(
			'agent_update_started',
			'agents',
			array(
				'slug'    => $slug,
				'zip_url' => $zip_url,
			)
		);

		// Download the zip to a temp file.
		$tmp_file = download_url( $zip_url, 30 );
		if ( is_wp_error( $tmp_file ) ) {
			\Agentic\Security_Log::log_system(
				'agent_update_failed',
				'agents',
				array(
					'slug'   => $slug,
					'reason' => 'download_failed',
					'error'  => $tmp_file->get_error_message(),
				)
			);
			return $tmp_file;
		}

		// Extract to a unique temp directory so we can inspect the zip structure
		// before committing to the final destination.  This mirrors the manual-upload
		// flow in admin/agents.php and correctly handles zips that contain a wrapper
		// subdirectory (e.g. ai-radar-1.2/agent.php) as well as flat zips
		// (e.g. agent.php at the archive root).
		$tmp_dir = $agents_dir . '/__update_tmp_' . wp_generate_password( 8, false );
		wp_mkdir_p( $tmp_dir );

		$result = unzip_file( $tmp_file, $tmp_dir );
		wp_delete_file( $tmp_file );

		if ( is_wp_error( $result ) ) {
			$wp_filesystem->delete( $tmp_dir, true );
			\Agentic\Security_Log::log_system(
				'agent_update_failed',
				'agents',
				array(
					'slug'   => $slug,
					'reason' => 'unzip_failed',
					'error'  => $result->get_error_message(),
				)
			);
			return $result;
		}

		// Locate agent.php — either at the archive root or one level deep inside
		// a wrapper folder (the most common GitHub / release-zip layout).
		$agent_root = null;

		if ( file_exists( $tmp_dir . '/agent.php' ) ) {
			$agent_root = $tmp_dir;
		} else {
			$subdirs = glob( $tmp_dir . '/*', GLOB_ONLYDIR );
			if ( $subdirs ) {
				foreach ( $subdirs as $subdir ) {
					if ( file_exists( $subdir . '/agent.php' ) ) {
						$agent_root = $subdir;
						break;
					}
				}
			}
		}

		if ( ! $agent_root ) {
			$wp_filesystem->delete( $tmp_dir, true );
			\Agentic\Security_Log::log_system(
				'agent_update_failed',
				'agents',
				array(
					'slug'   => $slug,
					'reason' => 'no_agent_php',
					'error'  => 'The update zip does not contain a valid agent.php file.',
				)
			);
			return new \WP_Error( 'invalid_agent', __( 'The update zip does not contain a valid agent.php file.', 'agent-builder' ) );
		}

		// Move the extracted agent into the canonical slug directory. The previous
		// version is moved aside first, never deleted: it holds the proof of
		// purchase (.license, .activation) and any edit the site owner made.
		$dest_dir   = $agents_dir . '/' . sanitize_file_name( $slug );
		$backup_dir = '';

		if ( is_dir( $dest_dir ) ) {
			$backup_dir = self::backup_dir_for( $slug );

			if ( '' === $backup_dir ) {
				$wp_filesystem->delete( $tmp_dir, true );
				\Agentic\Security_Log::log_system(
					'agent_update_failed',
					'agents',
					array(
						'slug'   => $slug,
						'reason' => 'backup_dir_unavailable',
					)
				);
				return new \WP_Error( 'backup_failed', __( 'Could not create a backup of the current agent. Update aborted.', 'agent-builder' ) );
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Moving a local directory; WP_Filesystem::move() does not reliably move directories.
			if ( ! rename( $dest_dir, $backup_dir ) ) {
				$wp_filesystem->delete( $tmp_dir, true );
				\Agentic\Security_Log::log_system(
					'agent_update_failed',
					'agents',
					array(
						'slug'   => $slug,
						'reason' => 'backup_failed',
						'dest'   => $backup_dir,
					)
				);
				return new \WP_Error( 'backup_failed', __( 'Could not back up the current agent. Update aborted.', 'agent-builder' ) );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Moving a local directory; WP_Filesystem::move() does not reliably move directories.
		if ( ! rename( $agent_root, $dest_dir ) ) {
			$wp_filesystem->delete( $tmp_dir, true );

			// Put the old agent back. Failing an update must not remove one.
			if ( '' !== $backup_dir && is_dir( $backup_dir ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Restoring the directory we moved aside a moment ago.
				rename( $backup_dir, $dest_dir );
			}

			\Agentic\Security_Log::log_system(
				'agent_update_failed',
				'agents',
				array(
					'slug'   => $slug,
					'reason' => 'rename_failed',
					'src'    => $agent_root,
					'dest'   => $dest_dir,
				)
			);
			return new \WP_Error( 'rename_failed', __( 'Failed to move updated agent files into place.', 'agent-builder' ) );
		}

		// Carry entitlement forward. A zip never contains .activation, and only a
		// premium zip contains .license; without them a purchased agent that has
		// just updated would fail License_Client::can_agent_run().
		if ( '' !== $backup_dir ) {
			self::carry_forward_entitlement( $backup_dir, $dest_dir );
		}

		// Clean up temp extraction dir (may already be empty if agent_root == tmp_dir).
		if ( is_dir( $tmp_dir ) ) {
			$wp_filesystem->delete( $tmp_dir, true );
		}

		// Stamp .uploaded so the registry treats this as a user-installed agent
		// rather than a bundled library agent (giving it load priority over library/).
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		file_put_contents( $dest_dir . '/.uploaded', '1' );

		// Bust both the registry cache and the updates transient.
		\Agentic_Agent_Registry::get_instance()->get_installed_agents( true );
		self::bust();

		\Agentic\Security_Log::log_system(
			'agent_update_succeeded',
			'agents',
			array(
				'slug'   => $slug,
				'backup' => $backup_dir,
			)
		);

		return true;
	}

	/**
	 * Base64 Ed25519 public key that authentic purchased-agent manifests are
	 * signed with by the marketplace.
	 *
	 * Pinned in the client so a manifest is only trusted when it carries a valid
	 * detached signature from the holder of the matching private key (kept in the
	 * vendor's secret store, never shipped). Overridable via the
	 * AGENTIC_AGENT_SIGNING_PUBLIC_KEY constant or the
	 * `agentic_agent_signing_public_key` filter so production can pin its own key
	 * without a code change.
	 *
	 * NOTE: the default below is a DEV key for local verification — it must be
	 * replaced with the production public key before release.
	 *
	 * @return string Base64-encoded public key.
	 */
	public static function signing_public_key(): string {
		// Production Ed25519 publisher key. The matching private key lives only in
		// GCP Secret Manager (agentic-agent-signing-private-key) and is used by the
		// marketplace to sign manifests and tool bundles. Overridable via the
		// constant/filter below for staging or key rotation.
		$key = 'TciYj4ezwIQ/u++c2f3bUeVjfIGcV+BXz3Px/Qcxo3w=';
		if ( defined( 'AGENTIC_AGENT_SIGNING_PUBLIC_KEY' ) && '' !== (string) constant( 'AGENTIC_AGENT_SIGNING_PUBLIC_KEY' ) ) {
			$key = (string) constant( 'AGENTIC_AGENT_SIGNING_PUBLIC_KEY' );
		}
		return (string) apply_filters( 'agentic_agent_signing_public_key', $key );
	}

	/**
	 * Verify a detached Ed25519 signature over the exact manifest bytes the
	 * marketplace signed.
	 *
	 * Verifying the transported bytes (not a re-serialized form) means the client
	 * needs no canonicalization parity with the vendor — semantic validation of
	 * the decoded manifest happens separately, in install_purchased_row().
	 *
	 * @param string $canonical     Exact manifest string the vendor signed.
	 * @param string $signature_b64 Base64 detached signature.
	 * @return bool True only when the signature verifies against the pinned key.
	 */
	public static function verify_manifest_signature( string $canonical, string $signature_b64 ): bool {
		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			return false; // Without libsodium we cannot verify — refuse rather than trust.
		}
		$pub = base64_decode( self::signing_public_key(), true );
		$sig = base64_decode( $signature_b64, true );
		if ( false === $pub || false === $sig
			|| SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== strlen( $pub )
			|| SODIUM_CRYPTO_SIGN_BYTES !== strlen( $sig )
		) {
			return false;
		}
		try {
			return sodium_crypto_sign_verify_detached( $sig, $canonical, $pub );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Verify a signed manifest and install it as a purchased row.
	 *
	 * The authenticity boundary: the detached signature must verify against the
	 * pinned publisher key before the manifest is decoded, validated, and stored.
	 *
	 * @param string $slug               Purchased agent slug.
	 * @param string $canonical_manifest Exact signed manifest string.
	 * @param string $signature_b64      Base64 detached signature.
	 * @param string $source_id          Optional marketplace listing/order id.
	 * @return true|\WP_Error
	 */
	public static function install_signed( string $slug, string $canonical_manifest, string $signature_b64, string $source_id = '' ): bool|\WP_Error {
		if ( ! self::verify_manifest_signature( $canonical_manifest, $signature_b64 ) ) {
			\Agentic\Security_Log::log_system(
				'agent_install_failed',
				'agents',
				array(
					'slug'   => sanitize_key( $slug ),
					'reason' => 'bad_signature',
				)
			);
			return new \WP_Error( 'bad_signature', __( 'The purchased agent could not be verified as authentic and was not installed.', 'agent-builder' ) );
		}

		$manifest = json_decode( $canonical_manifest, true );
		if ( ! is_array( $manifest ) ) {
			return new \WP_Error( 'invalid_manifest', __( 'The purchased agent manifest was invalid.', 'agent-builder' ) );
		}

		return self::install_purchased_row( $slug, $manifest, '', $source_id );
	}

	/**
	 * Install (or update) a purchased agent as a row in the agent library table.
	 *
	 * This is the table-native install path: instead of unzipping executable
	 * files into the writable agents directory (which 3.0.0 never loads —
	 * WP.org Guideline 8), a purchased agent is a declarative manifest written
	 * as a source=purchased row. The registry then interprets it via
	 * Manifest_Agent with no filesystem read.
	 *
	 * Integrity is enforced by comparing the canonical manifest hash to the one
	 * the marketplace vouched for. Asymmetric publisher-signature verification
	 * layers on top of this later (see AGENT_DISTRIBUTION.md §8.9); the hash
	 * check already blocks a manifest corrupted or altered in transit.
	 *
	 * A purchase may replace a prior purchased row for the same slug but never a
	 * bundled or user-created agent — those are the owner's and are left intact.
	 *
	 * @param string               $slug          Purchased agent slug.
	 * @param array<string, mixed> $manifest      Declarative manifest from the marketplace.
	 * @param string               $expected_hash Optional sha256 the marketplace vouched for.
	 * @param string               $source_id     Optional marketplace listing/order identifier.
	 * @return true|\WP_Error
	 */
	public static function install_purchased_row( string $slug, array $manifest, string $expected_hash = '', string $source_id = '' ): bool|\WP_Error {
		if ( ! class_exists( '\Agentic\Agent_Library' ) || ! class_exists( '\Agentic\Agent_Manifest_Validator' ) ) {
			return new \WP_Error( 'no_library', __( 'The agent library is unavailable.', 'agent-builder' ) );
		}

		$slug = sanitize_key( $slug );
		if ( '' === $slug ) {
			return new \WP_Error( 'invalid_slug', __( 'A valid agent slug is required.', 'agent-builder' ) );
		}

		// Pin the manifest to the purchased slug so a tampered manifest cannot
		// install under a different identity.
		$manifest['slug'] = $slug;

		$validated = \Agentic\Agent_Manifest_Validator::validate( $manifest );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$computed = hash( 'sha256', (string) wp_json_encode( $validated ) );
		if ( '' !== $expected_hash && ! hash_equals( $expected_hash, $computed ) ) {
			\Agentic\Security_Log::log_system(
				'agent_install_failed',
				'agents',
				array(
					'slug'   => $slug,
					'reason' => 'hash_mismatch',
				)
			);
			return new \WP_Error( 'hash_mismatch', __( 'The purchased agent failed its integrity check and was not installed.', 'agent-builder' ) );
		}

		// Never overwrite a bundled or user-created agent of the same slug.
		$existing = \Agentic\Agent_Library::get_by_slug( $slug );
		if ( $existing && 'purchased' !== ( $existing['source'] ?? '' ) ) {
			return new \WP_Error( 'slug_conflict', __( 'An agent with this name already exists and is not a purchased agent.', 'agent-builder' ) );
		}

		$id = \Agentic\Agent_Library::upsert(
			array(
				'slug'        => $slug,
				'name'        => (string) ( $validated['name'] ?? $slug ),
				'description' => (string) ( $validated['description'] ?? '' ),
				'manifest'    => $validated,
				'kind'        => 'manifest',
				'source'      => 'purchased',
				'origin'      => 'marketplace',
				'source_id'   => $source_id,
				'version'     => (string) ( $validated['version'] ?? '1.0.0' ),
				'author'      => (string) ( $validated['author'] ?? '' ),
				'hash'        => $computed,
				'enabled'     => 1,
			)
		);

		if ( ! $id ) {
			return new \WP_Error( 'install_failed', __( 'Failed to save the purchased agent.', 'agent-builder' ) );
		}

		// Surface the new row immediately; activation stays a separate, explicit step.
		\Agentic_Agent_Registry::get_instance()->get_installed_agents( true );
		self::bust();

		\Agentic\Security_Log::log_system(
			'agent_install_succeeded',
			'agents',
			array(
				'slug'      => $slug,
				'source'    => 'purchased',
				'source_id' => $source_id,
			)
		);

		return true;
	}

	/**
	 * Fetch a purchased agent's signed manifest from the marketplace and install
	 * it as a library row.
	 *
	 * Thin, Pro-gated, trusted-host wrapper over install_purchased_row(). The
	 * endpoint is expected to return JSON shaped as
	 * { "manifest": { ... }, "hash": "<sha256>", "source_id": "<id>" }; a bare
	 * manifest object is also tolerated.
	 *
	 * @param string $slug         Purchased agent slug.
	 * @param string $manifest_url Marketplace manifest endpoint (agentic-plugin.com).
	 * @return true|\WP_Error
	 */
	public static function install_from_marketplace( string $slug, string $manifest_url ): bool|\WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', __( 'Insufficient permissions.', 'agent-builder' ) );
		}

		// Installing purchased agents is an Agent Builder Pro feature.
		if ( ! class_exists( '\Agentic\License_Client' ) || ! \Agentic\License_Client::get_instance()->is_pro() ) {
			return new \WP_Error( 'pro_required', __( 'Installing purchased agents requires Agent Builder Pro.', 'agent-builder' ) );
		}

		// SSRF guard — only our host may serve manifests.
		if ( 'agentic-plugin.com' !== wp_parse_url( $manifest_url, PHP_URL_HOST ) ) {
			return new \WP_Error( 'invalid_url', __( 'Invalid install source.', 'agent-builder' ) );
		}

		$response = wp_remote_get( $manifest_url, array( 'timeout' => 20 ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'fetch_failed', __( 'Could not retrieve the purchased agent from the marketplace.', 'agent-builder' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new \WP_Error( 'invalid_manifest', __( 'The purchased agent manifest was invalid.', 'agent-builder' ) );
		}

		// Contract: { "manifest": "<exact signed json string>",
		// "signature": "<base64 ed25519 detached>",
		// "source_id": "<id>" }.
		$canonical = ( isset( $body['manifest'] ) && is_string( $body['manifest'] ) ) ? $body['manifest'] : '';
		$signature = isset( $body['signature'] ) ? (string) $body['signature'] : '';
		if ( '' === $canonical || '' === $signature ) {
			return new \WP_Error( 'invalid_manifest', __( 'The purchased agent response was missing a signed manifest.', 'agent-builder' ) );
		}
		$source_id = isset( $body['source_id'] ) ? (string) $body['source_id'] : $slug;

		return self::install_signed( $slug, $canonical, $signature, $source_id );
	}
	/**
	 * Reserve an unused directory to move the outgoing agent into.
	 *
	 * Backups live under uploads/, never inside agents_dir: the registry scans
	 * every directory there, so a backup alongside the agent would be picked up
	 * and registered as an agent of its own.
	 *
	 * @param string $slug Agent slug.
	 * @return string Absolute path, or '' when one could not be prepared.
	 */
	private static function backup_dir_for( string $slug ): string {
		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) ) {
			return '';
		}

		$root = trailingslashit( $uploads['basedir'] ) . 'agentic-agent-backups';

		if ( ! wp_mkdir_p( $root ) ) {
			return '';
		}

		$base = $root . '/' . sanitize_file_name( $slug ) . '-' . gmdate( 'Ymd-His' );
		$path = $base;

		// gmdate() is second-granular; two updates in one second must not collide.
		$suffix = 1;
		while ( is_dir( $path ) && $suffix < 100 ) {
			++$suffix;
			$path = $base . '-' . $suffix;
		}

		return is_dir( $path ) ? '' : $path;
	}

	/**
	 * Copy entitlement files forward from the previous version of an agent.
	 *
	 * A zip built by the store never contains .activation, and only a premium zip
	 * contains .license. Both are what License_Client::can_agent_run() checks, so
	 * an update that dropped them would silently disable a purchased agent.
	 * Anything the new zip does provide wins.
	 *
	 * @param string $backup_dir Directory the previous version was moved to.
	 * @param string $dest_dir   Directory the new version now occupies.
	 */
	private static function carry_forward_entitlement( string $backup_dir, string $dest_dir ): void {
		foreach ( array( '.license', '.activation' ) as $file ) {
			$from = $backup_dir . '/' . $file;
			$to   = $dest_dir . '/' . $file;

			if ( file_exists( $from ) && ! file_exists( $to ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.copy_copy -- Local file inside wp-content; WP_Filesystem adds nothing here.
				copy( $from, $to );
			}
		}
	}
}
