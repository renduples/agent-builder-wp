---
name: content-planning
description: "Plan, schedule, and manage the WordPress content publishing calendar. Use when the user asks to see what's scheduled, reschedule a post, plan upcoming content, find publishing gaps, move a draft to a publish date, or build a content calendar. Trigger when the user mentions 'schedule', 'publish date', 'content calendar', 'drafts', 'upcoming posts', or asks when something will go live. Do NOT trigger for editing post content — use update_post_content for that."
---

# Content Planning Skill

## Available Tools

| Tool | When to use |
|---|---|
| `get_schedule` | View scheduled posts, recent drafts, and publishing gaps. |
| `schedule_post` | Set or change the publish date on a draft or scheduled post. |
| `list_posts` | Browse all posts/pages by status when planning what to schedule. |
| `search_content` | Find specific posts to add to the schedule. |
| `db_update_post` | Publish immediately (status = publish) — not schedule. |

## Workflows

### Review the content calendar

1. Call `get_schedule` to see upcoming scheduled posts and recent active drafts.
2. Report: next publish date, any gaps > 7 days between scheduled posts, count of drafts ready to schedule.
3. Suggest which drafts could fill gaps.

### Schedule a draft

1. Use `search_content` or `list_posts` to confirm the post ID if the user gives a title rather than an ID.
2. Confirm the post title and target date with the user.
3. Call `schedule_post` with `post_id` and `publish_date` (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS).
4. Report the confirmed scheduled date and edit URL.

### Reschedule an existing post

1. Call `get_schedule` to see what's currently scheduled.
2. Identify the post by title from the `scheduled` array.
3. Call `schedule_post` with the new `publish_date`.
4. Confirm the change.

### Build a content calendar from drafts

1. Call `list_posts` with `status: "draft"` to get all drafts.
2. Call `get_schedule` to see existing slots and gaps.
3. Propose a schedule: assign one draft per available slot, spaced evenly.
4. For each draft the user approves, call `schedule_post`.

## Quality Rules

- **Always confirm post ID and date before scheduling** — `schedule_post` is irreversible without another tool call.
- **`publish_date` must be in the future** — `schedule_post` will return an error for past dates; use `db_update_post` with status `publish` to publish a post immediately.
- **Use the site's local timezone** — `schedule_post` interprets dates in WordPress site timezone; tell the user which timezone will be used.
- **Report `edit_url` after scheduling** — give the user a direct link to verify in WordPress admin.
- **Never schedule more than 5 posts in one session without confirmation** — confirm the full proposed calendar before bulk-scheduling.
