#!/usr/bin/env python3
"""Build .wordpress-org/ plugin-directory assets from existing brand + screenshots.

Reuses assets/icon.svg (the Agent Builder mark). Banners are composed with
Pillow using that mark plus colours already used in the mark and admin.css:

  #5b56db / #ab4ff3  mark gradient (assets/icon.svg)
  #1d2327            admin body text
  #646970            admin muted text
  #ffffff            admin card / page white

Screenshots are copied (not invented) from screenshots/ to screenshot-N.png
in readme.txt == Screenshots == order. See SCREENSHOTS below for the verified
mapping and FLAGS for captions that only partially match an existing file.

Requires: cairosvg, Pillow, Noto Sans (Regular + Bold) on the generator host.
Outputs are committed so reviewers do not need those tools.
"""
from __future__ import annotations

import shutil
import sys
from io import BytesIO
from pathlib import Path

try:
    import cairosvg
except ImportError:
    sys.exit("cairosvg is required to rasterize assets/icon.svg (pip install cairosvg)")

try:
    from PIL import Image, ImageDraw, ImageFont
except ImportError:
    sys.exit("Pillow is required to compose banners (pip install Pillow)")

ROOT = Path(__file__).resolve().parent.parent
ASSETS = ROOT / ".wordpress-org"
MARK_SVG = ROOT / "assets/icon.svg"

# readme.txt == Screenshots ==  (caption → source file that was visually verified)
SCREENSHOTS = [
    # 1. Dashboard — Overview of active agents, connected providers, safety status, and quick actions.
    "screenshots/m3-polish/dashboard-after-desktop-advanced.png",
    # 2. Interactive Chat — Chat with specialized agents; see every tool the agent calls and its result.
    "screenshots/m3-polish/chat-after-desktop-advanced.png",
    # 3. Agents Hub — Activate/deactivate bundled agents, see their tools, and assign MCP exposure.
    "screenshots/m3-polish/agents-after-desktop-advanced.png",
    # 4. Approvals Queue — Review, approve, or reject sensitive actions before agents execute them.
    "screenshots/m3-polish/approvals-after-desktop-advanced.png",
    # 5. Tools Hub — See every tool an agent can use, its risk level, and enable/disable by category.
    "screenshots/m3-polish/tools-after-desktop-basic.png",
    # 6. Approvals Preferences — Configure which risk levels need approval, confirmation, or immediate blocking.
    "screenshots/baseline/approvals-risk-gate.png",
    # 7. Site Passport / Agent-Ready Score — Verify your site is discoverable by AI agents and your commerce stack is ready.
    "screenshots/m3-polish/passport-after-desktop-advanced.png",
    # 8. Activity Log — Full audit trail showing what agents did, when, and whether they succeeded.
    "screenshots/m3-polish/activity-after-desktop-advanced.png",
    # 9. Safety Center — Risk inventory, kill switch, per-agent tool scopes, and audit-log integrity check.
    "screenshots/m3-polish/safety-center-after-desktop-basic.png",
    # 10. Quick Start Wizard — Connect your LLM provider and choose Basic or Advanced mode in under two minutes.
    "screenshots/m3-polish/setup-after-desktop-advanced.png",
    # 11. Settings & Providers — Manage LLM providers, UI modes, and security policies.
    "screenshots/m3-polish/settings-after-desktop-advanced.png",
]

# Captions that match a real screen but not every clause in the readme line.
FLAGS = [
    "screenshot-2: chat capture is the empty/welcome Agent Chat + Playground UI, not an in-progress conversation with tool-call traces. No existing file shows a sent message and tool result.",
    "screenshot-5: Tools Hub caption asks for every tool and its risk level. The Advanced full-list capture (screenshots/m3-polish/tools-after-desktop-advanced.png) is 1440×26525 / 4.3MB — unsuitable for the plugin directory — so this copies the Basic profile cards, which still show risk levels and enable/disable by category.",
    "screenshot-10: Quick Start capture is wizard step 1 (choose provider). No existing file shows the Basic/Advanced mode choice mentioned in the caption.",
    "screenshot-11: Settings capture is the Interface tab (UI modes). Providers and Security are in the left nav but not the main pane; no single screenshot shows all three.",
]

BRAND_LEFT = (91, 86, 219)    # #5b56db
BRAND_RIGHT = (171, 79, 243)  # #ab4ff3
TEXT = (29, 35, 39)           # #1d2327
MUTED = (100, 105, 112)       # #646970
WHITE = (255, 255, 255)
FONT_BOLD = "/usr/share/fonts/truetype/noto/NotoSans-Bold.ttf"
FONT_REG = "/usr/share/fonts/truetype/noto/NotoSans-Regular.ttf"


def raster_mark(px: int) -> Image.Image:
    png = cairosvg.svg2png(
        url=str(MARK_SVG),
        output_width=px,
        output_height=px,
    )
    return Image.open(BytesIO(png)).convert("RGBA")


def write_icon_png(mark: Image.Image, size: int, dest: Path) -> None:
    canvas = Image.new("RGBA", (size, size), (*WHITE, 255))
    pad = int(size * 0.08)
    inner = size - 2 * pad
    scaled = mark.resize((inner, inner), Image.Resampling.LANCZOS)
    canvas.alpha_composite(scaled, (pad, pad))
    canvas.convert("RGB").save(dest, "PNG", optimize=True)


def write_banners(mark: Image.Image) -> None:
    width, height = 1544, 500
    banner = Image.new("RGB", (width, height), WHITE)
    draw = ImageDraw.Draw(banner)

    bar = 10
    for x in range(width):
        t = x / (width - 1)
        colour = tuple(
            int(a + (b - a) * t) for a, b in zip(BRAND_LEFT, BRAND_RIGHT)
        )
        draw.line([(x, 0), (x, bar - 1)], fill=colour)

    icon_size = 320
    icon = mark.resize((icon_size, icon_size), Image.Resampling.LANCZOS)
    banner.paste(icon, (90, (height - icon_size) // 2 + 8), icon)

    bold = ImageFont.truetype(FONT_BOLD, 84)
    regular = ImageFont.truetype(FONT_REG, 32)
    text_x = 430
    draw.text((text_x, 168), "Agent Builder", font=bold, fill=TEXT)
    draw.text(
        (text_x, 278),
        "AI agents for WordPress, with built-in safety.",
        font=regular,
        fill=MUTED,
    )

    hi = ASSETS / "banner-1544x500.png"
    lo = ASSETS / "banner-772x250.png"
    banner.save(hi, "PNG", optimize=True)
    banner.resize((772, 250), Image.Resampling.LANCZOS).save(lo, "PNG", optimize=True)


def copy_screenshots() -> None:
    for index, rel in enumerate(SCREENSHOTS, start=1):
        src = ROOT / rel
        if not src.is_file():
            sys.exit(f"Missing screenshot source for caption {index}: {rel}")
        dest = ASSETS / f"screenshot-{index}.png"
        shutil.copy2(src, dest)
        print(f"screenshot-{index}.png <- {rel}")


def main() -> None:
    if not MARK_SVG.is_file():
        sys.exit(f"Missing brand mark: {MARK_SVG}")
    for path in (FONT_BOLD, FONT_REG):
        if not Path(path).is_file():
            sys.exit(f"Missing font: {path}")

    ASSETS.mkdir(parents=True, exist_ok=True)
    shutil.copy2(MARK_SVG, ASSETS / "icon.svg")

    mark = raster_mark(512)
    write_icon_png(mark, 256, ASSETS / "icon-256x256.png")
    write_icon_png(mark, 128, ASSETS / "icon-128x128.png")
    write_banners(mark)
    copy_screenshots()

    print("wrote", ASSETS)
    print("FLAGS:")
    for flag in FLAGS:
        print(" -", flag)


if __name__ == "__main__":
    main()
