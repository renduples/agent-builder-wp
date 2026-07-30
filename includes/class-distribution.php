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

	/**
	 * Public pricing / licensing page (off-site). Used for free → Pro promo when
	 * the in-plugin Upgrade screen is not available (WPorg free, or stripped build).
	 */
	public const PRICING_URL = 'https://agentic-plugin.com/licensing-and-pricing/';

	/**
	 * Community agents marketplace (browse only on free / WPorg — no remote install).
	 */
	public const COMMUNITY_AGENTS_URL = 'https://agentic-plugin.com/community-agents/';

	/**
	 * Whether the self-hosted in-admin "Upgrade to Pro" screen is available.
	 *
	 * False on WordPress.org builds (page is physically stripped) and whenever
	 * the upgrade template is missing.
	 */
	public static function has_in_admin_pro_upgrade(): bool {
		return self::is_self_hosted()
			&& defined( 'AGENT_BUILDER_DIR' )
			&& file_exists( AGENT_BUILDER_DIR . 'admin/upgrade-pro.php' );
	}

	/**
	 * URL for free-tier "Upgrade to Pro" links.
	 *
	 * Self-hosted free: admin screen (one-click installer when Pro zip is sold).
	 * WordPress.org free: external pricing page (no dead admin.php?page=… links).
	 */
	public static function free_pro_promo_url(): string {
		if ( self::has_in_admin_pro_upgrade() ) {
			return admin_url( 'admin.php?page=agentic-upgrade-pro' );
		}
		return self::PRICING_URL;
	}

	/**
	 * Whether {@see free_pro_promo_url()} opens off-site (use target=_blank).
	 */
	public static function free_pro_promo_is_external(): bool {
		return ! self::has_in_admin_pro_upgrade();
	}
}
