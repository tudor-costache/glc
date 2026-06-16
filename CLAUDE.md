# Great Lake Cleaners — Project Context

## Identity

**Organization:** Great Lake Cleaners  
**Tagline:** The lake starts here.  
**Mission:** Regular cleanups of Guelph's local waterways — by foot and paddle — that flow into the Great Lakes system via the Grand River and Lake Erie.  

---

## ⚠️ Packaging — ALWAYS use repack.ps1

**After every edit session, run:**
```
! powershell -File repack.ps1
```

**Never** use `Compress-Archive`, Python `zipfile`, or `ZipFile.CreateFromDirectory` directly — all produce broken zips (wrong separators, missing directory entries, or mangled bytes). Only `repack.ps1` produces WordPress-valid zips with correct entry structure.

- Plugin source: `plugin-dev/great-lake-cleaners/` — edit here only
- Theme source: `theme-dev/great-lake-cleaners-theme/` — edit here only

---

## WordPress Plugin: `great-lake-cleaners`

**Prefix:** functions/constants `glc_` / `GLC_`, CSS classes `.glc-`  
**After install or update:** Deactivate and reactivate once to trigger the rewrite rule flush (transient-based — fires on the next page load after activation).

### Custom Post Type: `cleanup_event`

Fields via native "Cleanup Details" meta box below the block editor.

| Field | Meta key | Notes |
|---|---|---|
| Cleanup Date | `cleanup_date` | YYYY-MM-DD — archive sorts using `strcmp`, so format matters |
| Site Name | `site_name` | |
| GPS Latitude | `gps_lat` | decimal degrees |
| GPS Longitude | `gps_lon` | negative for Ontario |
| Volunteers | `volunteers` | headcount |
| Volunteer Hours | `hours` | person-hours (duration × people) |
| Bags | `bags` | |
| Weight (kg) | `weight_kg` | |
| Items Recycled | `items_recycled` | cans + bottles |
| Recyclables Weight (kg) | `recycled_weight_kg` | stored for future use; not added to debris total |
| Tires Removed | `tires_removed` | feeds `[glc_impact_highlights]` |
| Hazardous Waste Removed | `hazards_removed` | stored but not currently surfaced |
| Notable Finds | `notable_finds` | |
| Native Species Planted | `species_planted` | |
| Metres Bank Cleared | `meters_bank_cleared` | displayed as km if ≥ 1000 m |
| Wildlife Observed | `wildlife_obs` | |
| Instagram Post URL | `instagram_url` | |

**Titles** are display labels only — rename freely.  
**GPS:** Google Maps — phone: tap blue dot → coordinates at top. Desktop: right-click → coordinates at top of context menu.

### Community Submission Post Type: `glc_submission`

Submissions land as `pending`. Admin reviews in WP Admin → Submissions. Photos (up to 5) attached to post.

**CPT settings:** `publicly_queryable: true`, `rewrite slug: cleanup-submission` — public URLs resolve to `/cleanup-submission/{slug}/`.

**Stats counting:** Published submissions count fully toward all public stats alongside `cleanup_event` posts.

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
| Wildlife Observed | `glc_wildlife_obs` / `wildlife_obs` | dual-key — see note below |
| Instagram URL | `glc_instagram_url` |
| Photo Repost Consent | `glc_photo_repost_ok` |
| Photo IDs | `glc_photo_ids` |

**Key:** `items_recycled`, `weight_kg`, and `wildlife_obs` are stored without the `glc_` prefix (matching `cleanup_event`) so `glc_get_impact_stats()` and `page-stats.php` can aggregate both post types without special-casing. Wildlife is also stored under `glc_wildlife_obs` (prefixed) for the admin meta box. If footer stats or wildlife cards diverge between CPTs, check these shared keys first.

### Community Events Post Type: `glc_event`

Upcoming community cleanups, announced publicly. **Distinct from `cleanup_event`** (which is a record of a *completed* cleanup). Registered in `includes/events.php` — CPT, meta box, helpers, and RSVP all live in that one file. Archive slug `events` → `/events/`.

**⚠️ Never create a WP page with slug `events`** — it conflicts with the CPT archive rewrite.

Fields via native "Event Details" meta box:

| Field | Meta key | Notes |
|---|---|---|
| Event Date | `event_date` | YYYY-MM-DD — upcoming/past split strcmp's it; normalised on save |
| Start Time | `event_start_time` | HH:MM 24h; display formatting via `glc_event_time_range()` |
| End Time | `event_end_time` | optional |
| Site Name | `site_name` | **shared key** with `cleanup_event` |
| GPS Latitude / Longitude | `gps_lat` / `gps_lon` | **shared keys** — `[glc_map post_id=N]` works on events unchanged |
| Meeting Point | `meeting_point` | textarea |
| What to Bring / Notes | `what_to_bring` | textarea |
| Linked Cleanup Report | `linked_cleanup_id` | post ID of the resulting `cleanup_event` — past events show "See the results →" |
| RSVP totals | `rsvp_count` / `rsvp_parties` | aggregate counters bumped by the RSVP handler; read-only in the meta box |

**Stats:** events never count toward public stats — `glc_get_impact_stats()` and `page-stats.php` query by post type.

**Upcoming vs past:** an event is upcoming through the end of its calendar day, compared with `current_time('Y-m-d')` (site timezone), never `date()` (server may be UTC). Undated events count as past. Helpers: `glc_event_is_past()`, `glc_get_upcoming_events( $limit )` (date ASC, start-time tiebreak), `glc_get_past_events()` (date DESC).

**RSVP (`[glc_event_rsvp post_id=N]`):** name + email + party size (clamped 1–20), AJAX action `glc_event_rsvp`, honeypot + nonce + rate limit (3/10 min per IP, same as join-crew). Email-only to `info@greatlakecleaners.ca` with `Reply-To` set to the attendee — no names/emails stored in the DB; only the aggregate headcount counters are bumped (after successful send). Server rejects RSVPs to past or unpublished events. The form uses its own `.glc-ev-rsvp-form` class + script — do not reuse the `.glc-join-form` selector.

### Submission Form — Thank-You / Receipt State

After success, `[glc_submit_form]` shows a receipt line ("You submitted: 3 bags, 6.0 kg, Parkwood Gardens, April 3") built from `$_POST` data that remains available after the handler returns `'success'`. Each part is conditional — omitted if empty.

### Report an Issue (`[glc_report_form]`)

Email-only — no CPT, no admin review queue. Reports go directly to `info@greatlakecleaners.ca`.

**Two-stage flow:** Triage cards first (city → ArcGIS external link, waterway → reveals the form in-page). Validation errors re-show the form section open so the user doesn't need to click the triage button again.

**Error pattern:** field errors return `['field' => 'field_id', 'message' => '...']` → `aria-invalid` + `aria-describedby` on the input + inline `.glc-field-error` span. Non-field errors return a plain string → `.glc-form-error` banner. Same pattern in `submission.php`.

**Required indicator:** `.glc-required` `*` span must sit **inside** `.glc-label-text` — placing it outside causes it to wrap to a new line in the column flex layout.

### Shortcodes

| Shortcode | Notes |
|---|---|
| `[glc_stats]` | Cumulative totals banner |
| `[glc_map]` | Leaflet map. Attrs: `height`, `post_id` (single-event mode), `limit` (markers per cluster), `cluster_radius` (km). Clustering is greedy: markers sorted by impact score (kg + bags×2), each joins nearest anchor within radius. Hero uses `limit="5" cluster_radius="10"`, archive uses `limit="7" cluster_radius="10"`. |
| `[glc_archive]` | Paginated cleanup archive |
| `[glc_submit_form]` | Community submission form |
| `[glc_gallery]` | Photo gallery — year tabs + lightbox. Only images flagged `_glc_gallery=1` appear. Global meta query finds all flagged attachments regardless of `post_parent` — images inserted from the Media Library (which keep `post_parent=0`) are included. Within each year, photos sort by `sort_date` (cleanup date if known, upload date as fallback). |
| `[glc_report_form]` | Waterway issue report |
| `[glc_timeline]` | SVG area chart (debris + recycled) — same renderer as `page-stats.php`. Outputs `.dirCL-chartwrap` + `.dirCL-legend`. No Chart.js. |
| `[glc_impact_highlights]` | Stat cards (sites, tires, cleanups) + SVG hours chart — same renderer as `page-stats.php`. Chart wrapped in `.dirCL-chartwrap` + `.dirCL-legend`. No Chart.js. |
| `[glc_references]` | Wrapping shortcode — hides an `<ol>` behind a gold-bordered slide-in panel. Usage: `[glc_references]<ol>...</ol>[/glc_references]`. Button label auto-counts `<li>` items. |
| `[glc_join_crew]` | Email signup, AJAX, rate-limited (3/10 min per IP), honeypot + nonce. No CPT. |
| `[glc_wildlife_log]` | Chronological wildlife sightings list. Superseded by `page-stats.php` wildlife cards but still usable. |
| `[glc_event_rsvp]` | Event RSVP form — see `glc_event` CPT section. Attr: `post_id` (defaults to current post). Returns empty for past/non-event posts. |

---

## WordPress Theme: `great-lake-cleaners-theme`

**PHP upload limit:** Default WP limit is too small for the theme zip. Set in `/etc/php/8.3/apache2/php.ini`: `upload_max_filesize = 64M`, `post_max_size = 64M`, `max_execution_time = 300`, then `sudo systemctl restart apache2`.  
**Cache busting:** `GLC_THEME_VERSION` in `functions.php` and the matching `Version:` header in `style.css` must be bumped together whenever CSS or JS changes. Browsers cache by version query string — if you don't bump, they serve the old file.

### Page Layout Architecture

**This was hard-won through many iterations — do not change without understanding the full chain.**

```
<body>                        flex column, min-height: 100vh, background: #f0f0ee
  <header .glc-site-header>  sticky, full-width navy
  <div .glc-nav-bar>         full-width navy nav
  <div .glc-main-outer>      flex: 1, flex-direction: column, background: #f0f0ee
    <main .glc-main>         flex: 1, max-width: 1140px, margin: 0 auto, white bg
  <div .glc-wave-footer>     full-width, linear-gradient background
  <div .glc-stats-strip>     full-width navy
  <footer>                   full-width navy
```

**Why `.glc-main-outer` exists:** `<main>` is a centered `max-width: 1140px` column and can't fill the viewport. Without the outer wrapper, short pages leave a gray band between the bottom of `<main>` and the wave. The outer div takes `flex: 1` on the body chain and is itself a flex column so `<main>` can take `flex: 1` and stretch to fill it.

**Why `min-height: 100%` doesn't work:** It only resolves when the parent has a definite CSS height — `flex: 1` alone doesn't count. The correct fix is `display: flex; flex-direction: column` on the parent + `flex: 1` on the child, all the way up the chain.

**Wave footer gradient:** The area above the wave crests must match the page layout (white centre, gray sides):

```css
.glc-wave-footer {
    background: linear-gradient(
        to right,
        #f0f0ee calc(50% - 570px),
        var(--glc-white) calc(50% - 570px),
        var(--glc-white) calc(50% + 570px),
        #f0f0ee calc(50% + 570px)
    );
}
```

570px = half of 1140px. **Do not** set `background: white` on `body` — removes gutters globally.

### Header

- `::after` pseudo-element creates the wave transition below the header — **never add `overflow: hidden` to `<header>`**, it clips the wave.
- Customizer logo setting must be cleared for file-based `glc-badge.png` to take effect.
- **`html { overflow-anchor: none }` must be preserved** — without it, the browser compensates for the compact-header height change by adjusting `scrollY`, which drops below the compact threshold, reverting the header, causing a flash/oscillation loop.

### Compact Header on Scroll

Collapses at 80px scroll, expands below 40px (hysteresis prevents oscillation). Mobile always shows compact styles — the JS still fires but has no visual effect.

**Badge crossfade:** `.glc-badge-sm-img` is absolutely positioned inside the same `<a>` as the large badge so it stays centred as the large badge collapses. `aria-hidden="true"` on the small badge — the large badge's `alt` covers accessibility.

### Navigation Bar

**Submenu** (`.sub-menu`) has **no** `border-top` — the gold top border was removed because it created a double-underline artifact where the nav item's gold bottom-border and the submenu border overlapped.

### Footer Structure

**Stats strip — all labels are links:**
- **Cleanups** → `/cleanups/`
- **Debris Removed** → `/stats/#debris`
- **Volunteer Hours** → `/stats/#hours`
- **Items Recycled** → `/stats/#debris`
- **River Corridors** → `/cleanups/#cleanups-map`

All five stats show a `+` superscript (minimums, not exact counts).

**Instagram hover specificity:** Must be `.glc-footer-base a.glc-footer-insta:hover` to beat `.glc-footer-base a:hover`.

**`.glc-footer-base a`** must be defined only once in `style.css`. A duplicate rule previously existed in the Privacy Policy section that silently overrode the color with `rgba(255,255,255,0.6)` and removed the underline — do not re-add a second rule.

### Three-Layer Footer Wave

SVG viewBox `0 0 1200 80`. Three paths: `#5a9fc0` at 45% opacity (lightest, highest crest), `#2d6a96` (mid), `#1a4a6b` (exact footer navy, lowest — merges into footer). `<footer>` has `margin-top: -2px` and wave div has `margin-bottom: -2px` to close sub-pixel seams.

### Maps

`isolation: isolate` on all three map wrapper classes — must be preserved. Leaflet's internal pane z-indices (200–600) must be contained within a stacking context to prevent overlapping the sticky header.

### Front Page

**Hours display on cards:** values under 1 hour display as minutes (`30 min`); at or above display as `1.5 h`. Applied in `front-page.php` and `archive-cleanup_event.php`.

**Portrait photos:** `object-position: center top` inline on the "Get Involved" photo keeps the volunteer's face in frame.

### Archive Page (`/cleanups/`)

Fetches all `cleanup_event` + published `glc_submission` posts, merges, sorts by date descending, paginates at 12. Map section has `id="cleanups-map"` and `scroll-margin-top: 110px` — the River Corridors footer stat links directly to it.

### Events Pages (`/events/`) — `.glc-ev-*`

- **`archive-glc_event.php`** — Upcoming cards (full list, page 1 only) then a compact Past Events list (paginated at 24, manual `array_slice` pagination like cleanups). Past rows show "See the results →" when `linked_cleanup_id` is set **and** that report is still published.
- **`single-glc_event.php`** — past banner (replaces RSVP when the event is over) → header → content → meeting point / what-to-bring cards → "Where to Meet" map (`[glc_map post_id]`, `isolation: isolate` preserved) → RSVP section.
- **Front page** — "Upcoming events" section (≤ 3 `.glc-ev-fp-card` cards) sits between Recent Cleanups and About. The section **and its own wave divider** are wrapped in one conditional — both vanish when nothing is scheduled, so no double divider appears.
- **Navigation** — menus are admin-managed: add an "Events" custom link (`/events/`) to the primary menu after Cleanups. `glc_nav_fallback()` in `functions.php` includes Events automatically.
- After deploying: deactivate/reactivate the plugin to flush rewrites, or `/events/` 404s.

### Stats Page (`/stats/`) — `.dirCL-*` redesign

**Template:** `page-stats.php` — fully self-contained, no shortcodes. All CSS class names prefixed `.dirCL-*`.

**`.glc-main` padding override:** `.page-template-page-stats .glc-main` has no side padding — sections provide their own. `.dirCL-wave` has `padding: 0 64px` to align with the content sections rather than spanning full column width.

**SVG charts:** server-side PHP, no Chart.js. `glc_stats_smooth_path()` and `glc_stats_area_chart()` are defined in `functions.php` (globally available to templates and shortcodes) — `page-stats.php` only defines `glc_stats_wildlife_img()` locally. `pathLength="2600"` keeps stroke-dash animation consistent across varying path lengths.

**`glc_stats_area_chart()` behaviour:** Each series always normalizes to a "nice" ceiling computed from its actual max (e.g. 400 kg → axis ceiling 500, 660 items → 800), so multi-series endpoint circles land at distinct heights rather than converging to the same point. The `$show_axes` parameter (6th arg, default `false`) controls Y-axis tick labels — keep it `false`; the `.dirCL-legend` provides the values and the chart stays clean. X-axis month labels are suppressed within 12 days of the start/end to prevent crowding (e.g. "Apr 1" won't appear when data starts "Mar 28").

**Moose metaphor:** `count = max(1, round(debris_kg / 350))`. No `filter: drop-shadow` on `moose-scene.png` — transparent PNG + drop-shadow creates an artifact border.

**Item dot pictograph:** self-scaling — `itemsPerDot` from `[2,5,10,20,25,50,…]` so grid stays ≤ 672 dots (28 cols × 24 rows). IntersectionObserver adds `.glc-dots-visible` when the block enters viewport; dots are hidden by default and animate in on that class.

**Wildlife cards:** `glc_stats_wildlife_img()` maps observation text to an image filename — see `page-stats.php` for the current list. Cards show a brand-tinted gradient stage (`.wfig`) with the illustration + `drop-shadow`, then `.wbody` below with observation and site/date. Stagger delay `0.15 + i × 0.12s` via inline `--d` CSS property. Cards without a matching image render text-only (no `.wfig`). Gold border + shadow on hover; title shifts to gold-deep. No translateY on hover.

**Wildlife data source:** `page-stats.php` queries both `cleanup_event` and `glc_submission` CPTs for the `wildlife_obs` meta key (same unprefixed key on both). Sorted via `glc_cleanup_field()` for CPT-agnostic date access.

**Wildlife card height:** `.wfig` uses `height: 160px` (fixed, not `min-height`) — all cards are uniform regardless of image proportions. `.wfig img` uses `width: auto; max-width: 250px; height: auto; max-height: 100%`. The `width: auto` is required — CSS proportional scaling only kicks in when both width and height are `auto`; a fixed `width: 90%` with `max-height` would squish tall images instead of scaling them.

**Adding a new wildlife image — full workflow:**
1. Prepare the asset: `python prepare_wildlife_asset.py input.png theme-dev/great-lake-cleaners-theme/assets/images/name.png` (defaults: tolerance 28, 600px wide, 20px pad — see tool docs in theme CLAUDE.md)
2. Add a keyword match in `page-stats.php:glc_stats_wildlife_img()` — e.g. `if ( strpos( $obs, 'nest' ) !== false ) return 'nest.png';`
3. Run `repack.ps1`

**Footer anchor links** (`#debris`, `#hours`) work because `.dirCL-sec` has `scroll-margin-top: 110px`.

### Photos Page (`/photos/`)

Only attachments with `_glc_gallery = '1'` meta appear. Flagging workflow: Media Library → click photo → attachment modal → Gallery row → tick "Feature in photo gallery" → click **Save**. Implemented via `attachment_fields_to_edit` / `attachment_fields_to_save` hooks.

**Important — must click Save:** the flag is only persisted when you click the **Save** button in the attachment sidebar. Checking the box and then clicking **Select/Insert** (in the block editor media modal) does NOT save custom fields — that flow uses `wp_ajax_save_attachment` which bypasses `attachment_fields_to_save`. Always flag images from Media Library directly, not from within a post's Insert Media dialog.

**Attachment lookup:** `[glc_gallery]` queries all `_glc_gallery=1` images globally (not filtered by `post_parent`), so images with any `post_parent` value are included.

**Gallery thumbnail Option B (not yet done):** register `glc-thumb` image size with `crop: ['center', 'top']` in `functions.php`, use it in the gallery shortcode instead of `medium`, run "Regenerate Thumbnails". Worth doing when the gallery grows large enough that payload matters.

### Submit a Cleanup Page

"Number of People" is in section 2 (The Cleanup), not section 3 (What You Collected) — it belongs with outing details, not debris. Has `?` tooltip: "Used to calculate volunteer hours".

### WordPress Pages Required

| Title | Slug | Template | Notes |
|---|---|---|---|
| Home | `home` | (default) | Blank — set as static front page |
| Photos | `photos` | Photos | Blank — template calls `[glc_gallery]` |
| Stats | `stats` | Stats | Blank — template is self-contained |
| Submit a Cleanup | `submit-cleanup` | (default) | Blank — template handles layout |
| Privacy Policy | `privacy-policy` | (default) | Blank — template handles content |
| Report an Issue | `report-issue` | (default) | Blank — template handles layout |
| Join our Crew | `join-crew` | Join our Crew | Blank — template handles layout |

**Do not create a page with slug `events`** — it collides with the `glc_event` archive rewrite.

### Accessibility

- **Focus styles:** Global `:focus-visible` (3px solid gold, 2px offset) at end of `style.css`. Form inputs use gold outline on `:focus` — the old `outline: none` was a WCAG 2.4.7 failure.
- **Community badge contrast:** `.glc-community-badge` and `.glc-fp-label` use `--glc-green-dark` (not `--glc-green`) for WCAG AA.
- **Instagram header link:** `aria-label` on the `<a>`, SVG with `aria-hidden="true" focusable="false"`. `.glc-insta-link` has `min-width/height: 24px; padding: 2px` for 24×24 touch target.
- **Wave SVG:** The `.glc-wave-footer` outer div has `aria-hidden="true"`. The `<svg>` also carries `aria-hidden="true" focusable="false" role="presentation"` for older browsers that ignore parent `aria-hidden`.
- **Footer stat label contrast:** `.glc-stat-lbl` is `rgba(255,255,255,0.78)` — confirmed ≈5.3:1 against navy.
- **Recycled items suffix:** The `$ic` closure call for recycled items uses `'items'` as suffix so screen readers hear "33 items" not a bare number.

---

## Next Steps

- [ ] **Events rollout (~July 2026)** — The `glc_event` feature is fully built and packed in both zips (plugin 1.1.0, theme 1.2.0) but **not yet deployed to production**. Holding until closer to the first hosted community event. Rollout checklist:
  1. Upload both zips to production (plugin: Plugins → Upload; theme: Appearance → Themes → Upload)
  2. **Deactivate → reactivate the plugin** (rewrite flush — skipping this 404s `/events/`)
  3. Appearance → Menus → primary: add Custom Link `/events/`, label "Events", after Cleanups
  4. Create the first event (Events → Add New Event): date, start/end time, site, meeting point, GPS, what to bring
  5. Verify: `/events/` archive, single page map + RSVP (test email arrives at info@ with Reply-To), front-page "Upcoming events" section appears, footer stats unchanged
  6. After the cleanup happens: log the `cleanup_event` as usual, then set "Linked Cleanup Report" on the event so it shows "See the results →"
- [ ] **Donate / support page** — E-transfer or PayPal link, honest note that tax receipts aren't available pre-incorporation. **Blocked on:** deciding on a dedicated e-transfer email or PayPal account separate from `info@`.
- [ ] Consider physical badge ("Watershed Steward" patch) for top contributors at year-end — award based on cleanups logged (3+), not weight.
- [ ] **Gallery thumbnail Option B** — register `glc-thumb` custom image size with `crop: ['center', 'top']`, update gallery shortcode, run "Regenerate Thumbnails". Do when gallery load time becomes a concern.
