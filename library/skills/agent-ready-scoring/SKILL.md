---
name: agent-ready-scoring
description: "Explain and interpret the Agent-Ready Score — a 7-check readiness score covering MCP reachability, WebMCP tool registration, approval-gate safety, and llms.txt/robots.txt/schema.org discoverability. Use when the user asks how agent-ready their site is, what the score means, what a specific check does, or how to raise it. Call check_agent_readiness first, then explain the result using this skill."
---

# Agent-Ready Score

## What it measures

Seven checks, each weighted high/medium/low, combined into an overall score (0-100) and a letter grade (A-F):

| Check | Category | Weight | Free fix available? |
|---|---|---|---|
| `mcp_server_reachable` | Capability exposure | high | Yes |
| `webmcp_tools_registered` | Capability exposure | high | Yes |
| `approval_gate_configured` | Safety & trust | high | Yes |
| `llms_txt_present` | Discoverability | medium | No — Agent Builder Pro (AI Radar) |
| `robots_ai_directives` | Bot access control | medium | No — Agent Builder Pro (AI Radar) |
| `schema_org_present` | Content | medium | No — Agent Builder Pro (AI Radar) |
| `well_known_manifest` | Discoverability | low | Yes |

All seven checks run locally, in-process, with zero outbound HTTP requests.

## How to read a result

`check_agent_readiness` returns:
- `overall` — 0-100 weighted score.
- `grade` — a letter, A (≥90) through F (<40).
- `categories` — one entry per check above, each with `{score, status, detail, category, weight, fixable}`. `status` is `pass`/`partial`/`fail`; `detail` is a human-readable explanation of exactly why the check scored what it did.
- `checked_at` — when this was last computed. Pass `force_rescan: true` to recompute (checks run in well under a second — there's no cost to rescanning on request).

## What you can fix directly

The four checks marked "Yes" above have real one-click fixes available in wp-admin → Agent-Ready, and corresponding tools you may be asked to run directly:
- `resign_agent_manifest` — re-signs any active agent whose abilities.json signature has gone stale.
- `enable_webmcp_defaults` — exposes safe, read-only, low-risk-or-below tools to WebMCP.
- `configure_approval_gate` — turns off WebMCP exposure for anything exposed above a safe risk tier (never lowers a tool's own declared risk).
- `enable_agent_readiness` — the master WebMCP Bridge switch; also backs `webmcp_tools_registered` and `well_known_manifest`.

## What Agent Builder Pro fixes

`llms_txt_present`, `robots_ai_directives`, and `schema_org_present` are checked here, but real one-click fixes for them (`generate_llms_txt`, `update_robots_txt`, and deeper schema.org tooling) are part of Agent Builder Pro's "AI Radar" agent. If the user asks you to fix one of these three directly, tell them plainly that it requires Agent Builder Pro rather than attempting a workaround with unrelated tools — don't try to write to robots.txt or llms.txt yourself using generic file tools; that would bypass the safety and correctness checks AI Radar's dedicated tools provide.

## Explaining the score conversationally

Lead with the overall grade and the single highest-impact fixable issue (usually the highest-weight check that scored 0 or low), not a recitation of all seven rows. Offer to run a free fix directly when one exists and the user confirms; otherwise point to wp-admin → Agent-Ready for the fuller breakdown and Pro upsell CTAs.
