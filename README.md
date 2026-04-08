# Great Lake Cleaners — WordPress Plugin

Self-contained plugin for logging cleanup events, displaying cumulative
stats, and showing a site map. No page builders required.

---

## Requirements

- WordPress 6.0+
- [Advanced Custom Fields (ACF)](https://wordpress.org/plugins/advanced-custom-fields/) — free version

---

## Installation

1. Copy the `great-lake-cleaners/` folder into `wp-content/plugins/`
2. Install and activate **Advanced Custom Fields** from the WP plugin directory
3. Activate **Great Lake Cleaners** from Plugins → Installed Plugins
4. Visit Settings → Permalinks and click **Save Changes** (flushes rewrite rules)

---

## Importing existing CSV data

1. Go to **Tools → Import Cleanups CSV**
2. Upload your `cleanups.csv` — columns must match the Python script format:
   `date, site_name, gps_lat, gps_lon, volunteers, hours, bags, weight_kg,
    species_planted, meters_bank_cleared, notable_finds, wildlife_obs, notes,
    photo_folder, best_photo`
3. Duplicate date+site combinations are skipped automatically

---

## Logging a new cleanup

1. **Cleanup Events → Log New Cleanup** in the WP admin sidebar
2. Set the **title** to anything descriptive (it's not shown publicly — site name is)
3. Fill in the **Cleanup Details** fields below the editor
4. Use the **editor area** for freeform notes / narrative
5. Publish

---

## Shortcodes

Place these on any page or in your theme:

| Shortcode | Output |
|-----------|--------|
| `[glc_stats]` | Cumulative totals banner (events, volunteers, hours, bags, kg, plants) |
| `[glc_map]` | Leaflet map of all cleanup sites with popups |
| `[glc_archive]` | Card list of recent cleanups (default: 20) |
| `[glc_archive limit="10"]` | Limit to N most recent |

**Suggested home page layout:**
```
[glc_stats]
[glc_map]
[glc_archive limit="5"]
```

---

## Generating Instagram cards

Run locally after exporting or syncing data:

```bash
python cleanup_report.py              # latest event from cleanups.csv
python cleanup_report.py 2026-04-05   # specific date
```

Outputs land in `reports/YYYY-MM-DD_sitename/`:
- `card.png`     — 1080×1080 stats card, ready to post
- `caption.txt`  — caption with hashtags, ready to copy/paste

---

## Archive URL

All published cleanup events are listed at `/cleanups/` automatically.
Individual events are at `/cleanups/post-slug/`.

To style the archive, add `archive-cleanup_event.php` and
`single-cleanup_event.php` to your child theme.

---

## Updating ORG name / Instagram handle

Edit the constants at the top of `cleanup_report.py`:
```python
ORG_NAME   = "Great Lake Cleaners"
IG_HANDLE  = "@greatlakecleaners"
```
