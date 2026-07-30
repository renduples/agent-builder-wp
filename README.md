# Agent Builder (WordPress.org free edition)

**Version:** 3.3.0

Public source for the free [Agent Builder](https://agentic-plugin.com/) WordPress plugin — the same tree packaged for WordPress.org (no self-hosted updater, no remote Pro installer).

- **Plugin slug:** `agent-builder`
- **License:** GPL-2.0-or-later
- **Requires:** WordPress 6.4+, PHP 8.1+
- **Docs / product site:** https://agentic-plugin.com/
- **Community agents:** https://agentic-plugin.com/community-agents/

This repository is the public development location for the free plugin (WordPress.org Plugin Directory Guideline 4). Day-to-day product development may happen elsewhere; tagged releases are mirrored here automatically.

## Install from source

1. Clone into `wp-content/plugins/agent-builder` (or symlink).
2. Optional document tools: `composer install --no-dev`
3. Optional rebuild admin React: `npm ci && npm run build`  
   Pre-built assets already ship in `build/`.
4. Activate **Agent Builder** in WordPress.

## What is *not* in this tree

| Excluded | Why |
|----------|-----|
| `includes/self-hosted/` | Self-hosted free auto-updater + one-click Pro installer (not allowed on WordPress.org — Guideline 8) |
| Agent Builder Pro | Separate premium plugin |
| Private monorepo docs / CI | Not part of the free plugin distribution |

## Releases

GitHub tags match plugin versions (`v3.3.0`, etc.). Downloadable ZIPs for production installs are published from the product site and WordPress.org once listed.

## Support

- WordPress.org support forum (once listed)
- https://agentic-plugin.com/support/
