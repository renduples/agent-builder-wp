---
name: post-admin
description: "Use when user wants to duplicate, copy, or clone a post; change or switch post type; generate a preview link; set a featured image automatically; view or restore revisions; or clean up old revisions."
---

# Post Admin Skill

## Available Tools

| Tool | When to use |
|------|-------------|
| `duplicate_post` | Clone a post, page, or CPT — copies all meta and terms, defaults to draft status. |
| `switch_post_type` | Change a post from one post type to another (e.g. post → page). |
| `get_public_preview_url` | Generate a shareable preview link for a draft or private post. |
| `set_auto_featured_image` | Automatically pull the first image from post content and set it as the featured image. |
| `find_posts_without_featured_image` | Audit which published posts are missing a featured image. |
| `get_post_revisions` | List saved revisions for a post with dates and authors. |
| `restore_revision` | Roll a post back to an earlier revision. |
| `cleanup_post_revisions` | Delete old revisions beyond a keep limit to reduce database size. |
| `get_revision_diff` | Compare two revisions side-by-side to see what changed. |

## Workflows

### Duplicate a post for a new campaign

1. Ask the user for the post ID (or title) to duplicate.
2. Call `duplicate_post` with `post_id` and an optional `new_title`.
3. Return the new post ID, title, and edit URL.

### Set featured images in bulk

1. Call `find_posts_without_featured_image` to get the list.
2. For each post, call `set_auto_featured_image` with `overwrite: false`.
3. Report how many were updated and how many were sideloaded vs. matched from existing media.

### Restore a post to an earlier version

1. Call `get_post_revisions` to list available revisions.
2. Present the revision list to the user and ask which one to restore.
3. Optionally call `get_revision_diff` to preview changes.
4. Call `restore_revision` with the chosen revision ID.

### Clean up revision bloat

1. Call `cleanup_post_revisions` with `dry_run: true` first to show the count.
2. Confirm with the user.
3. Call again with `dry_run: false` and the desired `keep_latest` count.

## Rules

- Always confirm before calling `restore_revision` — it overwrites the live post content.
- When duplicating, remind the user the new post is in draft status and needs to be reviewed before publishing.
- For `switch_post_type`, warn that switching a post's type may affect its visibility and URL structure.
- `cleanup_post_revisions` is marked destructive — run dry_run first if the user hasn't specified a count.
