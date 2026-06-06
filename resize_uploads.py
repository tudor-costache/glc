#!/usr/bin/env python3
"""
resize_uploads.py

Batch-resize images in a directory to a maximum dimension, in place.
Any image wider or taller than --max is resized (aspect ratio preserved).
Images already within the limit are skipped unchanged.

Usage:
    python resize_uploads.py /path/to/wp-content/uploads
    python resize_uploads.py /path/to/wp-content/uploads --max 1200 --quality 85
    python resize_uploads.py /path/to/wp-content/uploads --dry-run

Requires: Pillow
    pip install Pillow
"""

import argparse
import sys
from pathlib import Path

from PIL import Image

EXTENSIONS = {'.jpg', '.jpeg', '.png', '.webp'}


def resize_image(path: Path, max_size: int, quality: int, dry_run: bool) -> tuple[bool, str]:
    try:
        with Image.open(path) as img:
            w, h = img.size
            if w <= max_size and h <= max_size:
                return False, f"  skip   {w}×{h}  {path.name}"

            scale = max_size / max(w, h)
            new_w = max(1, round(w * scale))
            new_h = max(1, round(h * scale))
            info  = f"  resize {w}×{h} → {new_w}×{new_h}  {path.name}"

            if dry_run:
                return True, info + "  [dry run]"

            fmt = img.format or 'JPEG'
            mode = img.mode
            if fmt == 'JPEG' and mode in ('RGBA', 'P', 'LA'):
                img = img.convert('RGB')

            resized = img.resize((new_w, new_h), Image.LANCZOS)

            save_kw = {}
            if fmt in ('JPEG', 'WEBP'):
                save_kw['quality'] = quality
                save_kw['optimize'] = True
            elif fmt == 'PNG':
                save_kw['optimize'] = True

            resized.save(path, fmt, **save_kw)
            return True, info

    except Exception as exc:
        return False, f"  ERROR  {path.name}: {exc}"


def main():
    parser = argparse.ArgumentParser(
        description="Batch-resize images in a directory to a maximum dimension, in place."
    )
    parser.add_argument("directory",
                        help="Directory to scan recursively (e.g. /var/www/wp-content/uploads)")
    parser.add_argument("--max", type=int, default=1200,
                        help="Maximum width or height in pixels (default: 1200)")
    parser.add_argument("--quality", type=int, default=85,
                        help="JPEG/WebP save quality, 1–95 (default: 85)")
    parser.add_argument("--dry-run", action="store_true",
                        help="Report what would be changed without writing any files")
    args = parser.parse_args()

    root = Path(args.directory)
    if not root.is_dir():
        sys.exit(f"Error: not a directory — {root}")

    images = sorted(p for p in root.rglob("*") if p.suffix.lower() in EXTENSIONS and p.is_file())

    print(f"Scanning  {root}")
    if args.dry_run:
        print("          (dry run — no files will be written)")
    print(f"Found     {len(images)} image(s)\n")

    if not images:
        return

    resized = skipped = errors = 0
    for path in images:
        changed, msg = resize_image(path, args.max, args.quality, args.dry_run)
        print(msg)
        if "ERROR" in msg:
            errors += 1
        elif changed:
            resized += 1
        else:
            skipped += 1

    print(f"\nDone.  Resized: {resized}  Skipped (already small): {skipped}  Errors: {errors}")
    if args.dry_run and resized:
        print("       Re-run without --dry-run to apply changes.")


if __name__ == "__main__":
    main()
