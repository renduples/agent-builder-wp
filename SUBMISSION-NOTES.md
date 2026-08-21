Agent Builder — notes for the WordPress.org Plugin Review team
================================================================

1. What this plugin is
-----------------------
Agent Builder lets a site owner create and run role-based AI "agents" —
each a bundle of a system prompt plus a whitelisted set of permission-
scoped "tools" (PHP functions the LLM can call: draft a post, check site
health, look up a user, etc.). Every tool call is classified into one of
five risk tiers; anything above a low bar pauses for the human's explicit
approval before it executes (Approvals queue, admin.php?page=agentic-approvals).
No tool runs unsupervised at a risk level the site owner hasn't allowed.

2. It's a bring-your-own-key (BYOK) service plugin
---------------------------------------------------
The plugin itself contains no AI. It calls out to whichever LLM provider
the admin configures (OpenAI, Anthropic, Google, xAI, Mistral, Meta Llama,
Cohere, Kimi, DeepSeek, or a fully local Ollama install) using the admin's
own API key. No request to any of these leaves the site until the admin
explicitly selects and configures a provider. Every external endpoint the
plugin can contact — including a handful of low-data calls to the vendor's
own agentic-plugin.com (a daily model-pricing sync, a WP-CLI dashboard
counter, sign-up, deactivation feedback, an opt-in support-diagnostics
tool) — is individually disclosed with its trigger condition and exact
payload in readme.txt's External Services section.

3. The model-catalog sync is opt-in, off by default
-----------------------------------------------------
A daily WP-Cron job that refreshes a curated model/pricing list from
agentic-plugin.com is gated behind an explicit Settings > Security
checkbox (`agentic_allow_platform_sync`, default off). It never fires
without that opt-in. A few genuinely user-initiated actions (the "Get
Latest Pricing" and "Refresh Models" buttons) are exempt from that gate
by design, since Guideline 7 targets automatic background phone-home,
not a button the admin just clicked.

4. MCP support is a local, unauthenticated-until-logged-in JSON-RPC
   endpoint — not a network service
---------------------------------------------------------------------
Agent Builder exposes each active agent's own tool set at
`/wp-json/agentic/{agent-slug}/mcp`, implementing the Model Context
Protocol so external clients (Claude Desktop, Cursor, etc.) can connect.
This is a REST route on the site's own existing REST API, gated by
`is_user_logged_in()` plus a standard WordPress Application Password —
nothing new is opened on the network beyond what any authenticated REST
route already is. Each agent's endpoint is scoped to only that agent's
own declared tools (`abilities.json`), and every tool is separately
filtered by risk tier before it's ever listed or callable over MCP —
high-risk and destructive tools are excluded from MCP entirely,
regardless of what the same tool's normal chat-UI risk gate allows.

5. `run_wp_cli` and `manage_cli_settings` are NOT in this zip
----------------------------------------------------------------
The free/WordPress.org build deliberately omits two tools that exist in
a separate, license-gated companion product: `run_wp_cli` (executes a
single WP-CLI command from an admin-curated whitelist) and
`manage_cli_settings` (edits that whitelist). Both are excluded from
this package via `.distignore` and a runtime check
(`Distribution::wporg_excluded_tools()`) that skips loading them even if
the files were somehow present. For context on how seriously these are
gated in general: even in the companion product, `run_wp_cli` classifies
as the plugin's highest ("EXTREME") risk tier, which hides the tool from
the LLM entirely with no approval path at any trust level — it can never
be enabled by a chat conversation, only by an administrator directly
editing a server-side whitelist gated behind `manage_options`.

6. Two references you may notice that don't correspond to any tool in
   this zip
-------------------------------------------------------------------------
The internal risk-classification table and the MCP tool blocklist both
contain entries for `git_*`-prefixed and `cloudflare_*`-prefixed tool
names (deploy/DNS/security-profile operations). These are pre-classified
placeholders for a separate, license-gated companion product's
capabilities — no `library/tools/git_*` or `library/tools/cloudflare_*`
files exist anywhere in this codebase or ship in this zip. They're inert
references, not a discovered/hidden feature.

7. PDF generation/reading is self-hosted-only; spreadsheet and DOCX
   tools are in this zip
----------------------------------------------------------------------
Four tools (create_pdf, read_pdf, get_pdf_info, merge_pdfs) depend on
PDF libraries (mPDF, pdfparser, FPDI) that are not bundled in this
free/WordPress.org build — mPDF alone bundles ~85 fonts for its
automatic Unicode font-selection mode, disproportionate to the rest of
the plugin. Each of these four tools detects the missing library at
runtime and reports itself unavailable with a clear message rather than
erroring; they're excluded from what any agent can call on this build.
Spreadsheet and Word document tools (PhpSpreadsheet, PhpWord) are
unaffected and ship normally — only the PDF-specific libraries were
removed.

8. Everything else
-------------------
- Pro is a distinct, separately-installed plugin; nothing in this zip
  unlocks or references purchasing it beyond an inert upsell link.
- React admin UI source lives in `src/`; built output (what actually
  ships/runs) is in `build/`, committed alongside source per WordPress
  plugin review convention for transparency.
- `includes/self-hosted/` and `admin/upgrade-pro.php` do not exist in
  this checkout — those paths are placeholder exclusions in `.distignore`
  for when this same export script is run against the private,
  self-hosted-only upstream repository this plugin is mirrored from.

RESOLVED — package size
=========================
Package size was initially ~122 MB uncompressed / ~51 MB zipped, almost
entirely mPDF's bundled font set. Resolved by moving PDF generation/
reading to self-hosted-only (see item 7 above) rather than trimming
mPDF's font set in place, since a curated subset would need real
per-script testing to avoid silently breaking PDF output for whatever
language's font got cut. Final package: ~27 MB uncompressed, ~5.8 MB
zipped, 2,568 files — a normal size for a WordPress plugin.
