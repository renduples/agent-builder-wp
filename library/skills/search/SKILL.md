---
name: search
description: "Search WordPress content, media, and posts by keyword. Use when the user asks to find posts, pages, or media files by topic, keyword, or phrase — for example 'find posts about pricing', 'search for images of the logo', 'which pages mention returns policy', or 'look up everything about onboarding'. Also trigger when the user references the site's search bar or asks to find something without knowing its post ID. Do NOT trigger for WooCommerce product searches (use wc_search_products instead) or for external web searches."
---

# WordPress Search Skill

## Available Tools

| Tool | When to use |
|---|---|
| `search_content` | Full-text search across posts, pages, and custom post types by keyword. Best first step for finding any content. |
| `search_media_library` | Search media files (images, PDFs, videos) by filename, title, MIME type, or upload date. |
| `list_posts` | Browse posts or pages by status or type when no keyword is given. |
| `get_post_content` | Fetch the full content of a specific post once you have its ID. |

## Workflows

### Finding a post or page by topic

1. Call `search_content` with the user's keyword as `query`.
2. Set `post_type` to `"any"` unless the user specifies posts, pages, or a custom type.
3. Present the top results: title, URL, excerpt, and date.
4. If the user wants to read or edit one, call `get_post_content` with its ID.

### Searching across a specific post type

1. Ask the user which type if unclear — post, page, or a named custom type.
2. Call `search_content` with `post_type` set to that slug.
3. If no results, retry with `post_type: "any"` and note the expanded scope.

### Finding media files

1. Call `search_media_library` with the `keyword` (filename, alt text, or caption term).
2. Add `mime_type` to narrow by file category: `"image"`, `"application/pdf"`, `"video"`.
3. Add `date_after` / `date_before` (YYYY-MM-DD) to filter by upload date.
4. Return `id`, `title`, `url`, and `mime_type` for each result.

### Paginating large result sets

1. First call uses default `limit` (10) and `offset` 0.
2. If `total_found` exceeds `total_returned`, offer to fetch the next page.
3. Next call: same params, `offset` += `limit`.

## Quality Rules

- **Always report `total_found`**, not just the items returned — the user needs to know if there are more results.
- **Default to `status: "publish"`** for `search_content` unless the user asks to include drafts.
- **Use `post_type: "any"`** unless the user specifies a type — content often lives in unexpected post types.
- **For media searches with no keyword**, use `search_media_library` with just `mime_type` to list all files of that format.
- **Never guess a post ID** — always use `search_content` first to find it.
- **Relevance order is automatic** — `search_content` uses WordPress relevance ranking, so the best match is first.
