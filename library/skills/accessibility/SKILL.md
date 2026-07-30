---
name: accessibility
description: "Audit WordPress post content, theme templates, or plugin UI for accessibility issues. Use when the user asks to check accessibility, improve WCAG compliance, fix missing alt text, audit heading structure, check colour contrast, or make content screen-reader friendly. Also trigger when the user mentions 'a11y', 'WCAG', 'screen reader', 'ARIA', or 'accessible'. Do NOT trigger for general SEO audits — use the SEO tools for that."
---

# Accessibility Skill

## Available Tools

| Tool | When to use |
|---|---|
| `get_post_content` | Fetch post HTML to audit for accessibility issues. |
| `search_content` | Find posts by keyword if the ID is unknown. |
| `update_post_content` | Save the corrected HTML after fixes are applied. |
| `search_media_library` | Find images missing alt text (search with empty keyword). |
| `fetch_url` | Fetch a live page's rendered HTML for audit. |

## WCAG Checks (Priority Order)

### Level A — Must Fix

**Images and alt text**
- Every `<img>` must have an `alt` attribute.
- Decorative images: `alt=""` (empty string, not missing).
- Informative images: alt text describes the meaning, not the appearance ("Chart showing 40% growth" not "graph.png").

**Headings**
- One `<h1>` per page — the post title.
- Heading levels never skip: `<h2>` → `<h3>`, not `<h2>` → `<h4>`.
- Headings convey structure, not styling — never use a heading tag just to make text big.

**Links**
- No "click here" or "read more" without context.
- Every link must make sense when read out of context.
- Links that open in a new tab must warn: `(opens in new tab)` or `aria-label`.

**Forms**
- Every `<input>` and `<select>` must have an associated `<label>` (not just placeholder text).
- Required fields marked with `aria-required="true"` or `required`.
- Error messages linked to the field with `aria-describedby`.

**Keyboard navigation**
- Every interactive element reachable by Tab.
- No keyboard traps.
- Focus indicator visible — never `outline: none` without an alternative.

### Level AA — Should Fix

**Colour contrast**
- Normal text: minimum 4.5:1 ratio against background.
- Large text (18pt/14pt bold): minimum 3:1.
- Never convey information by colour alone.

**ARIA usage**
- `aria-label` on icon buttons with no visible text.
- `role="navigation"` on `<nav>`, `role="main"` on `<main>`.
- `aria-expanded` on toggles and accordions.
- Never use ARIA to override semantic HTML when the correct element exists.

## Workflows

### Audit a post for accessibility

1. Call `get_post_content` to fetch the HTML.
2. Check the content against the WCAG items above.
3. Report issues grouped by: Critical (Level A), Warnings (Level AA), Suggestions.
4. For each issue: quote the problematic HTML and provide the corrected version.
5. If the user approves fixes, call `update_post_content` with the corrected HTML.

### Fix missing alt text across the media library

1. Call `search_media_library` with no keyword and `mime_type: "image"`.
2. Filter results where `alt_text` is empty.
3. For each image, suggest alt text based on the filename or title.
4. Ask the user to confirm the suggested alt text before saving (use `db_update_post` with the attachment post ID and `_wp_attachment_image_alt` meta).

### Audit heading structure

1. Fetch the page HTML with `get_post_content` or `fetch_url`.
2. Extract all heading tags in order.
3. Check for: missing H1, skipped levels, overuse of H1, headings used for styling.
4. Provide the corrected heading hierarchy.

## Quality Rules

- **Quote the exact HTML** — show the problematic markup and the corrected version side-by-side.
- **Severity first** — Level A failures before Level AA warnings.
- **Don't hallucinate contrast ratios** — only report contrast failures you can calculate from the provided CSS/colour values.
- **ARIA is a last resort** — always prefer the correct semantic HTML element over an ARIA workaround.
- **Never remove structure** — don't fix headings by removing them; restructure them.
