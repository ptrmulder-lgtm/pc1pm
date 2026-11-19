"""Maakt img/web/world-land.svg (equirectangulaire wereldkaart) uit Natural Earth 110m land.

Gebruik:  python tools/make_worldmap.py pad/naar/ne_110m_land.geojson
Bron: https://github.com/nvkelso/natural-earth-vector (public domain).
"""
import json
import sys
from pathlib import Path

OUT = Path(__file__).resolve().parent.parent / "img" / "web" / "world-land.svg"


def ring_to_path(ring):
    pts = [(round(lon + 180, 1), round(90 - lat, 1)) for lon, lat in ring]
    out, last = [], None
    for p in pts:
        if p != last:
            out.append(p)
            last = p
    if len(out) < 3:
        return ""
    return "M" + "L".join(f"{x:g} {y:g}" for x, y in out) + "Z"


def main(src):
    data = json.loads(Path(src).read_text(encoding="utf-8"))
    parts = []
    for feat in data["features"]:
        geom = feat["geometry"]
        polys = geom["coordinates"] if geom["type"] == "MultiPolygon" else [geom["coordinates"]]
        for poly in polys:
            for ring in poly:
                parts.append(ring_to_path(ring))
    d = "".join(parts)
    svg = (
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 360 180" preserveAspectRatio="none">'
        f'<path d="{d}" fill="#2c3b43" stroke="#3b4e58" stroke-width=".25" fill-rule="evenodd"/></svg>\n'
    )
    OUT.write_text(svg, encoding="utf-8")
    print(f"{OUT.name}: {len(svg) // 1024} kB")


if __name__ == "__main__":
    main(sys.argv[1])
