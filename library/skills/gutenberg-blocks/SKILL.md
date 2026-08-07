---
name: gutenberg-blocks
description: "Use this skill whenever content is being created or edited on a site using the native WordPress block editor (Gutenberg) or Full Site Editing, and the output needs to render correctly as blocks rather than raw HTML. Trigger when the user asks for content with real formatting (headings, columns, images, buttons, quotes) rather than plain text, or mentions 'blocks', 'Gutenberg', or 'the block editor'. Do NOT trigger for sites using Elementor, Divi, Beaver Builder, Bricks, or WPBakery — those store content in a different, incompatible format and need their own skill."
compatibility: "Native WordPress block editor / Full Site Editing (FSE) sites only. Not for Elementor, Divi, Beaver Builder, Bricks, or WPBakery — writing raw HTML into post_content on those does not render as intended and can corrupt the page builder's own data."
---

# Gutenberg Block Content

## How block content works

Native WordPress stores block content as HTML in `post_content`, with each block wrapped in an HTML comment delimiter:

```html
<!-- wp:paragraph -->
<p>Plain text becomes a paragraph block.</p>
<!-- /wp:paragraph -->
```

`create_post_content` and `update_post_content` (from the content-authoring skill) both write directly to `post_content` — pass properly delimited block markup as the content, not plain HTML without the comments. Content saved without block comments still displays, but it becomes one big "Classic" block instead of editable native blocks, which surprises users expecting to edit it visually afterward.

## Common core blocks

| Block | Markup |
|---|---|
| Paragraph | `<!-- wp:paragraph --><p>Text.</p><!-- /wp:paragraph -->` |
| Heading | `<!-- wp:heading {"level":2} --><h2>Heading</h2><!-- /wp:heading -->` |
| List | `<!-- wp:list --><ul><!-- wp:list-item --><li>Item</li><!-- /wp:list-item --></ul><!-- /wp:list -->` |
| Image | `<!-- wp:image {"id":123,"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="URL" alt="Alt text" class="wp-image-123"/></figure><!-- /wp:image -->` |
| Quote | `<!-- wp:quote --><blockquote class="wp-block-quote"><p>Quoted text.</p><cite>Source</cite></blockquote><!-- /wp:quote -->` |
| Button | `<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="URL">Label</a></div><!-- /wp:button --></div><!-- /wp:buttons -->` |
| Columns | `<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column">...</div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column">...</div><!-- /wp:column --></div><!-- /wp:columns -->` |
| Group | `<!-- wp:group --><div class="wp-block-group">...</div><!-- /wp:group -->` |
| Separator | `<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->` |

## Workflow

1. **Confirm it's a block-editor site before using this skill's markup** — if unsure, check whether existing `post_content` (via `get_post_content`) already contains `<!-- wp:` comments. If it doesn't (raw HTML, shortcodes, or a JSON blob in postmeta), this is the wrong skill — check for a page-builder-specific skill instead, or ask the user.
2. Compose content as a sequence of properly delimited blocks matching the request's structure — headings for section breaks, lists for enumerations, columns only when content genuinely needs side-by-side layout.
3. For images, you need a real attachment ID and URL already in the media library — do not invent an `id` or reference an external URL directly in an `<img>` inside a block; if no suitable image is uploaded yet, leave the image block out and say so rather than fabricating a broken reference.
4. Pass the assembled markup to `create_post_content`/`update_post_content` as the content field.

## Quality Rules

- **Every block needs both its opening and closing HTML comment.** A missing `<!-- /wp:x -->` breaks parsing of everything after it in the editor.
- **Don't over-nest.** Most content is flat paragraphs/headings/lists — reach for `columns`/`group` only when the user's request actually implies layout, not by default.
- **Never fabricate image or attachment IDs.** A block referencing a non-existent attachment ID shows as broken in the editor.
- **If `post_content` isn't already block-comment HTML, stop and check the site's actual editor/page-builder before writing more content into it.**
