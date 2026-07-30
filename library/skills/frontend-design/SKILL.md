---
name: frontend-design
description: "Create distinctive, production-grade frontend code with high design quality. Use this skill when the user asks to build a web component, landing page, dashboard, plugin admin UI, Gutenberg block, WordPress theme template, or any HTML/CSS/JavaScript interface. Also trigger when the user asks to style, beautify, or redesign an existing UI. Generates creative, polished code that avoids generic AI aesthetics."
---

# Frontend Design Skill

## Before Writing a Single Line of Code

Commit to a clear aesthetic direction. Ask yourself:

- **Purpose** — what is this UI for and who uses it?
- **Tone** — pick an extreme and own it: brutally minimal, maximalist, editorial, utilitarian, retro, futuristic. Never land in the middle.
- **Constraint** — one bold constraint makes design better: one typeface, two colours, no borders, or only geometric shapes.
- **Differentiation** — what makes this impossible to mistake for a generic AI output?

State your aesthetic direction in one sentence before writing code.

## Typography

- Choose one distinctive display font paired with a refined body font.
- Source from Google Fonts: Playfair Display, Space Mono, DM Serif Display, Syne, Bebas Neue, Instrument Serif.
- **Never use:** Inter, Roboto, Arial, or system-ui as a display or heading font.
- Size with intention — large type is a design decision, not just accessibility.

## Colour

- Use CSS custom properties (`--color-primary`, `--color-surface`, etc.) for all colours.
- One dominant colour (60%), one supporting (30%), one sharp accent (10%).
- Prefer unexpected combinations: rust + slate, ochre + navy, sage + charcoal.
- **Never use:** purple gradient on white (#a855f7 → white), teal hero sections, generic blue CTAs.

## Layout and Composition

- Break the grid deliberately: asymmetry, overlap, diagonal elements, full-bleed sections.
- Unexpected whitespace is more interesting than predictable padding.
- Vary rhythm: dense then sparse, large then small.

## Motion and Interaction

- One well-orchestrated entrance animation beats many micro-interactions.
- Prefer CSS animations over JavaScript where possible.
- Scroll-based reveals and hover states should surprise, not just confirm.

## WordPress-Specific Context

### Plugin admin pages
- Honour WordPress admin colours: use `--wp-admin-theme-color` as an accent.
- Use `wp-components` React components if building in JSX for Gutenberg.
- Admin pages must be accessible: proper focus rings, label associations, keyboard navigation.

### Gutenberg blocks
- Provide both `edit.js` (backend) and CSS for `style.css` (frontend) + `editor.css` (backend).
- Use `@wordpress/components` (Button, TextControl, PanelBody, etc.) for block inspector controls.
- Always register `block.json` with `attributes`, `supports`, and `editorScript`.

### Theme templates
- Use WordPress template tags (`get_header()`, `the_content()`, `the_title()`, `get_template_part()`).
- For block themes: write `theme.json` colour palettes and spacing scale alongside templates.
- For classic themes: keep PHP logic minimal — presentation only, no database queries in templates.

### Frontend widgets / landing pages
- Self-contained HTML/CSS/JS is fine for standalone pages or shortcode output.
- Enqueue scripts via `wp_enqueue_script()` with a version hash — never inline `<script>` in templates.
- If the component needs data from WordPress, pass it via `wp_localize_script()`.

## Code Quality

- Write semantic HTML: use `<nav>`, `<main>`, `<article>`, `<aside>`, `<section>` correctly.
- Every interactive element must be keyboard accessible and have an ARIA label where needed.
- CSS is mobile-first: base styles for small screens, `@media (min-width: ...)` for larger.
- No unused CSS classes, no inline styles (except CSS custom properties), no `!important`.

## Delivery

- Provide complete, runnable code — never stub out sections with "add your content here".
- If multiple files are needed (block.json, edit.js, style.css), deliver all of them.
- For standalone HTML output, include all CSS in a `<style>` block and all JS in a `<script>` block at the bottom.
