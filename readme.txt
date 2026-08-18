=== Agent Builder ===
Contributors: agenticplugin
Tags: ai, chatbot, automation, agents, llm
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 3.3.78
Donate link: https://agentic-plugin.com/donate/
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Orchestrate AI agents and teams with simple job descriptions.

== Description ==

**Agent Builder** lets you create and deploy AI agents and teams inside WordPress using natural language job descriptions. Use any combination of cloud based or local LLM providers (bring your own API key).

= Ten agents included free =

The free plugin ships **10 role-based agents** ready to activate:

* **Assistant Trainer** — trains new AI agents from plain job descriptions
* **Agent Orchestrator** — deploys agents as chat widgets, scheduled tasks, or automatic triggers
* **Content Writer** — creates, edits, and publishes posts and pages
* **Editorial Director** — plans content work and coordinates specialist agents
* **SEO Optimizer** — audits on-page SEO and proposes concrete improvements
* **Site Health Sentinel** — read-only site health, performance, and security signals
* **Skills Assistant** — writes and manages the reusable skills that teach other agents new workflows
* **Support Triage** — triages comments and form submissions, drafts replies
* **User Assistant** — manage user accounts, member outreach, and role-based access to agents
* **WordPress Assistant** — guide to WordPress and Agent Builder for new users

Additional community agents are listed on the product site (install separately if you choose).

= Key Features =

* Role-based AI agents from natural language job descriptions
* Multi-LLM support (OpenAI, Anthropic, xAI, Google, Kimi, DeepSeek, Mistral, Ollama, and more)
* Large library of tools and skills with risk levels and **Approvals** queue for supervision
* **Tools hub** — categories, risk filters, Basic ability profiles
* Shortcodes, Gutenberg blocks, WordPress Abilities API support (WP 6.9+)
* **Knowledge** hub — free local OKF wiki, per-agent Instructions, optional Memory
* Hosted Vector Store / RAG is available with **Agent Builder Pro** plugin when you need it

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
- Automatic backups of files and tables touched by agents

== Frequently Asked Questions ==

= What is Agent Builder? =

Agent Builder lets you create, train, and manage AI agents and teams inside WordPress. Agents use tools and skills you enable (with risk levels and human in the loop approval) to work with content, site settings, and other WordPress features.

= How is Agent Builder different from a simple chatbot plugin? =

Many AI plugins only chat with an external API. Agent Builder:

- Runs in WordPress admin and front-end surfaces (shortcodes, blocks, optional admin chat)
- Exposes tools and skills to perform real tasks — publish content, manage media, etc.
- Lets you run multiple specialized agents plus any you train or build yourself
- Keeps an audit trail of conversations and tool use

LLM replies come from any combination of provider you configure

= Is Agent Builder free? =

Yes. The free plugin includes the ten bundled agents, tools/skills library, Approvals, Knowledge wiki (OKF), and multi-provider LLM support using **your own** keys or a local model.

= Where does Agent Builder send my data? =

When you configure a cloud LLM provider, the plugin sends conversation messages, system context, and tool-related payloads to that provider’s API (see **External Services**). With Ollama (or similar local endpoints), traffic stays on the host you configure.

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
3. Agents — activate and manage the ten bundled agents
4. Settings — Interface, Agents, Providers, Users, and Security (React)
5. Providers — connect OpenAI, Anthropic, xAI, Kimi, Ollama, and more
6. Tools — categories, risk levels, and ability profiles
7. Activity — audit log of conversations and tool use

== Community Agents ==

To browse additional community-built agents, visit [Community Agents](https://agentic-plugin.com/community-agents/) (optional, off-site).

== External Services ==

This plugin can connect to third-party LLM APIs. **No LLM or Agentic service request is made until you configure that provider or enable it.** 

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

= WordPress.org Agent Skills (optional) =
* **Endpoint:** `https://api.github.com/repos/WordPress/agent-skills/` and `https://raw.githubusercontent.com/WordPress/agent-skills/`
* **When used:** When you open Agent Builder → Skills → Browse Community Skills, your browser lists and fetches skills directly from WordPress.org's official GitHub repository. In Basic mode, asking the bundled Skills Assistant agent to find or import a community skill makes the same requests server-side (from your WordPress site) instead.
* **Data sent:** No site data — these are unauthenticated, read-only requests to a public repository.
* **How to disable:** Don't open the Browse Community Skills screen, and don't ask Skills Assistant to browse or import skills.
* **Terms of Service:** [https://docs.github.com/site-policy/github-terms/github-terms-of-service](https://docs.github.com/site-policy/github-terms/github-terms-of-service)
* **Privacy Policy:** [https://docs.github.com/site-policy/privacy-policies/github-privacy-statement](https://docs.github.com/site-policy/privacy-policies/github-privacy-statement)

= Agentic Recommended Skills (optional) =
* **Endpoint:** `https://agentic-plugin.com/wp-json/agentic/v1/skills`
* **When used:** When you open Agent Builder → Skills → Browse Community Skills and view the curated "Recommended" feed (your browser), or when you ask the bundled Skills Assistant agent to find or import a recommended skill (server-side, from your WordPress site).
* **Data sent:** No site data for browsing. Importing a skill sends the skill's slug; a successful import also fires a best-effort, non-blocking import-count notification with the same slug.
* **How to disable:** Don't use the Recommended source in Browse Community Skills, and don't ask Skills Assistant to browse or import skills.
* **Terms of Service:** [https://agentic-plugin.com/terms-of-service/](https://agentic-plugin.com/terms-of-service/)
* **Privacy Policy:** [https://agentic-plugin.com/privacy-policy/](https://agentic-plugin.com/privacy-policy/)

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

= 3.3.78 - 2026-08-18 =
* Housekeeping: Trimmed the changelog to major releases only, per WordPress.org guidance to keep this section scannable — full version-by-version history stays in git and at the link below.

= 3.3.76 - 2026-08-18 =
* Settings > Users gets a Basic/Advanced split; User Assistant can now manage role-based plugin/agent access, per-role usage limits, and anonymous frontend chat conversationally.

= 3.3.75 - 2026-08-17 =
* Skills gets a Basic/Advanced split via a new bundled agent, Skills Assistant — create, edit, and manage skills conversationally, or find and import skills the community has already published.

= 3.3.67 - 2026-08-11 =
* Publish gets a Basic/Advanced split via a new bundled agent, Agent Orchestrator — deploy agents as chat widgets, scheduled tasks, or event triggers conversationally, without the technical Publish screens.

= 3.3.63 - 2026-08-10 =
* Standardized the Basic/Advanced switch across Tools, Skills, Approvals, and Activity; added real bulk approve/reject to the Approvals queue; list screens now use the full content width.

= 3.3.57 - 2026-08-07 =
* Skills now follow the open agentskills.io standard and are actually loaded into agent context at runtime — previously built but never wired up. New template gallery, spec validation, and four new bundled skills.

= 3.3.46 - 2026-08-03 =
* Full JavaScript translation coverage for the React admin UI (settings, wizards, dashboard, activity log) across all 11 bundled locales — previously only PHP-sourced strings were translated.

= 3.3.24 - 2026-08-01 =
* Guided setup wizards for deploying an agent (chat widget, admin bar, Ask AI launcher, or Gutenberg block) and for training one on your own content via the Knowledge wizard (3.3.23).

= 3.3.20 - 2026-08-01 =
* Tools, Approvals, and Activity each get their own in-page Basic/Advanced interface switch — the start of the plugin's progressive-disclosure approach for non-technical users.

= 3.3.0 - 2026-07-29 =
* React-based Settings, Tools, Approvals, and Knowledge hubs. Kimi and DeepSeek providers added.

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
