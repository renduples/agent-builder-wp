=== Agent Builder ===
Contributors: agenticplugin
Tags: ai, chatbot, automation, agents, llm
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 3.3.65
Donate link: https://agentic-plugin.com/donate/
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Orchestrate role-based AI agents and teams with simple job descriptions.

== Description ==

**Agent Builder** lets you create and manage role-based AI agents inside WordPress using plain job descriptions. You choose the LLM provider (your own API key, Agentic AI, or a local model such as Ollama). Higher-risk tool actions can require human approval before they run.

= Eight agents included free =

The free plugin ships **8 role-based agents** ready to activate:

* **Assistant Trainer** — trains new AI agents from plain job descriptions
* **Content Writer** — creates, edits, and publishes posts and pages
* **Editorial Director** — plans content work and coordinates specialist agents
* **SEO Optimizer** — audits on-page SEO and proposes concrete improvements
* **Site Health Sentinel** — read-only site health, performance, and security signals
* **Support Triage** — triages comments and form submissions, drafts replies
* **User Assistant** — helps manage registrations, accounts, and member outreach
* **WordPress Assistant** — guide to WordPress and Agent Builder for new users

Additional community agents are listed on the product site (install separately if you choose).

= Key Features =

* Role-based AI agents from natural language job descriptions
* Multi-LLM support (OpenAI, Anthropic, xAI, Google, Kimi, DeepSeek, Mistral, Meta Llama, Cohere, Ollama, and custom OpenAI-compatible endpoints)
* Large library of tools and skills agents can use **inside WordPress**, with risk levels and an **Approvals** queue for supervised operation
* **Tools hub** — categories, risk filters, Basic ability profiles
* **Settings** (React): Interface · Agents · Providers · Users · Security (Advanced: APIs / Endpoints)
* Shortcodes, Gutenberg blocks, WordPress Abilities API support (WP 6.9+)
* **Knowledge** hub — free local OKF wiki, per-agent Instructions, optional Memory
* Hosted Vector Store / RAG is available with the separate **Agent Builder Pro** plugin when you need it

Documentation: [agentic-plugin.com/documentation](https://agentic-plugin.com/documentation/)

== Installation ==

= Minimum Requirements =

* WordPress 6.4 or higher (6.9+ recommended for AI Abilities support)
* PHP 8.1 or higher
* MySQL 8.0 or MariaDB 10.6+

= Automatic installation =

Automatic installation is the easiest -- WordPress handles everything. To do an automatic install of Agent Builder, log in to your WordPress dashboard, navigate to the Plugins menu, and click “Add New.”

In the search field type “Agent Builder,” then click “Search Plugins.” Once found you can view details about the plugin. More importantly you can install it by clicking “Install Now”.

= Manual installation =

Download the plugin ZIP (from WordPress.org or your distribution package), then upload it under Plugins → Add New → Upload Plugin. See the [WordPress handbook on managing plugins](https://wordpress.org/documentation/article/manage-plugins/).

= Activation =
1. Activate **Agent Builder** under Plugins.
2. Open **Agent Builder → Quick Start** (or Settings → Providers) and connect an LLM provider — bring your own API key, use a local Ollama endpoint, or choose Agentic AI if you prefer that service.
3. Open **Agent Builder → Chat** and select an agent.

Also useful: **Settings** (Interface, Providers, Users, Security), **Knowledge**, and **Agents** (activate or deactivate agents).

= Updating =

Back up your site before major updates. Keep WordPress, PHP, and the plugin current.

= Supported LLM providers =

- OpenAI, Anthropic, xAI, Google, Kimi (Moonshot AI), Meta Llama, Mistral, Cohere, DeepSeek
- Ollama (local)
- Custom OpenAI-compatible endpoints

= AI provider costs =

Cloud LLM and optional Agentic services are billed by those providers according to their pricing. Local Ollama usage stays on your server.

= Safety controls =

- Per-tool risk levels and an Approvals queue for higher-risk actions
- Supervised mode can require review before changes apply
- Audit log of conversations and agent actions
- You control which tools and agents are enabled

== Frequently Asked Questions ==

= How do I build the JavaScript assets from source? =

The admin interface includes React components built with @wordpress/scripts. The compiled output ships in the `build/` directory, and the human-readable source lives in `src/`.

Public development source (WordPress.org free edition): [github.com/renduples/agent-builder-wp](https://github.com/renduples/agent-builder-wp)

To rebuild from source, clone that repository (or use a plugin tree that includes `package.json` and `webpack.config.js`) and run:

`npm ci && npm run build`

This regenerates `build/` from `src/`.

= What is Agent Builder? =

Agent Builder lets you create, train, and manage AI agents inside WordPress. Agents use tools you enable (with risk levels and optional human approval) to work with content, site settings, and other WordPress features.

= How is Agent Builder different from a simple chatbot plugin? =

Many AI plugins only chat with an external API. Agent Builder also:

- Runs in WordPress admin and front-end surfaces (shortcodes, blocks, optional admin chat)
- Exposes tools that can read or change WordPress data **when enabled** — publish content, manage media, etc., typically behind Approvals for higher risk
- Lets you run multiple specialized agents (the eight bundled roles, plus any you train or install yourself)
- Keeps an audit trail of conversations and tool use

LLM replies still come from the provider you configure (cloud API or local Ollama). WordPress is the control plane and tool runtime; the model is not “embedded” in core PHP.

= Is Agent Builder free? =

Yes. The free plugin includes the eight bundled agents, tools/skills library, Approvals, Knowledge wiki (OKF), and multi-provider LLM support using **your** keys or a local model.

A separate **Agent Builder Pro** plugin is optional for hosted Vector Store / RAG and other Pro-only features. The free plugin remains fully usable without Pro.

= Where does Agent Builder send my data? =

**No chat or document content is sent to an external LLM until you configure a provider and use chat (or another feature that calls that service).**

When you use a cloud provider, the plugin sends conversation messages, system context, and tool-related payloads to that provider’s API (see **External Services**). With Ollama (or similar local endpoints), traffic stays on the host you configure.

Optional Agentic product services (if you enable them) are also listed under External Services.

Documentation: [agentic-plugin.com/documentation](https://agentic-plugin.com/documentation/)

= What is the WordPress Abilities API? =

The Abilities API (introduced in WordPress 6.9) is WordPress's native system for plugins to declare what actions they can perform — making those actions discoverable by other plugins, the REST API, MCP clients, and the Command Palette.

Agent Builder integrates in both directions:

1. **Outbound** — All agent tools are registered as WordPress abilities under the `agent-builder/` namespace. Any abilities-aware plugin or client can discover and call them.
2. **Inbound** — Agent Builder queries all registered WordPress abilities from other plugins and makes them available as tools your agents can use. Install an abilities-aware plugin and your agents gain new capabilities automatically.
3. **Extended** — Agent Builder also registers common WordPress operations (get posts, manage media, handle taxonomies) as abilities under the `wp-extended/` namespace, filling gaps that WordPress core doesn't cover yet.

Each registered ability includes risk-level metadata, permission checks, and MCP annotations. On WordPress versions before 6.9, the abilities bridge stays inactive and the plugin works normally.

= What are Tools and Skills? =

**Tools** are the individual actions you permit an agent to take — each action is a permission-controlled operation.

**Skills** Assign a skill to any agent to expand what it can do without writing any code.

= Where can I report bugs or request features? =

Use the WordPress.org support forum for this plugin, or the contact options on [agentic-plugin.com/support](https://agentic-plugin.com/support/).

== Screenshots ==

1. Dashboard — overview, connected providers, and quick actions
2. Chat — talk to any agent with tools and conversation history
3. Agents — activate and manage the eight bundled agents
4. Settings — Interface, Agents, Providers, Users, and Security (React)
5. Providers — connect OpenAI, Anthropic, xAI, Kimi, Ollama, and more
6. Tools — categories, risk levels, and ability profiles
7. Activity — audit log of conversations and tool use

== Community Agents ==

The free WordPress.org build does not download or install agent packages from remote servers. Bundled agents update when you update this plugin from WordPress.org.

To browse additional community-built agents, visit [Community Agents](https://agentic-plugin.com/community-agents/) (optional, off-site).

== External Services ==

This plugin can connect to third-party LLM and optional Agentic product APIs. **No LLM or Agentic service request is made until you configure that provider or enable the feature and use it.** When a cloud service is used, conversation or media payloads described below are sent to that service.

= OpenAI =
* **Endpoint:** `https://api.openai.com/v1/chat/completions`
* **When used:** When OpenAI is selected as the AI provider in Settings.
* **Data sent:** Chat messages, system prompts, tool definitions, and tool call results.
* **Terms of Service:** [https://openai.com/terms](https://openai.com/terms)
* **Privacy Policy:** [https://openai.com/privacy](https://openai.com/privacy)

= Anthropic =
* **Endpoint:** `https://api.anthropic.com/v1/messages`
* **When used:** When Anthropic is selected as the AI provider in Settings.
* **Data sent:** Chat messages, system prompts, tool definitions, and tool call results.
* **Terms of Service:** [https://www.anthropic.com/terms](https://www.anthropic.com/terms)
* **Privacy Policy:** [https://www.anthropic.com/privacy](https://www.anthropic.com/privacy)

= xAI =
* **Endpoint:** `https://api.x.ai/v1/chat/completions`
* **When used:** When xAI is selected as the AI provider in Settings.
* **Data sent:** Chat messages, system prompts, tool definitions, and tool call results.
* **Terms of Service:** [https://x.ai/legal/terms-of-service](https://x.ai/legal/terms-of-service)
* **Privacy Policy:** [https://x.ai/legal/privacy-policy](https://x.ai/legal/privacy-policy)

= Google =
* **Endpoint:** `https://generativelanguage.googleapis.com/v1beta/models/`
* **When used:** When Google is selected as the AI provider in Settings.
* **Data sent:** Chat messages, system prompts, tool definitions, and tool call results.
* **Terms of Service:** [https://ai.google.dev/terms](https://ai.google.dev/terms)
* **Privacy Policy:** [https://policies.google.com/privacy](https://policies.google.com/privacy)

= Mistral =
* **Endpoint:** `https://api.mistral.ai/v1/chat/completions`
* **When used:** When Mistral is selected as the AI provider in Settings.
* **Data sent:** Chat messages, system prompts, tool definitions, and tool call results.
* **Terms of Service:** [https://mistral.ai/terms/](https://mistral.ai/terms/)
* **Privacy Policy:** [https://mistral.ai/terms/#privacy-policy](https://mistral.ai/terms/#privacy-policy)

= Meta Llama =
* **Endpoint:** `https://api.llama.com/v1/chat/completions`
* **When used:** When Meta Llama is selected as the AI provider in Settings.
* **Data sent:** Chat messages, system prompts, tool definitions, and tool call results.
* **Terms of Service:** [https://llama.meta.com/llama3/license/](https://llama.meta.com/llama3/license/)
* **Privacy Policy:** [https://www.meta.com/privacy/](https://www.meta.com/privacy/)

= Cohere =
* **Endpoint:** `https://api.cohere.com/v2/chat`
* **When used:** When Cohere is selected as the AI provider in Settings.
* **Data sent:** Chat messages, system prompts, tool definitions, and tool call results.
* **Terms of Service:** [https://cohere.com/terms-of-use](https://cohere.com/terms-of-use)
* **Privacy Policy:** [https://cohere.com/privacy](https://cohere.com/privacy)

= Kimi (Moonshot AI) =
* **Endpoint:** `https://api.moonshot.ai/v1/chat/completions`
* **When used:** When Kimi is selected as the AI provider in Settings.
* **Data sent:** Chat messages, system prompts, tool definitions, and tool call results.
* **Terms of Service:** [https://platform.moonshot.ai/docs/agreement/modeluse](https://platform.moonshot.ai/docs/agreement/modeluse)
* **Privacy Policy:** [https://platform.moonshot.ai/docs/agreement/privacy](https://platform.moonshot.ai/docs/agreement/privacy)

= DeepSeek =
* **Endpoint:** `https://api.deepseek.com/chat/completions`
* **When used:** When DeepSeek is selected as the AI provider in Settings.
* **Data sent:** Chat messages, system prompts, tool definitions, and tool call results.
* **Terms of Service:** [https://cdn.deepseek.com/policies/en-US/deepseek-terms-of-use.html](https://cdn.deepseek.com/policies/en-US/deepseek-terms-of-use.html)
* **Privacy Policy:** [https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html](https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html)

= Ollama (Local) =
* **Endpoint:** User-configured local URL (default: `http://localhost:11434`)
* **When used:** When Ollama is selected as the AI provider in Settings.
* **Data sent:** All data stays on your local machine. No external network requests are made.

= Agentic AI =
* **Endpoint:** `https://chat.agentic-plugin.com:11435`
* **When used:** When Agentic AI is selected as the AI provider.
* **Data sent:** Chat messages, system prompts, license key, and site URL. Free daily credits.
* **Terms of Service:** [https://agentic-plugin.com/terms-of-service/](https://agentic-plugin.com/terms-of-service/)
* **Privacy Policy:** [https://agentic-plugin.com/privacy-policy/](https://agentic-plugin.com/privacy-policy/)

= Agentic AI (RAG / Vector Store) — Agent Builder Pro =
* **Endpoint:** `https://rag.agentic-plugin.com`
* **When used:** Only when **Agent Builder Pro** is active and an administrator trains or queries the hosted Vector Store (Knowledge → Vector Store). The free Knowledge Wiki (OKF) stays fully local and does not call this service.
* **Data sent:** Site URL, license key, document content for vector indexing, and query text for semantic retrieval.
* **Terms of Service:** [https://agentic-plugin.com/terms-of-service/](https://agentic-plugin.com/terms-of-service/)
* **Privacy Policy:** [https://agentic-plugin.com/privacy-policy/](https://agentic-plugin.com/privacy-policy/)

= Agentic AI (Image Gen) =
* **Endpoint:** `https://imagegen.agentic-plugin.com`
* **When used:** When image generation/edit tools are enabled and a user requests them (may require Agentic credits / Pro depending on your plan).
* **Data sent:** Site URL, license or API credentials if configured, text prompts, and optionally a source image for edits.
* **Terms of Service:** [https://agentic-plugin.com/terms-of-service/](https://agentic-plugin.com/terms-of-service/)
* **Privacy Policy:** [https://agentic-plugin.com/privacy-policy/](https://agentic-plugin.com/privacy-policy/)

= Agentic AI (TTS) =
* **Endpoint:** `https://tts.agentic-plugin.com`
* **When used:** When Text-to-Speech is enabled and a response is spoken, or CLI chat is run with TTS active.
* **Data sent:** Site URL, license or API credentials if configured, and text to synthesize.
* **Terms of Service:** [https://agentic-plugin.com/terms-of-service/](https://agentic-plugin.com/terms-of-service/)
* **Privacy Policy:** [https://agentic-plugin.com/privacy-policy/](https://agentic-plugin.com/privacy-policy/)

= Agentic AI (Video Gen) =
* **Endpoint:** `https://videogen.agentic-plugin.com`
* **When used:** When video generation or editing tools are enabled and a user requests them.
* **Data sent:** Site URL, license or API credentials if configured, prompts, and media URLs/data required for the operation.
* **Terms of Service:** [https://agentic-plugin.com/terms-of-service/](https://agentic-plugin.com/terms-of-service/)
* **Privacy Policy:** [https://agentic-plugin.com/privacy-policy/](https://agentic-plugin.com/privacy-policy/)

= Site deregistration (optional, Pro) =
* **Endpoint:** `https://agentic-plugin.com/wp-json/agentic/v1/deregister`
* **When used:** Only on plugin deletion when the separate Pro install has opted in to release a license seat (`agentic_allow_deregister_on_uninstall`) and an API key is present. Off by default.
* **Data sent:** API key and site URL.
* **How to disable:** Leave deregister-on-uninstall disabled (default).
* **Terms of Service:** [https://agentic-plugin.com/terms-of-service/](https://agentic-plugin.com/terms-of-service/)
* **Privacy Policy:** [https://agentic-plugin.com/privacy-policy/](https://agentic-plugin.com/privacy-policy/)

= Deactivation feedback (optional) =
* **Endpoint:** `https://agentic-plugin.com/wp-json/agentic-license/v1/cancellation-feedback`
* **When used:** Only if you've connected to the free Agentic AI provider (Quick Start sign-up) and, when deactivating the plugin, pick a reason and click "Submit & deactivate" in the deactivation dialog's optional feedback step. Explicit opt-in — clicking "Skip & deactivate" instead sends nothing.
* **Data sent:** Your selected deactivation reason, any notes you type (up to 500 characters), your Agentic API/license key, site URL, and plugin version.
* **How to disable:** Never connect to the Agentic AI provider, or always click "Skip & deactivate" instead of "Submit & deactivate."
* **Terms of Service:** [https://agentic-plugin.com/terms-of-service/](https://agentic-plugin.com/terms-of-service/)
* **Privacy Policy:** [https://agentic-plugin.com/privacy-policy/](https://agentic-plugin.com/privacy-policy/)

= WordPress.org Agent Skills (optional) =
* **Endpoint:** `https://api.github.com/repos/WordPress/agent-skills/` and `https://raw.githubusercontent.com/WordPress/agent-skills/`
* **When used:** Only when you open Agent Builder → Skills → Browse Community Skills. Your browser lists and fetches skills directly from WordPress.org's official GitHub repository.
* **Data sent:** No site data — these are unauthenticated, read-only requests to a public repository.
* **How to disable:** Don't open the Browse Community Skills screen.
* **Terms of Service:** [https://docs.github.com/site-policy/github-terms/github-terms-of-service](https://docs.github.com/site-policy/github-terms/github-terms-of-service)
* **Privacy Policy:** [https://docs.github.com/site-policy/privacy-policies/github-privacy-statement](https://docs.github.com/site-policy/privacy-policies/github-privacy-statement)

= Anthropic Agent Skills (optional, Advanced mode) =
* **Endpoint:** `https://api.github.com/repos/anthropics/skills/` and `https://raw.githubusercontent.com/anthropics/skills/`
* **When used:** Only in Advanced mode, when you switch the Browse Community Skills screen to the Anthropic source. Your browser lists and fetches skills directly from Anthropic's official GitHub repository.
* **Data sent:** No site data — these are unauthenticated, read-only requests to a public repository.
* **How to disable:** Stay on the WordPress.org source, or don't switch to Advanced mode.
* **Terms of Service:** [https://docs.github.com/site-policy/github-terms/github-terms-of-service](https://docs.github.com/site-policy/github-terms/github-terms-of-service)
* **Privacy Policy:** [https://docs.github.com/site-policy/privacy-policies/github-privacy-statement](https://docs.github.com/site-policy/privacy-policies/github-privacy-statement)

= ClawHub (optional, Advanced mode) =
* **Endpoint:** `https://wry-manatee-359.convex.site/api/v1/` and `https://raw.githubusercontent.com/openclaw/skills/`
* **When used:** Only in Advanced mode, when you switch the Browse Community Skills screen to the OpenClaw/ClawHub source and search. ClawHub is a community-run, open-publish skill registry — content is not reviewed by Agent Builder or WordPress.org before you see it.
* **Data sent:** Your search query. No other site data.
* **How to disable:** Stay on the WordPress.org source, or don't switch to Advanced mode.
* **Terms of Service:** [https://docs.openclaw.ai/](https://docs.openclaw.ai/)

== Changelog ==

= 3.3.65 - 2026-08-11 =
* Change: Standardized on "agent" instead of "assistant" throughout the UI — admin pages, the chat widget, onboarding, tooltips, email notifications, and generated content all now consistently say "agent". Bundled agents that are actually named "Assistant" (WordPress Assistant, Assistant Trainer, User Assistant) keep their real names; only generic wording changed.

= 3.3.64 - 2026-08-10 =
* Change: "Publish" and "Activity" are now always shown in the Agent Builder admin menu, matching Tools/Skills/Approvals — previously they were hidden from the menu (though still reachable by direct URL) whenever the site-wide default was Basic. Basic/Advanced now only ever affects a page's own content, never whether it appears in navigation.

= 3.3.63 - 2026-08-10 =
* Change: The Basic/Advanced switch on Tools, Skills, Approvals, and Activity now lives in the same top-right spot on every one of those screens (previously it sat inline in body copy, and Skills had no switch at all).
* Fix: On Approvals, Advanced mode's only difference used to be an "Open classic queue" link that pointed nowhere real — the routing for it was never wired up, and the classic batch-approve page it targeted had no working buttons in the first place (removed along with the plugin's own React migration, never reconnected). Replaced with real bulk approve/reject: pending items are grouped by requesting agent and a short time window, with "Approve all"/"Reject all" actions per group. Approving runs the group in dependency-safe order (scaffold/create steps before whatever depends on them) and stops at the first failure; rejecting has no such ordering requirement.
* Fix: Tools, Skills, Approvals, and Activity — all table/list screens — were capped at the same 1100px content width as form-heavy pages like Settings, leaving a large empty strip on the right at typical admin widths instead of using the full content area the way classic WordPress list tables do.

= 3.3.62 - 2026-08-10 =
* Fix: Clicking "Save changes" on any Settings tab that used the shared save button directly (Interface, Security, Memory, Agents, Instructions) threw a "Converting circular structure to JSON" error and silently failed to save — the button's `onClick` passed the raw click event through as the save handler's data argument, which then got spread into the save payload instead of the actual form state. The shared save button no longer forwards its click event.

= 3.3.61 - 2026-08-10 =
* Fix: The Skills list page you actually land on from the nav menu renders through a separate React view, not the classic PHP page — so the Basic/Advanced split from 3.3.58 (Source/Version columns, Export action) never applied there; Source was never shown at all and Version was always shown regardless of mode. Both are now correctly Advanced-only there too, matching the classic edit/hub pages.

= 3.3.60 - 2026-08-08 =
* Fix: On Browse Community Skills, the search box (only meant for the ClawHub source) stayed visible on the WordPress.org and Anthropic sources instead of hiding, because a shared CSS utility class's `display: flex` was overriding the native `hidden` attribute/property. Toggled via inline style instead.
* Fix: Skill descriptions written in YAML's block-scalar form (`description: |` / `|-` / `>`, common for longer descriptions — Anthropic's own claude-api skill uses it) were parsed as literally just the block indicator instead of the actual text, both in the browser-side skill browser and the PHP-side parser. Both now read block-scalar and single-line descriptions correctly.

= 3.3.59 - 2026-08-08 =
* Add: Browse Community Skills now supports three sources — WordPress.org's official agent-skills repository (Basic mode default, no switcher needed), plus Anthropic's official skills repository and OpenClaw/ClawHub in Advanced mode, with a tab switcher between them. WordPress and Anthropic skills are listed directly from their GitHub repositories (no search required, small curated catalogs); ClawHub keeps its existing search flow.
* Fix: The import payload was passed through `sanitize_text_field()` as a whole JSON blob before decoding, which strips anything resembling an HTML tag — corrupting imported skill content that contains "<...>" sequences (routine in WordPress/PHP/block-markup examples). Individual fields are still sanitized on their own terms after decoding; the blob itself no longer is.
* Fix: Re-importing a community skill could match and silently overwrite a same-slug skill from a *different* source (e.g. two registries both publishing a "pdf" skill). Dedup now requires matching both source and slug.

= 3.3.58 - 2026-08-08 =
* Change: Skills now always appears in the admin menu, matching Tools, instead of being hidden entirely in Basic mode. The Basic/Advanced split now happens on the page itself — Source/Version columns, the Export action, Author/Version fields, the spec-validation notice, the prompt preview, and file import are Advanced-only; the rest (including assigning a skill to a specific assistant) is available in both.

= 3.3.57 - 2026-08-07 =
* Add: Skills now follow the agentskills.io open standard (the same SKILL.md format Anthropic and Google both use) — `allowed-tools` frontmatter, `license`/`compatibility`/`metadata` fields, and spec validation, alongside the plugin's original `tools:` syntax for backward compatibility.
* Add: Skills are now actually loaded by agents. Every enabled skill's name and description are injected into the system prompt; the full instructions load on demand via a new `load_skill` tool when a task matches — full bodies are no longer sent on every turn.
* Add: Bundled skills now refresh automatically on plugin updates if you never edited them; if you did, your changes are preserved and the Skills editor offers "Reset to shipped version".
* Add: A template gallery when creating a new skill, inline spec/tool validation, a preview of exactly what gets sent to the assistant, and file import/export for portability with other agentskills.io-compliant tools.
* Add: New bundled skills — WooCommerce catalog management, WooCommerce order management, WordPress content authoring, and Gutenberg block content.

= 3.3.56 - 2026-08-06 =
* Fix: When a tool call failed and got retried internally, agents sometimes surfaced troubleshooting commentary about it (e.g. "If the issue persists, try a different tool.") in their actual reply to the user. Root cause: the retry-guidance sent back to the model was tagged role:'user' — indistinguishable from something the human actually said — so the model would occasionally respond to it directly as part of its answer. Retry-guidance messages are now explicitly framed as internal system notices with an instruction not to mention them to the user.

= 3.3.55 - 2026-08-05 =
* Fix: Even after excluding always-on concepts from the knowledge index (3.3.54), agents still sometimes tried calling read_okf_concept for content already fully present in the prompt — the tool's own description ("use this instead of guessing") nudges the model to use it for any knowledge-shaped question regardless of what's already in context, and with the id no longer listed anywhere it would just guess (and fail) using the concept's title. Added an explicit "already fully loaded, do not call these tools" instruction directly in the always-on block.

= 3.3.54 - 2026-08-05 =
* Fix: Agents redundantly called read_okf_concept for facts already fully injected via "Always include in prompts," because the same concept also appeared as a title-only entry in the knowledge index, which explicitly instructs "never answer from a title alone." Always-on concepts are now excluded from that index. Also fixed the underlying cause of a related gap: an agent-scoped concept marked "Always include in prompts" was silently never injected at all (only site-wide always-on concepts worked) because the prompt builder never passed the agent's own slug through to the always-on lookup — it now does, so per-agent always-on concepts are injected too, and their titles are correctly excluded from the index on that agent's own prompts.

= 3.3.53 - 2026-08-05 =
* Add: The Dashboard page never had the standard plugin footer (Support Center, Documentation, Upgrade to Pro, Terms/Privacy/GDPR links) that every other admin page already has — it was built as a separate, self-contained React app and was simply never wired up to it. Added it, including a dashboard-specific summary blurb.

= 3.3.52 - 2026-08-05 =
* Change: Dashboard Interface Settings — reworded the Community Agents blurb to "Discover and install agents created by the WordPress community." and shortened its button from "Browse marketplace →" to "Browse →".
* Fix: Every "Upgrade to Pro" link (admin footer, in-admin upgrade page, tools upgrade prompt) pointed at the old `/licensing-and-pricing/` URL. Updated the shared `Distribution::PRICING_URL` source and its few defensive duplicate fallbacks to `https://agentic-plugin.com/pricing/`.

= 3.3.51 - 2026-08-04 =
* Change: The Dashboard's Approvals & Backups card now shows Completed Approvals alongside Pending Approvals, and moved the two backup counters (Files Backed Up, DB Tables Backed Up) to their own row below — a 2x2 grid instead of one cramped row of three. Completed Approvals counts only approvals a human actually approved or rejected, not the general tool-execution ledger.

= 3.3.50 - 2026-08-04 =
* Add: Each provider in the Dashboard's Connected Providers card now has a "Test" button that checks connectivity on demand — a real lightweight request to that provider's API, using its own already-stored key and model (not the site's active-default model, which could differ for a non-default provider). Shows a checkmark and short status message, or the provider's own error message, inline.

= 3.3.49 - 2026-08-04 =
* Change: Moved the "Disable All Agents" (Emergency Stop) toggle from the Interface Settings card to the Quick Actions card, pinned below the action buttons. It always shows there and isn't part of the "Manage Actions" list, so it can't be hidden or removed like the other quick actions.

= 3.3.48 - 2026-08-04 =
* Clarified the deactivation dialog's optional feedback step: it now states up front that answering is voluntary/opt-in and that nothing is sent unless you click "Submit & deactivate" (vs. "Skip & deactivate," which sends nothing). Also documented the feedback endpoint in External Services, which previously listed every other agentic-plugin.com endpoint except this one.

= 3.3.47 - 2026-08-03 =
* Fix: On the Plugins page, choosing "Delete all plugin data" and clicking Continue in the deactivation dialog appeared to hang — the follow-up "Are you sure?" confirmation was actually opening, but rendered underneath the still-visible deactivation dialog (z-index 100002 vs. 160000) instead of on top of it, so its buttons were unreachable. Raised the shared confirm-dialog overlay's z-index above the deactivation dialog's so it always stacks on top.

= 3.3.46 - 2026-08-03 =
* Fix: The React admin UI (settings, wizards, dashboard, activity log) had `wp_set_script_translations()` correctly wired but no JS translation files behind it, so it rendered in English regardless of site locale — the .pot only ever covered PHP strings. Extracted the ~400 JS-sourced strings, translated them into all 11 bundled locales, and generated the per-script JSON files (`agent-builder-{locale}-agentic-{handle}.json`) WordPress needs to load them.

= 3.3.45 - 2026-08-03 =
* Fix: The 11 bundled translation files (de_DE, es_ES, fr_FR, it_IT, ja, ko_KR, nl_NL, pl_PL, pt_BR, ru_RU, zh_CN) had only ever had 11 strings translated out of ~1600 — essentially the entire admin UI fell back to English regardless of site locale. Fully translated all strings in all 11 languages and recompiled the .mo files.

= 3.3.44 - 2026-08-02 =
* Fix: languages/agent-builder.pot (the PHP translation source template) hadn't been regenerated since version 2.9.261 and was missing roughly 470 strings added since — including everything from today's wizards and settings work. Regenerated to match 3.3.44.

= 3.3.43 - 2026-08-02 =
* Fix: The delete_agent tool checked the plugin's own bundled-agent directory instead of where user-created agents actually live, so it could never delete a real custom agent ("not found" every time) and — worse — could find and permanently delete one of the 5 bundled agents that weren't on its protected list. Now points at the correct directory and protects all 8 bundled agents. It also only removed the agent's files, leaving it listed as "active" with nothing there to load — now properly deactivated first.

= 3.3.42 - 2026-08-02 =
* Fix: The Agent Wizard and Knowledge Wizard left zero audit trail — both call their underlying tool/store directly instead of through the normal audited pipeline. Now log agent_installed / knowledge_added with the wizard's actual input. The Deploy Wizard's "Ask AI" launcher surface was the one path there missing the deployment_enabled entry the other three surfaces already get automatically — added.

= 3.3.41 - 2026-08-02 =
* Fix: The CSV export's fputcsv() calls relied on PHP 8.3+'s now-deprecated implicit $escape default, which could leak "Deprecated:" notices into the exported file's content on sites with error display on. Passed explicitly now.

= 3.3.40 - 2026-08-02 =
* Fix: The new "Export CSV" button on the Activity page 403'd — its URL was built with wp_nonce_url(), which HTML-entity-escapes "&" for embedding in server-rendered HTML, but the URL is consumed directly by React as a real link. Built without that escaping now.

= 3.3.39 - 2026-08-02 =
* Add: Changing a service endpoint URL (Settings → Endpoints) is now logged with the specific service and the before/after URL, not just a generic "settings changed" entry — endpoint changes redirect where AI/media traffic goes, so they're worth a clear audit trail.
* Add: An "Export CSV" button on the Activity (Logs) page downloads the current Timeline, Conversations, or Security log — handy to attach when emailing support about an issue.

= 3.3.38 - 2026-08-02 =
* Fix: Saving the new Endpoints → Agentic Services URLs after editing one crashed with a "circular structure" error — the shared Save button wires its click handler directly as the save callback's first argument, so editing a field could route non-plain data into the save payload. Now calls the save handler explicitly with no arguments.
* Add: Each Agentic service on the Endpoints tab now has a Test button that pings its real health check (or, for the marketplace API, its base URL) and reports reachability inline.

= 3.3.37 - 2026-08-02 =
* Add: Settings → Endpoints now lists the Agentic services (AI chat, knowledge search, image/video/TTS generation) with their current base URL, so an admin can see and override them the same way LLM provider endpoints already can. This backend support (Service_Registry::update()/reset()) existed but had no settings UI wired to it until now.

= 3.3.36 - 2026-08-02 =
* Fix: The report_issue tool — documented as available to every agent so any assistant can self-diagnose and offer to file a support report — wasn't actually wired into any of the 8 bundled agents. Added to all of them.
* Add: get_image_pricing tool, matching the existing get_video_pricing tool, for checking Agentic Image Generation rates.

= 3.3.35 - 2026-08-02 =
* Fix: Third-party provider model lists (OpenAI, Anthropic, Google, xAI, Mistral, and others) now refresh from our own curated catalog on agentic-plugin.com — the same source that already powers "Get Latest Pricing" — instead of every site querying each vendor's raw API directly.
* Fix: Video generation tools could snap a requested duration to 4 seconds, a value the video service always rejects (only 6 or 8 seconds are accepted) — durations now only snap to accepted values.
* Fix: Image generation/edit/upscale tools now send the site URL to the image service, matching what the video and TTS tools already send (used for per-site usage attribution).
* Fix: The "Agentic AI" entry in External Services was missing the `:11435` port from its declared endpoint.

= 3.3.33 - 2026-08-02 =
* Fix: Provider_Registry::upsert() was blanking out a provider's endpoint, name, and default model whenever it was called with only a partial field set (as the model "Refresh" action and the new daily refresh cron both do), instead of leaving untouched fields alone. Partial updates now correctly preserve everything they don't explicitly change.

= 3.3.32 - 2026-08-02 =
* Fix: Provider model lists now refresh daily straight from each provider's own API (reusing the existing "Refresh" action), instead of relying only on hardcoded lists baked into a plugin release. New models from Google, OpenAI, Anthropic, and others now appear automatically once a valid API key is saved.

= 3.3.31 - 2026-08-02 =
* Add: Gemini 3.1/3.5/3.6 Flash (Lite) models are now selectable for the Google and Agentic AI providers, alongside existing 2.5 models. Pricing shown for the new models is a placeholder until official rates are confirmed.

= 3.3.30 - 2026-08-02 =
* Fix: Google/Gemini requests now capture and replay the `thoughtSignature` field on function-call parts, required by upcoming Gemini 3.x models to avoid a 400 error; no effect on current Gemini 2.5 models.

= 3.3.29 - 2026-08-01 =
* Fix: WordPress.org Plugin Check compliance — the readme.txt Changelog section had grown past the 5,000-character limit the plugin directory's readme parser truncates at, so the Changelog tab on the plugin page would have shown a cut-off, broken-looking history. Trimmed older entries to one line each (full detail stays in git history); a link to the complete version history was already present and now correctly follows the trimmed section.

= 3.3.28 - 2026-08-01 =
* Feature: Dashboard "Approvals & Backups" card — pending-approval count and file/DB backup counts, with quick links.

= 3.3.27 - 2026-08-01 =
* Improvement: admin-bar chat overlay now renders in-chat proposal/approval cards, matching the main Agent Chat page.

= 3.3.26 - 2026-08-01 =
* Feature: high-risk queued actions show an in-chat Approve/Reject card instead of only a text pointer to the Approvals page.
* Feature: new get_user_context tool lets Assistant Trainer check who it's talking to before promising admin-only actions.

= 3.3.25 - 2026-08-01 =
* Improvement: added tooltips to Knowledge's four tabs and each Approvals queue item.

= 3.3.24 - 2026-08-01 =
* Feature: guided Publish wizard (chat widget, admin bar, Ask AI launcher, or Gutenberg block).
* Fix: the Approvals backups/restore link is now visible in Basic interface mode too.

= 3.3.23 - 2026-08-01 =
* Feature: guided Knowledge wizard (paste text, upload a file, or pick existing pages).

= 3.3.22 - 2026-08-01 =
* Improvement: Basic-mode navigation trimmed to 7 core items; new users default into WordPress Assistant.

= 3.3.21 - 2026-08-01 =
* Fix: the Dashboard was completely broken (fatal error on every load) due to a REST callback signature mismatch.

= 3.3.20 - 2026-08-01 =
* Improvement: Tools, Approvals, and Activity each get their own in-page Basic/Advanced switch.

= 3.3.19 - 2026-08-01 =
* Internal: groundwork for per-screen interface mode (no visible change yet).

= 3.3.18 - 2026-08-01 =
* Fix: create_agent_files was incorrectly blocked at extreme risk, breaking Assistant Trainer's core function.
* Improvement: better onboarding guidance, tooltips, and Approvals empty-state copy.

= 3.3.17 - 2026-07-30 =
* Fix: multi-instance chat (modal + shortcode/block together) could reload the page and lose conversations.
* Security: Pro-only tools stripped from the free package; MCP no longer advertises high-risk tools.

= 3.3.16 - 2026-07-30 =
* Fix: a second chat instance on the same page could reload the page instead of sending a message.

= 3.3.15 - 2026-07-30 =
* Fix: the chat rate limit never reset during sustained normal use, eventually blocking legitimate conversations.

= 3.3.14 - 2026-07-30 =
* Fix: scheduled tasks, event listeners, and the chat modal widget could throw a fatal error on execution.

= 3.3.13 - 2026-07-30 =
* Fix: Knowledge Wiki search/list/read tools failed to load; four bundled agents couldn't use them at all.

= 3.3.12 - 2026-07-30 =
* Fix: the "Trust more" approval profile behaved identically to the safer "Auto-approve low risk" profile.

= 3.3.11 - 2026-07-30 =
* Fix: in-chat proposal confirmations and the admin Approval Queue didn't work for non-administrator roles.

= 3.3.10 - 2026-07-30 =
* Fix: turning off Emergency Stop always reported success even when a restore actually failed.

= 3.3.9 - 2026-07-30 =
* Fix: Settings → Users role permissions had no effect for several granular capabilities.

= 3.3.8 - 2026-07-30 =
* Fix: two WP-CLI commands and the MCP relay threw a fatal error from an unqualified class reference.

= 3.3.7 - 2026-07-30 =
* Fix: search_capabilities could report an already-active local agent as "not found".

= 3.3.6 - 2026-07-30 =
* Fix: clarified when to use search_capabilities vs. get_agent_list for local agent status.

= 3.3.5 - 2026-07-30 =
* Fix: delegate_to_agent always failed with a false "delegation cycle" error.

= 3.3.4 - 2026-07-30 =
* Fix: editor sidebar suggestion heuristic threw a silent error, misreported as a connection failure.

= 3.3.3 - 2026-07-30 =
* Fix: editor sidebar's "Insert paragraph" success callback threw an error despite the insert succeeding.

= 3.3.2 - 2026-07-30 =
* Fix: editor sidebar error messages incorrectly offered "Insert"/"Replace" buttons.

= 3.3.1 - 2026-07-30 =
* Fix: editor sidebar crash, a get_failed_logins DB error, and a multi-agent handoff context bug.

= 3.3.0 - 2026-07-29 =
* New: React Settings, Tools, Approvals, and Knowledge hubs. Kimi and DeepSeek providers added.

For the full version history, visit [agentic-plugin.com/changelog](https://agentic-plugin.com/changelog/).

== Upgrade Notice ==

= 3.3.0 =
React Settings, Tools, Approvals, and Knowledge hubs. No intentional breaking changes for existing agents or API keys.

= 3.0.0 =
Contextual AI launchers across wp-admin (Plugins, Media, Users, Comments, Dashboard), a new manage_cache tool, WordPress Abilities fully integrated into the Tools hub with enable/disable toggles, and a fix for disabled tools being incorrectly published as outbound Abilities. No breaking changes.

= 2.9.272 =
WordPress.org Plugin Check compliance (Abilities API behind a guarded adapter, safe `%i`/`%s` queries) plus multi-agent orchestration, opt-in local memory, and an accessible non-blocking UI (toasts/modals replace browser alerts). No breaking changes.

= 2.9.194 =
SSE streaming fixes, dashboard improvements, and WP.org compliance updates. No breaking changes.

= 2.9.145 =
SSE streaming, 42 new tools, agent delegation, and TTS improvements. No breaking changes.

= 2.9.38 =
Privacy improvement: LLM connection only makes external request with explicit user consent.

== Privacy ==

See **External Services** for what is sent when you enable a cloud LLM or optional Agentic API. For Agentic product services, also see the [Agentic Privacy Policy](https://agentic-plugin.com/privacy-policy/) and [Terms of Service](https://agentic-plugin.com/terms-of-service/).
