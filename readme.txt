=== Agent Builder ===
Contributors: agenticplugin
Tags: ai, chatbot, automation, llm, mcp
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 3.3.86
Donate link: https://agentic-plugin.com/donate/
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create, train, and orchestrate autonomous AI agents, chatbots, and scheduled automations. 10 free agents, multi-LLM support, and MCP ready.

== Description ==

**Agent Builder** turns your WordPress site into an autonomous AI workspace. Unlike standard chatbots that only answer questions with text, Agent Builder deploys specialized AI agents with controlled tools to draft articles, audit SEO, triage comments, monitor site health, and automate repetitive tasks.

Equipped with a **Basic / Advanced interface switch**, Agent Builder is designed to be effortless for beginners while providing complete control, custom agent tooling, and Model Context Protocol (MCP) support for developers.

---

### 🚀 Zero-Code Simplicity for Site Owners

* **10 Free Built-In Agents:**
  * ✍️ **Content Writer:** Researches, writes, edits, and formats blog posts and pages.
  * 🔍 **SEO Optimizer:** Audits on-page content and proposes keyword and meta improvements.
  * 🛡️ **Site Health Sentinel:** Continuously checks performance, database health, and security alerts.
  * 💬 **Support Triage:** Summarizes customer comments, reviews form submissions, and drafts replies.
  * 🧭 **WordPress Assistant:** Onboards new users and helps troubleshoot core settings.
  * 🎓 **Assistant Trainer:** Build new specialized AI agents simply by describing their job in plain English.
  * ⚙️ **Agent Orchestrator:** Deploys assistants as frontend chat widgets, admin launchers, or background jobs.
  * 📰 **Editorial Director:** Plans editorial calendars and coordinates publishing workflows.
  * 👤 **User Assistant:** Manages member outreach, onboarding, and role-based permissions.
  * 🧩 **Skills Assistant:** Discovers and imports community skills to teach agents new capabilities.
* **Human-in-the-Loop Safety:** Sensitive actions (publishing content, updating settings, deleting data) pause in an **Approvals Queue** for one-click review before anything touches your live site.
* **Embed Everywhere:** Drop responsive chat widgets on any page using native **Gutenberg blocks**, shortcodes, or wp-admin launchers.
* **100% Free Core Knowledge Wiki:** Train agents on your company guidelines, docs, or site content using the local Open Knowledge Framework (OKF) wiki.

---

### ⚡ Built for Developers & Power Users

* **Model Context Protocol (MCP) Ready:** Connect external clients like **Claude Desktop**, **Cursor**, and **VS Code** directly to your WordPress site.
* **Bidirectional WordPress Abilities API (WP 6.9+):**
  * *Outbound:* Exposes all agent tools as native WordPress abilities (`agent-builder/` and `wp-extended/` namespaces).
  * *Inbound:* Automatically transforms abilities declared by other plugins into callable agent tools.
* **Multi-LLM & BYOK (Bring Your Own Key):** Connect OpenAI, Anthropic (Claude), Google Gemini, DeepSeek, xAI (Grok), Kimi (Moonshot), Mistral, Cohere, or run 100% private local models via **Ollama**.
* **Open Skill Architecture:** Full support for the `agentskills.io` open standard, WordPress.org Community Skills, and Anthropic Skills repositories.
* **Developer Controls:** Programmatic orchestration via REST API, automated cron triggers, and detailed JSON activity audit logs. React admin sources live in `src/`; production bundles in `build/` (`npm run build` with `@wordpress/scripts`).

Documentation & Guides: [agentic-plugin.com/documentation](https://agentic-plugin.com/documentation/)

== Installation ==

= Minimum Requirements =

* WordPress 6.4 or higher (6.9+ recommended for native AI Abilities API support)
* PHP 8.1 or higher
* MySQL 8.0 or MariaDB 10.6+

= Automatic Installation =

1. Log in to your WordPress admin dashboard.
2. Navigate to **Plugins → Add New Plugin**.
3. Search for **Agent Builder**.
4. Click **Install Now**, then click **Activate**.
5. The Quick Start wizard will guide you through connecting your preferred AI provider in under two minutes.

= Manual Installation =

1. Download the plugin ZIP file.
2. Go to **Plugins → Add New Plugin → Upload Plugin**.
3. Select the `.zip` file and click **Install Now**.
4. Activate the plugin through the WordPress Plugins menu.
5. Navigate to **Agent Builder → Settings** to connect your LLM provider.

= Supported LLM Providers =

* **Cloud Providers:** OpenAI (GPT-4o, o3-mini), Anthropic (Claude 3.5 Sonnet), Google Gemini, xAI (Grok), DeepSeek, Kimi (Moonshot), Mistral, Cohere, and OpenRouter.
* **Local & Private:** Ollama (default: `http://localhost:11434`) or any custom OpenAI-compatible endpoint.
* **Managed Credits:** Optional Agentic AI routing service with daily free credits.

== Frequently Asked Questions ==

= What is Agent Builder? =
Agent Builder allows you to create, train, and orchestrate autonomous AI agents and teams inside WordPress. Agents use modular tools and skills (guarded by risk levels and human approvals) to perform real administrative and editorial tasks.

= How is this different from generic WordPress chatbot plugins? =
Standard chatbot plugins simply stream text from an API endpoint. Agent Builder gives agents permission-controlled tools to interact directly with your site—such as querying posts, drafting content, and checking performance—backed by an audit log and supervised approval queue.

= Do I need coding skills to use Agent Builder? =
No. The plugin includes a Basic interface mode and 10 pre-configured agents. You can assign tasks, adjust settings, and train custom assistants using natural language.

= Is Agent Builder free? =
Yes. The free core plugin includes all 10 bundled agents, the complete tools/skills hub, the Approvals queue, the local OKF Knowledge wiki, and multi-provider BYOK support. Advanced hosted vector embeddings and cloud media generation are available via optional Agent Builder Pro add-ons.

= Where is my data sent? =
When using cloud LLM providers, conversation context and tool parameters are sent directly to your chosen provider via their official API (see External Services below). If you use Ollama or a local endpoint, 100% of your data stays on your local server.

= What is the WordPress Abilities API integration? =
On WordPress 6.9+, Agent Builder provides bidirectional integration:
1. **Outbound:** Registers agent actions as abilities under `agent-builder/` and `wp-extended/` for external MCP and core discovery.
2. **Inbound:** Automatically imports abilities exposed by other WordPress plugins so your agents can use them as tools.

= What is the difference between Tools and Skills? =
* **Tools:** Single, permission-controlled actions an agent can execute (e.g., `create_draft_post`, `get_site_health`).
* **Skills:** Pre-packaged instruction sets and tool workflows following the open `agentskills.io` standard that teach agents multi-step capabilities without writing code.

= What happens to my data if I delete the plugin? =
Uninstall keeps your data unless you check “Delete data” on the deactivation dialog. If you do choose to delete, conversation history, options, and most custom tables are removed. Custom agents you created and skills you imported are kept so a reinstall can find them.

= Where is the React admin source? =
React admin sources live in `src/`; production bundles are in `build/`. Rebuild with `npm run build` (`@wordpress/scripts`).

== Screenshots ==

1. Dashboard — Overview of active agents, connected providers, and quick actions.
2. Interactive Chat — Chat with specialized agents with full tool transparency.
3. Agents Hub — Activate, configure, and assign roles to bundled or custom agents.
4. Approvals Queue — Review, approve, or reject agent actions before execution.
5. Tools & Skills Hub — Manage risk levels, ability profiles, and community skill imports.
6. Settings — Manage LLM providers, UI modes (Basic/Advanced), and security policies.
7. Activity Log — Full audit trail of all agent conversations, tool calls, and executions.

== External Services ==

This plugin connects to external AI APIs to process prompts and tool executions — no request to any AI provider is made until you configure or explicitly activate it. Optional catalog refresh from Agentic is off by default and only runs after you enable it in Settings → Security. See "Agentic Account & Platform Services" below for every Agentic endpoint, when it is used, and what is sent.

= OpenAI =
* **Endpoint:** `https://api.openai.com/v1/chat/completions`
* **When used:** When OpenAI is selected as your AI provider.
* **Data sent:** Chat prompts, system instructions, tool definitions, and execution payloads.
* **Terms of Service:** [https://openai.com/terms](https://openai.com/terms)
* **Privacy Policy:** [https://openai.com/privacy](https://openai.com/privacy)

= Anthropic =
* **Endpoint:** `https://api.anthropic.com/v1/messages`
* **When used:** When Anthropic Claude is selected as your AI provider.
* **Data sent:** Chat prompts, system instructions, tool definitions, and execution payloads.
* **Terms of Service:** [https://www.anthropic.com/terms](https://www.anthropic.com/terms)
* **Privacy Policy:** [https://www.anthropic.com/privacy](https://www.anthropic.com/privacy)

= xAI =
* **Endpoint:** `https://api.x.ai/v1/chat/completions`
* **When used:** When xAI (Grok) is selected as your AI provider.
* **Data sent:** Chat prompts, system instructions, tool definitions, and execution payloads.
* **Terms of Service:** [https://x.ai/legal/terms-of-service](https://x.ai/legal/terms-of-service)
* **Privacy Policy:** [https://x.ai/legal/privacy-policy](https://x.ai/legal/privacy-policy)

= Google Gemini =
* **Endpoint:** `https://generativelanguage.googleapis.com/v1beta/models/`
* **When used:** When Google Gemini is selected as your AI provider.
* **Data sent:** Chat prompts, system instructions, tool definitions, and execution payloads.
* **Terms of Service:** [https://ai.google.dev/terms](https://ai.google.dev/terms)
* **Privacy Policy:** [https://policies.google.com/privacy](https://policies.google.com/privacy)

= Mistral AI =
* **Endpoint:** `https://api.mistral.ai/v1/chat/completions`
* **When used:** When Mistral is selected as your AI provider.
* **Data sent:** Chat prompts, system instructions, tool definitions, and execution payloads.
* **Terms of Service:** [https://mistral.ai/terms/](https://mistral.ai/terms/)
* **Privacy Policy:** [https://mistral.ai/terms/#privacy-policy](https://mistral.ai/terms/#privacy-policy)

= Meta Llama =
* **Endpoint:** `https://api.llama.com/v1/chat/completions`
* **When used:** When Meta Llama API endpoints are configured.
* **Data sent:** Chat prompts, system instructions, tool definitions, and execution payloads.
* **Terms of Service:** [https://llama.meta.com/llama3/license/](https://llama.meta.com/llama3/license/)
* **Privacy Policy:** [https://www.meta.com/privacy/](https://www.meta.com/privacy/)

= Cohere =
* **Endpoint:** `https://api.cohere.com/v2/chat`
* **When used:** When Cohere is selected as your AI provider.
* **Data sent:** Chat prompts, system instructions, tool definitions, and execution payloads.
* **Terms of Service:** [https://cohere.com/terms-of-use](https://cohere.com/terms-of-use)
* **Privacy Policy:** [https://cohere.com/privacy](https://cohere.com/privacy)

= Kimi (Moonshot AI) =
* **Endpoint:** `https://api.moonshot.ai/v1/chat/completions`
* **When used:** When Kimi is selected as your AI provider.
* **Data sent:** Chat prompts, system instructions, tool definitions, and execution payloads.
* **Terms of Service:** [https://platform.moonshot.ai/docs/agreement/modeluse](https://platform.moonshot.ai/docs/agreement/modeluse)
* **Privacy Policy:** [https://platform.moonshot.ai/docs/agreement/privacy](https://platform.moonshot.ai/docs/agreement/privacy)

= DeepSeek =
* **Endpoint:** `https://api.deepseek.com/chat/completions`
* **When used:** When DeepSeek is selected as your AI provider.
* **Data sent:** Chat prompts, system instructions, tool definitions, and execution payloads.
* **Terms of Service:** [https://cdn.deepseek.com/policies/en-US/deepseek-terms-of-use.html](https://cdn.deepseek.com/policies/en-US/deepseek-terms-of-use.html)
* **Privacy Policy:** [https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html](https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html)

= OpenRouter =
* **Endpoint:** `https://openrouter.ai/api/v1/chat/completions`
* **When used:** When OpenRouter is selected as your AI provider.
* **Data sent:** Chat prompts, system instructions, tool definitions, and execution payloads.
* **Terms of Service:** [https://openrouter.ai/terms](https://openrouter.ai/terms)
* **Privacy Policy:** [https://openrouter.ai/privacy](https://openrouter.ai/privacy)

= Ollama (Local) =
* **Endpoint:** User-configured local URL (default: `http://localhost:11434`)
* **When used:** When Ollama is selected as your AI provider.
* **Data sent:** All data remains strictly on your local infrastructure.

= Agentic AI Services (Optional) =
* **Endpoints:** 
  * Chat: `https://chat.agentic-plugin.com:11435`
  * Vector Store / RAG: `https://rag.agentic-plugin.com`
  * Image Generation: `https://imagegen.agentic-plugin.com`
  * Text-to-Speech: `https://tts.agentic-plugin.com`
  * Video Generation: `https://videogen.agentic-plugin.com`
* **When used:** Only when using Agentic managed AI credits or Pro cloud features.
* **Data sent:** Site URL, license key, prompt text, and task-specific media/document payloads.
* **Terms of Service:** [https://agentic-plugin.com/terms-of-service/](https://agentic-plugin.com/terms-of-service/)
* **Privacy Policy:** [https://agentic-plugin.com/privacy-policy/](https://agentic-plugin.com/privacy-policy/)

= Agentic Account & Platform Services (Optional) =
* **Endpoints:**
  * `https://agentic-plugin.com/wp-json/agentic/v1/model-pricing` — refreshes the LLM model/pricing catalog. Runs only after an administrator enables “Refresh model catalog from Agentic” in Settings → Security (off by default). Can also be triggered manually from the Costs page's "Get Latest Pricing" button. A plain GET request; no personal data is sent, and the response is cached locally.
  * `https://agentic-plugin.com/wp-json/agentic/v1/register` — only when an administrator submits the plugin's sign-up form to obtain a free Agentic API key. Sends the administrator's email address, site URL, site name, plugin version, and plan tier.
  * `https://agentic-plugin.com/wp-json/agentic-license/v1/cancellation-feedback` — only when a licensed, previously-consenting administrator submits a reason on the plugin-deactivation survey. Sends the license key, site URL, the selected reason, an optional free-text comment, and plugin version.
  * `https://agentic-plugin.com/wp-json/agentic/v1/agents/activate-token` — only when installing an uploaded community or purchased agent package that includes a license file. Sends the license token, agent slug, and site URL.
  * `https://agentic-plugin.com/wp-json/agentic/v1/report-issue` — only when an administrator explicitly confirms sending a diagnostic report via the in-chat "report an issue" tool (a preview is always shown first, and a second explicit confirmation is required before anything is sent). Sends site URL, license key (if any), recent error-log excerpts, the active AI provider, plugin/WordPress/PHP version information, and the administrator's own description of the problem.
* **Terms of Service:** [https://agentic-plugin.com/terms-of-service/](https://agentic-plugin.com/terms-of-service/)
* **Privacy Policy:** [https://agentic-plugin.com/privacy-policy/](https://agentic-plugin.com/privacy-policy/)

= Agentic Agent Marketplace (Agent Builder Pro Only, Optional) =
* **Endpoints:** `https://agentic-plugin.com/wp-json/agentic/v1/agents/check-updates`, plus a per-agent marketplace manifest URL and package download URL under the same domain.
* **When used:** Only on sites with an active Agent Builder Pro license, and only after an administrator separately opts in to agent update checks. Free and WordPress.org-only installs never contact this endpoint.
* **Data sent:** For update checks, the slug and version of every installed non-bundled agent. For installs/updates, the agent's slug and its manifest or package download URL.
* **Terms of Service:** [https://agentic-plugin.com/terms-of-service/](https://agentic-plugin.com/terms-of-service/)
* **Privacy Policy:** [https://agentic-plugin.com/privacy-policy/](https://agentic-plugin.com/privacy-policy/)

= Agentic MCP Connector Relay (Optional) =
* **Endpoints:**
  * `https://mcp.agentic-plugin.com/api/verify-state`
  * `https://mcp.agentic-plugin.com/oauth2/relay-callback`
* **When used:** Only when an administrator uses the browser-driven "Connect an MCP client" approval screen to link an external client such as Claude.ai or Cursor. Direct MCP access via a manually created Application Password (Settings → MCP) never contacts this service.
* **Data sent:** Site URL, the approving administrator's WordPress username and email address, a newly generated WordPress Application Password (base64-encoded) scoped to that connection, the list of active agent slugs, and the connecting provider's name.
* **Terms of Service:** [https://agentic-plugin.com/terms-of-service/](https://agentic-plugin.com/terms-of-service/)
* **Privacy Policy:** [https://agentic-plugin.com/privacy-policy/](https://agentic-plugin.com/privacy-policy/)

= Community Agent Skills Repositories (Optional) =
* **Endpoints:**
  * WordPress.org Skills: `https://api.github.com/repos/WordPress/agent-skills/` and `https://raw.githubusercontent.com/WordPress/agent-skills/`
  * Anthropic Skills: `https://api.github.com/repos/anthropics/skills/` and `https://raw.githubusercontent.com/anthropics/skills/`
  * Recommended Skills: `https://agentic-plugin.com/wp-json/agentic/v1/skills`
  * ClawHub: `https://wry-manatee-359.convex.site/api/v1/`
* **When used:** When browsing or importing community skills from the Skills screen.
* **Data sent:** Unauthenticated GET requests for public skills; search queries when using ClawHub.
* **Terms of Service:** [GitHub Terms](https://docs.github.com/site-policy/github-terms/github-terms-of-service) | [GitHub Privacy](https://docs.github.com/site-policy/privacy-policies/github-privacy-statement) | [Convex Terms](https://www.convex.dev/legal/tos) | [Convex Privacy](https://www.convex.dev/legal/privacy) | [OpenClaw Docs](https://docs.openclaw.ai/)

= Google PageSpeed Insights (Optional) =
* **Endpoint:** `https://www.googleapis.com/pagespeedonline/v5/runPagespeed`
* **When used:** Only when the Core Web Vitals check tool is used, and only if you've added your own free Google PageSpeed Insights API key in Settings → APIs. This plugin does not ship or use a shared API key — without your own key, this tool returns setup instructions instead of making a request. (Agent Builder Pro provides managed PageSpeed access through a separate mechanism, without requiring your own key.)
* **Data sent:** The URL being tested (defaults to your homepage), device strategy (mobile/desktop), and your Google API key.
* **Terms of Service:** [https://developers.google.com/terms](https://developers.google.com/terms)
* **Privacy Policy:** [https://policies.google.com/privacy](https://policies.google.com/privacy)

= WordPress.org Plugin & Core Directory (Optional) =
* **Endpoint:** `https://api.wordpress.org/`
* **When used:** Only when specific site-health tools are used — checking core file integrity against official checksums, or checking a plugin's abandonment/maintenance status or changelog. The same official API WordPress core itself uses for plugin/theme browsing and update checks.
* **Data sent:** WordPress version and locale, and the relevant plugin slug(s). No personal data.

= User-Configured Webhooks (Optional) =
* **Endpoint:** A URL you choose yourself when setting up a form.
* **When used:** Only if you enable a webhook on a form you create, so that form's submissions are also sent to a destination you specify.
* **Data sent:** Whatever data that form collects, sent only to the URL you configured — never to Agentic or any other third party.

== Changelog ==

= 3.3.86 - 2026-08-20 =
* WordPress.org submission: catalog sync is now opt-in (off by default), branding and WhatsApp promo default off, plugin install no longer auto-activates, unused Chart.js removed, WP-CLI agent tools omitted from the directory zip, and External Services entries added for OpenRouter and GitHub/Convex privacy.

= 3.3.85 - 2026-08-19 =
* WordPress.org submission readiness: corrected plugin name/trademark and Stable Tag mismatches, disclosed every External Service the plugin contacts (including several automatic, low-data background syncs), and removed the bundled/shared Google PageSpeed Insights key — Core Web Vitals checks now require your own free key, matching the plugin's existing bring-your-own-key model for every other provider.

= 3.3.78 - 2026-08-18 =
* Housekeeping: Trimmed the changelog to major releases only per WordPress.org guidelines. Full version history available on git.

= 3.3.76 - 2026-08-18 =
* Settings > Users: Added Basic/Advanced switch; User Assistant can now manage role-based plugin access and anonymous frontend chat conversationally.

= 3.3.75 - 2026-08-17 =
* Skills: Added Basic/Advanced split via Skills Assistant — create, edit, and import community skills conversationally.

= 3.3.67 - 2026-08-11 =
* Publish: Added Basic/Advanced split via Agent Orchestrator — deploy agents as chat widgets, scheduled tasks, or event triggers conversationally.

= 3.3.63 - 2026-08-10 =
* Standardized Basic/Advanced switches across Tools, Skills, Approvals, and Activity; added bulk approve/reject actions.

= 3.3.57 - 2026-08-07 =
* Skills now follow the open agentskills.io standard with runtime execution context, spec validation, and template gallery.

= 3.3.46 - 2026-08-03 =
* Full JavaScript translation coverage for React admin UI across all 11 bundled locales.

= 3.3.24 - 2026-08-01 =
* Guided deployment wizards for chat widgets, admin launchers, Gutenberg blocks, and Knowledge training.

= 3.3.0 - 2026-07-29 =
* React-based admin hubs for Settings, Tools, Approvals, and Knowledge; added Kimi and DeepSeek provider support.

For the full version history, visit [agentic-plugin.com/changelog](https://agentic-plugin.com/changelog/).

== Upgrade Notice ==

= 3.3.0 =
React Settings, Tools, Approvals, and Knowledge hubs. No breaking changes for existing agents or API keys.

= 3.0.0 =
Contextual AI launchers across wp-admin, manage_cache tool, and bidirectional WordPress Abilities integration. No breaking changes.

= 2.9.272 =
WordPress.org Plugin Check compliance, guarded Abilities API adapter, multi-agent orchestration, and opt-in local memory.

== Privacy ==

See **External Services** for what is sent when you enable a cloud LLM or optional Agentic API. For Agentic product services, also see the [Agentic Privacy Policy](https://agentic-plugin.com/privacy-policy/) and [Terms of Service](https://agentic-plugin.com/terms-of-service/).