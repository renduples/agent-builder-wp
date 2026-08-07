---
name: wp-content-authoring
description: "Use this skill whenever the user wants to create, edit, publish, schedule, duplicate, or SEO-optimize a WordPress post or page. Trigger on requests like 'write a new post about X', 'update this page', 'schedule this for Friday', 'improve the SEO on post 123', 'duplicate this page as a template', or 'what post types does this site have'. Do NOT trigger for bulk content audits (freshness, orphaned pages, missing excerpts) or for WooCommerce products — those are separate domains."
allowed-tools: list_posts get_post_content create_post_content update_post_content analyze_post_seo update_post_seo schedule_post duplicate_post switch_post_type list_registered_post_types get_custom_post_types_summary get_post_revisions cleanup_post_revisions
---

# WordPress Content Authoring

## Available Tools

| Tool | When to use |
|---|---|
| `list_posts` | Find existing content by status, type, or search term before fetching or editing it. |
| `get_post_content` | Full content, metadata, categories, tags for one post/page. **Always call before editing** — never assume current content. |
| `create_post_content` | Create a new post/page. Lands as **draft** by default. |
| `update_post_content` | Edit an existing post/page. Only supplied fields change — omitted fields are untouched. |
| `analyze_post_seo` | Full SEO audit (score, title/meta length, headings, keyword density, link counts, alt-text coverage) with specific recommendations. Run before `update_post_seo`. |
| `update_post_seo` | Change title, meta description, slug, or focus keyword. |
| `schedule_post` | Schedule a draft (or reschedule an already-scheduled post) for future publication. Post must be draft/scheduled already — not for publishing immediately. |
| `duplicate_post` | Clone a post/page/CPT including postmeta and taxonomy terms, as a new draft. Good for using an existing page as a template. |
| `switch_post_type` | Change a post's type to a different registered post type. |
| `list_registered_post_types` | See what post types exist on the site (with counts) before assuming "post" or "page" is right. |
| `get_custom_post_types_summary` | Detail on custom post types/taxonomies beyond WP defaults. |
| `get_post_revisions` | Check revision history before deciding whether to restore an earlier version. |
| `cleanup_post_revisions` | Prune old revisions, keeping the most recent N. Supports `dry_run` — use it first on any multi-post cleanup. |

## Workflows

### Writing a new post or page
1. If the post type isn't obviously "post" or "page" (e.g. the site has a custom "case-study" type), call `list_registered_post_types` or `get_custom_post_types_summary` first rather than assuming.
2. Call `create_post_content` — it saves as draft. Never pass a publish status without the user explicitly asking for it.
3. Once drafted, offer (don't assume) an SEO pass: `analyze_post_seo` → discuss findings → `update_post_seo` only with approval.

### Editing existing content
1. Call `get_post_content` first — always. Editing from a guessed or remembered version of the content risks silently reverting other changes.
2. Call `update_post_content` with only the fields that actually change.
3. If the edit is significant, mention that `get_post_revisions` exists in case the user wants to compare against the previous version later.

### SEO review
1. Call `analyze_post_seo` and lead with the score and the 2–3 highest-impact recommendations, not the full list.
2. Get explicit approval before calling `update_post_seo` — never silently rewrite a title or slug (slug changes can break existing links).

### Scheduling
1. Confirm the post is currently draft (or already scheduled) — `schedule_post` doesn't work on published posts.
2. Confirm the exact date/time and timezone with the user before calling; restate it back after scheduling succeeds.

### Using a page as a template
1. `duplicate_post` the source page — it comes back as a new draft titled "Copy of {original}".
2. `update_post_content` to adjust title/content, then treat it like any new draft (see "Writing a new post or page" for the SEO/publish flow).

### Revision cleanup
1. Always run `cleanup_post_revisions` with `dry_run: true` first, especially for a site-wide cleanup, and report the count before asking whether to proceed for real.

## Quality Rules

- **Never publish without explicit approval.** `create_post_content`/`duplicate_post` default to draft for exactly this reason — don't override that default speculatively.
- **Read before you write.** `get_post_content` before any `update_post_content` or `update_post_seo` call, no exceptions — partial-field updates make it easy to silently clobber content you never looked at.
- **Slugs are links.** Changing a post's slug via `update_post_seo` breaks any existing external links/bookmarks to it — flag this explicitly when proposing a slug change, don't just do it.
- **Dry-run destructive bulk actions.** `cleanup_post_revisions` across "all posts" always gets a `dry_run: true` pass and a reported count before the real run.
