# Great Lake Cleaners

**The lake starts here.**

A WordPress plugin and theme for [greatlakecleaners.ca](https://greatlakecleaners.ca) — a community waterway cleanup organization based in Guelph, Ontario, documenting regular cleanups of the Speed River, Eramosa River, and Hanlon Creek corridors that flow into the Great Lakes system via the Grand River and Lake Erie.

---

## Repository Contents

```
great-lake-cleaners/          — WordPress plugin
great-lake-cleaners-theme/    — WordPress theme
tracker_to_csv.py             — Data pipeline: Google Sheets → WordPress
remove_background.py          — Logo/badge background removal utility
CLAUDE.md                     — Developer context (architecture decisions, conventions)
```

---

## Plugin: `great-lake-cleaners`

Self-contained WordPress plugin. No external plugin dependencies.

### Features

- **Cleanup event post type** (`cleanup_event`) — custom post type with native meta box for logging cleanup data: date, location, GPS coordinates, bags, weight, recycling count, volunteers, notable finds, Instagram field log URL
- **Community submission form** (`[glc_submit_form]`) — public form for community members to submit their own cleanups; submissions land as pending for admin review before counting in stats
- **Waterway issue report form** (`[glc_report_form]`) — two-stage triage form routing general city issues to Guelph's ArcGIS tool and waterway issues to an email report; email-only, no post type
- **Stats shortcode** (`[glc_stats]`) — cumulative totals banner across all published events and approved submissions
- **Map shortcode** (`[glc_map]`) — Leaflet map of all cleanup locations with popups; tiles from Carto, Leaflet self-hosted
- **Archive shortcode** (`[glc_archive]`) — paginated cleanup archive with map
- **Gallery shortcode** (`[glc_gallery]`) — photo gallery with year tabs and lightbox; aggregates photos from tracker events and consent-approved community submissions
- **CSV importer** — Tools → Import Cleanups CSV; imports from the Python pipeline output; duplicate date+site pairs skipped automatically

### Plugin File Structure

```
great-lake-cleaners/
  great-lake-cleaners.php     — main loader, activation hook
  assets/
    leaflet.js                — Leaflet 1.9.4 (self-hosted)
    leaflet.css               — Leaflet 1.9.4 styles (self-hosted)
  includes/
    post-type.php             — cleanup_event CPT registration
    acf-fields.php            — native meta box (no ACF required)
    admin.php                 — list table columns, sortable date
    shortcodes.php            — all shortcode handlers
    import.php                — CSV import tool
    submission.php            — glc_submission CPT + [glc_submit_form]
    report.php                — [glc_report_form] shortcode (email-only)
```

### Installation

1. Upload `great-lake-cleaners/` to `wp-content/plugins/`
2. Activate from **Plugins → Installed Plugins**
3. Deactivate and reactivate once to flush rewrite rules

### Spam Protection

Both public forms use layered spam protection:
- **WordPress nonce** — prevents cross-site request forgery
- **Honeypot field** — hidden field bots fill in; silently discards submission
- **Rate limiting** — max 5 submissions per IP per 10 minutes, tracked via WordPress transients; counter only increments on successful send (validation failures don't burn a slot)

To reset rate limit transients:
```bash
wp transient delete --all
```

---

## Theme: `great-lake-cleaners-theme`

Custom WordPress theme. Requires the plugin to be active for shortcodes to render.

### Theme File Structure

```
great-lake-cleaners-theme/
  style.css                       — all styles + @font-face declarations
  functions.php                   — enqueues, nav menus, theme support
  header.php                      — sticky nav, site header
  footer.php                      — wave SVG, stats strip, footer
  front-page.php                  — home page template
  page.php                        — standard page template
  page-submit-cleanup.php         — Submit a Cleanup page shell + sidebar
  page-report-issue.php           — Report an Issue page shell + sidebar
  page-privacy-policy.php         — Privacy Policy (auto-generated content)
  page-photos.php                 — Photos gallery page
  archive-cleanup_event.php       — /cleanups/ archive
  single-cleanup_event.php        — individual cleanup event
  single-glc_submission.php       — individual community submission
  404.php
  assets/
    fonts/                        — self-hosted Nunito + Lato (woff2)
    images/                       — shield logo, illustrations, SVG icons
    js/
      nav.js                      — mobile menu toggle
```

### Visual Identity

| Token | Value |
|---|---|
| Navy | `#1a4a6b` (`--glc-navy`) |
| Gold | `#f5a623` (`--glc-gold`) |
| Green | `#2e8b57` (`--glc-green`) |
| Body font | Lato 400, 700 (self-hosted) |
| Display font | Nunito 700, 800, 900 (self-hosted) |
| Gutter | `#f0f0ee` |

### WordPress Pages Required

| Title | Slug | Notes |
|---|---|---|
| Home | `home` | Blank — set as static front page |
| Photos | `photos` | Template: Photos |
| Submit a Cleanup | `submit-cleanup` | Blank — template handles layout |
| Privacy Policy | `privacy-policy` | Blank — template handles content |
| Report an Issue | `report-issue` | Blank — template handles layout |

### Post-Deployment Steps (not in files)

These live in the WordPress database and must be re-done after a fresh install:

- **Appearance → Customize → Site Identity** — site name, tagline, favicon (`glc-badge.png`)
- **Settings → Reading** — set static front page to the Home page
- **Appearance → Menus** — rebuild primary and footer nav menus
- **Pages** — create pages above with correct slugs and templates

---

## Data Pipeline: `tracker_to_csv.py`

Pulls cleanup data from a Google Sheet and exports a CSV for WordPress import.

### Setup

**`config.toml`** (not committed — create locally):
```toml
spreadsheet_id   = "your-sheet-id-from-url"
credentials_file = "credentials.json"
```

**`credentials.json`** — Google service account key. Add to `.gitignore`. Share the Sheet with the service account's `client_email` as Viewer.

> **Never commit `credentials.json` or `config.toml` to version control.**

### Usage

```bash
python tracker_to_csv.py                    # pull from Google Sheets
python tracker_to_csv.py --no-sheets        # use local xlsx fallback
python tracker_to_csv.py --xlsx my.xlsx     # specific local file
python tracker_to_csv.py -o out.csv         # custom output path
```

### Sync Workflow

1. Log outings in the Google Sheet (`Daily Log` tab)
2. Run `python tracker_to_csv.py` → writes `cleanups/cleanups.csv`
3. WP Admin → Tools → Import Cleanups CSV → upload
4. Duplicate date + site pairs are skipped automatically

### Spreadsheet Column Layout

| Col | Field | Notes |
|---|---|---|
| A | Date | Store as date value, not text |
| B | Location / Corridor | Must match exactly for same-site merging |
| C | Duration (min) | |
| D | Bags (#) | |
| E | Weight (kg) | |
| F | Notes | Imported as post body |
| G | Cans (#) | |
| H | Bottles (#) | |
| I–L | Scrap Metal | Not exported |
| M | Number of people | |
| N | Notable Finds | |
| O | Latitude | Enter once per new site |
| P | Longitude | Negative for Ontario |
| Q | Instagram Post URL | Field log link |

---

## Utilities

### `remove_background.py`

Removes solid or textured backgrounds from badge/logo images, producing a transparent PNG.

```bash
python remove_background.py input.png output.png [tolerance]
```

Tolerance guide: `15–20` clean white · `25–30` textured (default: 28) · `30–35` heavy noise

Requires: Python 3, Pillow, NumPy

---

## Development Workflow

The site is developed locally using [WPLocal](https://localwp.com/) on Windows and deployed to the production VPS by uploading plugin and theme zips via WP Admin → Plugins/Themes → Upload.

**Local dev:**
1. Make changes to plugin or theme files
2. Zip the plugin or theme directory
3. Upload via WP Admin → Upload → Activate
4. Deactivate and reactivate the plugin once after updates to flush rewrite rules

**Production stack:** Ubuntu 24, Apache 2.4, MySQL, PHP 8.3 (`mod_php`)

---

## `.gitignore` Recommendations

```
credentials.json
config.toml
*.csv
__pycache__/
*.pyc
.DS_Store
```

---

## License

Copyright © Great Lake Cleaners. All rights reserved.

Icon SVGs from [Twemoji](https://twemoji.twitter.com/), licensed under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/).
