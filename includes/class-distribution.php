<?php
/**
 * Distribution channel helper.
 *
 * The plugin ships in two flavours built from one codebase:
 *
 *  - 'wporg' — the WordPress.org build. Links out to pricing; contains NO
 *    remote-install or self-hosted update code (Guideline 8). The build strips
 *    includes/self-hosted/ and admin/upgrade-pro.php from this artifact.
 *  - 'self'  — the self-hosted build served from agentic-plugin.com. Adds the
 *    one-click Pro installer and the free-plugin auto-updater.
 *
 * The channel is stamped at build time into includes/dist-channel.php (which
 * defines AGENTIC_DIST_CHANNEL). In a dev checkout that file is absent and we
 * default to 'self' so the full feature set is exercisable locally.
 *
 * @package Agent_Builder
 */

declare(strict_types=1);

namespace Agentic;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reports which distribution channel this build belongs to.
 */
final class Distribution {

	/**
	 * The distribution channel ('wporg' or 'self').
	 *
	 * @return string
	 */
	public static function channel(): string {
		return defined( 'AGENTIC_DIST_CHANNEL' ) ? (string) AGENTIC_DIST_CHANNEL : 'self';
	}

	/**
	 * Whether this is the self-hosted build (agentic-plugin.com download).
	 *
	 * Self-hosted-only features — the one-click Pro installer and the
	 * free-plugin auto-updater — must gate on this.
	 *
	 * @return bool
	 */
	public static function is_self_hosted(): bool {
		return 'self' === self::channel();
	}

	/**
	 * Whether this is the WordPress.org build.
	 *
	 * @return bool
	 */
	public static function is_wporg(): bool {
		return 'wporg' === self::channel();
	}
}
