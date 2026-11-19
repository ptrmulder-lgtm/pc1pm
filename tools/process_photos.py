"""Bewerkt de foto's in img/ en schrijft webversies naar img/web/.

Gebruik:  python tools/process_photos.py
De originelen in img/ worden niet aangepast.
"""
from pathlib import Path

import numpy as np
from PIL import Image, ImageFilter, ImageOps

ROOT = Path(__file__).resolve().parent.parent
SRC = ROOT / "img"
OUT = SRC / "web"
OUT.mkdir(exist_ok=True)

TROPHIES = SRC / "IMG_20261119_114715.jpg"
WALL = SRC / "IMG_20261119_114743.jpg"


def load(path):
    return ImageOps.exif_transpose(Image.open(path)).convert("RGB")


def tone(img, warmth=1.0, highlights=0.0, shadows=0.0, contrast=0.0, saturation=1.0):
    """Eenvoudige 'Lightroom'-achtige correctie op float-niveau."""
    a = np.asarray(img).astype(np.float32) / 255.0

    # witbalans: iets warmer in de middentonen; wit en zwart blijven neutraal
    k = (warmth - 1.0) * 4
    a[..., 0] += k * a[..., 0] * (1 - a[..., 0])
    a[..., 2] -= k * a[..., 2] * (1 - a[..., 2])

    # hooglichten iets terug, schaduwen iets op – via een vloeiende curve
    # (werkt per kanaal gelijk, dus geen kleurbanden in uitgebrande vlakken)
    a = np.clip(a, 0, 1)
    a = a - highlights * (a ** 3) * (1 - a) * 2 + shadows * a * (1 - a) ** 3 * 2

    # zachte S-curve voor contrast (smoothstep-mix)
    if contrast:
        a = np.clip(a, 0, 1)
        a = a * (1 - contrast) + (a * a * (3 - 2 * a)) * contrast

    # verzadiging
    lum = 0.2126 * a[..., 0] + 0.7152 * a[..., 1] + 0.0722 * a[..., 2]
    a = lum[..., None] + (a - lum[..., None]) * saturation

    return Image.fromarray((np.clip(a, 0, 1) * 255 + 0.5).astype(np.uint8))


def radial_mask(size, center, radius, feather):
    """0 binnen de straal rond center, oplopend naar 1 daarbuiten."""
    w, h = size
    yy, xx = np.mgrid[0:h, 0:w].astype(np.float32)
    d = np.hypot((xx - center[0]) / radius[0], (yy - center[1]) / radius[1])
    return np.clip((d - 1.0) / feather, 0, 1)


def depth_and_vignette(img, center, radius, blur=10, darken=0.35, desat=0.35):
    """Achtergrond rustiger maken: lichte blur, minder kleur en donkerder naar de randen."""
    m = radial_mask(img.size, center, radius, feather=1.2)
    m3 = m[..., None]

    sharp = np.asarray(img).astype(np.float32)
    soft = np.asarray(img.filter(ImageFilter.GaussianBlur(blur))).astype(np.float32)
    a = sharp * (1 - m3) + soft * m3

    lum = (0.2126 * a[..., 0] + 0.7152 * a[..., 1] + 0.0722 * a[..., 2])[..., None]
    a = a + (lum - a) * (desat * m3)
    a *= 1 - darken * (m3 ** 1.3)

    return Image.fromarray(np.clip(a, 0, 255).astype(np.uint8))


def save(img, name, width, quality=84, sharpen=True):
    if img.width > width:
        img = img.resize((width, round(img.height * width / img.width)), Image.LANCZOS)
    if sharpen:
        img = img.filter(ImageFilter.UnsharpMask(radius=1.2, percent=60, threshold=2))
    img.save(OUT / name, "JPEG", quality=quality, optimize=True, progressive=True)
    print(f"{name}: {img.width}x{img.height}")


# ---------- hoofdfoto: Peter in de shack ----------
# Sinds 19-11-2026 is img/hoofdfoto1.png de hoofdfoto (al bewerkt aangeleverd, 1448 x 1086).
# Alleen bijsnijden, niet opnieuw kleur-corrigeren of verscherpen.
MAIN = SRC / "hoofdfoto1.png"
shack = load(MAIN)

# Hero 16:9 – volledige breedte
hero = shack.crop((0, 130, 1448, 130 + 815))
save(hero, "peter-shack-hero.jpg", 2400, quality=88, sharpen=False)

# Portret 4:5 – Peter met een stukje station
portrait = shack.crop((600, 70, 600 + 808, 70 + 1010))
save(portrait, "peter-portret.jpg", 1000, quality=88, sharpen=False)

# Social/OG-beeld 1200x630
og = shack.crop((0, 160, 1448, 160 + 760))
save(og, "og-pc1pm.jpg", 1200, quality=86, sharpen=False)

# ---------- bekers (90° draaien zodat ze rechtop staan) ----------
tro = load(TROPHIES).rotate(90, expand=True)
tro = tone(tro, warmth=1.0, highlights=0.15, shadows=0.1, contrast=0.10, saturation=1.05)
save(tro, "pacc-bekers.jpg", 1600)

# ---------- wimpels + QSL-kaarten ----------
wall = load(WALL)
wall = tone(wall, warmth=1.02, highlights=0.10, shadows=0.12, contrast=0.08, saturation=1.05)
save(wall, "qsl-wand.jpg", 1200)
save(wall.crop((0, 0, wall.width, int(wall.height * 0.31))), "pacc-wimpels.jpg", 1600)
