=== Agent Builder ===
Contributors: agenticplugin
Tags: ai, chatbot, automation, agents, llm
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 3.3.0
Donate link: https://agentic-plugin.com/donate/
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Orchestrate role-based AI agents and teams with simple job descriptions.

== Description ==

**Agent Builder** lets you orchestrate AI agents and teams directly in WordPress using plain job descriptions.

No coding. No complex prompts. Just describe the role you need, and your AI employee starts working.

= Eight agents included free =

The free plugin ships **8 role-based agents** ready to activate:

* **Assistant Trainer** — trains new AI assistants from plain job descriptions
* **Content Writer** — creates, edits, and publishes posts and pages
* **Editorial Director** — plans content work and coordinates specialist agents
* **SEO Optimizer** — audits on-page SEO and proposes concrete improvements
* **Site Health Sentinel** — read-only site health, performance, and security signals
* **Support Triage** — triages comments and form submissions, drafts replies
* **User Assistant** — helps manage registrations, accounts, and member outreach
* **WordPress Assistant** — guide to WordPress and Agent Builder for new users

Discover more on [Community Agents](https://agentic-plugin.com/community-agents/).

= Key Features =

* Create unlimited role-based AI agents using natural language
* Multi-LLM support (OpenAI, Anthropic, xAI, Google, Kimi, local models via Ollama, and more)
* 250+ built-in tools and skills let your agents take real actions inside WordPress
* **Tools hub** with category tabs, risk levels, filters, and Basic ability profiles (Pro: site-local custom tools)
* Secure sandboxed execution with **Approvals** (comfort preferences, email alerts, progress feedback) and full audit logs
* Modern **Settings** (React): Interface · Agents · Providers · Users · Security — plus Advanced APIs/Endpoints
* Shortcodes, Gutenberg blocks, WordPress Abilities API support
* **Knowledge** hub — free OKF wiki, per-assistant Instructions, optional Memory, and Pro Vector Store path
* Optional **Agent Builder Pro** add-on for hosted Vector Store / RAG (semantic search over PDFs and large site content)

Agent Builder turns WordPress into a smarter, autonomous platform — perfect for bloggers, agencies, and business owners who want to scale without hiring more staff.

Visit [agentic-plugin.com](https://agentic-plugin.com/) for premium extensions, community agents, and additional templates.

== Installation ==

= Minimum Requirements =

* WordPress 6.4 or higher (6.9+ recommended for AI Abilities support)
* PHP 8.1 or higher
* MySQL 8.0 or MariaDB 10.6+

= Automatic installation =

Automatic installation is the easiest -- WordPress handles everything. To do an automatic install of Agent Builder, log in to your WordPress dashboard, navigate to the Plugins menu, and click “Add New.”

In the search field type “Agent Builder,” then click “Search Plugins.” Once found you can view details about the plugin. More importantly you can install it by clicking “Install Now”.

= Manual installation =

Manual installation method requires [downloading Agent Builder](https://agentic-plugin.com/download/) and then uploading it to your web server. The WordPress codex contains [instructions on how to do this here](https://wordpress.org/support/article/managing-plugins/#manual-plugin-installation).

= Activation =
1. Find Agent Builder and click **Activate** at the WordPress Plugins page.
2. **Agent Builder → Quick Start.** Connect Agentic AI as your default LLM Provider with free daily credits.
3. Once connected you land directly on the **Agent Builder → Chat** page — ready to go.

Navigate to **Agent Builder → Settings** for Interface (theme), Providers, Users, and Security.
Navigate to **Agent Builder → Knowledge** for the wiki, per-assistant instructions, and memory.
Navigate to **Agent Builder → Agents** to activate or deactivate your AI agents.

= Updating =

Plugin updates works smoothly, but we recommend you always backup your website.

= You Choose your own LLM provider =

Works with popular AI services you already know:
- OpenAI
- Anthropic
- Cohere  
- xAI
- Google
- Kimi (Moonshot AI)
- Meta Llama
- Mistral
- DeepSeek
- Ollama
- Your provider not in the list? You can add as many LLM providers as you like. 

= AI Provider Costs =
You are billed directly for API usage by your selected LLM provider(s) according to their pricing.

= Your Data Stays Secure =

- Full control of Agent tools, skills and abilities.
- Automated file and database backup and restore.
- Supervised mode shows you exactly what agents will change before they do. 
- Complete audit log of every conversation and action performed by your users and agents.

== Frequently Asked Questions ==

= How do I build the JavaScript assets from source? =

The admin interface includes React components built with @wordpress/scripts. The compiled output ships in the `build/` directory, and the human-readable source lives in `src/`.

Public development source (WordPress.org free edition): [github.com/renduples/agent-builder-wp](https://github.com/renduples/agent-builder-wp)

To rebuild from source, clone that repository (or use a plugin tree that includes `package.json` and `webpack.config.js`) and run:

`npm ci && npm run build`

This regenerates `build/` from `src/`.

= What is Agent Builder? =

Agent Builder lets you create, train and manage AI agents directly inside your WordPress site.

Your AI agents have access to over 250 powerful tools in a managed environment. 

= How is Agent Builder different from other plugins? =

Most AI plugins are just chat interfaces that send every request to a LLM provider.

Agent Builder is different:

- Everything runs inside WordPress (deep integration with core functions, hooks, REST API, user roles, capabilities)
- Agents can take real actions (publish posts, edit pages, run WP-CLI, send emails, update plugins on schedule…)
- You can create an unlimited number of specialized agents for various jobs (Content Writer, SEO Optimizer, Security Guardian, Support Agent…) and orchestrate them together with shared tools.
- Built-in approval and backup workflows with audit logs that keep everything safe and under your control.

Unlike external frameworks (OpenClaw, LangChain-based tools, etc.) that treat WordPress as just another API, Agent Builder treats WordPress as the primary runtime — faster, more secure, and dramatically simpler to maintain.

= Is Agent Builder free? =

The core plugin lets you deploy an unlimited number of AI agents with hundreds of tools and skills - completely free. 

You can also switch to [Agent Builder Pro](https://agentic-plugin.com/download/) any time.

= Where does Agent Builder send my data? =

Data is sent to your LLM providers such as Agentic AI, xAI, OpenAI, Anthropic, Google, Kimi, or others you configure.

You can also use local models via Ollama / LM Studio (fully private — nothing leaves your server)

Extensive [Documentation](https://agentic-plugin.com/documentation/) is available.

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

Use the official support forum on WordPress.org or [report an issue](https://agentic-plugin.com/support/) at https://agentic-plugin.com/

We respond to support tickets within 24–48 hours (faster for confirmed bugs).

== Screenshots ==

1. Setup Wizard – choose your AI provider with pricing comparison and recommendations
2. Setup Wizard – step-by-step API key entry with screenshots for each provider
3. Setup Wizard – connection test and first chat with an AI agent
4. Chat interface – talk to any AI agent with quick actions, markdown support, and file uploads
5. Agents overview – manage all your installed agents in one place - just like plugins
6. Agent configuration – set provider, model, mode, capabilities, and channel permissions per agent
7. Audit log – review every AI action, tool call, and decision with full transparency

== Community Agents ==

The free plugin does **not** phone home to check for agent updates, and it does not download or install agent packages from remote servers.

To discover more assistants, open **Agent Builder → Agents → Community Agents**, or visit [Community Agents](https://agentic-plugin.com/community-agents/) (marketplace). Bundled agents update when you update the plugin from WordPress.org.

Agent Builder Pro can offer in-dashboard update notices for marketplace packages after you opt in.

== External Services ==

This plugin lets you connect to third-party services to process and execute tasks. **No data is transmitted unless you configure a cloud-based provider.** The plugin sends conversation messages (user input and system context) to your selected LLM provider's API to receive AI-generated responses.

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

= Ollama (Local) =
* **Endpoint:** User-configured local URL (default: `http://localhost:11434`)
* **When used:** When Ollama is selected as the AI provider in Settings.
* **Data sent:** All data stays on your local machine. No external network requests are made.

= Agentic AI =
* **Endpoint:** `https://chat.agentic-plugin.com`
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
* **When used:** When an agent generates or edits images. Only active if the Media Assistant agent is installed and a user requests image generation.
* **Data sent:** Site URL, license key, text prompts, and optionally a source image (for edit operations). No user-identifying information beyond the license key.
* **Terms of Service:** [https://agentic-plugin.com/terms-of-service/](https://agentic-plugin.com/terms-of-service/)
* **Privacy Policy:** [https://agentic-plugin.com/privacy-policy/](https://agentic-plugin.com/privacy-policy/)

= Agentic AI (TTS) =
* **Endpoint:** `https://tts.agentic-plugin.com`
* **When used:** When Text to Speech is enabled and an agent reads a chat response aloud, or when `wp agent chat` / `wp agent prompt` is run with TTS active.
* **Data sent:** Site URL, license key, and the text content to be synthesized. No user-identifying information beyond the license key.
* **Terms of Service:** [https://agentic-plugin.com/terms-of-service/](https://agentic-plugin.com/terms-of-service/)
* **Privacy Policy:** [https://agentic-plugin.com/privacy-policy/](https://agentic-plugin.com/privacy-policy/)

= Agentic AI (Video Gen) =
* **Endpoints:** `https://videogen.agentic-plugin.com` (video generation, stitching, trimming, captions, analysis, add audio)
* **When used:** When an agent generates video clips, stitches or edits videos, searches for background music, or adds an audio track.
* **Data sent:** Site URL, license key, text prompts, image data (for image-to-video), video URLs (for editing operations), and audio URLs (for audio mixing).
* **Terms of Service:** [https://agentic-plugin.com/terms-of-service/](https://agentic-plugin.com/terms-of-service/)
* **Privacy Policy:** [https://agentic-plugin.com/privacy-policy/](https://agentic-plugin.com/privacy-policy/)

= Agent Update Checks (Opt In) =
* **Endpoint:** `https://agentic-plugin.com/wp-json/agentic/v1/agents/check-updates`
* **When used:** Only when the administrator has explicitly opted in for update checks on AI agents.
* **Data sent:** Installed agent slugs and version numbers.
* **How to disable:** Decline the opt-in prompt, or deactivate/reactivate the plugin to reset your choice.
* **Terms of Service:** [https://agentic-plugin.com/terms-of-service/](https://agentic-plugin.com/terms-of-service/)
* **Privacy Policy:** [https://agentic-plugin.com/privacy-policy/](https://agentic-plugin.com/privacy-policy/)

= Site Deregistration (Opt In) =
* **Endpoint:** `https://agentic-plugin.com/wp-json/agentic/v1/deregister`
* **When used:** Only on plugin deletion, and only if you have explicitly opted in (the `agentic_allow_deregister_on_uninstall` option is enabled) and an Agentic AI API key is configured. Applies to Agent Builder Pro installs.
* **Data sent:** Your Agentic AI API key and site URL, so the license seat tied to this site can be released.
* **How to disable:** Leave the deregister-on-uninstall option disabled (the default). No request is made unless you opt in.
* **Terms of Service:** [https://agentic-plugin.com/terms-of-service/](https://agentic-plugin.com/terms-of-service/)
* **Privacy Policy:** [https://agentic-plugin.com/privacy-policy/](https://agentic-plugin.com/privacy-policy/)

== Changelog ==

= 3.3.0 - 2026-07-29 =
* New: React **Settings** app (Interface, Agents, Providers, Users, Security; Advanced APIs/Endpoints).
* New: React **Tools**, **Approvals**, and **Knowledge** hubs (Wiki, Instructions, Memory, Vector).
* Improve: clearer admin navigation, policy footers, and Documentation links on every surface.
* Providers: Kimi (Moonshot AI) and DeepSeek listed alongside existing LLMs.

= 3.2.4 - 2026-07-25 =
* New: Model_Capabilities matrix — ordered rules (exact/prefix/regex/provider) replace ad-hoc model-name regexes for tools + reasoning.
* Fix: Future-proof gpt-5 / sol / o-series / reasoning models via filterable rules (agentic_model_capability_rules, agentic_model_capabilities).
* Fix: Google/OpenAI/Anthropic tool schema dialects driven by capabilities (additionalProperties, sanitize_callback, etc.).
* Hook: agentic_use_responses_api_for_tools (default off) for a future Responses API path.

= 3.2.3 - 2026-07-25 =
* Fix: gpt-5.x / reasoning models — set reasoning_effort=none when function tools are present (Chat Completions).
* Fix: Google Gemini tools — strip additionalProperties and sanitize_callback from functionDeclarations schemas.

= 3.2.2 - 2026-07-25 =
* Fix: Sanitize tool schemas for OpenAI/Gemini — strip top-level oneOf/anyOf/enum (fixes Invalid schema for function woocommerce__product_create and similar WP abilities).
* Fix: abilities.json declaration — honor agent.json tools list and WP ability name mapping (reduces "Not declared in abilities.json" blocks).

= 3.2.1 - 2026-07-25 =
* Fix: OpenAI/Google provider catalogs refresh; Google resp_format must be google (heals retired -exp/1.5 models).
* Fix: PHPCS alignment for OKF Knowledge Wiki.
* Include provider heal in release zip so installs get working Gemini/OpenAI defaults.

= 3.2.0 - 2026-07-25 =
* New: **Knowledge Wiki (Open Knowledge Format / OKF)** — free, local markdown knowledge base under Knowledge → Knowledge Wiki. Concepts use YAML frontmatter + body, progressive tools (`list_okf_concepts`, `read_okf_concept`, `search_okf`), export/import, and persona-text migration. No cloud, no credits.
* Product split: **hosted Vector Store / RAG** is a Pro upgrade surface in free (clear upgrade card, WP.org-friendly — free OKF remains fully usable). Vector train/query AJAX requires Agent Builder Pro.
* Improve: Knowledge admin UX (wiki browser + editor), prompt injects a compact OKF index; full concepts load on demand via tools.
* Docs: Knowledge Wiki and Vector Store guidance updated at agentic-plugin.com.

= 3.1.0 - 2026-07-24 =
* New: "Knowledge" step in the Train an Agent wizard — train new assistants on your pages, posts and files (Vector Store) during creation.
* New: Automatic Agent Updates toggle on the dashboard — opt in or out any time.
* Fix: newly created agents now auto-activate correctly (abilities manifest no longer flags the framework-global report_issue tool).
* Fix: hosted-chat requests always carry the licence key so usage is metered; unlicensed sites are guided to setup instead of running unmetered.
* Improve: Agents settings table layout, Account tab (removed duplicate licence key, clearer status), Tools table overflow, Approvals empty-state icon.

= 3.0.0 - 2026-06-30 =
* feature: **contextual AI launchers** across wp-admin — Agent Builder now surfaces relevant chat prompts on the Plugins screen, Media Library, Users list, Comments list, and Dashboard. Each launcher opens the chat overlay pre-seeded with a task-appropriate prompt (editable before sending, never auto-sent). Launchers are dismissible per-user and can be disabled globally under Deployment → Admin UI
* feature: **manage_cache** tool (Caching category, high-risk, disabled by default) — agents can flush the WordPress object cache, purge all transients, reset the opcode cache (opcache_reset), flush rewrite rules, and auto-detect and purge major page-cache plugins (WP Rocket, W3 Total Cache, WP Super Cache, LiteSpeed Cache, WP Fastest Cache, Cache Enabler, and more)
* improvement: **WordPress Abilities integrated into Tools** — the former separate "WP Abilities" section is removed; Inbound Abilities now appear as a category in the Tools hub with per-ability enable/disable toggles, and each tool row shows its published ability name inline
* fix: **WP Abilities outbound leak** — disabled tools were previously still published as WordPress Abilities (exposing them via MCP/REST/core AI). Per-tool Enabled toggles now properly gate ability registration and execution
* improvement: contextual launcher on the Plugins screen is capability-aware — shows a "Create a plugin with AI" entry only when Agent Builder Pro's Plugin Assistant is active, and always routes the health-check prompt to an agent that can actually audit plugins



= 2.17.0 - 2026-06-21 =
* improvement: the **Activity**, **Deployment**, **Knowledge**, **Approvals**, and **Tools** admin pages now use the same left vertical navigation as Settings, for a consistent, easier-to-scan layout across the whole plugin
* dev: new reusable `\Agentic\Admin_Vnav` helper renders the shared two-pane navigation shell, plus a shared `admin-vnav.js` search filter (removes the duplicated inline Settings filter script)

= 2.16.0 - 2026-06-21 =
* feature: new **Account** settings tab — see your current plan at a glance and which AI providers have an API key configured (shown as a connection status with a masked hint; keys are never displayed in full). Includes a quick link to manage providers and, on the free plugin, to explore Pro
* dev: Pro can extend the Account view via the new `agentic_after_account_tab` action (used to list MCP connector credentials)

= 2.15.0 - 2026-06-21 =
* feature: redesigned **Settings** with a left vertical navigation and a search box, so sections are easier to scan and find — mirroring the familiar layout of popular desktop AI apps
* feature: new **General** settings tab for global personalization that applies to every agent — set what agents should call administrators and frontend visitors, give all agents shared site context via "Instructions for Agents", and choose a global chat font and accent colour (with a link to Chat Themes for fine-grained control)
* improvement: global appearance (font + accent) now applies consistently across the admin chat, admin-bar overlay, and frontend chat surfaces
* dev: `Agent_Prompt_Builder` gains opt-in global instruction and addressing blocks (empty by default, no token cost); saving the General tab clears the response cache so new instructions take effect immediately

= 2.14.0 - 2026-06-14 =
* feature: new **Agent Creation Wizard** ("Train an Agent") — a guided, multi-step React flow that lets you create and activate a new AI agent in minutes: name & description, persona/instructions, provider/model, autonomy level, and optional tools, with a review step and follow-up links to add knowledge or start chatting
* feature: added a prominent "Train an Agent" quick action to the Dashboard
* dev: new admin-only REST endpoints `agentic/v1/agent-wizard/options` and `agentic/v1/agent-wizard/create`; agent creation is delegated to the existing audited create-agent tool (manifest + signed abilities + activation), and the wizard ships as a third @wordpress/scripts build entry

= 2.13.0 - 2026-06-14 =
* feature: Settings tabs are now organized into labelled groups — Basic, Security & limits, and Advanced — so common options are easier to find and developer-only tabs (APIs, Endpoints) stay grouped under Advanced, shown only in Advanced mode
* improvement: every Settings tab remains reachable by direct URL, and Pro-added tabs (such as License and Health) are placed in the appropriate group automatically

= 2.12.0 - 2026-06-14 =
* feature: the dashboard "Weekly Statistics" card is now a live React "Activity" card — it refreshes automatically and adds a Week/Month period toggle, with the server-rendered version kept as a fallback
* feature: new admin-only REST endpoint `agentic/v1/dashboard-stats` returns live activity metrics (actions, tokens, estimated cost) plus the ecosystem agent counts
* dev: added a second @wordpress/scripts build entry (dashboard-activity); the rest of the dashboard remains server-rendered

= 2.11.0 - 2026-06-14 =
* feature: introduced a React-powered Settings → Interface panel (built with @wordpress/scripts) where you can switch between Basic and Advanced interface modes and toggle the dashboard Getting Started checklist
* dev: added a Node/JSX build pipeline (package.json, webpack config, src/) whose compiled output ships in build/; the release scripts now build assets automatically and exclude JS sources from the distributed package
= 2.10.0 - 2026-06-14 =
* ux: streamlined the admin navigation — renamed Deploy to Publish and Logs to Activity, and grouped advanced developer tools (Skills, APIs, Endpoints) behind a new Basic/Advanced interface toggle so the menu stays approachable for non-technical users
* ux: redesigned the Dashboard — replaced the recent-activity preview with a Getting Started checklist, surfaced estimated usage, and trimmed quick actions to the most common tasks
= 2.9.272 - 2026-06-07 =
* compat: centralised all WordPress Abilities API calls behind a single guarded adapter so the plugin passes WordPress.org Plugin Check cleanly while still supporting WordPress 6.4+
* compat: queries in the local memory engine now use the `%i` identifier placeholder instead of interpolating the table name
* compat: replaced array_is_list() with an equivalent helper, added a missing ABSPATH guard to the validate_agent_code tool, and bumped "Tested up to" to 7.0

= 2.9.271 - 2026-06-07 =
* feature: multi-agent orchestration — team-lead agents can delegate subtasks to other active agents (sequential, in-process) with depth, fan-out and cost/token budgets
* feature: local conversational memory (opt-in) — agents can recall a short, relevant excerpt of a user's earlier messages, stored entirely on your own server (Settings → Security → Local Memory)
* feature: WordPress Abilities readiness banner and an outbound abilities list under Tools → WP Abilities, with guidance for sites not yet on WordPress 6.9/7
* feature: forward-compatible WordPress core AI provider adapter (dormant until a native AI runtime is available)
* feature: four vertical quick-start agent templates — Support Triage, SEO Optimizer, Site Health Sentinel, and an Editorial Director team lead that delegates to your specialist agents (one-click activate from the Agents screen)
* privacy: local memory honours the Chat History Retention window and is removed on personal-data erasure
* privacy: local memory is scoped to logged-in users only, so it is never shared between anonymous visitors, and recalled excerpts are framed as untrusted data
* perf: added a (memory_type, created_at) index so memory retention cleanup stays fast on large sites
* ux: replaced all blocking browser alert()/confirm() dialogs with accessible, non-blocking toast notifications and confirmation modals (keyboard focus trap, Esc to cancel, screen-reader roles) across the admin and chat UI

= 2.9.270 - 2026-06-07 =
* Release 2.9.270

= 2.9.269 - 2026-06-07 =
* Release 2.9.269

= 2.9.268 - 2026-06-07 =
* Release 2.9.268

= 2.9.267 - 2026-06-07 =
* Release 2.9.267

= 2.9.266 - 2026-06-07 =
* Release 2.9.266

= 2.9.265 - 2026-06-07 =
* Release 2.9.265

= 2.9.264 - 2026-06-05 =
* Release 2.9.264

= 2.9.195 - 2026-05-07 =
* Release 2.9.195



= 2.9.194 - 2026-05-07 =
* fix: SSE streaming now works correctly with tool calls and cache-hit responses
* fix: dashboard audit-log links corrected to the right page slug
* fix: Usage and Agent Health dashboard buttons removed from free build (WP.org guideline 5 compliance)
* fix: welcome message and agent shortcuts now reflect only active agents
* improvement: dashboard "Tools & Channels" renamed to "Tools"; Skills quick-action button added
* improvement: chat UI cleanup and message action bar (copy, thumbs, regenerate)

= 2.9.154 - 2026-05-04 =
* Release 2.9.154



= 2.9.145 - 2026-05-03 =
* feat: Agent delegation — context-switch buttons let you hand off to any installed agent mid-conversation

= 2.9.141 - 2026-05-01 =
* feat: SSE streaming for chat — agent responses now stream in real time instead of waiting for the full reply
* refactor: Agent_Prompt_Builder extracted to a dedicated class; unused Plugin methods removed

= 2.9.108 - 2026-04-30 =
* feat: 42 new tools and 8 new skills (content planning, web browsing, translation, data analysis, PDF/DOCX, XLSX)
* fix: TTS word-by-word sync with audio playback;

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

== Legal ==

By using this plugin you agree to our [Terms of Service](https://agentic-plugin.com/terms-of-service/) and [Privacy Policy](https://agentic-plugin.com/privacy-policy/)