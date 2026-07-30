---
name: image-processing
description: "Work with images in the WordPress media library: resize, crop, compress, convert format, search, and AI-edit. Use when the user asks to resize an image, make a thumbnail, compress photos for the web, convert to WebP, find images by filename or type, change alt text, or AI-edit an image. Trigger when the user mentions image files, photo sizes, file format conversion, or media library management. Do NOT trigger for video files."
---

# Image Processing Skill

## Available Tools

| Tool | When to use |
|---|---|
| `resize_image` | Scale an image to specific dimensions or crop to an exact size. No AI credits. |
| `compress_image` | Reduce file size by lowering JPEG/WebP quality. No AI credits. |
| `convert_image` | Convert between JPEG, PNG, WebP, GIF. No AI credits. |
| `search_media_library` | Find images by keyword, MIME type, or upload date. |
| `scan_media_library` | Audit for oversized, orphaned, or unattached files. |
| `edit_image` | AI-powered editing: change content, inpaint, recolour. Costs credits. |
| `upscale_image` | AI 2×/4× resolution upscaling. Costs 9 credits. |
| `generate_image` | AI image generation from a text prompt. Costs credits. |
| `set_featured_image` | Assign a media library image as a post's featured image. |

## Workflows

### Resize for web (non-destructive)

1. Call `search_media_library` with `keyword` to find the attachment ID.
2. Call `resize_image` with `attachment_id`, `width`, and optionally `height`.
3. Default `mode` is `"resize"` (preserve aspect ratio). Use `"crop"` for exact dimensions.
4. Return the new attachment URL.

### Make a WebP version for performance

1. Find the attachment ID via `search_media_library`.
2. Call `convert_image` with `format: "webp"` and `quality: 85`.
3. Report the `size_change_pct` — WebP is typically 25–35% smaller than JPEG.

### Bulk compress oversized images

1. Call `scan_media_library` with `size_threshold_kb: 500` to find large files.
2. For each file in `oversized_files`, call `compress_image` with `quality: 80`.
3. Report the total saving across all files.

### Create a thumbnail / featured image

1. Call `resize_image` with `mode: "crop"`, `width: 800`, `height: 450` (or the required size).
2. Call `set_featured_image` with the new `attachment_id` and the target `post_id`.

### AI image editing

1. Use `edit_image` when the user wants to change the *content* of an image (remove object, change background, recolour).
2. Use `upscale_image` when the image is too small and needs higher resolution.
3. Always confirm credit cost before calling AI tools: `edit_image` costs 6 credits by default.

## Quality Rules

- **All local tools are non-destructive** — they always save as a new attachment; the original is never overwritten.
- **Always report the new attachment ID and URL** so the user can find the result.
- **WebP is preferred for web use** — suggest conversion if the user doesn't specify a format.
- **Confirm before AI tools** — credit-consuming tools (`edit_image`, `upscale_image`) need user confirmation.
- **Quality 80 is the safe default** for compression; warn below 60 that quality loss will be visible.
- **Max dimensions: 8000 px** — `resize_image` will reject larger values.
