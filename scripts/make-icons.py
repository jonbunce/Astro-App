"""
Build the app's icons and splash image from the club badge.

    python scripts/make-icons.py path/to/badge.png

The badge should be a PNG with a transparent background, ideally 1024px or larger
(smaller ones are scaled up and look softer). Writes into app/assets/images/:

  icon.png                     iOS / store icon: badge on carbon with a red glow (1024, no transparency)
  icon-tinted.png              iOS 18+ tinted icon: white badge artwork on transparent
  android-icon-background.png  adaptive icon background (carbon + glow)
  android-icon-foreground.png  adaptive icon foreground (badge inside the safe zone)
  android-icon-monochrome.png  themed icon and notification icon (badge with its black parts cut out)
  splash-icon.png              splash screen badge
  favicon.png                  web preview

Needs Pillow (pip install pillow).
"""

import sys
from pathlib import Path

from PIL import Image, ImageChops, ImageFilter

CARBON = (11, 12, 15)
RED = (200, 16, 46)
GOLD = (227, 154, 0)
OUT = Path(__file__).resolve().parent.parent / 'app' / 'assets' / 'images'


def load_badge(path):
    """The badge cropped to its visible pixels, on a square transparent canvas."""
    im = Image.open(path).convert('RGBA')
    box = im.getchannel('A').getbbox()
    if not box:
        sys.exit('The badge image is fully transparent.')
    im = im.crop(box)
    side = max(im.size)
    square = Image.new('RGBA', (side, side), (0, 0, 0, 0))
    square.paste(im, ((side - im.width) // 2, (side - im.height) // 2))
    if side < 1024:
        print(f'Note: the badge is only {side}px across; a 1024px+ original will give sharper icons.')
    return square


def sized(badge, px):
    return badge.resize((px, px), Image.LANCZOS)


def glow(size, colour, centre, radius, strength):
    """A soft round glow of colour, as an RGBA layer."""
    # radial_gradient is 256x256: 0 at the centre rising to 255 at the corners.
    falloff = Image.radial_gradient('L').resize((int(radius * 2), int(radius * 2)), Image.BICUBIC)
    mask = Image.new('L', (size, size), 0)
    mask.paste(falloff.point(lambda v: max(0, 255 - v * 2) * strength), (int(centre[0] - radius), int(centre[1] - radius)))
    layer = Image.new('RGBA', (size, size), colour + (0,))
    layer.putalpha(mask)
    return layer


def background(size):
    bg = Image.new('RGBA', (size, size), CARBON + (255,))
    bg.alpha_composite(glow(size, RED, (size * 0.5, size * 0.42), size * 0.62, 0.55))
    bg.alpha_composite(glow(size, GOLD, (size * 0.92, size * 0.96), size * 0.42, 0.16))
    return bg


def with_shadow(canvas, art, pos, blur, offset, opacity):
    shadow = Image.new('RGBA', art.size, (0, 0, 0, 0))
    shadow.putalpha(art.getchannel('A').point(lambda a: a * opacity))
    shadow = shadow.filter(ImageFilter.GaussianBlur(blur))
    canvas.alpha_composite(shadow, (pos[0], pos[1] + offset))
    canvas.alpha_composite(art, pos)


def cutout(badge):
    """White where the badge is coloured or white, transparent where it's black (its outlines and lettering)."""
    rgb = badge.convert('RGB')
    r, g, b = rgb.split()
    bright = ImageChops.lighter(ImageChops.lighter(r, g), b)
    keep = bright.point(lambda v: 0 if v < 60 else 255 if v > 150 else (v - 60) * 255 // 90)
    alpha = ImageChops.multiply(keep, badge.getchannel('A'))
    out = Image.new('RGBA', badge.size, (255, 255, 255, 0))
    out.putalpha(alpha)
    return out


def centred(art, size):
    canvas = Image.new('RGBA', (size, size), (0, 0, 0, 0))
    canvas.alpha_composite(art, ((size - art.width) // 2, (size - art.height) // 2))
    return canvas


def main():
    if len(sys.argv) != 2:
        sys.exit(__doc__)
    badge = load_badge(sys.argv[1])

    icon = background(1024)
    art = sized(badge, 760)
    with_shadow(icon, art, (132, 132), blur=22, offset=16, opacity=0.6)
    icon.convert('RGB').save(OUT / 'icon.png')

    centred(cutout(sized(badge, 760)), 1024).save(OUT / 'icon-tinted.png')

    background(1024).convert('RGB').save(OUT / 'android-icon-background.png')
    centred(sized(badge, 600), 1024).save(OUT / 'android-icon-foreground.png')
    centred(cutout(sized(badge, 600)), 1024).save(OUT / 'android-icon-monochrome.png')

    sized(badge, 1024).save(OUT / 'splash-icon.png')
    sized(badge, 196).save(OUT / 'favicon.png')
    print(f'Wrote icons to {OUT}')


if __name__ == '__main__':
    main()
