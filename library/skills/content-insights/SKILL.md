---
name: content-insights
description: "Use when user wants to analyse content quality, find reading time or word count, identify stale or outdated posts, find duplicate titles, see writing statistics, or find posts missing excerpts."
---

# Content Insights Skill

## Available Tools

| Tool | When to use |
|------|-------------|
| `get_post_reading_time` | Calculate estimated reading time and word count for a specific post. |
| `find_duplicate_titles` | Find published posts that share the same title (SEO risk). |
| `get_stale_posts` | Find posts that haven't been updated in N months. |
| `get_site_writing_stats` | Aggregate overview: posts by type, top authors, monthly cadence, avg word count. |
| `find_posts_with_no_excerpt` | Find posts missing a manual excerpt (important for SEO meta descriptions). |

## Workflows

### Content quality audit

1. Call `get_site_writing_stats` for a high-level overview.
2. Call `find_duplicate_titles` to identify SEO title conflicts.
3. Call `find_posts_with_no_excerpt` to find posts needing meta descriptions.
4. Call `get_stale_posts` with `months: 12` to find outdated content.
5. Present a prioritised action list.

### Pre-publish content check

1. Call `get_post_reading_time` for the post to estimate reader commitment.
2. Flag if word count is very low (under 300) or very high (over 3000) for the content type.

### Find stale content for a refresh campaign

1. Call `get_stale_posts` with the desired `months` threshold.
2. Sort by `months_stale` descending to prioritise the oldest content first.
3. Present the list with permalinks so the user can quickly open and update them.

## Rules

- Reading time uses 200 words per minute — a conservative average suitable for technical or detailed content.
- `find_duplicate_titles` checks only post_title, not post_name (slug) or SEO meta titles — those may differ.
- Stale posts are identified by `post_modified`, not `post_date`. A post updated recently won't appear even if it's old.
- `get_site_writing_stats` samples 50 random posts for average word count — treat it as an estimate, not an exact figure.
