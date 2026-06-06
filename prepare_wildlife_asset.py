#!/usr/bin/env python3
"""
prepare_wildlife_asset.py

Removes solid or near-white backgrounds from wildlife illustration images
and outputs transparent PNGs ready for the GLC wildlife card asset library.

Usage:
    python prepare_wildlife_asset.py input.png output.png
    python prepare_wildlife_asset.py input.png output.png --tolerance 30 --width 600 --pad 20

Defaults are tuned for clean flat illustrations on white/near-white backgrounds.

Requires: Pillow, NumPy
    pip install Pillow numpy
"""

import argparse
import sys
from collections import deque

import numpy as np
from PIL import Image


def sample_background_colour(rgb):
    """Median of the four corner pixels — robust to texture or slight noise."""
    h, w = rgb.shape[:2]
    corners = [rgb[0, 0], rgb[0, w - 1], rgb[h - 1, 0], rgb[h - 1, w - 1]]
    return np.median(corners, axis=0).astype(np.uint8)


def colour_distance(pixels, target):
    """Per-pixel Euclidean distance in RGB space."""
    return np.sqrt(np.sum((pixels.astype(float) - target.astype(float)) ** 2, axis=-1))


def flood_fill_background(arr, tolerance):
    """
    BFS flood-fill from all four image edges.
    Returns a boolean mask (True = background pixel to erase).

    Only pixels reachable from an edge AND within `tolerance` of the sampled
    background colour are marked — interior pixels of the same shade are kept.
    """
    h, w = arr.shape[:2]
    bg_colour = sample_background_colour(arr[:, :, :3])

    dist = colour_distance(arr[:, :, :3], bg_colour)
    candidate = dist <= tolerance  # pixels that *could* be background

    visited = np.zeros((h, w), dtype=bool)
    queue = deque()

    # Seed every edge pixel that qualifies
    for x in range(w):
        for y in (0, h - 1):
            if candidate[y, x] and not visited[y, x]:
                visited[y, x] = True
                queue.append((y, x))
    for y in range(1, h - 1):
        for x in (0, w - 1):
            if candidate[y, x] and not visited[y, x]:
                visited[y, x] = True
                queue.append((y, x))

    # Expand flood fill through connected background-coloured pixels
    for dy, dx in ((-1, 0), (1, 0), (0, -1), (0, 1)):
        pass  # directions defined inline below for speed

    while queue:
        y, x = queue.popleft()
        for dy, dx in ((-1, 0), (1, 0), (0, -1), (0, 1)):
            ny, nx = y + dy, x + dx
            if 0 <= ny < h and 0 <= nx < w and not visited[ny, nx] and candidate[ny, nx]:
                visited[ny, nx] = True
                queue.append((ny, nx))

    return visited  # True = erase this pixel


def remove_background(img, tolerance):
    """Return RGBA image with background pixels set fully transparent."""
    img = img.convert("RGBA")
    arr = np.array(img)

    bg_mask = flood_fill_background(arr, tolerance)
    arr[bg_mask, 3] = 0

    return Image.fromarray(arr, "RGBA")


def autocrop(img, padding):
    """Crop to bounding box of non-transparent pixels, then add padding."""
    arr = np.array(img)
    alpha = arr[:, :, 3]

    rows = np.any(alpha > 0, axis=1)
    cols = np.any(alpha > 0, axis=0)

    if not rows.any():
        print("  Warning: image is fully transparent after background removal.")
        return img

    rmin, rmax = np.where(rows)[0][[0, -1]]
    cmin, cmax = np.where(cols)[0][[0, -1]]

    h, w = alpha.shape
    rmin = max(0, rmin - padding)
    rmax = min(h - 1, rmax + padding)
    cmin = max(0, cmin - padding)
    cmax = min(w - 1, cmax + padding)

    return img.crop((cmin, rmin, cmax + 1, rmax + 1))


def resize_to_width(img, target_width):
    """Resize preserving aspect ratio."""
    w, h = img.size
    new_h = max(1, round(h * target_width / w))
    return img.resize((target_width, new_h), Image.LANCZOS)


def main():
    parser = argparse.ArgumentParser(
        description="Remove background from a wildlife illustration and export as transparent PNG."
    )
    parser.add_argument("input",  help="Input image (PNG, JPG, etc.)")
    parser.add_argument("output", help="Output transparent PNG path")
    parser.add_argument(
        "--tolerance", type=int, default=28,
        help=(
            "Colour-distance tolerance for background detection "
            "(15–20 clean white, 25–30 off-white/textured, 30–35 heavy noise; default: 28)"
        ),
    )
    parser.add_argument(
        "--width", type=int, default=600,
        help="Output width in pixels (default: 600). Pass 0 to skip resize.",
    )
    parser.add_argument(
        "--pad", type=int, default=20,
        help="Padding in pixels to add around the subject after crop (default: 20)",
    )
    args = parser.parse_args()

    print(f"Loading   {args.input}")
    try:
        img = Image.open(args.input)
    except FileNotFoundError:
        sys.exit(f"Error: file not found — {args.input}")
    except Exception as e:
        sys.exit(f"Error opening image: {e}")

    original_size = img.size
    print(f"          {original_size[0]}×{original_size[1]}px, mode={img.mode}")

    print(f"Removing  background (tolerance={args.tolerance})")
    img = remove_background(img, args.tolerance)

    print(f"Cropping  to subject (pad={args.pad}px)")
    img = autocrop(img, args.pad)

    if args.width > 0:
        print(f"Resizing  to {args.width}px wide")
        img = resize_to_width(img, args.width)

    print(f"Saving    {args.output}")
    try:
        img.save(args.output, "PNG", optimize=True)
    except Exception as e:
        sys.exit(f"Error saving output: {e}")

    w, h = img.size
    print(f"Done.     {w}×{h}px transparent PNG → {args.output}")
    print()
    print("Next: copy the PNG to theme-dev/great-lake-cleaners-theme/assets/images/")
    print("      then add a keyword match in glc_stats_wildlife_img() in page-stats.php")


if __name__ == "__main__":
    main()
