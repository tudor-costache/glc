"""
cleanup_report.py
-----------------
Reads cleanups/cleanups.csv and generates Instagram-ready outputs
for the most recent event (or a specified date):
  - A 1080x1080 stats card image  (reports/YYYY-MM-DD_site/card.png)
  - A caption text file            (reports/YYYY-MM-DD_site/caption.txt)

Usage:
  python cleanup_report.py              # latest event
  python cleanup_report.py 2026-04-05   # specific date
"""

import csv
import os
import sys
import textwrap
from datetime import datetime
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

# ── Configuration ────────────────────────────────────────────────────────────

ORG_NAME    = "Great Lake Cleaners"   # update when you have a name
IG_HANDLE   = "@greatlakecleaners"    # update when you have an account
CSV_PATH    = Path("cleanups/cleanups.csv")
REPORTS_DIR = Path("reports")

HASHTAGS = (
    "#GreatLakeCleaners #SpeedRiver #EramosoRiver #HanlonCreek "
    "#GuelphOntario #GrandRiver #CleanupGuelph #RiverCleanup "
    "#VolunteerGuelph #NativeSpecies #RipiarianRestoration #GRCA"
)

# Palette — deep river teal + warm sand
COL_BG        = (18,  78,  76)    # deep teal
COL_CARD      = (24,  98,  96)    # slightly lighter teal panel
COL_ACCENT    = (162, 213, 171)   # soft green
COL_WHITE     = (245, 245, 240)
COL_MUTED     = (160, 200, 195)
COL_STAT_BG   = (12,  55,  54)    # dark stat tile

FONT_BOLD   = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
FONT_REG    = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"

# ── Data loading ─────────────────────────────────────────────────────────────

def load_events():
    if not CSV_PATH.exists():
        raise FileNotFoundError(f"CSV not found: {CSV_PATH}")
    with open(CSV_PATH, newline="", encoding="utf-8") as f:
        return list(csv.DictReader(f))


def pick_event(events, date_str=None):
    if not events:
        raise ValueError("No events in CSV.")
    if date_str:
        matches = [e for e in events if e["date"] == date_str]
        if not matches:
            raise ValueError(f"No event found for date {date_str}")
        return matches[-1]
    return events[-1]   # most recent row


def safe_int(val, default=0):
    try:
        return int(val) if val.strip() else default
    except (ValueError, AttributeError):
        return default


def safe_float(val, default=0.0):
    try:
        return float(val) if val.strip() else default
    except (ValueError, AttributeError):
        return default

# ── Image card ───────────────────────────────────────────────────────────────

SIZE = 1080

def font(path, size):
    return ImageFont.truetype(path, size)


def draw_rounded_rect(draw, xy, radius, fill):
    x0, y0, x1, y1 = xy
    draw.rounded_rectangle([x0, y0, x1, y1], radius=radius, fill=fill)


def centered_text(draw, text, y, fnt, color, width=SIZE):
    bbox = draw.textbbox((0, 0), text, font=fnt)
    w = bbox[2] - bbox[0]
    draw.text(((width - w) / 2, y), text, font=fnt, fill=color)


def make_card(event, out_path):
    date_str    = event["date"]
    site        = event["site_name"]
    volunteers  = safe_int(event["volunteers"])
    hours       = safe_float(event["hours"])
    bags        = safe_int(event["bags"])
    weight      = safe_float(event["weight_kg"])
    planted     = safe_int(event["species_planted"])
    bank        = safe_float(event["meters_bank_cleared"])
    wildlife    = event.get("wildlife_obs", "").strip()

    dt = datetime.strptime(date_str, "%Y-%m-%d")
    date_display = dt.strftime("%B %-d, %Y")

    img  = Image.new("RGB", (SIZE, SIZE), COL_BG)
    draw = ImageDraw.Draw(img)

    # ── background texture: subtle horizontal bands ──
    for y in range(0, SIZE, 6):
        alpha = 8 if (y // 6) % 2 == 0 else 0
        draw.line([(0, y), (SIZE, y)], fill=(255, 255, 255), width=1)

    # ── top accent bar ──
    draw.rectangle([(0, 0), (SIZE, 6)], fill=COL_ACCENT)

    # ── org name ──
    f_org = font(FONT_BOLD, 34)
    centered_text(draw, ORG_NAME.upper(), 28, f_org, COL_ACCENT)

    # ── divider ──
    draw.line([(80, 82), (SIZE - 80, 82)], fill=COL_MUTED, width=1)

    # ── site name (wrapped) ──
    f_site = font(FONT_BOLD, 52)
    words = site.split()
    lines, line = [], []
    for w in words:
        test = " ".join(line + [w])
        bbox = draw.textbbox((0, 0), test, font=f_site)
        if bbox[2] - bbox[0] > SIZE - 100 and line:
            lines.append(" ".join(line))
            line = [w]
        else:
            line.append(w)
    if line:
        lines.append(" ".join(line))

    y_site = 100
    for ln in lines[:3]:
        centered_text(draw, ln, y_site, f_site, COL_WHITE)
        y_site += 62

    # ── date ──
    f_date = font(FONT_REG, 32)
    centered_text(draw, date_display, y_site + 8, f_date, COL_MUTED)

    # ── stat tiles ──
    stats = [
        ("🧑‍🤝‍🧑", str(volunteers), "Volunteers"),
        ("⏱", f"{hours:.1f}h", "Hours"),
        ("🛍", str(bags), "Bags Out"),
        ("⚖", f"{weight:.0f}kg", "Debris"),
    ]
    if planted:
        stats.append(("🌿", str(planted), "Plants In"))
    if bank:
        stats.append(("📏", f"{bank:.0f}m", "Bank Cleared"))

    # layout: up to 4 per row
    n = len(stats)
    cols = min(n, 4)
    rows = (n + cols - 1) // cols
    tile_w = (SIZE - 80 - (cols - 1) * 16) // cols
    tile_h = 145
    x0_grid = 40
    y0_grid = y_site + 70

    f_ico  = font(FONT_REG,  44)
    f_val  = font(FONT_BOLD, 46)
    f_lbl  = font(FONT_REG,  24)

    for i, (icon, value, label) in enumerate(stats):
        row, col = divmod(i, cols)
        tx = x0_grid + col * (tile_w + 16)
        ty = y0_grid + row * (tile_h + 14)
        draw_rounded_rect(draw, [tx, ty, tx + tile_w, ty + tile_h], 14, COL_STAT_BG)
        # icon
        draw.text((tx + 14, ty + 12), icon, font=f_ico, fill=COL_ACCENT)
        # value
        bbox = draw.textbbox((0, 0), value, font=f_val)
        vw = bbox[2] - bbox[0]
        draw.text((tx + tile_w - vw - 14, ty + 14), value, font=f_val, fill=COL_WHITE)
        # label
        draw.text((tx + 14, ty + tile_h - 34), label, font=f_lbl, fill=COL_MUTED)

    # ── wildlife line ──
    y_bottom = y0_grid + rows * (tile_h + 14) + 10
    if wildlife:
        f_wl = font(FONT_REG, 28)
        wl_text = f"👀  {wildlife}"
        # wrap
        wrapped = textwrap.fill(wl_text, width=52)
        for ln in wrapped.split("\n"):
            centered_text(draw, ln, y_bottom, f_wl, COL_ACCENT)
            y_bottom += 36

    # ── cumulative totals (if more than 1 event) ──
    events = load_events()
    if len(events) > 1:
        total_bags = sum(safe_int(e["bags"]) for e in events)
        total_vol  = sum(safe_int(e["volunteers"]) for e in events)
        total_hrs  = sum(safe_float(e["hours"]) for e in events)
        f_cum = font(FONT_REG, 26)
        cum_line = f"All-time: {total_vol} volunteers · {total_hrs:.0f}h · {total_bags} bags removed"
        draw_rounded_rect(draw,
            [40, SIZE - 110, SIZE - 40, SIZE - 66], 10, COL_CARD)
        centered_text(draw, cum_line, SIZE - 104, f_cum, COL_MUTED)

    # ── handle + bottom accent ──
    f_handle = font(FONT_BOLD, 30)
    centered_text(draw, IG_HANDLE, SIZE - 56, f_handle, COL_ACCENT)
    draw.rectangle([(0, SIZE - 6), (SIZE, SIZE)], fill=COL_ACCENT)

    img.save(out_path, "PNG")
    print(f"  Card saved → {out_path}")

# ── Caption ──────────────────────────────────────────────────────────────────

def make_caption(event, out_path):
    date_str   = event["date"]
    site       = event["site_name"]
    volunteers = safe_int(event["volunteers"])
    hours      = safe_float(event["hours"])
    bags       = safe_int(event["bags"])
    weight     = safe_float(event["weight_kg"])
    planted    = safe_int(event["species_planted"])
    bank       = safe_float(event["meters_bank_cleared"])
    notable    = event.get("notable_finds", "").strip()
    wildlife   = event.get("wildlife_obs", "").strip()
    notes      = event.get("notes", "").strip()

    dt = datetime.strptime(date_str, "%Y-%m-%d")
    date_display = dt.strftime("%B %-d, %Y")

    lines = []
    lines.append(f"🌊 Cleanup at {site} — {date_display}")
    lines.append("")

    # lead sentence
    vol_str = f"{volunteers} volunteer{'s' if volunteers != 1 else ''}"
    hr_str  = f"{hours:.1f} hour{'s' if hours != 1.0 else ''}"
    lines.append(f"Today {vol_str} spent {hr_str} at {site}, "
                 f"removing {bags} bags ({weight:.0f} kg) of debris from the riverbank.")

    if planted:
        lines.append(f"We also put {planted} native plant{'s' if planted != 1 else ''} "
                     f"in the ground to help stabilize the bank. 🌿")
    if bank:
        lines.append(f"{bank:.0f} metres of bank cleared of invasive vegetation.")

    if notable:
        lines.append(f"\n🔍 Notable finds: {notable}")

    if wildlife:
        lines.append(f"👀 Wildlife spotted: {wildlife}")

    if notes:
        lines.append(f"\n{notes}")

    lines.append("")
    lines.append("Want to join the next cleanup? Follow along and watch for our next event date.")
    lines.append("")
    lines.append(HASHTAGS)

    caption = "\n".join(lines)
    with open(out_path, "w", encoding="utf-8") as f:
        f.write(caption)
    print(f"  Caption saved → {out_path}")
    print()
    print("─" * 60)
    print(caption)
    print("─" * 60)

# ── Main ─────────────────────────────────────────────────────────────────────

def main():
    date_arg = sys.argv[1] if len(sys.argv) > 1 else None
    events   = load_events()
    event    = pick_event(events, date_arg)

    date_str  = event["date"]
    site_slug = event["site_name"].lower().replace(" ", "-").replace("–", "").replace("—", "")
    site_slug = "".join(c for c in site_slug if c.isalnum() or c == "-")[:40]
    folder    = REPORTS_DIR / f"{date_str}_{site_slug}"
    folder.mkdir(parents=True, exist_ok=True)

    print(f"\nGenerating report for: {event['site_name']} on {event['date']}\n")
    make_card(event,    folder / "card.png")
    make_caption(event, folder / "caption.txt")
    print(f"\nOutputs in: {folder}/")


if __name__ == "__main__":
    main()
