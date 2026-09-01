#!/usr/bin/env python3
"""
WYSIWYG — prepare images for the rotating server.

Every request is served `no-store` by design, so each view re-downloads the whole
file. Source images here run ~1.5 MB median, which is 2-5 seconds of blank space
in an eBay listing on mobile — that reads as broken, not deliberate.

The set is two different kinds of picture and they need different codecs:

  * flat UI placeholders (broken-image icons, "photo unavailable") — mostly one
    colour, thin text and lines. JPEG rings badly around that; quantised PNG is
    both sharper AND far smaller.
  * photographic AI generations — continuous tone, where PNG is enormous and
    JPEG is near-lossless at q85.

So this encodes BOTH ways per image and keeps whichever is smaller. Guessing by
extension would get it backwards for most of this set.

    python3 tools/prep-images.py ../images-source
    python3 tools/prep-images.py --max-edge 1400 --quality 82 ../images-source
    python3 tools/prep-images.py --dry-run ../images-source

Reads a directory (or explicit files); writes into server/images/.
Originals are never modified.
"""
import argparse, os, sys, io, shutil, subprocess, tempfile
from PIL import Image, ImageOps

HERE = os.path.dirname(os.path.abspath(__file__))
OUT  = os.path.join(os.path.dirname(HERE), "images")
EXTS = (".jpg", ".jpeg", ".png", ".gif", ".webp", ".bmp", ".tif", ".tiff", ".svg")


def open_image(path):
    """PIL cannot read SVG, and broken-image placeholders are often SVG.
    Rasterise via cairosvg if installed, else macOS Quick Look."""
    if not path.lower().endswith(".svg"):
        return Image.open(path)
    try:
        import cairosvg
        buf = io.BytesIO()
        cairosvg.svg2png(url=path, write_to=buf, output_width=1600)
        buf.seek(0)
        return Image.open(buf)
    except ImportError:
        pass
    if shutil.which("qlmanage"):
        tmp = tempfile.mkdtemp()
        try:
            subprocess.run(["qlmanage", "-t", "-s", "1600", "-o", tmp, path],
                           stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, check=False)
            made = [os.path.join(tmp, f) for f in os.listdir(tmp)]
            if made:
                im = Image.open(made[0])
                im.load()          # read before the temp dir goes away
                return im
        finally:
            shutil.rmtree(tmp, ignore_errors=True)
    raise OSError("cannot rasterise SVG (install cairosvg, or run on macOS)")


def encode_jpeg(im, quality):
    b = io.BytesIO()
    im.convert("RGB").save(b, "JPEG", quality=quality, optimize=True, progressive=True)
    return b.getvalue()


def encode_png(im):
    """Quantised PNG. Enormous win on flat graphics, useless on photos."""
    best = None
    rgb = im.convert("RGB")
    for colors in (64, 256):
        b = io.BytesIO()
        try:
            rgb.quantize(colors=colors, method=Image.MEDIANCUT).save(b, "PNG", optimize=True)
        except Exception:
            continue
        v = b.getvalue()
        if best is None or len(v) < len(best):
            best = v
    return best


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("sources", nargs="+", help="directories and/or image files")
    ap.add_argument("--max-edge", type=int, default=1600)
    ap.add_argument("--quality", type=int, default=85)
    ap.add_argument("--normalize", action="store_true",
                    help="letterbox onto one canvas so the eBay layout never jumps")
    ap.add_argument("--canvas", default="3:2")
    ap.add_argument("--bg", default="black")
    ap.add_argument("--start-index", type=int, default=1,
                    help="first output number. Filenames are permanent identity — "
                         "never reuse one, or published eras would silently point "
                         "at different pictures than they recorded.")
    ap.add_argument("--dry-run", action="store_true")
    a = ap.parse_args()

    files = []
    for s in a.sources:
        if os.path.isdir(s):
            for f in sorted(os.listdir(s)):
                if f.startswith(".") or not f.lower().endswith(EXTS):
                    continue
                files.append(os.path.join(s, f))
        elif os.path.isfile(s):
            files.append(s)
    if not files:
        sys.exit("no images found")

    os.makedirs(OUT, exist_ok=True)

    canvas = None
    if a.normalize:
        cw, ch = (int(x) for x in a.canvas.split(":"))
        canvas = ((a.max_edge, round(a.max_edge * ch / cw)) if cw >= ch
                  else (round(a.max_edge * cw / ch), a.max_edge))

    src_total = out_total = 0
    n_jpeg = n_png = n_skip = 0
    seen = {}

    for i, src in enumerate(files, 1):
        try:
            im = open_image(src)
            im = ImageOps.exif_transpose(im)
            if im.mode in ("RGBA", "LA", "P"):
                # SVG and PNG transparency composites to black in JPEG; these are
                # placeholder graphics, which are overwhelmingly light-on-white.
                bg = Image.new("RGB", im.size, "white")
                im = im.convert("RGBA")
                bg.paste(im, mask=im.split()[-1])
                im = bg
        except Exception as e:
            print(f"  skip {os.path.basename(src)[:50]}: {e}")
            n_skip += 1
            continue

        if canvas:
            im = ImageOps.pad(im.convert("RGB"), canvas, color=a.bg, centering=(0.5, 0.5))
        else:
            im.thumbnail((a.max_edge, a.max_edge), Image.LANCZOS)

        jpg = encode_jpeg(im, a.quality)
        png = encode_png(im)
        use_png = png is not None and len(png) < len(jpg)
        data, ext = (png, ".png") if use_png else (jpg, ".jpeg")

        # Deterministic, collision-free, URL-safe names. The originals carry
        # spaces and 100+ character Midjourney prompts; rotation order is the
        # sort order of these names, so a stable numeric prefix keeps it sane.
        stem = f"{i + a.start_index - 1:04d}"
        name = stem + ext
        while name in seen:
            stem += "x"; name = stem + ext
        seen[name] = True

        src_total += os.path.getsize(src)
        out_total += len(data)
        n_png += use_png
        n_jpeg += not use_png

        if not a.dry_run:
            with open(os.path.join(OUT, name), "wb") as fh:
                fh.write(data)

        if i % 100 == 0 or i == len(files):
            print(f"  {i:>5}/{len(files)}  {out_total/1048576:7.1f} MB out "
                  f"({src_total/1048576:7.1f} MB in)", flush=True)

    n = n_jpeg + n_png
    print(f"\n{n} image(s) -> {OUT}"
          f"{'  [DRY RUN, nothing written]' if a.dry_run else ''}")
    print(f"  jpeg {n_jpeg}   png {n_png}   skipped {n_skip}")
    print(f"  {src_total/1048576:.0f} MB -> {out_total/1048576:.0f} MB "
          f"({100*out_total/src_total:.1f}%),  mean {out_total/max(n,1)/1024:.0f} KB")
    if not a.dry_run and n:
        print("\nNext: ./tools/deploy.sh --images, then publish a new era.")


if __name__ == "__main__":
    main()
