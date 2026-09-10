# M1: Basic / Advanced Interface Modes — Design Doc

Status: **design only**, no implementation. Companion to issue #3. This
reconciles the mode system that already exists in the codebase with the
Console-style product target Renier described, and lays out a phased path
between the two.

Base for all file/line references: `release/3.4-wporg` at commit `8f03152`.

---

## 1. Current-state audit

The mode primitive is `Admin_Menu_Handler::is_advanced_mode( string $screen = '' )`
(`includes/class-admin-menu-handler.php:297-306`). It resolves in this order:

1. If `$screen` is given, check the current user's `agentic_screen_mode` user
   meta (`SCREEN_MODE_META`, `class-admin-menu-handler.php:276`) — an
   associative array `{ screen_key: 'basic'|'advanced' }` set via
   `set_screen_mode()` (`class-admin-menu-handler.php:315-327`) and cleared via
   `reset_screen_modes()` (`:335-337`).
2. Otherwise (or with no override for that screen) fall back to the site-wide
   `agentic_ui_mode` option, default `'basic'` (`:305`).

There are **two independent write paths** to the same `agentic_ui_mode`
option today: the Dashboard's "Interface Settings" card posts
`{action_name:'set_ui_mode'}` to the admin-page REST action
(`src/dashboard-app/index.js:834-839`, handled in `class-dashboard-rest.php`),
and Settings → Interface posts to the separate `agentic/v1/ui-settings` REST
route (`includes/class-ui-settings-rest.php:90-91`). Both write the same
option and the classic `admin-post` handler `handle_set_ui_mode()`
(`class-admin-menu-handler.php:667-697`, wired at `agent-builder.php:244`) is
a third, apparently-unused path (no form in the current templates posts to
`admin_post_agentic_set_ui_mode`; it appears to predate the two REST paths).
Confirmed by screenshot: `screenshots/baseline/dashboard.png` shows the
"Interface Settings" card's Basic/Advanced buttons, and
`screenshots/baseline/settings.png` shows the same choice as radio buttons on
Settings → Interface — two UIs for one setting.

There is **no persistent global header switch**. "Switchable at any time
from the header" does not exist yet in any form; today mode is set from (a)
the Dashboard card, (b) Settings → Interface, or (c) a per-screen
`ScreenModeToggle` (`src/shared/components.js:151-200`) rendered inside the
screens that use it.

Per-screen table, screens keyed as in `bin/screenshot-admin.js` `SCREENS`
(`bin/screenshot-admin.js:23-43`):

| Screen (slug / menu page) | Basic/Advanced today? | What each mode shows | Citation |
|---|---|---|---|
| **dashboard** (`agent-builder`) | Site-wide toggle lives *here*, but the dashboard's own content doesn't change by mode | Same 8-card grid (Status, Approvals & Backups, Site Passport, Activity, Connected Providers, Quick Actions, Interface Settings, Getting Started) regardless of mode; `is_advanced_mode('dashboard')` is read (`class-dashboard-rest.php:190`) but only affects secondary Quick Actions filtering (`quick_actions_catalog()`'s `advanced` flag, `class-admin-menu-handler.php:394-428`) | `screenshots/baseline/dashboard.png` |
| **chat** (`agentic-chat`) | None | Single unified chat UI, agent picker, no mode switch visible | `screenshots/baseline/chat.png`; `render_chat_page()` (`class-admin-menu-handler.php:1084-1152`) has no `is_advanced_mode` call |
| **agents** (`agentic-agents`) | None | WordPress-plugins-list-style table (Activate/Deactivate/Chat links, MCP/WebMCP/WhatsApp columns) — identical for every user | `screenshots/baseline/agents.png`; no `is_advanced_mode` reference anywhere in `admin/agents.php` or `admin/agent-wizard.php` |
| **publish** (`agentic-deployment`) | Yes, per-screen | `is_advanced_mode('deployment')` gates fields on the classic PHP deployment page | `admin/deployment.php:26` |
| **run-task** (`agentic-run-task`) | None | Fixed two-step "Execute Task" flow, no mode branching | `admin/run-task.php:1-20` header comment |
| **agent-wizard / knowledge-wizard / deploy-wizard** (hidden pages) | None | Guided wizards already exist as dedicated flows, but are not mode-gated — they're the same for every user | Registered as hidden submenu pages, `class-admin-menu-handler.php:131-158`; no `is_advanced_mode` hits in `admin/agent-wizard.php`, `admin/knowledge-wizard.php`, `admin/deploy-wizard.php` |
| **knowledge** (`agentic-train-data`) | None | Classic PHP wiki editor + Instructions/Memory/Vector tabs, same for everyone | `render_train_data_page()` (`class-admin-menu-handler.php:1243-1269`); no `is_advanced_mode` call |
| **tools** (`agentic-tools`) | Yes, per-screen | React admin-pages app; `is_advanced_mode('tools')` gates what's returned | `class-admin-pages-rest.php:758` |
| **skills** (`agentic-skills`) | Yes, per-screen | React app + classic create/edit forms; Basic mode swaps to an embedded chat with a Skills-building assistant (comment at `class-admin-menu-handler.php:1021-1023`) | `admin/skills.php:195`; `class-admin-pages-rest.php:891` |
| **approvals** (`agentic-approvals`) | Yes, per-screen | React app, Basic/Advanced gates detail shown | `class-admin-pages-rest.php:1004` |
| **passport** (`agentic-agent-ready`) | Yes, per-screen | React app (Site Passport / Agent-Ready Score) | `class-admin-pages-rest.php:2195` |
| **logs** (`agentic-audit-log`) | Yes, per-screen | React app; friendly ("Activity") vs technical (raw audit log) split | `class-admin-pages-rest.php:1454` |
| **settings** (`agentic-settings`, tab=interface/agents/providers/users/security/apis/endpoints/mcp) | Only the **Users** tab | `is_advanced_mode('settings-users')` gates that one tab; every other tab (Interface, Agents, Providers, Security, APIs, Endpoints, MCP) shows the same fields to everyone | `class-admin-settings-rest.php:900` |
| **settings-providers** (`agentic-settings&tab=providers`) | None (see above) | Same provider CRUD form for all users | — |
| **setup** (`agentic-setup`) | None | Provider/model onboarding wizard (`admin/setup.php`); no mode step | grep of `admin/setup.php` for `ui_mode`/mode finds none |
| **signup** (`agentic-signup`) | None | "Quick Start" / initial config screen (`admin/signup.php`); Security Mode select there is an *agent* safety setting, unrelated to UI mode | `admin/signup.php:93-100` |
| **usage-costs** (`agentic-costs`) | N/A in this (free/WPorg) codebase | Page is registered entirely by Agent Builder Pro — comment: "Usage / Costs page is registered by Agent Builder Pro" | `class-admin-menu-handler.php:209` |

Screens with a per-screen toggle write through the same REST action
(`agentic/v1/admin-page`, `action_name: set_screen_mode`/`reset_screen_modes`),
authorized per-screen at `class-admin-pages-rest.php:124-139` and executed at
`:299-318`, always requiring at least whatever capability the screen itself
already requires (e.g. `tools`/`skills` need `agentic_manage_tools`).

**Summary**: of the 19 registered screens, 7 have a real per-screen
Basic/Advanced split (Publish, Tools, Skills, Approvals, Passport, Logs,
Settings→Users). The rest — critically **Agents**, **Knowledge**, **Chat**,
**Settings** (all tabs but Users), and every wizard — have none. There is no
global header switch; the site-wide default lives in two places (Dashboard
card, Settings → Interface) that both write `agentic_ui_mode`.

---

## 2. Gap analysis — target Advanced-mode sections vs. today

| Target section | Exists today? | Gap to Console-skeleton vision |
|---|---|---|
| **Dashboard** | Yes (`agent-builder`) — card grid dashboard | Already close in spirit (glanceable summary cards). Needs: reachable from an Advanced-mode nav rather than only wp-admin's top-level menu; drop the "Interface Settings" card duplication once a header switch exists (§7). |
| **Agents** | Yes (`agentic-agents`) — flat plugins-list table | No Console-style per-agent detail view (config, versions, logs, test-in-place). No Basic/Advanced split at all today — needs both a guided Basic list ("your agents", plain cards) and a data-dense Advanced table/detail view. |
| **Tools & Skills** | Yes, two separate menu items (`agentic-tools`, `agentic-skills`), each already mode-aware | Console vision treats these as one section; today they're two top-level nav entries. Needs IA decision: keep as two panes under one "Tools & Skills" umbrella, or merge. Both already have working Basic/Advanced content splits to build on. |
| **Knowledge** | Yes (`agentic-train-data`) — wiki editor + Instructions/Memory/Vector tabs | No mode split at all. Needs a Basic guided "teach your agent" flow (the existing `knowledge-wizard` hidden page is the closest building block, `class-admin-menu-handler.php:141-148`) vs. an Advanced raw document/vector-store manager. |
| **Logs** | Yes (`agentic-audit-log`, page title "Activity") — already mode-aware, plus the newly-added hash-chained integrity surfacing | Content split exists; needs to be reachable as a first-class Advanced-console section (it already reads like one) and to expose the tamper-evident audit trail more prominently in Advanced. |
| **Usage & Costs** | Pro-only; no free-tier equivalent | This is the single largest gap for the free/WPorg build — no local token/cost accounting UI exists to mode-split at all. Out of scope to build from scratch in M1; design must note this explicitly and decide whether "Usage & Costs" appears as a locked/upsell nav entry in Advanced mode for free users, or is omitted entirely. |
| **Providers & Keys** | Yes (`agentic-settings&tab=providers`) | No Basic/Advanced split; today it's one CRUD form for everyone. Basic could hide advanced fields (auth_type, req_format, resp_format, sort_order) behind sensible defaults; Advanced exposes all of them (they already exist in `handle_provider_actions()`, `class-admin-menu-handler.php:549-616`). |
| **Settings** | Yes, multi-tab (`agentic-settings`) | Only the Users tab is mode-aware. Interface/Agents/Security/APIs/Endpoints/MCP tabs need the same per-tab (or whole-page) treatment, or a deliberate decision that Settings is "just Advanced" and Basic mode only ever reaches it via specific guided flows (e.g. connecting a provider via Setup Wizard). |

**Playground** (not one of the 8 sections, but the issue's #4 requirement) —
see §4.

---

## 3. Information architecture

Constraint: this must stay a normal WordPress plugin. It cannot replace or
hide wp-admin's own chrome (admin bar, WP left menu, screen options) — every
existing screen is already a `add_submenu_page()` entry
(`class-admin-menu-handler.php:36-264`) and must remain one, so deep links,
capability checks, and WordPress's own screen options keep working.

Proposal: Advanced mode adds a **secondary in-page nav** rendered inside the
existing `.wrap` container that every Agentic screen already provides (see
`render_page()`, `class-admin-menu-handler.php:976-1077`, and the shared
`.agentic-page-footer` injection at `:820-877` — both prove there's already
a pattern for injecting shared chrome across every Agentic admin screen
without touching wp-admin itself). Concretely:

- The 8 target sections become entries in this secondary nav (a fixed rail or
  top tab-strip, à la the Anthropic Console's left rail), rendered once,
  shared by every Agentic React screen — the same way `React_Admin::mount()`
  already shares one mount point per app (`dashboard-app`, `admin-pages`,
  `settings-app`).
- It maps 1:1 onto existing wp-admin submenu pages: "Agents" in the secondary
  nav still routes to `admin.php?page=agentic-agents`, it's not a new URL
  scheme. This keeps bookmarks, capability checks (`add_submenu_page`'s
  `$capability` arg), and the existing REST auth checks
  (`class-admin-pages-rest.php`) completely unchanged.
- Tools & Skills collapse into one nav entry with two tabs (reusing the
  existing `?tab=`/`?section=` convention already used by Settings and
  Deployment) rather than two separate wp-admin submenu items — this is a
  presentation change only; the underlying `agentic-tools` / `agentic-skills`
  pages don't need to merge.
- Basic mode keeps today's flat wp-admin left-menu-only navigation (no
  secondary nav) — this matches "Basic hides complexity" and avoids adding
  new chrome to the simple experience.
- The **global header switch** (§0 of the product target) is a small control
  injected via the same `admin_footer`/inline-script pattern
  `render_admin_page_links()` already uses (`class-admin-menu-handler.php:820-877`)
  — placed near the WordPress admin bar or as a sticky element at the top of
  `.wrap`, present on every Agentic screen. It writes the site-wide
  `agentic_ui_mode` option via the existing REST path(s) — ideally
  consolidated to one (§7) — and, unlike per-screen toggles, is the "master"
  control the product spec describes. Per-screen overrides remain available
  as a secondary, more targeted control (e.g. via screen options or a small
  affordance next to the header switch), not removed.

---

## 4. Playground design

Closest existing building block: **Chat** (`agentic-chat`,
`render_chat_page()`, `class-admin-menu-handler.php:1084-1152`, and
`templates/chat-interface.php`). It already has: agent picker (URL → cookie →
default resolution, `:1101-1113`), a message thread, slash commands
(`Chat_Assets::get_slash_commands_for_js()`), audio/vision/tts feature flags
per agent (`agentic_get_effective_chat_features()`), and handoff-context
support for agent-to-agent delegation. `screenshots/baseline/chat.png`
confirms the current UI: agent dropdown with version/author, a
credits/history/edit toolbar, suggested-prompt chips, and a composer.

An OpenAI-Playground-style view **extends** Chat rather than replacing it:

- Playground = Chat's message thread and agent picker, **plus** a visible
  parameter/config side panel (model, temperature/provider overrides if the
  agent allows them, system-prompt/instructions preview, tool access for
  this run) and a raw request/response inspector — the things a developer
  testing an agent wants to see that a normal chat user doesn't.
- This is additive UI on the same underlying `agentic/v1/` chat endpoints;
  it does not need a new execution path. Only Advanced mode shows the side
  panel; Basic mode's Chat stays exactly as it is today.
- **Run** (`agentic-run-task`, `admin/run-task.php`) is a different concept
  (fire a specific scheduled task once, see its result) and should not be
  conflated with Playground — it stays a dedicated execution page, not part
  of this redesign.

---

## 5. Basic-mode plan

Today, "Basic" on the screens that have any mode split at all
(Tools, Skills, Approvals, Passport, Logs, Settings→Users, Deployment) means
*fewer fields on the same page*, not a different flow. That satisfies "hides
complexity" but not "task-oriented, plain language, guided" — there is no
screen today that asks "What do you want your agent to do?" as a wizard
step; the closest are the already-built guided wizards
(`agent-wizard.php`, `knowledge-wizard.php`, `deploy-wizard.php`,
registered as hidden pages, `class-admin-menu-handler.php:131-158`), which
exist but are **not mode-gated** — every user sees the same wizard whether
their preference is Basic or Advanced.

Concrete plan per gap:

- **Agents** (no split today): Basic mode replaces the plugins-style table
  (`screenshots/baseline/agents.png`) with a card-per-agent, plain-language
  layout (name, one-line description, a single primary "Chat" action) and
  routes "Add an agent" straight into the existing `agent-wizard` guided
  flow instead of a bare Activate/Deactivate table row. Advanced mode keeps
  today's dense table (version, signature, capability flags, MCP/WebMCP/
  WhatsApp columns) — this is a genuinely different layout, not a field
  count difference.
- **Knowledge** (no split today): Basic mode is a guided "what do you want
  your agents to know?" flow built on the existing `knowledge-wizard`
  hidden page; Advanced mode is today's full wiki editor + Instructions/
  Memory/Vector tabs, unchanged.
- **Tools/Skills/Approvals/Passport/Logs** (already field-reduced in Basic):
  keep the existing reduced-field Basic views — they're already
  appropriately scoped for their content type (a list of toggles, a log
  feed) where a wizard doesn't make sense — but tighten copy to plain
  language and add contextual help text (`InfoTip`, already used in
  `ScreenModeToggle`, `src/shared/components.js:192-197`) wherever Advanced-
  only jargon (e.g. "signature", "capability", "hash-chained") appears
  without explanation.
- **Providers & Keys**: Basic mode narrows the CRUD form to name + API key +
  "use recommended defaults" (auth_type/req_format/resp_format inferred);
  Advanced exposes every field `handle_provider_actions()` already accepts
  (`class-admin-menu-handler.php:561-588`).
- **Settings** (only Users tab split today): each remaining tab needs an
  explicit decision — Interface/Security are plausible Basic candidates
  (few, high-stakes toggles, benefit from guidance); APIs/Endpoints/MCP are
  inherently developer-facing and can reasonably be Advanced-only, reachable
  from Basic mode only via an "Advanced settings" escape hatch (never
  hidden, per the "same capabilities" rule — just one click further in
  Basic).

Guiding rule carried through every item above: Basic must never omit a
capability Advanced has (per the issue's requirement) — it can wrap it in a
wizard, default it, or push it one click further behind an "advanced
settings" disclosure, but the underlying action (e.g. setting a provider's
`resp_format`) must stay reachable.

---

## 6. Onboarding

Mode selection has no home in the current signup/setup flow.
`admin/signup.php` (Quick Start) only collects Security Mode (an *agent*
safety setting — `admin/signup.php:93-100`, unrelated to UI mode) and
model/provider choices; `admin/setup.php` is the provider-connection wizard
Setup Wizard and likewise has no UI-mode step (confirmed by grep: no
`ui_mode`/mode-selection markup in either file).

Proposal: add one step to the existing Quick Start flow
(`admin/signup.php`, alongside the current Security Mode field) — a plain
"How would you like Agent Builder to work?" choice with two cards (Basic:
"Guided, plain language, best for getting started" / Advanced: "Full
console, best if you've used developer tools like this before"), defaulting
the radio to Basic per the product spec. This writes `agentic_ui_mode`
directly (same option every other path already writes) before the user ever
reaches a mode-aware screen, so there's no flash of the wrong mode on first
load. It does not need its own wizard step/page — it's one additional field
on the existing signup form, consistent with how Security Mode is already
presented there.

---

## 7. Migration / compatibility

No data migration is needed — the new design is additive on top of the
existing storage:

- `agentic_screen_mode` user meta (`SCREEN_MODE_META`) keeps its exact
  current shape (`{ screen_key: 'basic'|'advanced' }`). Existing overrides
  for `tools`, `skills`, `approvals`, `logs`, `agent-ready`,
  `settings-users`, `deployment` continue to mean exactly what they mean
  today. New screens that gain a split (`agents`, `knowledge`, tools/skills
  merge, remaining Settings tabs) simply add new keys to the same array —
  additive, not a breaking format change.
- `agentic_ui_mode` site option stays the single source of the site-wide
  default. **Agent Builder Pro reads this same option** (per the issue) and
  must keep working unchanged — nothing here renames or moves it. The one
  cleanup this design recommends (not required for compatibility, but worth
  doing while touching this code) is collapsing the three current write
  paths (`class-dashboard-rest.php`'s `set_ui_mode` action, `class-ui-
  settings-rest.php`'s `/ui-settings` route, and the seemingly-dead
  `admin-post` handler `handle_set_ui_mode()`) down to one, and having the
  new header switch call that same single path. Any consolidation must keep
  writing the literal `agentic_ui_mode` option (not a renamed/namespaced
  option) so Pro's read continues to work.
- The secondary Advanced-mode nav (§3) is purely a rendering layer over
  existing `add_submenu_page()` routes — it introduces no new capability
  checks and no new page slugs, so Pro's own registered pages (e.g.
  `agentic-costs`) can opt into the same nav later without this plugin
  needing to know about them ahead of time (matching the existing pattern
  where Pro registers its own submenu page and this plugin's shared chrome,
  like `render_admin_page_links()`, already applies to any `agentic-*`
  page generically).
- Per-screen `ScreenModeToggle` UI (`src/shared/components.js:151-200`)
  keeps working as-is for any screen that still wants a targeted override
  next to the new global header switch — it's additive, not replaced.

---

## 8. Phased implementation plan

Ordered so each PR is independently reviewable and the product is never left
in a broken state between phases.

1. **Consolidate the site-wide mode write path** (S) — pick one of the three
   existing writers (recommend the `agentic/v1/ui-settings` REST route,
   since it's the newest and purpose-built) and have the Dashboard card call
   it too; delete the dead `admin-post` handler and its registration
   (`agent-builder.php:244`, `class-admin-menu-handler.php:667-697`) if
   confirmed unused. No visible behavior change.
2. **Global header mode switch** (M) — add the persistent header control
   described in §3, wired to the consolidated path from #1. Ships alongside
   (not replacing) existing per-screen and Dashboard/Settings toggles.
3. **Onboarding step** (S) — add the mode-choice field to `admin/signup.php`
   per §6, defaulting Basic.
4. **Agents screen Basic/Advanced split** (M) — card-based Basic view +
   existing table as Advanced, per §5. Reuses `agent-wizard` for Basic's
   "add an agent" action.
5. **Knowledge screen Basic/Advanced split** (M) — guided flow (built on
   `knowledge-wizard`) as Basic, existing wiki/tabs as Advanced.
6. **Providers & Keys Basic/Advanced split** (S) — narrow the CRUD form in
   Basic per §5; no new fields, just visibility.
7. **Remaining Settings tabs mode split** (M) — extend the pattern already
   proven on Settings→Users to Interface/Security at minimum; decide and
   document the Basic-mode treatment (escape hatch vs. hidden) for APIs/
   Endpoints/MCP.
8. **Advanced-mode secondary nav (IA shell)** (L) — the persistent
   Console-style rail from §3, shared across the React apps
   (`dashboard-app`, `admin-pages`, `settings-app`), pointing at existing
   page slugs. Tools + Skills present as one nav entry with two tabs.
9. **Playground view** (L) — extend Chat with the config side panel and
   request/response inspector described in §4, gated to Advanced mode only.
10. **Usage & Costs placeholder decision** (S) — implement whichever
    outcome §2's gap analysis settles on (locked/upsell nav entry vs.
    omitted for free users); no functional cost-tracking UI is in scope
    here since that's Pro-only today.

Phases 1-3 are pure infrastructure and can land immediately. Phases 4-7 are
independent of each other and of the nav shell (8) — the mode split on any
one screen doesn't require the Console rail to exist first. Phase 8 (nav
shell) and 9 (Playground) are the largest single changes and should each get
their own design review before implementation, given their size.
