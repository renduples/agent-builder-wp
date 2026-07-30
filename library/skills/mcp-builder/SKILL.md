---
name: mcp-builder
description: "Guide the user through building a Model Context Protocol (MCP) server. Use when the user wants to expose WordPress data or functionality to AI tools via MCP, build a custom MCP server in Python or TypeScript, create MCP tools that wrap WordPress REST API endpoints, or integrate WordPress with Claude Desktop or other MCP clients. Trigger when the user mentions 'MCP', 'Model Context Protocol', 'MCP server', 'Claude Desktop', or 'expose WordPress to AI'."
---

# MCP Server Builder Skill

## What You're Building

An MCP server is a small program that exposes **tools** (callable functions), **resources** (readable data), and optionally **prompts** (reusable templates) to any MCP-compatible AI client (Claude Desktop, Cursor, Claude Code, etc.).

For WordPress, MCP servers are ideal for:
- Exposing posts, pages, and custom post types to Claude Desktop
- Wrapping WooCommerce order and product APIs
- Reading plugin settings or site analytics
- Triggering WordPress actions (publish post, send email, clear cache) from an AI tool

## Phase 1: Plan Before You Code

Answer these before writing a line:

1. **What data or actions does this server expose?** List 5–10 specific tools (e.g. `get_posts`, `create_post`, `get_woocommerce_orders`).
2. **Read-only or read-write?** Read-only servers are safe to run anywhere. Write operations need explicit confirmation in the tool description and should be marked with `destructiveHint: true`.
3. **Authentication?** WordPress REST API requires either a cookie+nonce (for same-origin) or Application Password (for remote). Most MCP servers use Application Passwords.
4. **Transport?** `stdio` for local (Claude Desktop config), `streamable HTTP` for remote/multi-user.

## Phase 2: Implementation

### Python (FastMCP — recommended for quick builds)

```python
from mcp.server.fastmcp import FastMCP
import httpx

mcp = FastMCP("WordPress")

WP_URL   = "https://your-site.com/wp-json/wp/v2"
WP_USER  = "admin"
WP_PASS  = "xxxx xxxx xxxx xxxx xxxx xxxx"  # Application Password

def wp_client() -> httpx.Client:
    return httpx.Client(auth=(WP_USER, WP_PASS), timeout=15)

@mcp.tool()
def get_posts(status: str = "publish", per_page: int = 10) -> list[dict]:
    """Get recent WordPress posts."""
    with wp_client() as client:
        r = client.get(f"{WP_URL}/posts", params={"status": status, "per_page": per_page})
        r.raise_for_status()
        return [{"id": p["id"], "title": p["title"]["rendered"], "status": p["status"],
                 "date": p["date"], "link": p["link"]} for p in r.json()]

@mcp.tool()
def create_post(title: str, content: str, status: str = "draft") -> dict:
    """Create a new WordPress post. Returns the new post ID and link."""
    with wp_client() as client:
        r = client.post(f"{WP_URL}/posts",
                        json={"title": title, "content": content, "status": status})
        r.raise_for_status()
        p = r.json()
        return {"id": p["id"], "link": p["link"], "status": p["status"]}

if __name__ == "__main__":
    mcp.run()
```

**Run:** `python server.py` (stdio transport, for Claude Desktop)

### TypeScript (MCP SDK — better for production/remote)

```typescript
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";

const server = new McpServer({ name: "WordPress", version: "1.0.0" });

const WP = { url: "https://your-site.com/wp-json/wp/v2", user: "admin", pass: "xxxx xxxx" };
const auth = "Basic " + Buffer.from(`${WP.user}:${WP.pass}`).toString("base64");

server.tool("get_posts",
  { status: z.string().default("publish"), per_page: z.number().max(100).default(10) },
  async ({ status, per_page }) => {
    const r = await fetch(`${WP.url}/posts?status=${status}&per_page=${per_page}`,
      { headers: { Authorization: auth } });
    const posts = await r.json();
    return { content: [{ type: "text", text: JSON.stringify(posts, null, 2) }] };
  }
);

const transport = new StdioServerTransport();
await server.connect(transport);
```

**Install:** `npm install @modelcontextprotocol/sdk zod`
**Run:** `npx ts-node server.ts`

## Phase 3: Claude Desktop Configuration

Add to `~/Library/Application Support/Claude/claude_desktop_config.json` (macOS):

```json
{
  "mcpServers": {
    "wordpress": {
      "command": "python",
      "args": ["/path/to/server.py"]
    }
  }
}
```

For TypeScript: `"command": "npx", "args": ["ts-node", "/path/to/server.ts"]`

Restart Claude Desktop after editing the config.

## WordPress Application Password Setup

1. WordPress admin → Users → Your profile
2. Scroll to **Application Passwords**
3. Enter a name (e.g. "Claude MCP") and click **Add New Application Password**
4. Copy the generated password (shown once) — format: `xxxx xxxx xxxx xxxx xxxx xxxx`
5. Use this with your WordPress username for Basic Auth

## Tool Design Rules

- **One tool, one job** — `get_post_by_id` not `get_post_or_page_or_product`.
- **Return only what the AI needs** — strip `rendered` HTML wrappers, keep IDs, titles, dates, links.
- **Mark destructive tools** — add `"destructiveHint": true` in tool annotations and say so in the description.
- **Paginate large datasets** — always accept `per_page` and `page` params; never return more than 100 items.
- **Fail gracefully** — return a descriptive error string, never raise unhandled exceptions.

## Common WordPress MCP Tool Patterns

```
Posts:          get_posts, get_post, create_post, update_post, delete_post
Pages:          get_pages, get_page, create_page
Media:          get_media, get_media_item
Taxonomies:     get_categories, get_tags
Users:          get_users, get_current_user
WooCommerce:    get_orders, get_products, get_customers (use /wc/v3/ base)
Settings:       get_site_info (from /wp-json/ root)
Search:         search_content (WP /wp/v2/search endpoint)
```
