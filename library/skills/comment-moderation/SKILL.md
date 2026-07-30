---
name: comment-moderation
description: "Moderate WordPress comments: review pending comments, approve or reject, mark spam, reply on behalf of the site, and manage the comment queue. Use when the user asks to check comments, approve or reject a comment, reply to a commenter, clean up spam, or review the moderation queue. Trigger when the user mentions 'pending comments', 'comment approval', 'spam comments', or asks to respond to a reader. Do NOT trigger for WooCommerce product reviews."
---

# Comment Moderation Skill

## Available Tools

| Tool | When to use |
|---|---|
| `list_comments` | Fetch the comment queue filtered by status, post, or search term. |
| `moderate_comment` | Approve, hold, mark as spam, or trash a comment. |
| `reply_to_comment` | Post an admin reply to a comment. |

## Workflows

### Review the pending queue

1. Call `list_comments` with `status: "hold"` (default).
2. Present each comment: author, content excerpt, date, and post title.
3. For each comment, suggest an action: approve, hold, spam, or trash.
4. Wait for the user to confirm before calling `moderate_comment`.

### Bulk moderate

1. Call `list_comments` with the relevant status and `limit: 50`.
2. Group comments by suggested action: approve (genuine), spam (mass marketing, links), trash (duplicates, off-topic).
3. Confirm the groupings with the user.
4. Call `moderate_comment` for each comment — one call per comment.

### Reply to a commenter

1. Call `list_comments` or identify the comment ID from context.
2. Draft a reply — show it to the user before posting.
3. On approval, call `reply_to_comment` with the comment ID and content.
4. Note: the reply is posted as the site admin and published immediately.

### Clean up spam

1. Call `list_comments` with `status: "spam"` to review existing spam.
2. Verify the comments are correctly classified.
3. For any spam still in "hold", call `moderate_comment` with `action: "spam"`.
4. Trash cannot be emptied via the API — direct the user to wp-admin → Comments for bulk deletion.

### Find comments on a specific post

1. Call `list_comments` with `post_id` set to the post ID and `status: "all"`.
2. Review the full comment thread for that post.

## Quality Rules

- **Always show the comment before moderating** — never approve or trash without presenting the content.
- **Spam signals** — URLs in author name/website, generic compliments with unrelated links, identical repeated content.
- **Approve signals** — specific reference to post content, genuine question or feedback, no promotional links.
- **Always draft replies for user approval** — call `reply_to_comment` only after the user has reviewed the reply text.
- **Report `total` always** — tell the user how many pending comments exist, not just the ones returned.
- **One moderation per call** — `moderate_comment` takes one ID; don't attempt to batch in a single call.
