---
name: code-review
description: "Review WordPress theme or plugin PHP, JavaScript, or CSS code for bugs, security issues, coding standards violations, and performance problems. Use when the user asks to review code, check a file for issues, audit a plugin, or asks whether code is safe, correct, or well-written. Also trigger when the user pastes code and asks for feedback. Do NOT trigger for running or executing code — only for reviewing it."
---

# Code Review Skill

## Available Tools

| Tool | When to use |
|---|---|
| `git_diff` | Get the changed code for a pending commit or between two commits. |
| `git_log` | Review recent commit history for context. |
| `fetch_url` | Fetch a public GitHub file or raw code URL for review. |
| `get_post_content` | Read a plugin or theme file stored as a post (rare). |

## What to Check

### Security (highest priority)

- **SQL injection** — any `$wpdb->query()` with string interpolation instead of `$wpdb->prepare()`.
- **XSS** — unescaped output; every echo should use `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`.
- **Nonce missing** — form handlers and AJAX actions without `check_ajax_referer()` or `wp_verify_nonce()`.
- **Capability check missing** — actions that modify data without `current_user_can()`.
- **Direct file access** — PHP files without `if ( ! defined( 'ABSPATH' ) ) { exit; }`.
- **`eval()`, `exec()`, `system()`** — flag all shell execution; check for injection risk.
- **Unvalidated redirects** — `wp_redirect()` with user-supplied URLs.

### WordPress Coding Standards

- Functions prefixed with plugin/theme namespace to avoid collisions.
- Hooks use `add_action`/`add_filter` — not direct function calls in template files.
- Database queries use `$wpdb->prepare()`.
- Options use `get_option()` / `update_option()`, not direct DB access.
- Assets enqueued via `wp_enqueue_script()` / `wp_enqueue_style()`, not inline in templates.

### Performance

- Database queries inside loops — flag `get_post_meta()` or `WP_Query` inside `foreach`.
- Missing caching — expensive queries without `wp_cache_get()` / `set_transient()`.
- `query_posts()` usage — always a bug; should be `WP_Query`.
- Autoloaded options that are large arrays.

### PHP Quality

- Strict types — `declare(strict_types=1)` at top of file.
- Type hints on function signatures.
- Unused variables, dead code, unreachable branches.
- `error_suppression (@)` — flag every `@` usage.

## Review Format

Structure every review as:

1. **Critical** — security vulnerabilities, data loss risks. Must fix before shipping.
2. **Warnings** — standards violations, performance issues. Should fix.
3. **Suggestions** — style, readability, minor improvements. Optional.
4. **Positives** — what's done well (keep reviews balanced).

For each issue: quote the exact line(s), explain why it's a problem, give the corrected code.

## Quality Rules

- **Quote the exact code** — never refer to "line 42" without showing the code on that line.
- **Fix, don't just flag** — always provide the corrected version, not just a description of the problem.
- **Prioritise security over style** — a SQL injection is more important than a missing docblock.
- **WordPress context matters** — apply WordPress-specific standards (WPCS), not generic PHP linting rules.
- **Never mark code as safe without evidence** — if you can't assess a security concern (e.g. complex auth flow), say so explicitly.
