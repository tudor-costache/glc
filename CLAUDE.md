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
- **Local dev:** WPLocal (Windows, WordPress 6.9.4) — actively in use, tested over HTTPS locally
- **Production VPS:** OVHcloud Canada or WebSavers (not yet provisioned)
- **Stack:** Ubuntu 24, Apache, MySQL, PHP 8.2, Python 3
- **WordPress** for public site and cleanup event management

### Domains
- Registered at CanSpace
- Primary: greatlakecleaners.ca
- SSL: Let's Encrypt (free, auto-renews via certbot)

### Post-Deployment Checklist (things stored in DB, not files)
When deploying to VPS, the following must be re-done in WP Admin — they do not travel with the theme/plugin zips:
- **Appearance → Customize → Site Identity** — site name, tagline, and Site Icon (favicon). Upload `glc-badge.png` (transparent background version preferred) and WordPress generates all favicon sizes automatically.
- **Settings → Reading** — set static front page to the "Home" page
- **Appearance → Menus** — rebuild primary and footer nav menus
- **Pages** — recreate "Home" and "Submit a Cleanup" pages with correct slugs
- **Privacy Policy page** — create a blank page with slug `privacy-policy`; `page-privacy-policy.php` handles all content automatically
- Use **Tools → Export / Import** (WXR) or WP Migrate to carry over posts, pages, menus, and options in one shot

---

## WordPress Plugin: `great-lake-cleaners`

**File:** `great-lake-cleaners-plugin.zip`  
**Install:** Plugins → Upload → Activate. No other plugins required (ACF dependency removed).  
**Prefix:** functions/constants `glc_` / `GLC_`, CSS classes `.glc-`  
**After install or update:** Deactivate and reactivate the plugin once to trigger the rewrite rule flush (uses a transient — fires automatically on the next page load after activation).

### Plugin File Structure

```
great-lake-cleaners/
  great-lake-cleaners.php   — main loader, activation hook, transient-based rewrite flush
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
| Cleanup Date | `cleanup_date` | YYYY-MM-DD — must be this format for correct archive sorting |
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
**Date format:** Always `YYYY-MM-DD`. The archive sorts using `strcmp` on this format — display-format dates like `Mar 30` will be normalised at render time by `strtotime()`, but it's better to store them correctly. Re-import from `tracker_to_csv.py` to fix any legacy mangled dates.

### Community Submission Post Type: `glc_submission`

Public form via `[glc_submit_form]` shortcode. Submissions land as `pending`. Admin reviews in WP Admin → Submissions, publishes (counts in stats) or trashes. Email notification on each submission. Photos (up to 5) attached to post.

**CPT settings:** `publicly_queryable: true`, `exclude_from_search: true`, `query_var: true`, `rewrite slug: cleanup-submission`. Public single-post URLs resolve to `/cleanup-submission/{slug}/`.

**Stats counting:** Published submissions add to the cleanup count and weight/recycled totals. Volunteer hours from submissions are NOT added to public stats (unverifiable).

**Community submission meta keys:**

| Field | Meta key |
|---|---|
| Submitter Name | `glc_submitter_name` |
| Email | `glc_email` |
| Cleanup Date | `glc_cleanup_date` |
| Waterway | `glc_waterway` |
| Site / Location | `glc_site_name` |
| GPS Latitude | `glc_gps_lat` |
| GPS Longitude | `glc_gps_lon` |
| Duration (min) | `glc_duration_min` |
| Bags | `glc_bags` |
| Weight (kg) | `glc_weight_kg` |
| Garbage Notes | `glc_garbage_notes` |
| Cans (#) | `glc_cans` |
| Bottles (#) | `glc_bottles` |
| Items Recycled (total) | `items_recycled` |
| Weight for stats | `weight_kg` |
| Volunteers | `glc_volunteers` |
| Person-Hours | `glc_hours` |
| Notable Finds | `glc_notable_finds` |
| Instagram URL | `glc_instagram_url` |
| Photo Repost Consent | `glc_photo_repost_ok` |
| Photo IDs | `glc_photo_ids` |

Note: `items_recycled` and `weight_kg` are stored under those exact keys (matching `cleanup_event`) so `glc_get_impact_stats()` can count them without special-casing.

Note: Phone field was removed from the public submission form. GPS coordinates are now collected via lat/lon inputs + browser geolocation button (requires HTTPS — works on production, blocked on plain HTTP). "Hours per Person" field was removed — person-hours are calculated automatically from duration × volunteers.

### Shortcodes

| Shortcode | Output |
|---|---|
| `[glc_stats]` | Cumulative totals banner |
| `[glc_map]` | Leaflet/CartoDB Light map — all cleanup sites, deduplicated |
| `[glc_map height="240px"]` | Map at specific height |
| `[glc_map post_id="123"]` | Single-pin map for one event or submission (used on single post templates) |
| `[glc_archive]` | Card list of recent cleanups |
| `[glc_archive limit="5"]` | Limited archive |
| `[glc_submit_form]` | Community cleanup submission form |

### Map Behaviour

Leaflet uses **CartoDB Positron** (`light_all`) via `basemaps.cartocdn.com`. Free, no API key required, attribution shown in map. `zoomControl: false` on hero map.

**Deduplication:** In all-events mode, markers are grouped by lat/lon (rounded to 5 decimal places, ~1 m precision). When multiple events share a location, the one with the highest score (weight_kg + bags×2) wins the pin. This prevents stacking markers on frequently-visited sites.

**Single-event mode:** `post_id` attribute renders a one-pin map centred at zoom 15. Tries `gps_lat`/`gps_lon` first (tracker events), then `glc_gps_lat`/`glc_gps_lon` (community submissions). Returns empty string if no coordinates found — map section is skipped gracefully.

### CSV Importer

WP Admin → Tools → Import Cleanups CSV. Accepts `cleanups.csv` from `tracker_to_csv.py`. Duplicate date+site_name pairs are skipped. To regenerate posts cleanly: trash all cleanup events, empty trash, re-import.

### Stats Strip (front page)

Powered by `glc_get_impact_stats()` helper in `functions.php` — shared by the front page header and the archive page stat cards.

- **Cleanups** — cleanup_event posts + approved glc_submission posts
- **Debris Removed** — sum of weight_kg across cleanup_events only
- **Volunteer Hours** — sum of hours from cleanup_event only
- **Items Recycled** — sum of items_recycled; hidden if zero
- **River Corridors** — hard-coded as 3

---

## WordPress Theme: `great-lake-cleaners-theme`

**File:** `great-lake-cleaners-theme.zip`  
**Install:** Appearance → Themes → Upload → Activate.

### Template Files

| File | Purpose |
|---|---|
| `header.php` | Site header, nav, hero (two-column: text + map) + wave + stats strip (front page only). Also outputs `<meta name="description">` for all page types. |
| `front-page.php` | Static front page — four content sections below stats |
| `index.php` | Fallback blog/archive template |
| `page.php` | Standard WordPress pages (About, Donate, etc.) — narrow centred column |
| `404.php` | Not-found page — branded with 🌊 icon and nav buttons back to Home / Cleanups |
| `footer.php` | Site footer with wave divider (appears on all pages). Includes Privacy Policy link in base bar. |
| `functions.php` | Theme setup, nav menus, asset enqueue, `glc_get_impact_stats()` helper |
| `style.css` | All theme styles |
| `archive-cleanup_event.php` | Cleanups archive — all events + community submissions merged and sorted. Map appears at bottom of Cumulative Impact section. |
| `page-submit-cleanup.php` | Submit a Cleanup page — form + sidebar tips (auto-loaded for slug `submit-cleanup`) |
| `page-privacy-policy.php` | Privacy policy — full content baked into template (auto-loaded for slug `privacy-policy`). Edit this file directly to update policy content. |
| `single-cleanup_event.php` | Single view for tracker-imported cleanup events |
| `single-glc_submission.php` | Single view for approved community submissions — mirrors cleanup event layout exactly |

### Bundled Illustrations (assets/images/)

| File | Used in |
|---|---|
| `glc-badge.png` | Header logo (130×130px). Also used as favicon — upload transparent-background version via Appearance → Customize → Site Identity |
| `stylized-map-rivers-lake.png` | About section (front page) |
| `stylized-paddler.png` | Get Involved section (front page) |
| `cleanup_stylized.png` | Submit a Cleanup section (front page) |
| `stylized-thankyou.png` | Thank-you state after form submission |

### Favicon

Set via **Appearance → Customize → Site Identity → Site Icon**. WordPress auto-generates all required sizes (16×16, 32×32, 180×180 Apple touch). Use the transparent-background version of `glc-badge.png` (remove background in Adobe Express, export as PNG). This is stored in the database — must be re-set after deploying to VPS.

### Meta Descriptions (`header.php`)

Generated before `wp_head()` for each page type. No SEO plugin required.

| Context | Description source |
|---|---|
| Single cleanup event | Stat summary: site name, date, bags, weight |
| Single community submission | Site name + submitter name |
| Cleanups archive | Fixed waterway-specific string |
| Front page | Site tagline / bloginfo description |
| Standard pages | Page excerpt (if set) or site tagline |
| Everything else | Site tagline fallback |

All descriptions trimmed to 160 characters without cutting mid-word.

### Front Page Setup

Settings → Reading → "A static page" → create blank page titled "Home" → set as Homepage.

**Page structure top to bottom:**
1. Header bar — badge (130×130px), site name, tagline, Instagram icon (bare SVG, no border — matches footer), Submit a Cleanup button
2. Nav bar — assign at Appearance → Menus
3. Hero — two-column: left = headline + body + CTA buttons; right = live Leaflet map (340px, rounded)
4. Wave divider — white-to-navy SVG
5. Stats strip — five live stats (navy background)
6. About / Mission — text + stylized watershed map
7. Get Involved — corridor cards + paddler illustration
8. Submit a Cleanup — 3-step process + cleanup illustration
9. Recent Cleanups — 3 most recent events from DB
10. Footer (includes wave divider into navy footer on all pages)

### Header

- Logo: `glc-badge.png` at 130×130px. Header padding reduced to compensate so overall header height is unchanged.
- Instagram icon: bare SVG only (20×20, stroke-width 1.8), no border, no text label — matches footer icon exactly.
- "Submit a Cleanup" button: white background, navy border — matches the hero outline button style.

### Hero Text

In **`header.php`** inside `is_front_page()` block. Edit strings inside `esc_html_e( '...' )`. Apostrophes must be escaped as `\'` in PHP single-quoted strings — e.g. `doesn\'t`, `Guelph\'s`. Failure causes a fatal parse error.

### Archive Page (`/cleanups/`)

`archive-cleanup_event.php` — fetches all `cleanup_event` posts and all published `glc_submission` posts via `get_posts(-1)`, merges them into a single array, normalises any legacy display-format dates to `YYYY-MM-DD` via `strtotime()`, sorts globally by date descending, then paginates manually at 12 per page.

Community cards show a green "Community" pill badge and a subtle left border. Clicking a community card links to `/cleanup-submission/{slug}/`. Tracker event cards link to `/cleanups/{slug}/`.

The **Cumulative Impact** section appears below the card grid with stat icons and a full-width Leaflet map (400px) showing all cleanup locations — deduplicated, best-stats pin per location.

### Single Cleanup Event Page (`/cleanups/{slug}/`)

`single-cleanup_event.php` — loaded for all `cleanup_event` posts. Layout top to bottom:

1. ← All Cleanups back link
2. Date + corridor badge (inferred from site name — matches Speed River, Eramosa River, Hanlon Creek, Guelph Lake, Grand River) + volunteer count byline
3. **Featured image** (if attached to the post)
4. **Blog body** — WordPress editor content rendered as free prose (`.glc-single-body`). The tracker `notes` column is imported into `post_content` and appears here. Can be freely edited in the WP block editor.
5. Stat tiles: Bags 🗑 / Debris ⚖ / Items Recycled ♻ / Hrs ⏱ (only tiles with data are shown)
6. Notable Finds box (from `notable_finds` meta)
7. Wildlife Observed box (from `wildlife_obs` meta, with green left accent)
8. Restoration extras: native species planted 🌱 / metres of bank cleared 🏞 (pill badges)
9. "View Field Log on Instagram →" outline button (if `instagram_url` set)
10. **Cleanup Location map** (320px) — single pin at zoom 15, only rendered if `gps_lat` + `gps_lon` are set

Meta keys read: `cleanup_date`, `site_name`, `bags`, `weight_kg`, `hours`, `items_recycled`, `notable_finds`, `wildlife_obs`, `instagram_url`, `volunteers`, `species_planted`, `meters_bank_cleared`, `gps_lat`, `gps_lon`.

### Single Community Submission Page (`/cleanup-submission/{slug}/`)

`single-glc_submission.php` — loaded for all `glc_submission` posts. Layout mirrors the tracker single view exactly:

1. ← All Cleanups back link
2. Date + "Community" green badge + corridor badge (from `glc_waterway`) + "Submitted by [name]" byline
3. **Featured image** (if attached)
4. **Blog body** — WordPress editor content rendered as free prose
5. Stat tiles: Bags 🗑 / Debris ⚖ / Items Recycled ♻ / Hrs ⏱ (only if data present)
6. Submitted photos gallery (only if photo repost consent was given)
7. Notable Finds box
8. "View Field Log on Instagram →" outline button
9. **Cleanup Location map** (320px) — single pin, only rendered if `glc_gps_lat` + `glc_gps_lon` are set

Both post types use identical emoji icons for stat tiles (🗑 ⚖ ♻ ⏱) for visual consistency.

### Standard Page Template (`page.php`)

Narrow centred column (max-width 780px), same layout as single event pages. Handles featured image, full block editor content, and sensible heading/link/paragraph styles. Used for About, Donate, and any future static pages.

### 404 Page (`404.php`)

Branded not-found page. Shows 🌊 icon, on-brand copy ("this stretch of river has already been cleaned up"), and two action buttons: Back to Home and See Our Cleanups.

### Privacy Policy (`page-privacy-policy.php`)

Auto-loaded for slug `privacy-policy`. Full policy content is baked into the template — edit the file directly to make changes, do not use the WordPress editor for content. To activate: create a blank WordPress page with slug `privacy-policy` and publish it.

**To update after going live:**
- Change `$contact` variable near top of file to the real email address once set up
- Update the "Last updated" `<time>` tag date whenever policy changes

Policy covers: name/email/cleanup data collection and purpose; GPS note (public place, not personal location); no analytics; no third-party data sharing; CASL/PIPEDA rights; Leaflet/CARTO/OSM third-party disclosure.

Privacy Policy link appears in the footer base bar alongside the copyright line.

### Submit a Cleanup Page (`/submit-cleanup/`)

`page-submit-cleanup.php` — auto-loaded by WordPress for any page with the slug `submit-cleanup`. Two-column layout: form on the left, sticky sidebar on the right with three info cards (what happens next, logging tips, corridor list). The sidebar collapses below the form on mobile.

The form (`[glc_submit_form]`) has five sections:
1. **About You** — Name (required), Email (optional)
2. **The Cleanup** — Date (required), Duration, Waterway (required), Access Point, GPS Location (lat/lon inputs + "Use my location" browser geolocation button — requires HTTPS)
3. **What You Collected** — Garbage (bags, weight, notes), Recycling (cans, bottles), Team (number of people)
4. **Notable Finds & Field Log** — textarea + Instagram URL
5. **Photos** — up to 5 images with repost consent checkbox

Person-hours calculated automatically from duration × volunteers. Phone field removed. GPS stored as `glc_gps_lat` / `glc_gps_lon`.

### WordPress Pages Required

| Title | Slug | Notes |
|---|---|---|
| Home | `home` | Blank — set as static front page in Settings → Reading |
| Submit a Cleanup | `submit-cleanup` | Leave blank — `page-submit-cleanup.php` handles layout |
| Privacy Policy | `privacy-policy` | Leave blank — `page-privacy-policy.php` handles all content |

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
| 5 | F | Notes | Imported into post_content — appears as blog body on single event page |
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
**Date format in sheet:** The script normalises dates to `YYYY-MM-DD` automatically. If pulling from Google Sheets, ensure the sheet stores dates as actual date values (not text) so the script converts them correctly. Display-format text like `Mar 30` will fail to parse to a year-qualified date.

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
| `great-lake-cleaners-plugin.zip` | ✅ Installed and working in WPLocal |
| `great-lake-cleaners-theme.zip` | ✅ Installed and working in WPLocal |
| `tracker_to_csv.py` | ✅ Working — pulls from Google Sheets |
| `config.toml` | ✅ Configured |
| `credentials.json` | ✅ In place (never commit to version control) |
| `Great_Lake_Cleaners_Outing_Tracker.xlsx` | ✅ Local backup of Google Sheet |
| `cleanup_report.py` | From earlier session — Instagram card generator |

---

## Next Steps

- [ ] **Provision production VPS** (OVHcloud Canada or WebSavers)
- [ ] **Point greatlakecleaners.ca nameservers** to VPS
- [ ] **Install LAMP stack + WordPress** on VPS
- [ ] **Deploy plugin + theme to production**
- [ ] **Re-do site identity on VPS** — name, tagline, favicon (Appearance → Customize → Site Identity)
- [ ] **Re-create pages on VPS** — Home, Submit a Cleanup, Privacy Policy (correct slugs required)
- [ ] **Re-build nav menus on VPS** — primary and footer
- [ ] Re-import cleanups from Google Sheets (trash existing events, re-run `tracker_to_csv.py`, re-import CSV)
- [ ] Update `$contact` email in `page-privacy-policy.php` once email address is set up
- [ ] Post Instagram bio and first pinned post
- [ ] Get a digital fish scale (~$15–20) for accurate weight logging
- [ ] Connect with OPIRG Speed River Project coordinator
- [ ] Register for City of Guelph Clean and Green (April)
- [ ] Build donate/e-transfer page
