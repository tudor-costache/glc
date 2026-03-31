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
- Photos uploaded directly to WordPress posts (Instagram embed killed by Meta in 2020)
- Instagram used as real-time field log; post URL stored in tracker and linked from cleanup post

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
- **Swim Drink Fish / Lake Ontario Waterkeeper** — aligned mission, broader network
- **CleanSwell / Litterati** — crowdsourced cleanup data platforms worth registering with for grant credibility

## Revenue Streams (informal phase)

- Scrap metal → Benmet Steel & Metal, 415 Elizabeth St (519-763-1209) or B&F Scrap (226-566-1630)
- Deposit return containers → Beer Store (verify nearest open location at thebeerstore.ca/where-to-return-empties)
- Eventually: grants (GRCA Foundation, TD Friends of the Environment, Ontario Trillium Foundation)

## Fundraising Note

Indiegogo and crowdfunding not appropriate for Phase 1. Better options:
- Simple donate/e-transfer page on own site for now
- Grants become viable after ONCA incorporation (Phase 3)

## Legal / Incorporation Notes

- Incorporate provincially under ONCA ($155, ServiceOntario) when ready
- CRA charitable registration takes 6–12 months — file early
- Environmental restoration qualifies under "other purposes beneficial to the community"
- Purposes clause is the most scrutinized part of CRA application — draft carefully

---

## Technical Infrastructure

### Hosting
- **Local dev:** WPLocal (Windows, WordPress 6.9.4) — actively in use
- **Production VPS:** OVHcloud Canada or WebSavers (not yet provisioned)
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
**Prefix:** functions/constants `glc_` / `GLC_`, CSS classes `.glc-`

### Plugin File Structure

```
great-lake-cleaners/
  great-lake-cleaners.php   — main loader
  includes/
    post-type.php           — cleanup_event CPT registration
    acf-fields.php          — native WordPress meta box (replaces ACF)
    admin.php               — list table columns, sortable date, admin styles
    shortcodes.php          — [glc_stats], [glc_map], [glc_archive]
    import.php              — Tools → Import Cleanups CSV
    submission.php          — glc_submission CPT + [glc_submit_form] shortcode
```

### Custom Post Type: `cleanup_event`

Fields via native "Cleanup Details" meta box below the block editor.

| Field | Meta key | Notes |
|---|---|---|
| Cleanup Date | `cleanup_date` | YYYY-MM-DD |
| Site Name | `site_name` | display name |
| GPS Latitude | `gps_lat` | decimal degrees |
| GPS Longitude | `gps_lon` | negative for Ontario |
| Volunteers | `volunteers` | headcount |
| Volunteer Hours | `hours` | person-hours (duration × people) |
| Bags | `bags` | garbage bags |
| Weight (kg) | `weight_kg` | |
| Items Recycled | `items_recycled` | cans + bottles |
| Notable Finds | `notable_finds` | |
| Native Species Planted | `species_planted` | |
| Metres Bank Cleared | `meters_bank_cleared` | |
| Wildlife Observed | `wildlife_obs` | |
| Instagram Post URL | `instagram_url` | link to field log |

**Editing:** Open post → scroll below editor → Cleanup Details → Update.  
**Titles** are display labels only — rename freely.  
**GPS:** Google Maps — phone: tap blue dot → coordinates at top. Desktop: right-click location → coordinates at top of context menu.

### Community Submission Post Type: `glc_submission`

Public form via `[glc_submit_form]` shortcode. Submissions land as `pending`. Admin reviews in WP Admin → Submissions, publishes (counts in stats) or trashes. Email notification on each submission. Photos (up to 5) attached to post. Volunteer counts from submissions are NOT added to public stats.

### Shortcodes

| Shortcode | Output |
|---|---|
| `[glc_stats]` | Cumulative totals banner |
| `[glc_map]` | Leaflet/OpenStreetMap of cleanup sites |
| `[glc_map height="240px"]` | Map at specific height |
| `[glc_archive]` | Card list of recent cleanups |
| `[glc_archive limit="5"]` | Limited archive |
| `[glc_submit_form]` | Community cleanup submission form |

### CSV Importer

WP Admin → Tools → Import Cleanups CSV. Accepts `cleanups.csv` from `tracker_to_csv.py`. Duplicate date+site_name pairs are skipped. To regenerate posts cleanly: trash all cleanup events, empty trash, re-import.

### Stats Strip (front page)

- **Cleanups** — cleanup_event posts + approved glc_submission posts
- **Debris Removed** — sum of weight_kg
- **Volunteer Hours** — sum of hours from cleanup_event only
- **Items Recycled** — sum of items_recycled; hidden if zero

---

## WordPress Theme: `great-lake-cleaners-theme`

**File:** `great-lake-cleaners-theme.zip`  
**Install:** Appearance → Themes → Upload → Activate.

### Template Files

| File | Purpose |
|---|---|
| `header.php` | Site header, nav, hero + map + stats (front page only) |
| `front-page.php` | Static front page — four content sections below stats |
| `index.php` | Fallback blog/archive template |
| `footer.php` | Site footer |
| `functions.php` | Theme setup, nav menus, asset enqueue |
| `style.css` | All theme styles |

### Bundled Illustrations (assets/images/)

| File | Used in |
|---|---|
| `glc-badge.png` | Header logo |
| `stylized-map-rivers-lake.png` | About section |
| `stylized-paddler.png` | Get Involved section |
| `cleanup_stylized.png` | Submit a Cleanup section |

### Front Page Setup

Settings → Reading → "A static page" → create blank page titled "Home" → set as Homepage.

**Page structure top to bottom:**
1. Header bar — badge, site name, tagline (Settings → General → Tagline), Instagram link, Submit a Cleanup button
2. Nav bar — assign at Appearance → Menus
3. Hero — live Leaflet cleanup map (240px), headline, body text, two CTA buttons
4. Wave divider — white-to-navy SVG
5. Stats strip — four live stats
6. About / Mission — text + stylized watershed map
7. Get Involved — corridor cards + paddler illustration
8. Submit a Cleanup — 3-step process + cleanup illustration
9. Recent Cleanups — 3 most recent events from DB
10. Footer

### Hero Text

In **`header.php`** inside `is_front_page()` block (~lines 143–157). Edit strings inside `esc_html_e( '...' )`. Apostrophes must be escaped as `\'` in PHP single-quoted strings — e.g. `doesn\'t`, `Guelph\'s`. Failure causes a fatal parse error.

### WordPress Pages Required

| Title | Slug | Content |
|---|---|---|
| Home | `home` | Blank — set as static front page |
| Submit a Cleanup | `submit-cleanup` | `[glc_submit_form]` only |

### Known Issues / Next Session

- **Leaflet map styling** — functional but visually noisy in the hero. Next: explore muted tile providers to match navy/green palette.
- **`single-cleanup_event.php`** — not yet built. Will display: stats, map pin, photos, wildlife observations, Instagram field log link.
- **`archive-cleanup_event.php`** — not yet built.

---

## Outing Tracker

**Primary source:** Google Sheet (must be native Google Sheets format, not xlsx stored in Drive)  
**Local backup:** `Great_Lake_Cleaners_Outing_Tracker.xlsx`  
**Tab name:** `Daily Log`

### Column Layout (0-based)

| Index | Excel col | Field | Notes |
|---|---|---|---|
| 0 | A | Date | |
| 1 | B | Location / Corridor | Must match exactly for same-site merging |
| 2 | C | Duration (min) | |
| 3 | D | Bags (#) | Garbage |
| 4 | E | Weight (kg) | Garbage |
| 5 | F | Notes | |
| 6 | G | Cans (#) | Recycling |
| 7 | H | Bottles (#) | Recycling |
| 8–11 | I–L | Scrap Metal | Not currently exported |
| 12 | M | Number of people | Volunteers |
| 13 | N | Notable / Unusual Finds | |
| 14 | O | Latitude | GPS — enter on first visit to a new site |
| 15 | P | Longitude | GPS — negative for Ontario |
| 16 | Q | Instagram Post URL | Link to field log |

**Volunteer hours** = person-hours: duration × volunteers. 70 min × 2 people = 2.33h.  
**GPS:** enter once per new location. Leave blank on return visits — converter uses first non-empty value for the group. No fallback dictionary — blank GPS means no map pin.

---

## Python Script: `tracker_to_csv.py`

### Data Sources (priority order)
1. **Google Sheets** — if `config.toml` present with `spreadsheet_id`
2. **Local xlsx** — if `--no-sheets` or `--xlsx` passed, or no config

### Config Files

**`config.toml`:**
```toml
spreadsheet_id   = "your-sheet-id-from-url"
credentials_file = "credentials.json"
# sheet_name     = "Daily Log"
# output         = "cleanups/cleanups.csv"
```

**`credentials.json`** — service account key from Google Cloud Console. Add to `.gitignore`. Share the Google Sheet with the service account's `client_email` (Viewer).

**Google Sheets requirement:** Must be a native Google Sheet. If converting from xlsx: File → Save as Google Sheets — the spreadsheet ID in the URL will change, update config.toml.

### Usage

```bash
python tracker_to_csv.py                    # Google Sheets (default)
python tracker_to_csv.py --no-sheets        # local xlsx fallback
python tracker_to_csv.py --xlsx my.xlsx     # specific local file
python tracker_to_csv.py -o out.csv         # custom output path
```

### Sync Workflow

1. Log outings in Google Sheet (works from phone in the field)
2. `python tracker_to_csv.py` — pulls from Sheets, writes `cleanups/cleanups.csv`
3. WP Admin → Tools → Import Cleanups CSV → upload
4. Duplicate date+site pairs skipped automatically

### Merge Behaviour

- Same date + same location → one cleanup event, totals summed
- Same date + different location → separate events
- Hours = sum of person-hours across merged outings
- Instagram URL = first non-empty URL in the group
- GPS = first non-empty lat/lon in the group

---

## Current File Inventory

| File | Status |
|---|---|
| `great-lake-cleaners-plugin.zip` | ✅ Installed in WPLocal |
| `great-lake-cleaners-theme.zip` | ✅ Installed in WPLocal |
| `tracker_to_csv.py` | ✅ Working — pulls from Google Sheets |
| `config.toml` | ✅ Configured |
| `credentials.json` | ✅ In place (never commit to version control) |
| `Great_Lake_Cleaners_Outing_Tracker.xlsx` | ✅ Local backup of Google Sheet |
| `cleanup_report.py` | From earlier session — Instagram card generator |

---

## Next Steps

- [ ] **Leaflet map styling** — muted/watercolour tiles to reduce visual noise in hero
- [ ] **Build `single-cleanup_event.php`** — stats, map pin, photos, wildlife, Instagram link
- [ ] **Build `archive-cleanup_event.php`** — cleanup listing template
- [ ] Provision production VPS (OVHcloud Canada or WebSavers)
- [ ] Point greatlakecleaners.ca nameservers to VPS
- [ ] Install LAMP stack + WordPress on VPS
- [ ] Deploy plugin + theme to production
- [ ] Create Home page (blank, set as static front page)
- [ ] Create Submit a Cleanup page (`[glc_submit_form]`)
- [ ] Post Instagram bio and first pinned post
- [ ] Get a digital fish scale (~$15–20) for accurate weight logging
- [ ] Connect with OPIRG Speed River Project coordinator
- [ ] Register for City of Guelph Clean and Green (April)
