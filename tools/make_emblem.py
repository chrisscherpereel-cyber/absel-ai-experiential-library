"""Draw the SELAR emblem: the brick arch, the shelf of activity cards, and the SELAR lettering.

Writes wp-plugin/absel-ai-library/assets/selar-emblem.svg. The lettering is text in
Alfa Slab One (stretched to a fixed width so other fonts still fit). After changing
the emblem, run tools/build_form.py to update the submission page's copy.

Usage: py tools/make_emblem.py
"""
import math
from pathlib import Path

OUT = Path(__file__).resolve().parent.parent / "wp-plugin" / "absel-ai-library" / "assets" / "selar-emblem.svg"

BRICK, DOME, LETTER = "#a1311f", "#7a2214", "#8c2a1b"
GREEN, GOLD, CARD = "#0f4a40", "#f0b43a", "#fbf1df"
CX, CY = 330, 186
ORX, ORY, IRX, IRY = 310, 176, 256, 126  # outer and inner radii of the arch (an ellipse)
N, GAP = 15, 1.4  # bricks in the arch, gap between them in degrees


def pt(rx, ry, deg):
    a = math.radians(deg)
    return CX + rx * math.cos(a), CY - ry * math.sin(a)


def f(v):
    return f"{v:.1f}".rstrip("0").rstrip(".")


out = []
step = 180 / N
for i in range(N):
    a0, a1 = 180 - i * step - GAP / 2, 180 - (i + 1) * step + GAP / 2
    if i == 0:
        a0 = 180
    if i == N - 1:
        a1 = 0
    (x0, y0), (x1, y1) = pt(ORX, ORY, a0), pt(ORX, ORY, a1)
    (x2, y2), (x3, y3) = pt(IRX, IRY, a1), pt(IRX, IRY, a0)
    out.append(f'<path d="M{f(x0)} {f(y0)}A{ORX} {ORY} 0 0 1 {f(x1)} {f(y1)}'
               f'L{f(x2)} {f(y2)}A{IRX} {IRY} 0 0 0 {f(x3)} {f(y3)}Z"/>')
bricks = "".join(out)

legs = []
for row in range(4):
    y = CY + 8 + row * 38
    for x in (CX - ORX, CX + IRX):
        legs.append(f'<rect x="{x}" y="{y}" width="{ORX - IRX}" height="30"/>')
legs = "".join(legs)

# The dark interior of the arch, down to the shelf.
shelf_y = 176
rx, ry = IRX - 7, IRY - 7
half = rx * math.sqrt(1 - ((CY - shelf_y) / ry) ** 2)
dome = f'<path fill="{DOME}" d="M{f(CX - half)} {shelf_y}A{rx} {ry} 0 0 1 {f(CX + half)} {shelf_y}Z"/>'


def card(x, y, angle, fill):
    lines = "".join(
        f'<path d="M{x + 20} {y + 19 + k * 14}h{length}"/>' for k, length in enumerate((60, 60, 46))
    )
    return (f'<g transform="rotate({angle} {x + 52} {y + 62})"><rect x="{x}" y="{y}" width="104" height="66" rx="10" fill="{fill}"/>'
            f'<g stroke="{GREEN}" stroke-width="6" stroke-linecap="round">{lines}</g></g>')


cards = card(150, 104, -4, CARD) + card(272, 104, -3, CARD) + card(398, 88, -5, GOLD)
sparkle = (f'<g stroke="{GOLD}" stroke-width="6" stroke-linecap="round">'
           '<path d="M396 80l-9-11"/><path d="M412 74v-14"/><path d="M428 78l9-11"/></g>')
shelf = (f'<g fill="{GREEN}"><rect x="{f(CX - half - 8)}" y="{shelf_y - 8}" width="{f(2 * half + 16)}" height="22" rx="6"/>'
         + "".join(f'<rect x="{x}" y="{shelf_y - 18}" width="28" height="14" rx="4"/>' for x in (122, 250, 376, 512))
         + "</g>")
letters = (f'<text x="{CX}" y="322" text-anchor="middle" textLength="476" lengthAdjust="spacingAndGlyphs" '
           f'font-family="\'Alfa Slab One\', \'Rockwell Extra Bold\', Rockwell, \'Roboto Slab\', Georgia, serif" '
           f'font-size="142" fill="{LETTER}">SELAR</text>')

svg = (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="14 4 632 342" role="img" aria-label="SELAR">'
       f'<g fill="{BRICK}" stroke="{BRICK}" stroke-width="5" stroke-linejoin="round">{bricks}{legs}</g>'
       f'{dome}{shelf}{cards}{sparkle}{letters}</svg>\n')
with open(OUT, "w", encoding="utf-8", newline="\n") as fh:
    fh.write(svg)
print(len(svg), "bytes")
