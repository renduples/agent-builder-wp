---
name: web-browsing
description: "Fetch and read external web pages, APIs, RSS feeds, or any public URL. Use when the user asks to visit a website, check a URL, read an article, fetch JSON from an API, monitor a competitor page, or pull in external data. Also trigger when the user pastes a URL and asks what's on it, or asks to check if a link is working. Do NOT trigger for searching the WordPress site itself (use search_content) or for fetching WordPress admin URLs."
---

# Web Browsing Skill

## Available Tools

| Tool | When to use |
|---|---|
| `fetch_url` | Retrieve the text content of any public URL. Supports web pages, JSON APIs, RSS feeds, and sitemaps. |
| `search_content` | Search content within this WordPress site — not for external URLs. |

## Workflows

### Reading a web page

1. Call `fetch_url` with the URL and `format: "text"`.
2. The response includes `title`, `text` (HTML stripped), and `truncated` flag.
3. If `truncated` is true and more content is needed, increase `max_chars` (up to 40000).
4. Summarise or extract the requested information from the `text`.

### Fetching a JSON API

1. Call `fetch_url` with `format: "json"` and any required `headers` (e.g. `{"Authorization": "Bearer token"}`).
2. The response includes `data` as a formatted JSON string.
3. Parse the structure and return the relevant fields to the user.

### Reading an RSS feed

1. Call `fetch_url` with the feed URL and `format: "text"` (RSS is XML, text strip works well).
2. For structured parsing, use `format: "html"` to get the raw XML and extract `<title>` and `<link>` elements.

### Checking if a URL is live

1. Call `fetch_url` with the URL.
2. Check `status_code` — 200 means live, 404 means not found, 301/302 means redirected.
3. Report the status and the page title.

## Quality Rules

- **Always check `status_code`** — a 4xx/5xx response means the page is unavailable; tell the user.
- **Respect `truncated: true`** — if the response was cut off, say so and offer to fetch more with a higher `max_chars`.
- **Do not fetch internal WordPress URLs** — use `get_post_content`, `search_content`, or other WordPress tools instead.
- **Never fetch URLs containing credentials** — strip API keys or tokens from any URLs you show the user.
- **Private/local addresses are blocked** — `fetch_url` will return an error for localhost or RFC1918 addresses; explain this to the user.
