#!/usr/bin/env python3
"""Render the Kostaa pin from the website loading screen.

The mark is the orange circle, the cut-out hole, and the diamond point in
resources/views/map.blade.php (.boot-pin). Favicon files are the pin alone.
App icons put that pin on the loading screen's white. Logos add the KOstaa word.
"""

import math
import urllib.request
from pathlib import Path

from fontTools.ttLib import TTFont
from fontTools.varLib.instancer import instantiateVariableFont
from PIL import Image, ImageChops, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[1]
BRAND = ROOT / "public" / "brand"
APP = ROOT.parent / "blip-app" / "assets"
FONT_URL = "https://github.com/google/fonts/raw/main/ofl/sofiasans/SofiaSans%5Bwght%5D.ttf"
ORANGE = (194, 65, 12, 255)
INK = (28, 27, 25, 255)
WHITE = (255, 255, 255, 255)

PIN_W = 64
PIN_H = 85
DIAMOND_HALF = 12 * math.sqrt(2)

ICON_SIZES = (16, 32, 48, 64, 128, 180, 192, 256, 512, 1024)
APP_SIZES = (180, 192, 512, 1024)
LOGO_HEIGHTS = (64, 128, 256, 512)
STACK_HEIGHTS = (128, 256, 512, 1024)
BANNERS = (
    ("banner-og.png", 1200, 630, "wide"),
    ("banner-facebook.png", 820, 312, "wide"),
    ("banner-x.png", 1500, 500, "wide"),
    ("banner-linkedin.png", 1584, 396, "wide"),
    ("banner-instagram.png", 1080, 1080, "stack"),
    ("banner-story.png", 1080, 1920, "story"),
    ("banner-youtube.png", 2560, 1440, "youtube"),
    ("banner-discord.png", 1920, 1080, "wide"),
)

SVG = """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 85" role="img">
<title>Kostaa</title>
<path fill="#c2410c" fill-rule="evenodd" d="M32 0a32 32 0 1 0 .001 0zM32 20a12 12 0 1 1-.001 0z"/>
<path fill="#c2410c" d="M32 51.0294 48.9706 68 32 84.9706 15.0294 68z"/>
</svg>
"""


def font_path() -> Path:
    cache = Path("/tmp/SofiaSans-700.ttf")
    if cache.exists():
        return cache
    raw = Path("/tmp/SofiaSans-vf.ttf")
    if not raw.exists():
        urllib.request.urlretrieve(FONT_URL, raw)
    font = TTFont(raw)
    instantiateVariableFont(font, {"wght": 700}, inplace=True)
    font.save(cache)
    return cache


def render_pin(target_h: int, color=ORANGE) -> Image.Image:
    target_h = max(1, target_h)
    target_w = max(1, round(target_h * PIN_W / PIN_H))
    scale_up = 8 if target_h < 256 else 4
    width = target_w * scale_up
    height = target_h * scale_up
    scale = height / PIN_H
    image = Image.new("RGBA", (width, height), (0, 0, 0, 0))
    draw = ImageDraw.Draw(image)
    cx, cy, radius = 32 * scale, 32 * scale, 32 * scale
    draw.ellipse((cx - radius, cy - radius, cx + radius, cy + radius), fill=color)
    half = DIAMOND_HALF * scale
    dcx, dcy = 32 * scale, 68 * scale
    draw.polygon(
        [(dcx, dcy - half), (dcx + half, dcy), (dcx, dcy + half), (dcx - half, dcy)],
        fill=color,
    )
    hole = Image.new("L", (width, height), 0)
    hole_draw = ImageDraw.Draw(hole)
    hole_r = 12 * scale
    hole_draw.ellipse((cx - hole_r, cy - hole_r, cx + hole_r, cy + hole_r), fill=255)
    image.putalpha(ImageChops.multiply(image.getchannel("A"), Image.eval(hole, lambda p: 255 - p)))
    return image.resize((target_w, target_h), Image.Resampling.LANCZOS)


def fit_pin(box: int, fill: float, color=ORANGE) -> Image.Image:
    side = max(1, round(box * fill))
    pin = render_pin(side, color)
    if pin.width > side:
        pin = pin.resize((side, max(1, round(side * pin.height / pin.width))), Image.Resampling.LANCZOS)
    return pin


def place(canvas: Image.Image, pin: Image.Image) -> Image.Image:
    canvas.alpha_composite(
        pin,
        ((canvas.width - pin.width) // 2, (canvas.height - pin.height) // 2),
    )
    return canvas


def square_mark(size: int, fill: float, color=ORANGE) -> Image.Image:
    return place(Image.new("RGBA", (size, size), (0, 0, 0, 0)), fit_pin(size, fill, color))


def app_plate(size: int) -> Image.Image:
    return place(Image.new("RGBA", (size, size), WHITE), fit_pin(size, 0.62)).convert("RGB")


def draw_word(px: int) -> Image.Image:
    font = ImageFont.truetype(font_path(), px)
    tracking = -0.04 * px
    chars = [("K", ORANGE), ("O", ORANGE), ("s", INK), ("t", INK), ("a", INK), ("a", INK)]
    widths = [font.getlength(ch) for ch, _ in chars]
    total = sum(widths) + tracking * (len(chars) - 1)
    ascent, descent = font.getmetrics()
    pad = px // 2
    image = Image.new("RGBA", (math.ceil(total) + pad * 2, ascent + descent + pad * 2), (0, 0, 0, 0))
    draw = ImageDraw.Draw(image)
    x = pad
    for (ch, color), width in zip(chars, widths):
        draw.text((x, pad), ch, font=font, fill=color)
        x += width + tracking
    return image.crop(image.getbbox())


def logo_horizontal(text_px: int) -> Image.Image:
    word = draw_word(text_px)
    pin = render_pin(round(text_px * 1.7))
    gap = round(text_px * 0.38)
    height = max(pin.height, word.height)
    image = Image.new("RGBA", (pin.width + gap + word.width, height), (0, 0, 0, 0))
    image.alpha_composite(pin, (0, (height - pin.height) // 2))
    image.alpha_composite(word, (pin.width + gap, (height - word.height) // 2))
    return image


def logo_stack(text_px: int) -> Image.Image:
    word = draw_word(text_px)
    pin = render_pin(round(text_px * 1.52))
    gap = round(text_px * 0.16)
    width = max(pin.width, word.width)
    image = Image.new("RGBA", (width, pin.height + gap + word.height), (0, 0, 0, 0))
    image.alpha_composite(pin, ((width - pin.width) // 2, 0))
    image.alpha_composite(word, ((width - word.width) // 2, pin.height + gap))
    return image


def at_height(image: Image.Image, height: int) -> Image.Image:
    ratio = height / image.height
    return image.resize((max(1, round(image.width * ratio)), height), Image.Resampling.LANCZOS)


def banner(width: int, height: int, layout: str) -> Image.Image:
    image = Image.new("RGB", (width, height), (255, 255, 255))
    if layout in ("stack", "story"):
        logo = logo_stack(640)
        max_h = int(height * (0.34 if layout == "story" else 0.56))
        max_w = int(width * 0.72)
    elif layout == "youtube":
        logo = logo_horizontal(480)
        max_h, max_w = 320, 1200
    else:
        logo = logo_horizontal(480)
        max_h = int(height * 0.58)
        max_w = int(width * 0.78)
    logo.thumbnail((max_w, max_h), Image.Resampling.LANCZOS)
    image.paste(logo, ((width - logo.width) // 2, (height - logo.height) // 2), logo)
    return image


def save(image: Image.Image, path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    image.save(path, optimize=True)


def main() -> None:
    BRAND.mkdir(parents=True, exist_ok=True)
    (BRAND / "icon.svg").write_text(SVG, encoding="utf-8")

    marks = {size: square_mark(size, 0.92 if size <= 32 else 0.88) for size in ICON_SIZES}
    for size, image in marks.items():
        save(image, BRAND / f"icon-{size}.png")

    ico = marks[48]
    ico.save(
        ROOT / "public" / "favicon.ico",
        format="ICO",
        sizes=[(16, 16), (32, 32), (48, 48)],
        append_images=[marks[16], marks[32]],
    )

    for size in APP_SIZES:
        save(app_plate(size), BRAND / f"app-icon-{size}.png")

    horizontal = logo_horizontal(800)
    stacked = logo_stack(800)
    for height in LOGO_HEIGHTS:
        save(at_height(horizontal, height), BRAND / f"logo-h-{height}.png")
    for height in STACK_HEIGHTS:
        save(at_height(stacked, height), BRAND / f"logo-stack-{height}.png")

    for name, width, height, layout in BANNERS:
        save(banner(width, height, layout), BRAND / name)

    if APP.exists():
        save(app_plate(1024), APP / "icon.png")
        save(square_mark(1024, 0.7), APP / "splash-icon.png")
        save(marks[48], APP / "favicon.png")
        save(square_mark(1024, 0.58), APP / "android-icon-foreground.png")
        save(Image.new("RGB", (1024, 1024), (255, 255, 255)), APP / "android-icon-background.png")
        mono = square_mark(1024, 0.58, WHITE)
        alpha = mono.getchannel("A")
        white = Image.new("RGBA", mono.size, (255, 255, 255, 0))
        white.putalpha(alpha)
        save(white, APP / "android-icon-monochrome.png")

    print(f"wrote {BRAND}")


if __name__ == "__main__":
    main()
