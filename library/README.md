# Agentic Library – 10 Pre-Built Agents

> Ready-to-use AI agents that solve real WordPress problems.

---

## What's Inside

**10 production-ready agents** ship with Agent Builder. Each agent is fully functional out of the box and can be customised for your needs.

---

## Agents

### Assistant Trainer (`assistant-trainer/`)
Meta-agent that creates new AI agents from natural language descriptions.
- Analyses requirements and generates agent scaffolding
- Creates tool schemas and system prompts
- Validates agent code
- Detects duplicate agents

**Category:** Developer

---

### WordPress Assistant (`wordpress-assistant/`)
Your guide to WordPress and the AI ecosystem. Answers questions about the plugin and helps new users get started.
- Site overview and health checks
- File structure and custom post type analysis
- Search intent analysis
- Content listing and inspection

**Category:** Starter

---

### Content Writer (`content-writer/`)
Creates, edits, and publishes posts and pages. Drafts from a description, rewrites for clarity, and optimises titles, excerpts, and structure.
- Drafts posts from outlines or descriptions
- Rewrites for readability and tone
- Manages categories, tags, and featured images
- Analyses post performance

**Category:** Content

---

### Theme Assistant (`theme-assistant/`)
Helps you choose and customise WordPress themes. Detects your active theme, reads global styles, recommends themes, and guides Site Editor usage.
- Reads theme details and global styles
- Searches the WordPress theme directory
- Checks theme status and compatibility

**Category:** Design

---

### Plugin Assistant (`plugin-assistant/`)
Creates complete WordPress plugins from natural language descriptions. Generates WPCS-compliant code with security best practices.
- Scaffolds plugins from requirements
- Generates custom post types and taxonomies
- Follows WordPress coding standards

**Category:** Developer

---

### SEO Assistant (`seo-assistant/`)
Audits posts and pages for SEO health. Fixes meta titles, descriptions, headings, and keyword usage so your content ranks higher.
- Analyses on-page SEO per post
- Fixes internal links and orphan pages
- Adds FAQ schema markup
- Optimises titles and alt text

**Category:** SEO

---

### AI Radar (`ai-radar/`)
Scan your site for AI visibility. Checks AI crawler access, schema markup, content structure, and technical readiness — then fixes what it can.
- Scans robots.txt and AI-specific headers
- Checks schema markup and content structure
- Generates and updates `robots.txt` and `llms.txt`
- Simulates AI crawler access

**Category:** SEO

---

### Site Doctor (`site-doctor/`)
Diagnoses your site's health. Finds broken links, database bloat, orphaned content, outdated plugins, and PHP errors.
- Database health and autoload impact checks
- Broken link detection
- Core integrity verification
- Core Web Vitals and caching status

**Category:** Maintenance

---

### Security Assistant (`security-assistant/`)
Monitors your site for security threats. Checks failed logins, outdated plugins, suspicious user accounts, and recently modified files.
- Failed login monitoring
- Plugin update checks
- Privileged user auditing
- File modification detection and core integrity verification

**Category:** Security

---

### Media Assistant (`media-assistant/`)
Generate, edit, and manage images in your WordPress media library using AI. Powered by Agentic Image Generation (Vertex AI Imagen 4).
- AI image generation, editing, and upscaling
- Media library search
- Credit balance checking
- Featured image and alt text management

**Category:** Media

---

## Quick Start

1. **Activate in WordPress:**
   - Go to **Agentic → Agents**
   - All 10 agents appear automatically
   - Click **Activate** on any agent

2. **Start using:**
   - Open the chat interface
   - Select an agent and start typing

### Example Usage

**Content Writer:**
> "Draft a blog post about WordPress security best practices"

**SEO Assistant:**
> "Audit my latest 5 posts for SEO issues and fix what you can"

**Site Doctor:**
> "Run a full health check on my site"

---

## Customising Agents

Each agent is open-source and fully customisable. See the [Developer Documentation](https://agentic-plugin.com/documentation/#doc-developers) for a complete guide to building and customising agents.

---

## Need Help?

- **Docs** – [agentic-plugin.com/documentation](https://agentic-plugin.com/documentation/)

---

Built by the Agentic community.
