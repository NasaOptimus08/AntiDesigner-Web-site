# -*- coding: utf-8 -*-
"""Фото товаров → прозрачный фон + WebP.

  python3 cutout.py --src assets/prod --out dist/img --prefix prod- --size 560
  python3 cutout.py --src assets/prod --out dist/img --prefix prod- --thresh 18
  python3 cutout.py --check /tmp/sheet.png --out dist/img --prefix prod-

Если у исходника уже есть альфа — фон НЕ трогаем (только кроп+ресайз).
"""
import argparse, glob, os
from PIL import Image, ImageDraw, ImageFilter


def has_alpha(im):
    """Настоящая ли прозрачность.

    Наличия канала мало: у многих PNG альфа есть, но забита 255, а белый фон
    запечён в пиксели. Поэтому смотрим, есть ли реально прозрачные пиксели.
    """
    if im.mode == "P":
        im = im.convert("RGBA")
    if im.mode not in ("RGBA", "LA"):
        return False
    a = im.convert("RGBA").getchannel("A")
    lo, hi = a.getextrema()
    if lo > 250:                       # канал есть, но пустой → фон запечён
        return False
    share = sum(1 for v in a.getdata() if v < 20) / (im.width * im.height)
    return share > 0.02                # прозрачного должно быть заметно


def cutout(im, thresh):
    """Убрать фон заливкой от всего периметра. Вернуть RGBA."""
    im = im.convert("RGB")
    w, h = im.size
    work, KEY = im.copy(), (255, 0, 255)
    seeds = ([(x, 0) for x in range(0, w, 6)] + [(x, h - 1) for x in range(0, w, 6)] +
             [(0, y) for y in range(0, h, 6)] + [(w - 1, y) for y in range(0, h, 6)])
    for s in seeds:
        if work.getpixel(s) != KEY:
            try:
                ImageDraw.floodfill(work, s, KEY, thresh=thresh)
            except Exception:
                pass
    px = work.load()
    alpha = Image.new("L", (w, h), 255)
    ap = alpha.load()
    for y in range(h):
        for x in range(w):
            if px[x, y] == KEY:
                ap[x, y] = 0
    alpha = alpha.filter(ImageFilter.GaussianBlur(0.7))
    out = im.convert("RGBA")
    out.putalpha(alpha)
    return out


def process(src, out_dir, prefix, size, thresh, only):
    os.makedirs(out_dir, exist_ok=True)
    files = sorted(f for f in glob.glob(os.path.join(src, "*"))
                   if f.lower().endswith((".png", ".jpg", ".jpeg", ".webp")))
    for f in files:
        stem = os.path.splitext(os.path.basename(f))[0]
        if only and stem not in only:
            continue
        im = Image.open(f)
        if has_alpha(im):
            out, how = im.convert("RGBA"), "альфа уже была"
        else:
            out, how = cutout(im, thresh), "вырезан фон (порог %d)" % thresh
        bbox = out.getbbox()
        if bbox:
            out = out.crop(bbox)
        out.thumbnail((size, size), Image.LANCZOS)
        dst = os.path.join(out_dir, "%s%s.webp" % (prefix, stem))
        out.save(dst, "WEBP", quality=88, method=6)
        a = out.getchannel("A")
        opaque = sum(1 for v in a.getdata() if v > 200) / (out.width * out.height)
        print("%-16s %-22s %sx%s  продукт %.0f%%" %
              (stem, how, out.width, out.height, opaque * 100))


def check(path, out_dir, prefix, cell=210, cols=6):
    """Контрольный лист на контрастном фоне: видно и остатки фона, и дырки."""
    files = sorted(glob.glob(os.path.join(out_dir, prefix + "*.webp")))
    if not files:
        print("нечего проверять")
        return
    rows = (len(files) + cols - 1) // cols
    sheet = Image.new("RGB", (cols * cell, rows * cell), (255, 80, 160))
    for i, f in enumerate(files):
        im = Image.open(f).convert("RGBA")
        im.thumbnail((cell - 16, cell - 16), Image.LANCZOS)
        sheet.paste(im, ((i % cols) * cell + (cell - im.width) // 2,
                         (i // cols) * cell + (cell - im.height) // 2), im)
    sheet.save(path)
    print("контрольный лист:", path, "(%d шт)" % len(files))


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--src", default="assets/prod")
    ap.add_argument("--out", default="dist/img")
    ap.add_argument("--prefix", default="prod-")
    ap.add_argument("--size", type=int, default=560)
    ap.add_argument("--thresh", type=int, default=35)
    ap.add_argument("--only", nargs="*", help="обработать только эти файлы (без расширения)")
    ap.add_argument("--check", help="собрать контрольный лист в этот файл")
    a = ap.parse_args()
    if a.check:
        check(a.check, a.out, a.prefix)
    else:
        process(a.src, a.out, a.prefix, a.size, a.thresh, a.only)
