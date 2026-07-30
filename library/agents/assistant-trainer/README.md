# 🏗️ Assistant Trainer

> Meta-agent that trains new AI assistants from natural language descriptions.

| Field | Value |
|-------|-------|
| Slug | `assistant-trainer` |
| Version | 1.1.0 |
| Category | Developer |
| Author | Agentic Community |
| Required Capabilities | `manage_options` |

## Tools

| Tool | Description |
|------|-------------|
| `analyze_requirements` | Analyze a natural language description and return a structured agent design specification |
| `generate_agent` | Generate complete agent PHP code from a specification |
| `validate_agent_code` | Validate agent PHP code for syntax and compliance |
| `create_agent_files` | Create agent files in the library directory |
| `list_library_agents` | List all agents in the library for reference |
| `read_agent_source` | Read the source code of an existing agent for reference |
| `get_agent_template` | Get a blank agent template with all required components |
| `generate_tool_schema` | Generate OpenAI function-calling schema for a tool from a structured specification |
| `generate_system_prompt` | Generate a system prompt for an agent given its purpose |
| `delete_agent` | Delete an agent from the library (use with caution) |
| `generate_agent_documentation` | Generate a comprehensive README.md for an agent by reading its metadata: name, description, category, tools with parameter schemas, system prompt summary, capabilities, scheduled tasks, event listeners, suggested prompts, and WP-CLI usage examples. Returns markdown content ready to write to disk. |
| `detect_agent_duplicates` | Compare a proposed agent specification against all existing agents in the library. Returns a similarity report with scores for name, description, category, and tool overlap. Use before creating a new agent to prevent duplicates or identify agents that could be extended instead. |

### Tool Details

#### `analyze_requirements`

Analyze a natural language description and return a structured agent design specification

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `description` | string | Yes | Natural language description of what the agent should do |
| `category` | string | No | Agent category (optional, will be inferred if not provided) |

#### `generate_agent`

Generate complete agent PHP code from a specification

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | string | Yes | Agent display name (e.g., "Backup Manager") |
| `slug` | string | Yes | Agent slug/ID (e.g., "backup-manager") |
| `description` | string | Yes | Brief description (max 160 chars) |
| `category` | string | Yes | Agent category |
| `icon` | string | No | Emoji icon for the agent |
| `capabilities` | array | No | Required WordPress capabilities |
| `tools` | array | Yes | Array of tool definitions |
| `system_prompt_focus` | string | No | Key areas of expertise for the system prompt |
| `suggested_prompts` | array | No | Example prompts for users (4 max) |

#### `validate_agent_code`

Validate agent PHP code for syntax and compliance

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `code` | string | Yes | PHP code to validate |

#### `create_agent_files`

Create agent files in the library directory

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `slug` | string | Yes | Agent slug (directory name) |
| `agent_code` | string | Yes | Complete agent.php content |
| `readme_content` | string | No | README.md content (optional) |
| `overwrite` | boolean | No | Overwrite if exists (default: false) |

#### `list_library_agents`

List all agents in the library for reference

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `include_code_sample` | boolean | No | Include code snippets from each agent |

#### `read_agent_source`

Read the source code of an existing agent for reference

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `agent_slug` | string | Yes | Agent slug to read (e.g., "content-assistant") |

#### `get_agent_template`

Get a blank agent template with all required components

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `minimal` | boolean | No | Return minimal template vs full template with examples |

#### `generate_tool_schema`

Generate OpenAI function-calling schema for a tool from a structured specification

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `tool_name` | string | Yes | Tool function name (snake_case) |
| `tool_description` | string | Yes | What the tool does |
| `parameters` | array | No | Array of parameter definitions |

#### `generate_system_prompt`

Generate a system prompt for an agent given its purpose

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `agent_name` | string | Yes | Agent name |
| `purpose` | string | Yes | What the agent does and its expertise areas |
| `personality` | string | No | Agent personality traits (helpful, strict, casual, etc.) |
| `constraints` | array | No | Things the agent should NOT do |
| `tool_names` | array | No | Names of tools available to the agent |

#### `delete_agent`

Delete an agent from the library (use with caution)

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `slug` | string | Yes | Agent slug to delete |
| `confirm` | boolean | Yes | Must be true to confirm deletion |

#### `generate_agent_documentation`

Generate a comprehensive README.md for an agent by reading its metadata: name, description, category, tools with parameter schemas, system prompt summary, capabilities, scheduled tasks, event listeners, suggested prompts, and WP-CLI usage examples. Returns markdown content ready to write to disk.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `agent_slug` | string | Yes | Slug of the agent to document (e.g., "content-writer") |
| `include_examples` | boolean | No | Include sample interaction examples (default: true) |

#### `detect_agent_duplicates`

Compare a proposed agent specification against all existing agents in the library. Returns a similarity report with scores for name, description, category, and tool overlap. Use before creating a new agent to prevent duplicates or identify agents that could be extended instead.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | string | Yes | Proposed agent name |
| `slug` | string | No | Proposed agent slug |
| `description` | string | Yes | Proposed agent description |
| `category` | string | No | Proposed agent category |
| `tool_names` | array | No | Proposed tool names for the new agent |
| `threshold` | integer | No | Similarity threshold 0-100 for flagging (default: 50) |

## Usage

### Chat

When you open a chat with Assistant Trainer, you'll see:

> I train new AI assistants for WordPress!

Tell me what kind of assistant you need and I'll:
- Generate compliant code
- Design its tools and capabilities
- Help test and write documentation

What assistant would you like to build?

### Example Prompts

- "Create an assistant that writes a new blog post every Monday morning about trending topics in my niche"
- "Build a customer support assistant that answers product questions from my WooCommerce store"
- "I need an assistant that checks for broken links weekly and emails me a report"
- "Design an assistant that optimises images in my media library and compresses anything over 500KB"
- "Show me all the assistants that already exist so I don't build a duplicate"
- "Create a maintenance assistant that cleans up post revisions and spam comments every Sunday night"
- "Read the source code of the SEO assistant — I want to see how it handles schema markup"
- "Generate full documentation for the content-writer agent"

### WP-CLI

```bash
# Interactive prompt
wp agent prompt assistant-trainer "Your message here"

# View agent info
wp agent info assistant-trainer

# List available tools
wp agent tools assistant-trainer
```

## Example Interactions

**User:** Create an assistant that manages WordPress backups

**Assistant Trainer:** _(The assistant will use its tools to handle this request)_

**User:** Build an image optimization assistant for media library

**Assistant Trainer:** _(The assistant will use its tools to handle this request)_

**User:** Design an assistant for managing redirects

**Assistant Trainer:** _(The assistant will use its tools to handle this request)_

---

_Generated by Assistant Trainer — Agent Builder for WordPress_
