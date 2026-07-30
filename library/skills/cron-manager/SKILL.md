---
name: cron-manager
description: "Use when user wants to see scheduled WordPress cron jobs, check when a cron event next runs, or audit WP-Cron configuration."
---

# Cron Manager Skill

## Available Tools

| Tool | When to use |
|------|-------------|
| `get_cron_jobs` | List all scheduled WP-Cron events with next run times, schedules, and arguments. |

## Workflows

### Audit WP-Cron

1. Call `get_cron_jobs` to retrieve all scheduled events.
2. Highlight any overdue events (those with a past next_run_timestamp).
3. Group by schedule to identify recurring vs. one-time events.
4. Present the full list sorted by next run time.

### Check a specific hook

1. Call `get_cron_jobs` to get all events.
2. Filter the result in the response for the hook name the user is asking about.
3. Report the next run time and schedule interval.

## Rules

- WP-Cron is triggered by site traffic — jobs may be delayed on low-traffic sites. Recommend the user configure a real server cron (`*/5 * * * * wp cron event run --due-now`) for high-reliability scheduling.
- Overdue jobs are those where `next_run_timestamp` is in the past. This is common and usually not a problem, but many overdue jobs may indicate WP-Cron is blocked.
- Do not attempt to delete or add cron jobs with this skill — that requires direct server access or a dedicated WP-Cron management plugin.
