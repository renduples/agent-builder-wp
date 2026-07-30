---
name: media-optimizer
description: "Use when user wants to convert images to WebP, find unused or orphaned media, replace a media file, audit storage usage, or identify oversized images."
---

# Media Optimizer Skill

## Available Tools

| Tool | When to use |
|------|-------------|
| `convert_image_to_webp` | Convert a JPEG or PNG attachment to WebP format for smaller file sizes. |
| `find_unused_media` | Find attachments that are not used as featured images or in post content. |
| `replace_media_file` | Swap the file behind an existing attachment ID with a new file from a URL. |
| `get_media_storage_report` | Get a breakdown of storage by MIME type and the 10 largest files. |
| `find_oversized_images` | Find images that exceed a specified width or height threshold. |

## Workflows

### Audit media library storage

1. Call `get_media_storage_report` for an overview of total size and breakdown by type.
2. Call `find_oversized_images` to identify images that should be resized.
3. Call `find_unused_media` to identify candidates for deletion.
4. Present findings and recommended actions to the user.

### Convert images to WebP

1. Ask the user if they want to convert a single image or run a batch.
2. For a single image: call `convert_image_to_webp` with the `attachment_id` and desired `quality`.
3. Report savings percentage and the new attachment ID.
4. Warn that converting does not replace existing page references — the old attachment ID still exists unless `delete_original: true` is set.

### Replace a media file

1. Confirm the `attachment_id` and the new file URL (must be HTTPS).
2. Call `replace_media_file`.
3. Confirm the new URL and filename. Note that all existing references to the attachment ID will automatically point to the new file.

## Rules

- `convert_image_to_webp` requires GD or Imagick to be installed on the server — if it fails, inform the user.
- `find_unused_media` is best-effort: images used in page builder meta or widget options will not be detected. Always warn the user before any deletions.
- `replace_media_file` preserves the attachment ID so existing links and featured image assignments are not broken.
- `delete_original: true` on `convert_image_to_webp` is permanent — confirm with the user before using it.
