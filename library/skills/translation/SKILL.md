---
name: translation
description: "Translate WordPress post content, page copy, product descriptions, or any text into another language. Use when the user asks to translate a post, page, or any text, or when they mention making content available in another language. Also trigger when the user asks to localise UI strings, translate a plugin description, or produce a multilingual version of any content. Do NOT trigger for language detection without translation, or for WPML/Polylang plugin configuration."
---

# Translation Skill

## Available Tools

| Tool | When to use |
|---|---|
| `get_post_content` | Fetch the source post HTML before translating. |
| `search_content` | Find the post by title or keyword if the ID is unknown. |
| `update_post_content` | Save the translated version back to WordPress. |
| `wc_get_product` | Fetch WooCommerce product content for translation. |
| `wc_update_product` | Save translated product name and description. |
| `create_post_content` | Create a new post for the translated version (preferred for multilingual setups). |

## Workflows

### Translate a WordPress post

1. Use `search_content` or `get_post_content` to retrieve the source text.
2. Identify the target language from the user's request.
3. Translate the content — preserve all HTML tags, shortcodes, and block markup exactly; only translate visible text strings.
4. Present the translation to the user for review.
5. On approval:
   - If updating in-place: call `update_post_content` with the translated HTML.
   - If creating a separate post: call `create_post_content` (status: `draft`) so the user can review before publishing.

### Translate a WooCommerce product

1. Call `wc_get_product` to get `name`, `description`, and `short_description`.
2. Translate all three fields.
3. Show the user the translations for approval.
4. Call `wc_update_product` with the translated `name`, `description`, and `short_description`.

### Translate UI strings or plugin copy

1. Ask the user to paste or provide the strings.
2. Translate each string, preserving placeholders (`%s`, `%d`, `{variable}`, `{{key}}`).
3. Return the translations in the same format — ready to copy into `.po` / `.pot` files or theme templates.

## Quality Rules

- **Preserve all HTML** — never translate or alter tag attributes, class names, IDs, `src`, or `href` values.
- **Never translate shortcodes** — `[contact-form]`, `[product id="5"]`, etc. must pass through unchanged.
- **Preserve Gutenberg block comments** — `<!-- wp:paragraph -->` markers must not be translated.
- **Placeholders are untouchable** — `%s`, `%1$s`, `{name}`, `{{ variable }}` must survive verbatim.
- **Always show the translation before saving** — the user must approve the output before `update_post_content` is called.
- **Tone matching** — match the formality and style of the source. Ask if uncertain (e.g. formal vs. informal "you" in German/French/Spanish).
- **For new-post workflow** — always use `status: draft`; never publish a translation directly without user review.
