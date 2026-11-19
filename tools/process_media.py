"""Verwerkt ruwe software-opnames uit media-inbox/ voor de Software-pagina.

Gebruik:  python tools/process_media.py            (alles wat nieuw of gewijzigd is)
          python tools/process_media.py --force    (alles opnieuw)

  media-inbox/<app>/*.png|jpg      → img/software/<app>/<naam>.webp (max 1600 px) + <naam>-thumb.webp (600 px)
  media-inbox/<app>/*.mp4|mkv|mov  → video/<app>/<naam>.mp4 (H.264) + .webm (VP9) + <naam>-poster.jpg
                                      zonder geluid, max 1280 px breed. Langer dan 30 s of na verwerking
                                      groter dan 8 MB → overgeslagen: die hoort op YouTube.

Vereist: Pillow en ffmpeg/ffprobe in PATH. Metadata (EXIF/GPS) wordt niet meegenomen.
"""
import json
import shutil
import subprocess
import sys
from pathlib import Path

from PIL import Image, ImageOps

ROOT = Path(__file__).resolve().parent.parent
INBOX = ROOT / "media-inbox"
IMG_OUT = ROOT / "img" / "software"
VID_OUT = ROOT / "video"

IMG_EXT = {".png", ".jpg", ".jpeg", ".webp"}
VID_EXT = {".mp4", ".mkv", ".mov", ".webm", ".avi"}
MAX_SECONDS = 30
MAX_BYTES = 8 * 1024 * 1024
FORCE = "--force" in sys.argv


def slug(name: str) -> str:
    keep = "".join(c.lower() if c.isalnum() else "-" for c in name)
    while "--" in keep:
        keep = keep.replace("--", "-")
    return keep.strip("-") or "media"


def is_fresh(src: Path, out: Path) -> bool:
    return not FORCE and out.exists() and out.stat().st_mtime >= src.stat().st_mtime


def process_image(src: Path, app: str) -> None:
    out_dir = IMG_OUT / app
    out_dir.mkdir(parents=True, exist_ok=True)
    name = slug(src.stem)
    full, thumb = out_dir / f"{name}.webp", out_dir / f"{name}-thumb.webp"
    if is_fresh(src, full):
        print(f"  = {full.relative_to(ROOT)} (al actueel)")
        return
    img = ImageOps.exif_transpose(Image.open(src)).convert("RGB")
    for target, width, q in ((full, 1600, 82), (thumb, 600, 78)):
        im = img.copy()
        if im.width > width:
            im = im.resize((width, round(im.height * width / im.width)), Image.LANCZOS)
        im.save(target, "WEBP", quality=q, method=6)
    print(f"  + {full.relative_to(ROOT)}  {img.width}x{img.height}  "
          f"({full.stat().st_size // 1024} kB, thumb {thumb.stat().st_size // 1024} kB)")


def ffprobe_duration(src: Path) -> float:
    r = subprocess.run(["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "json", str(src)],
                       capture_output=True, text=True, check=True)
    return float(json.loads(r.stdout)["format"]["duration"])


def ffmpeg(*args: str) -> None:
    subprocess.run(["ffmpeg", "-y", "-hide_banner", "-loglevel", "error", *args], check=True)


def process_video(src: Path, app: str) -> None:
    out_dir = VID_OUT / app
    out_dir.mkdir(parents=True, exist_ok=True)
    name = slug(src.stem)
    mp4, webm, poster = out_dir / f"{name}.mp4", out_dir / f"{name}.webm", out_dir / f"{name}-poster.jpg"
    if is_fresh(src, mp4):
        print(f"  = {mp4.relative_to(ROOT)} (al actueel)")
        return
    dur = ffprobe_duration(src)
    if dur > MAX_SECONDS:
        print(f"  ! {src.name}: {dur:.0f} s is te lang voor een korte clip → zet deze op YouTube")
        return
    scale = "scale='min(1280,iw)':-2:flags=lanczos,fps=30"
    ffmpeg("-i", str(src), "-an", "-vf", scale, "-c:v", "libx264", "-preset", "slow", "-crf", "28",
           "-pix_fmt", "yuv420p", "-movflags", "+faststart", str(mp4))
    ffmpeg("-i", str(src), "-an", "-vf", scale, "-c:v", "libvpx-vp9", "-crf", "38", "-b:v", "0",
           "-row-mt", "1", "-deadline", "good", str(webm))
    ffmpeg("-ss", str(min(1.0, dur / 2)), "-i", str(src), "-frames:v", "1",
           "-vf", "scale='min(1280,iw)':-2", "-q:v", "4", str(poster))
    too_big = [p for p in (mp4, webm) if p.stat().st_size > MAX_BYTES]
    if too_big:
        for p in (mp4, webm, poster):
            p.unlink(missing_ok=True)
        print(f"  ! {src.name}: na verwerking nog groter dan 8 MB → inkorten of op YouTube zetten")
        return
    print(f"  + {mp4.relative_to(ROOT)}  {dur:.1f} s  "
          f"(mp4 {mp4.stat().st_size // 1024} kB, webm {webm.stat().st_size // 1024} kB)")


def main() -> None:
    if not shutil.which("ffmpeg") or not shutil.which("ffprobe"):
        sys.exit("ffmpeg/ffprobe niet gevonden in PATH")
    if not INBOX.is_dir():
        sys.exit(f"{INBOX} bestaat niet")
    found = False
    for app_dir in sorted(p for p in INBOX.iterdir() if p.is_dir()):
        files = sorted(p for p in app_dir.iterdir() if p.suffix.lower() in IMG_EXT | VID_EXT)
        if not files:
            continue
        found = True
        print(f"[{app_dir.name}]")
        for f in files:
            try:
                if f.suffix.lower() in IMG_EXT:
                    process_image(f, app_dir.name)
                else:
                    process_video(f, app_dir.name)
            except Exception as exc:  # één kapot bestand mag de rest niet tegenhouden
                print(f"  ! {f.name}: {exc}")
    if not found:
        print("Niets te verwerken in media-inbox/.")


if __name__ == "__main__":
    main()
