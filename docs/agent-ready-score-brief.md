# Agent-Ready Score — brief for this repo (agent-builder-wp, WP.org free tier)

Branch: `feature/agent-ready-score`. This is one of three repos carrying this feature — see [Scope split across repos](#scope-split-across-repos) below before assuming this brief is the whole picture.

**Full references** (fetchable via WebFetch if you have claude.ai access):
- Strategy memo: https://claude.ai/code/artifact/a10682fb-4d7e-4d8e-af01-1a860862c84f — "The Agent-Ready Play"
- Coding brief: https://claude.ai/code/artifact/91ef96be-5448-49eb-bc7d-857455c8f28c — "Building the Agent-Ready Feature" (this file is a repo-scoped excerpt of it)
- Companion project: `/Volumes/Code/sitepassport` — the standalone directory service this feature reports into (see its own `CLAUDE.md`)

## What ships in this repo

This is the WP.org-facing public listing — the compliance-trimmed subset of the free tier. Everything below is **free**, no license check, no Pro branch anywhere in this code.

1. **Score Engine** — new `includes/class-agent-ready-score.php`, `Agent_Ready_Score` class. Local checks only, zero external calls, no opt-in flag needed:

   | Check | Category | Verifies | Weight | Free fix? |
   |---|---|---|---|---|
   | `mcp_server_reachable` | Capability exposure | Server-side MCP responds per active agent — reuse `Agentic_Relay_Connect::mcp_readiness()`, already built (3.3.81+) | high | yes |
   | `webmcp_tools_registered` | Capability exposure | Count of tools the Bridge (below) successfully auto-mapped and confirmed live | high | yes |
   | `approval_gate_configured` | Safety & trust | Every `webmcp_expose:true` tool has a declared risk tier; `Approval_Queue` reachable | high | yes |
   | `llms_txt_present` | Discoverability | `/llms.txt` exists, well-formed | medium | **no — Pro (AI Radar)** |
   | `robots_ai_directives` | Bot access control | `robots.txt` has explicit GPTBot/ClaudeBot/PerplexityBot/Google-Extended rules — silence scores lower than an explicit allow | medium | **no — Pro (AI Radar)** |
   | `schema_org_present` | Content | Organization/WebSite JSON-LD; Product too if WooCommerce active | medium | **no — Pro (AI Radar)** |
   | `well_known_manifest` | Discoverability | Plugin auto-generates `/.well-known/webmcp.json` | low | yes (genuinely new, no Pro overlap) |

   > **Important — checked against `agent-builder-pro` before writing this table:** Pro already ships an "AI Radar" agent (`library/agents/ai-radar/`, v1.2.0) whose tools cover `check_robots_txt`, `check_schema_markup`, `check_technical_readiness` (llms.txt/HTTPS/sitemap/noindex), plus working **fix** tools `generate_llms_txt` and `update_robots_txt` (both correctly risk-tiered `high` — they write to the web root), a weekly scan cron with score-delta admin notices, and cache-invalidating event listeners on `updated_option`/`activated_plugin`/`deactivated_plugin`. **Do not rebuild fix tools for these three checks in this free repo — that would cannibalize an existing Pro feature.** The free Score Engine still *checks* all 7 categories with its own lightweight, read-only logic (so the score stays real and complete — Site Passport needs the full picture regardless of tier), but the "top 3 fixes" UI (Section 4 below) only offers a real one-click fix for the checks marked "yes" above. For the rest, the CTA is explicitly **"Fix this with AI Radar (Pro)"** — a sharper, more concrete Pro upsell than a generic "upgrade" nudge, since the fix tool genuinely already exists on the other side of it.

   Storage: option `agentic_score_latest` (`{overall, grade, categories, checked_at}`). Re-scan: `POST agentic/v1/score/rescan` + weekly `agentic_rescan_score` WP-Cron. Read: `GET agentic/v1/score`, same `xxx_payload()` pattern as `class-admin-pages-rest.php`.

   **Self-expose the score itself**: register a `check_agent_readiness` MCP tool through the existing relay, and publish the check table above as an Agent Skill (`library/skills/agent-ready-scoring/SKILL.md`) — so an MCP client can query a site's score without visiting wp-admin, and the plugin proves it's exactly as agent-ready as it claims.

2. **WebMCP Bridge** — new `includes/class-webmcp-bridge.php` + `assets/js/webmcp-bridge.js`.

   **Read this before writing any JS**: WebMCP has no native declarative HTML-attribute mechanism — only `navigator.modelContext.registerTool({name, description, inputSchema, execute})`, called from JS. "Declarative" here means the site owner writes zero JS (the bundled script auto-detects known form structures and emits the calls), not that the browser reads custom attributes natively. Every entry point must open with `if (!('modelContext' in navigator)) return;` — silent no-op, since Chrome ships this flag-gated and off by default as of this writing.

   Detectors (v1): WooCommerce product search, native WP Search block/widget, WPForms, Contact Form 7. Map fields to existing `Tools_Registry` tools via `Tool_Base::get_parameters()` (already emits JSON Schema close to 1:1 with `inputSchema`).

   New `abilities.json` fields: `"webmcp_expose": false` (opt-in per tool) and `"webmcp_context": "frontend" | "admin" | "both"`.

   **New permission model — do not reuse the MCP relay's.** `class-relay-connect.php` authenticates a *remote* agent via Application Password, acting on the site owner's behalf continuously. WebMCP serves whoever's *currently in the browser* — often an anonymous visitor. New endpoint `POST agentic/v1/webmcp/execute`, gated on `webmcp_expose`, `webmcp_context` matching where the call originated, and the *current session's* own WP capability. An anonymous visitor only ever reaches tools explicitly marked safe for that.

   **Risk gating happens in the page, not wp-admin.** Any tool above `risk: low` needs an in-page confirm before `execute` fires — a new, dependency-free widget (can't reuse `ProposalCard`, which assumes the wp-admin chat UI). Promise stays pending until *that visitor* clicks Approve.

3. **Admin UI** — one new dashboard card, "Agent-Ready." Reuse `AdminPage`, `ScreenModeToggle`, `SaveBar` (`src/shared/components.js`) and the established Basic/Advanced pattern (`Admin_Menu_Handler::is_advanced_mode('agent-ready')`).
   - Basic: score gauge, top 3 fixes — real one-click actions for Capability/Safety findings (routed through the same risk-tier/`Approval_Queue` flow as any other agent action), "Fix this with AI Radar (Pro)" CTA for Discoverability/Content/Bot-Access findings — one "Make my site agent-ready" switch, "Submit to Directory" button.
   - Advanced: full per-check breakdown, per-tool `webmcp_expose` matrix grouped by agent, manual re-scan, directory listing status/edit.
   - New `agent_ready_payload()` in `class-admin-pages-rest.php`, same convention as `tools_payload()`/`approvals_payload()`.

4. **Directory integration** — "Submit to Directory" sends only a URL to sitepassport.org's API; the directory pulls this plugin's own `/.well-known/webmcp.json` to verify, not the other way around. This plugin never phones home automatically for this feature.

## Compliance — this is the repo that has to pass WP.org review

- **readme.txt External Services**: two new entries — the directory submission call (opt-in; state exactly what's sent — site URL, score payload — link the directory's privacy policy) and an explicit line that the local score checks themselves make zero external requests.
- **SUBMISSION-NOTES.md**: new "WebMCP Bridge" section explaining `agentic/v1/webmcp/execute` only dispatches to already-vetted, server-side `Tool` classes shipped with the plugin — not new remote-code-execution surface — same framing already used for Turnstile and the MCP relay.
- **DB version bump**: new options/table → standard `AGENT_BUILDER_DB_VERSION` bump, wired into the existing `Activator::maybe_upgrade()` cascade.
- **i18n**: new strings need the standard POT regen; a new React entry needs its own per-handle JSON bucket.
- **Plugin Check**: run before every deploy per the established workflow; expect to justify the directory-submission call the same way Turnstile/GitHub fetches are already justified.
- This plugin has **not been submitted to WP.org yet** (still pre-submission as of 3.3.89) — worth deciding deliberately whether this feature is the headline of the first submission or a fast-follow, not by default.

## Scope split across repos

| | This repo (`agent-builder-wp`) | `agent-builder` (self-hosted free) | `agent-builder-pro` |
|---|---|---|---|
| Score Engine, WebMCP Bridge, Admin UI | Full — this is the compliance-trimmed public listing | Same functional feature — likely the actual upstream dev surface, mirrored down here per the existing mirror-commit workflow | — |
| Cross-origin `exposedTo`, score history, bulk approval policies, white-label export, featured directory placement | Not present, ever | Not present, ever | Pro-only — separate repo, not a license flag |

Check `agent-builder`'s own `docs/agent-ready-score-brief.md` before assuming this repo is where net-new code should land — this project's established pattern is that this repo receives periodic mirror commits from the private `agent-builder` upstream, not independent parallel development. If that pattern holds for this feature too, the real work happens there and lands here via the usual export process (`bin/export-wporg-tree.sh`), and this branch's job is mostly the WP.org-specific compliance layer above.
