# Great Lake Cleaners — Project Context

## Identity

**Organization:** Great Lake Cleaners  
**Tagline:** The lake starts here.  
**Mission:** Regular cleanups of Guelph's local waterways — by foot and paddle — that flow into the Great Lakes system via the Grand River and Lake Erie.  
**Location:** Guelph, Ontario, Canada  
**Stage:** Pre-incorporation, Phase 1 (personal/family effort, year one)

## Online Presence

| Asset | Value |
|---|---|
| Primary domain | greatlakecleaners.ca |
| Domain registrar | CanSpace |
| Instagram | @greatlakecleaners |
| Facebook | (to claim) |
| .org domain | Not registered yet — revisit at incorporation |

## Instagram Bio (final)

```
The lake starts here. 🌊
Cleaning Guelph's rivers and shores by
foot and paddle — because what enters
our waterways reaches the Great Lakes.

🔗 greatlakecleaners.ca
```

## First Pinned Post (approved)

```
The lake starts here. 🌊

Guelph sits at the confluence of the Speed and Eramosa Rivers. From here,
water flows into the Grand River, through southwestern Ontario, and into
Lake Erie — one of the five Great Lakes that hold a fifth of the world's
fresh surface water.

What gets left on a riverbank in Guelph doesn't stay in Guelph.

Great Lake Cleaners is a Guelph-based group doing regular cleanups along
the Speed River, Eramosa River, and Hanlon Creek corridors — by foot on
the shores and by paddle on the water. We locate and remove what shouldn't
be there before it travels downstream.

Small local effort. Watershed-scale impact.

We're just getting started. Follow along. 🛶

#GreatLakeCleaners #SpeedRiver #EramosoRiver #HanlonCreek #GuelphOntario
#GrandRiver #LakeErie #GreatLakes #RiverCleanup #CleanupGuelph
#Watershed #FreshWater
```

## Operating Corridors

- Speed River
- Eramosa River
- Hanlon Creek
- Guelph Lake area (secondary)

## Watershed Chain

Speed/Eramosa Rivers → Grand River → Lake Erie → Great Lakes system

## Cleanup Methodology

- Shore cleanups on foot (dog walks = regular informal outings)
- Paddle cleanups on water
- Family outings — volunteers tracked per outing
- Scrap metal and deposit-return items collected for revenue to fund operations

## Phase Roadmap

| Phase | Timeline | Focus |
|---|---|---|
| 1 — Individual | Now–~1 year | Personal/family cleanups, documentation, relationship building |
| 2 — Informal group | 6–18 months | Unincorporated association, small donor base, city/GRCA relationships |
| 3 — Nonprofit incorporation | 18–36 months | Provincial incorporation under ONCA, CRA charitable registration |

## Key Relationships to Build

- **City of Guelph** — Clean and Green program (April), parks permits
- **GRCA** — Site access permits for conservation areas, Grand River Conservation Foundation (grants)
- **OPIRG Guelph** — Speed River Project, existing volunteer network (collaborate not compete)
- **Wellington Water Watchers** — 2Rivers Festival partnership history with OPIRG

## Revenue Streams (informal phase)

- Scrap metal → Benmet Steel & Metal, 415 Elizabeth St (519-763-1209) or B&F Scrap (226-566-1630)
- Deposit return containers → Beer Store (verify nearest open location at thebeerstore.ca/where-to-return-empties)
- Eventually: grants (GRCA Foundation, TD Friends of the Environment, Ontario Trillium Foundation)

## Fundraising Note

Indiegogo and crowdfunding not appropriate for Phase 1. Better options:
- Simple donate/e-transfer page on own site for Phase 1
- Grants become viable after ONCA incorporation (Phase 3)

## Legal / Incorporation Notes

- Incorporate provincially under ONCA ($155, ServiceOntario) when ready
- CRA charitable registration takes 6–12 months — file early
- Environmental restoration qualifies under "other purposes beneficial to the community"
- Purposes clause is the most scrutinized part of CRA application — draft carefully

---

## Technical Infrastructure

### Hosting
- **Local dev:** WPLocal (Windows) — WordPress 6.9.4
- **Production VPS:** OVHcloud Canada or WebSavers (Canadian data residency, green power) — not yet provisioned
- **Stack:** Ubuntu 24, Apache, MySQL, PHP 8.2, Python 3
- **WordPress** for public site and cleanup event management

### Domains
- Registered at CanSpace
- Primary: greatlakecleaners.ca
- SSL: Let's Encrypt (free, auto-renews via certbot)

---

## WordPress Plugin: `great-lake-cleaners`

**File:** `great-lake-cleaners-plugin.zip`  
**Install:** Plugins → Upload → Activate. No other plugins required (ACF dependency removed).

### Custom Post Type: `cleanup_event`

Fields managed via native WordPress meta box ("Cleanup Details"), visible below the block editor when editing any Cleanup Event post. No ACF needed.

**Meta fields:**
| Field | Meta key | Type |
|---|---|---|
| Cleanup Date | `cleanup_date` | date (YYYY-MM-DD) |
| Site Name | `site_name` | text |
| GPS Latitude | `gps_lat` | number |
| GPS Longitude | `gps_lon` | number |
| Volunteers | `volunteers` | number |
| Volunteer Hours | `hours` | number (person-hours = duration × people) |
| Bags | `bags` | number |
| Weight (kg) | `weight_kg` | number |
| Items Recycled | `items_recycled` | number (cans + bottles) |
| Notable Finds | `notable_finds` | text |
| Native Species Planted | `species_planted` | number |
| Metres Bank Cleared | `meters_bank_cleared` | number |
| Wildlife Observed | `wildlife_obs` | text |

**To edit a cleanup:** Open post → scroll below editor → edit Cleanup Details meta box → Update.  
**Titles** are display-only and can be renamed freely (e.g. "Great neighbourhood cleanup") without breaking anything. Data lives in meta fields, not the title.

### Community Submission Post Type: `glc_submission`

Public-facing form via `[glc_submit_form]` shortcode. Submissions land as `pending`, visible only in WP Admin → Submissions. Admin reviews, publishes (counts in stats) or trashes. Email notification sent to admin on each submission. Photos (up to 5) attached to submission post.

**Volunteer counts from community submissions are NOT added to public stats** — unverifiable. Only hours from your own `cleanup_event` posts count toward volunteer hours.

### Shortcodes

| Shortcode | Output |
|---|---|
| `[glc_stats]` | Cumulative totals banner |
| `[glc_map]` | Leaflet/OpenStreetMap of all cleanup sites |
| `[glc_map height="240px"]` | Map at specific height |
| `[glc_archive]` | Card list of recent cleanups |
| `[glc_archive limit="5"]` | Limited archive |
| `[glc_submit_form]` | Community cleanup submission form |

### CSV Importer

**Location:** WP Admin → Tools → Import Cleanups CSV  
**Format:** `cleanups.csv` (generated by `tracker_to_csv.py`)  
**Behaviour:** Duplicate date + site_name combinations are skipped automatically — safe to re-import full file each time.  
**ACF pointer keys** are written alongside values so fields display correctly in the editor.

### Stats Strip (front page)

Four stats, all live from the database:
- **Cleanups** — your `cleanup_event` posts + approved `glc_submission` posts
- **Debris Removed** — sum of `weight_kg` across all cleanup events
- **Volunteer Hours** — sum of `hours` across your cleanup events only (not community submissions)
- **Items Recycled** — sum of `items_recycled`; hidden if zero

---

## WordPress Theme: `great-lake-cleaners-theme`

**File:** `great-lake-cleaners-theme.zip`  
**Install:** Appearance → Themes → Upload → Activate.

### Template Files

| File | Purpose |
|---|---|
| `header.php` | Site header, nav, hero section (front page only), stats strip |
| `front-page.php` | Static front page content (four sections below stats) |
| `index.php` | Fallback blog/archive template |
| `footer.php` | Site footer |
| `functions.php` | Theme setup, nav menus, asset enqueue |
| `style.css` | All theme styles |

### Front Page Setup

**In WordPress:** Settings → Reading → "A static page" → create a blank page titled "Home", set as Homepage. `front-page.php` takes over automatically.

**Page structure (top to bottom):**
1. **Header bar** — badge, site name, tagline (from WP Settings → General → Tagline), Instagram link, "Submit a Cleanup" button
2. **Nav bar** — Home, Cleanups (assign menu at Appearance → Menus)
3. **Hero** — live Leaflet cleanup map (240px, rounded), headline, body text, two CTA buttons
4. **Wave divider** — white-to-navy SVG wave
5. **Stats strip** — navy bar with live stats
6. **About / Mission** — two-column: text + stylized watershed map illustration
7. **Get Involved** — two-column (reversed): corridor cards + paddler illustration
8. **Submit a Cleanup** — two-column: 3-step process + cleanup materials illustration
9. **Recent Cleanups** — 3 most recent events, live from DB, "See All Cleanups" link
10. **Footer** — brand, footer nav, Instagram icon

### Hero Text Location

Hero headline and body text are in **`header.php`** (not `front-page.php`), inside the `is_front_page()` block around lines 143–157. Edit the strings inside `esc_html_e( '...' )`. Use `\'` to escape apostrophes inside single-quoted PHP strings (e.g. `doesn\'t`, `Guelph\'s`).

### "Submit a Cleanup" Button

Header button links to WordPress page with slug `submit-cleanup`. Create that page with `[glc_submit_form]` shortcode in the body.

### Illustrations (bundled in theme zip)

| File | Used in |
|---|---|
| `assets/images/glc-badge.png` | Header logo |
| `assets/images/stylized-map-rivers-lake.png` | About section (and hero fallback) |
| `assets/images/stylized-paddler.png` | Get Involved section |
| `assets/images/cleanup_stylized.png` | Submit a Cleanup section |

### Known Issue / Next Session

**Leaflet map styling** — the interactive Leaflet/OpenStreetMap in the hero is functional but visually distracting against the clean theme design. Next session: explore map tile customisation (muted/watercolour tile providers) or a custom Leaflet style to better match the navy/green palette.

---

## Outing Tracker: `Great_Lake_Cleaners_Outing_Tracker.xlsx`

Google Sheets compatible. One sheet: **Daily Log**.

**Columns (0-based index):**
| Col | Index | Field |
|---|---|---|
| Date | 0 | Date of outing |
| Location / Corridor | 1 | Site name (must match `SITE_GPS` keys in tracker script for auto GPS) |
| Duration (min) | 2 | Duration of outing in minutes |
| Bags (#) | 3 | Garbage bags collected |
| Weight (kg) | 4 | Garbage weight |
| Notes | 5 | Free notes |
| Cans (#) | 6 | Recycling — cans |
| Bottles (#) | 7 | Recycling — bottles |
| Recycling Weight (kg) | 8 | (not currently exported) |
| Aluminum (kg) | 9 | Scrap metal |
| Steel/Iron (kg) | 10 | Scrap metal |
| Copper/Other (kg) | 11 | Scrap metal |
| Number of people | 12 | Volunteers on this outing |
| Notable / Unusual Finds | 13 | Notable finds |

**Volunteer hours:** calculated as person-hours (duration × volunteers), so a 70-min outing with 2 people = 2.33h.

---

## Python Script: `tracker_to_csv.py`

Converts the outing tracker `.xlsx` to `cleanups/cleanups.csv` for WordPress import.

```bash
python tracker_to_csv.py                                          # default paths
python tracker_to_csv.py path/to/tracker.xlsx                    # custom tracker
python tracker_to_csv.py path/to/tracker.xlsx -o cleanups.csv    # custom output
```

**Behaviour:**
- Same-day + same-location outings are **merged** into one cleanup event (totals combined)
- Same-day + different-location outings remain **separate**
- GPS coordinates auto-filled from `SITE_GPS` lookup table at top of script — add new sites there
- Volunteers = peak count for the event; hours = sum of person-hours across outings
- Items recycled = cans + bottles, passed through to CSV and shown in notable_finds summary
- Output columns match the plugin's CSV importer field map exactly

**Sync workflow:**
1. `python tracker_to_csv.py` → generates `cleanups/cleanups.csv`
2. WP Admin → Tools → Import Cleanups CSV → upload file
3. Duplicate date+site entries skipped automatically

**GPS lookup table** (in script, add new sites as needed):
```python
SITE_GPS = {
    "Parkwood Gardens":  ("43.5520", "-80.2330"),
    "Eramosa River":     ("43.5580", "-80.2460"),
    "Speed River":       ("43.5400", "-80.2600"),
    "Hanlon Creek":      ("43.5280", "-80.2840"),
    "Guelph Lake":       ("43.6050", "-80.2280"),
}
```

---

## Current File Inventory

| File | Purpose | Status |
|---|---|---|
| `great-lake-cleaners-plugin.zip` | WordPress plugin | ✅ Installed and working |
| `great-lake-cleaners-theme.zip` | WordPress theme | ✅ Installed and working |
| `tracker_to_csv.py` | Outing tracker → cleanups.csv converter | ✅ Working |
| `cleanups/cleanups.csv` | Master cleanup event log | ✅ 4 events imported |
| `Great_Lake_Cleaners_Outing_Tracker.xlsx` | Daily outing tracker | ✅ 4 outings logged |
| `cleanup_report.py` | Instagram card + caption generator | From prior session |

---

## WordPress Pages to Create

| Page title | Slug | Content |
|---|---|---|
| Home | `home` | Blank — set as static front page in Settings → Reading |
| Submit a Cleanup | `submit-cleanup` | `[glc_submit_form]` shortcode only |

---

## Next Steps

- [ ] Provision VPS (OVHcloud Canada or WebSavers)
- [ ] Point greatlakecleaners.ca nameservers to VPS
- [ ] Install LAMP stack + WordPress
- [ ] Install Great Lake Cleaners plugin + theme
- [ ] Create Home page, set as static front page
- [ ] Create Submit a Cleanup page with `[glc_submit_form]`
- [ ] Import cleanups.csv seed data
- [ ] **Review Leaflet map styling** — explore muted tile providers or custom style to reduce visual noise in hero
- [ ] Build `archive-cleanup_event.php` and `single-cleanup_event.php` theme templates
- [ ] Post Instagram bio and first pinned post
- [ ] Get a digital fish scale (~$15–20) for accurate weight logging
- [ ] Connect with OPIRG Speed River Project coordinator
- [ ] Register for City of Guelph Clean and Green (April)
