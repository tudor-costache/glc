# Great Lake Cleaners — Project Context

## Identity

**Organization:** Great Lake Cleaners  
**Tagline:** The lake starts here.  
**Mission:** Regular cleanups of Guelph's local waterways — by foot and paddle — that flow into the Great Lakes system via the Grand River and Lake Erie.  
**Email:** `info@greatlakecleaners.ca`  
**Instagram:** https://instagram.com/greatlakecleaners  
**Substack:** https://greatlakecleaners.substack.com/  
**Fundraiser (PayPal Pool):** https://www.paypal.com/pools/c/9rTJrg2a4B — cigarette butt dispensers at trail heads

Social links appear in the header and footer, and in the NGO JSON-LD `sameAs` array in `header.php` — **add new profiles to all three.**

The fundraiser URL is **not** a `sameAs` entry (it isn't a profile identifying the org). It lives in one place — `GLC_DONATE_URL` in `functions.php` — and is read by the header icon, the footer icon, and the NGO JSON-LD `potentialAction` (`DonateAction`). Change the pool link there only.

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
| Tires Removed | `tires_removed` | car/truck tires only; feeds `[glc_impact_highlights]`; **shared key** with `glc_submission` |
| Bikes Removed | `bikes_removed` | whole bicycles; split out from Tires Removed 2026-08; feeds `[glc_impact_highlights]`; **shared key** with `glc_submission` |
| Shopping Carts Removed | `carts_removed` | feeds `[glc_impact_highlights]`; **shared key** with `glc_submission` |
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
| Tires Removed | `glc_tires_removed` / `tires_removed` | dual-key — see note below |
| Bikes Removed | `glc_bikes_removed` / `bikes_removed` | dual-key — see note below |
| Shopping Carts Removed | `glc_carts_removed` / `carts_removed` | dual-key — see note below |
| Volunteers | `glc_volunteers` |
| Person-Hours | `glc_hours` |
| Notable Finds | `glc_notable_finds` |
| Wildlife Observed | `glc_wildlife_obs` / `wildlife_obs` | dual-key — see note below |
| Instagram URL | `glc_instagram_url` |
| Photo Repost Consent | `glc_photo_repost_ok` |
| Photo IDs | `glc_photo_ids` |

**Key:** `items_recycled`, `weight_kg`, `tires_removed`, `bikes_removed`, and `carts_removed` are stored without the `glc_` prefix (matching `cleanup_event`) so `glc_get_impact_stats()`, `[glc_impact_highlights]`, and the `/cleanups/` `?impact=` filter can aggregate both post types without special-casing. Wildlife, tires, bikes, and carts are also stored under their `glc_`-prefixed name for the admin meta box. If footer stats, wildlife cards, or impact-highlight totals diverge between CPTs, check these shared keys first.

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
| `[glc_map]` | Leaflet map. Attrs: `height`, `post_id` (single-event mode), `limit` (markers per cluster), `cluster_radius` (km), `corridors` (river corridor lines, see below), `corridor_pins` (default `1` — the gold cumulative-impact pins that come with `corridors`; `0` = lines only, no extra marker layer), `corridor_bounds` (default `1` — whether corridor pins can widen the map's fit-to-bounds zoom; no effect when `corridor_pins="0"`, since there's nothing to include either way), `markers` (default `1` — render individual site pins; `0` for a corridor-pins-only view). Clustering is greedy: markers sorted by impact score (kg + bags×2), each joins nearest anchor within radius. Hero uses `limit="5" cluster_radius="10" corridors="1" corridor_pins="0"` — site pins plus river lines, no gold pins (see Front Page for why); archive uses `limit="7" cluster_radius="10" corridors="1" markers="0"` — corridor pins (lines + pins) only, no site pins. |
| `[glc_archive]` | Paginated cleanup archive |
| `[glc_submit_form]` | Community submission form |
| `[glc_gallery]` | Photo gallery — year tabs + lightbox. Only images flagged `_glc_gallery=1` appear. Global meta query finds all flagged attachments regardless of `post_parent` — images inserted from the Media Library (which keep `post_parent=0`) are included. Within each year, photos sort by `sort_date` (cleanup date if known, upload date as fallback). Attr: `limit` (default `0` = full year-tabbed gallery; `>0` drops the tabs and shows only the newest N in one grid — the Crew at Work wall). |
| `[glc_video_gallery]` | Video gallery — same year tabs + lightbox as `[glc_gallery]`, playing self-hosted clips. Only videos flagged `_glc_video_gallery=1` appear. Same `limit` attr as `[glc_gallery]`. See **Videos Page** below. |
| `[glc_report_form]` | Waterway issue report |
| `[glc_timeline]` | SVG area chart (debris + recycled) — same renderer as `page-stats.php`. Outputs `.dirCL-chartwrap` + `.dirCL-legend`. No Chart.js. |
| `[glc_impact_highlights]` | Grouped by meaning, not one flat grid: `.glc-ih-top` puts the SVG hours chart (`.dirCL-chartwrap` + `.dirCL-legend`, same renderer as `page-stats.php`, no Chart.js) beside two stacked "headline" cards (unique sites, total cleanups) in `.glc-ih-headline`; below that, `.glc-ih-removed` holds the three "removed from the water" cards (tires, bikes, shopping carts) side by side. Cards (`.glc-ih-card`) are icon-left / label+value-right — the same compact shape as the `/stats` wildlife cards and the single-event Wildlife Observed block (`.glc-wildlife-thumb`) — not a tall icon-over-number stack. Headline cards get a `var(--glc-green)` left border (matching the hours chart's line color) instead of the default navy, so the eye groups them apart from the three material cards below; hover state only re-colors the top/right/bottom border to navy, deliberately leaving the left accent untouched so the category color persists through interaction. Card icons reuse the `/stats` pictograph PNGs (`tire-icon.png`, `bike-icon.png`, `cart-icon.png`) plus `sites-icon.png` and `garbage-bag.png`, fetched from the theme's `assets/images/` via `get_stylesheet_directory_uri()` (plugin code reaching into theme assets — same pattern as the submit-form thank-you image in `submission.php`). Tires/bikes/carts cards link to `/cleanups/?impact=tires` / `?impact=bikes` / `?impact=carts` (handled by `archive-cleanup_event.php`, filters to cleanups with that count > 0); sites/total cards link to plain `/cleanups/`. |
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

**Social icons:** Instagram + Substack + a donate heart (`GLC_DONATE_URL`), all inside `.glc-header-social` (a flex **row**) nested in `.glc-header-actions`. The wrapper is required — `.glc-header-actions` is `flex-direction: column`, so icons added to it directly stack vertically instead of sitting side by side. All three links use the `.glc-insta-link` class (named before Substack existed — it is the shared social-icon style, not Instagram-specific). `.glc-header-actions` is `display: none` below 768px, so no icon shows on mobile — the footer row covers social + donate there.

**Icon weight matching:** the Instagram mark and the donate heart are both stroke-drawn (`fill="none"`, `stroke-width: 1.8`) and inset to ~20 of their 24 units; the Substack mark is a solid `fill` path spanning the full 24. Rendered raw, Substack reads noticeably larger and heavier. It is wrapped in `<g transform="translate(12,12) scale(0.85) translate(-12,-12)">` — scaling about the centre brings its height to ~20.4 to match. Keep the wrapper if the path is ever swapped. Both icons use `fill`/`stroke="currentColor"` so they inherit the white-70% default and gold hover.

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

**Social icons:** Instagram + Substack + donate heart, all using the `.glc-footer-insta` class, inline in the `.glc-footer-base` bar after the Privacy Policy link. Separated by `.glc-footer-insta + .glc-footer-insta { margin-left: 8px }` — they sit adjacent, with no `·` divider between them (the `·` separators divide *sections*, not the icon run).

**Hover specificity:** Must be `.glc-footer-base a.glc-footer-insta:hover` to beat `.glc-footer-base a:hover`.

**`.glc-footer-base a`** must be defined only once in `style.css`. A duplicate rule previously existed in the Privacy Policy section that silently overrode the color with `rgba(255,255,255,0.6)` and removed the underline — do not re-add a second rule.

### Three-Layer Footer Wave

SVG viewBox `0 0 1200 80`. Three paths: `#5a9fc0` at 45% opacity (lightest, highest crest), `#2d6a96` (mid), `#1a4a6b` (exact footer navy, lowest — merges into footer). `<footer>` has `margin-top: -2px` and wave div has `margin-bottom: -2px` to close sub-pixel seams.

### Maps

`isolation: isolate` on all three map wrapper classes — must be preserved. Leaflet's internal pane z-indices (200–600) must be contained within a stacking context to prevent overlapping the sticky header.

### Front Page

**Hours display on cards:** values under 1 hour display as minutes (`30 min`); at or above display as `1.5 h`. Applied in `front-page.php` and `archive-cleanup_event.php`.

**Portrait photos:** `object-position: center top` inline on the "Get Involved" photo keeps the volunteer's face in frame.

**Hero map shows river lines, but no gold corridor pins** — `[glc_map ... corridors="1" corridor_pins="0"]`. First attempt used plain `corridors="1"` with `corridor_bounds="0"`: gold corridor pins mixed with the navy site pins read as cluttered, and `corridor_bounds="0"` alone didn't fully fix it since the hero's own site markers (`limit="5" cluster_radius="10"`) can already span a wide area on their own when a recent high-impact cleanup happens to be far away — corridor pins on top of an already-wide view just added more noise regardless of whether they could *widen* it further. Dropped corridors entirely for one iteration, then brought back just the lines (`corridor_pins="0"`) once it was clear the actual complaint was the extra marker layer, not the rivers themselves — the lines alone read as context, not clutter.

### Archive Page (`/cleanups/`)

Fetches all `cleanup_event` + published `glc_submission` posts, merges, sorts by date descending, paginates at 12. Map section has `id="cleanups-map"` and `scroll-margin-top: 110px` — the River Corridors footer stat links directly to it.

**Filters:** `?impact=tires|bikes|carts` (from `[glc_impact_highlights]`) and `?corridor={slug}` (from a gold corridor pin on the map, or hand-built — see below) both filter `$all_cleanups` and can combine (AND). `$any_filter = $impact_filter || $corridor_filter` gates the "show a back link, swap the empty-state message" behavior. `$corridor_filter` is validated against `glc_corridor_table()` (same table the map/geojson matching uses) via `array_key_exists()`, and each `$c` in `$all_cleanups` carries a `'corridor'` key (`glc_corridor_slug()` of the post's raw `corridor`/`glc_corridor` meta) so the filter can compare slugs, not free text. `$pagenum_link()` preserves both query args across pagination.

**The `#cleanups-map` section is always rendered** — not gated on `$any_filter`, and not gated on `$all_cleanups` being non-empty. It used to hide whenever any filter was active ("the map shows every cleanup location, not just the filtered subset"), but that reasoning applied to the old individual-site-pin map; the corridor-pins-only map (`markers="0"`, see below) summarizes every corridor regardless of the current filter, so it stays a valid "jump to a different corridor" point no matter how deep into a filtered/paginated view someone is — e.g. `/cleanups/page/4/?corridor=speed-river` still shows it at the bottom.

**River corridor overlay:** `[glc_map ... corridors="1"]` draws river/creek lines, plus (unless `corridor_pins="0"`) one gold diamond pin per corridor showing cumulative bags/kg/items recycled and a "View cleanups on the {corridor}" link to `/cleanups/?corridor={slug}`. `#cleanups-map` uses the full thing with `markers="0"` (corridor pins carry the summary, individual site pins are hidden entirely — see `markers` below); the front-page hero uses `corridor_pins="0"` (lines only, alongside its normal site pins — see Front Page for why); not passed on single-event maps or `glc_event` maps.

- **Corridor table:** `glc_corridor_table()` in `shortcodes.php` is the single source of truth for known corridor slugs — free-text `corridor` (`cleanup_event`) / `glc_corridor` (`glc_submission`) meta is matched against it via `glc_corridor_slug()` (trim/case/apostrophe-normalized), same spirit as `glc_stats_wildlife_img()`'s text-to-bucket matching. Add a new corridor by adding one row here **and** one row in `prepare_corridors_geojson.py`'s `CORRIDORS` list — the `slug` must match exactly in both places. Grand River is deliberately absent from this table (see below).
- **Line geometry** lives in `plugin-dev/great-lake-cleaners/assets/corridors.geojson`, prepared offline by `prepare_corridors_geojson.py` (repo root) — not a live dependency. Re-fetch one or a few corridors without re-querying (and re-rate-limiting against) everything else: `python prepare_corridors_geojson.py speed-river eramosa-river` — this patches just those slugs into the existing file.
- **`fetch_corridor()` always queries both sources and keeps whichever is richer** (by total coordinate points), rather than stopping at the first source that returns *anything*. This isn't optional polish — OHN returning a technically-non-empty but nearly useless match (a single 2-point stub for Big Creek; a rural-only fragment for Speed/Eramosa that misses the urban core entirely) and the code stopping there was a real, recurring bug. `osm_only: True` on a corridor just skips a known-always-empty OHN call as an optimization; it doesn't change the "richer wins" comparison.
  - **OHN** (Ontario Hydro Network Watercourse, ArcGIS REST, Open Government Licence – Ontario) is queried by exact official name near a known cleanup-site GPS anchor, radius expanding tight → wide until something matches. Its official naming is sparse — most segments, even along clearly-named creeks, carry no name at all, and a river can be named upstream but not through the stretch that actually matters.
  - **OSM** (Overpass API) is queried the same way but **widest-first**: many rivers are digitized as several disconnected ways rather than one relation (Nine Mile River, Ausable River), so stopping at the *first* radius that returns anything tends to grab an incomplete fragment, not the full river. A wider bbox costs nothing extra in false-positive risk for a name-filtered query, so search wide → narrow and keep the first success; narrower boxes only get tried as a fallback when the wide query times out under Overpass's (frequent) server load.
  - Nine Mile River and Grand River skip the anchor+radius search entirely and use a fixed `bbox` instead — both are so fragmented into disconnected ways that even the widest anchor radius wasn't reliably capturing everything; a bbox sized to the known full extent is simpler and more consistent than chasing radius sizes.
- **OSM tags wider river stretches as an area, not a centerline — `query_osm()` must search both.** Nine Mile River's middle stretch (the part that actually reaches the cleanup site) looked like a genuine data gap at first — the named `waterway=stream`/`river` ways stopped ~6.5km short on both ends. It wasn't a gap: that whole stretch is mapped as a `natural=water` + `water=river` polygon (a "river area", visible as a filled shape in the iD editor) with no `waterway` tag on it at all, so the original centerline-only query silently skipped it. `query_osm()` now searches both tagging styles in one combined query (see the four `way`/`relation` clauses inside it) — a closed polygon `way` just gets treated as a `LineString` of its boundary ring, which draws as a thin doubled line tracing both banks; no PHP/JS changes were needed since everything downstream already only cares about LineString/MultiLineString. If a corridor still looks incomplete after this, check the iD editor directly for a filled "River Area" shape before assuming another anchor/radius/bbox tweak will fix it.
- **Grand River** is fetched OSM-only (OHN only tags a ~1km stretch near Fergus for it — nowhere near the full Ontario course) and rendered as a **context line with no pin** — it's not itself a cleanup corridor, just the trunk river GLC's corridors flow into. It's intentionally excluded from `glc_corridor_table()` so a cleanup can never accidentally get slugged to it.
- **Pin-only fallback:** a corridor with cumulative totals but no matched line geometry still gets a pin, placed at the average GPS of that corridor's cleanup sites instead of the line's midpoint. A corridor with zero published cleanups gets neither line-triggered nor GPS-triggered pin (skipped) — this is why a not-yet-published cleanup's corridor (e.g. a brand-new site) shows no pin until it's published, even if its line is already in the static file.
- **Marker placement on a matched line:** `glc_corridor_midpoint()` walks the corridor's (possibly multiple, disjoint) line segments by cumulative arc length and drops the pin at the halfway point — deliberately on the river, not at an off-line centroid.
- **`markers="0"`** suppresses individual site pins entirely (rendering *and* the fit-to-bounds calculation) — used on the archive map so the corridor pins are the whole story instead of competing with dozens of navy droplets. Each corridor's `slug` travels all the way to JS (stored in `corridor_totals`, stripped of its array key by `array_values()` so it has to be an explicit field) so the popup can link to `/cleanups/?corridor={slug}` — see the Archive Page's `$corridor_filter`.
- **Line data is fetched client-side**, not inlined — `corridors.geojson` is over 1MB (15 corridors' worth of real geometry, several including full river-area polygon outlines), too large to embed in every page load via `wp_json_encode()` like the marker array is. The JS `fetch()`s the file directly; `corridor_totals` (small) is still inlined the normal way. The OHN attribution is added to the Leaflet attribution control only after that fetch resolves; OSM's is already covered by the base tile layer's existing credit.
- **Caching:** `plugin-dev/great-lake-cleaners/assets/.htaccess` gzips `corridors.geojson` (1.3MB → ~350KB on the wire) and caches it 1 month — nothing in WordPress itself sets these headers, a shared host's defaults usually don't cover an uncommon extension like `.geojson`, so without this file it's whatever the host happens to do. Long caching is safe because the fetch URL is versioned with the file's mtime (`?v=<filemtime()>`, set in both `glc_shortcode_map()` and `page-stats.php`) — any re-run of the prep script changes the URL, so there's no stale-cache risk to weigh against the cache lifetime. All three maps (`#cleanups-map`, the wildlife map, the front-page hero) request the exact same URL, so a visitor who's hit any one of them gets the other two from cache.
- **`corridor_pins="0"`** keeps the river lines but skips the gold diamond layer (and its bounds contribution) entirely — the front-page hero's setting. Independent of `markers`: the hero runs `markers="1"` (default, its normal site pins) + `corridors="1"` + `corridor_pins="0"`, i.e. site pins and river lines together, no corridor pins at all.
- **`corridor_bounds` (default `1`):** controls whether corridor pins can widen the map's fit-to-bounds zoom, only relevant when `corridor_pins` is on. The archive map wants it on — it's a "show everything" page. No view currently sets it to `0` (the hero solves the same problem more simply via `corridor_pins="0"` — no pins at all beats pins-that-don't-affect-zoom for a curated view) but it's kept for a hypothetical view that wants corridor pins visible without letting a far-off one drag the zoom out.

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

**Large items pictograph ("Tires, bikes & shopping carts, pulled from the water"):** sits between the Hours and Wildlife sections (own pair of wave dividers, both skipped together when `$_total_tires`, `$_total_bikes`, and `$_total_carts` are all 0 — same "vanish together" pattern as the front-page Upcoming Events divider). One icon per unit — **not** scaled down like the item dot grid, since tire/bike/cart counts are small. Built from `$_tire_items` / `$_bike_items` / `$_cart_items` in `page-stats.php`, populated by walking `array_merge( $_events, $_subs )` and reading the shared `tires_removed` / `bikes_removed` / `carts_removed` keys via `glc_cleanup_field()` (same aggregation `[glc_impact_highlights]` uses). Each icon is a real `<a>` linking to its source post, with `aria-label`/`title` built from site + date so a screen reader gets a distinct name per icon rather than N identical unlabeled links. Icons are `tire-icon.png` / `bike-icon.png` / `cart-icon.png` in `assets/images/` — plain cropped PNGs prepared with `prepare_wildlife_asset.py` (main output only; no `--pin` needed since these aren't map markers, just inline icons at `object-fit: contain` in a 40×40 box). **Row layout:** `.dirCL-picto-row` is a horizontal-bar-chart style flex row — fixed-width (`flex: 0 0 200px`) label gutter on the left (`.dirCL-picto-lbl`) so icons start at the same x-position on every row regardless of label text length, icons flowing inline to the right (`.dirCL-picto-icons`) — not stacked label-above-icons, and no border divider between rows (just a small `margin-top`). Stacks back to label-above-icons under 560px (label reverts to auto width there). Visible labels are just the unit name ("5 tires", "4 bikes") — no "removed" suffix, since the section heading and intro paragraph already establish that.

**Wildlife cards:** `glc_stats_wildlife_img()` maps observation text to an image filename — see `page-stats.php` for the current list. Cards show a brand-tinted gradient stage (`.wfig`) with the illustration + `drop-shadow`, then `.wbody` below with observation and site/date. Stagger delay `0.15 + i × 0.12s` via inline `--d` CSS property. **Sightings that don't match a known species are excluded entirely** (not rendered as a text-only card) — `page-stats.php` filters `$_wildlife_events`/`$_wildlife_all` down to `glc_stats_wildlife_img()` matches right after the initial query, before dedup, so unrecognised free text (typos, test data) never reaches the "Who we met along the way" section or its map. Gold border + shadow on hover; title shifts to gold-deep. No translateY on hover. (Single `cleanup_event`/`glc_submission` pages still show the raw Wildlife Observed text regardless of a match — this exclusion is `/stats`-only.)

**Wildlife data source:** `page-stats.php` queries both `cleanup_event` and `glc_submission` CPTs for the `wildlife_obs` meta key (same unprefixed key on both). Sorted via `glc_cleanup_field()` for CPT-agnostic date access. Dedup key is the matched image filename (e.g. "Snapping Turtle" and "snapping turtle" share one slot) since every post reaching dedup already has a match.

**Wildlife card height:** `.wfig` uses `height: 160px` (fixed, not `min-height`) — all cards are uniform regardless of image proportions. `.wfig img` uses `width: auto; max-width: 250px; height: auto; max-height: 100%`. The `width: auto` is required — CSS proportional scaling only kicks in when both width and height are `auto`; a fixed `width: 90%` with `max-height` would squish tall images instead of scaling them.

**Adding a new wildlife image — full workflow:**
1. Prepare the asset: `python prepare_wildlife_asset.py input.png theme-dev/great-lake-cleaners-theme/assets/images/name.png --pin`
   - Outputs `name.png` (600px wide card image) + `name_s.png` (200×200px map pin crop)
   - Defaults: `--tolerance 28 --width 600 --pad 20 --pin-anchor right --pin-pad 8`
   - `--pin-anchor left` for left-facing animals; `--pin-anchor center` for symmetric subjects (e.g. nest/eggs)
   - `--pin-pad` = breathing room on the nose side in output pixels (default 8 ≈ 2px at 48px display); increase if the face feels cramped
2. Re-crop an existing asset's pin only: `python prepare_wildlife_asset.py name.png name.png --pin-only`
   - Skips bg removal and resize; autocrop + pin crop only. Add `--pin-anchor` / `--pin-pad` as needed.
3. Add a keyword match in `page-stats.php:glc_stats_wildlife_img()` — e.g. `if ( strpos( $obs, 'nest' ) !== false ) return 'nest.png';`
4. Run `repack.ps1`

**Windows console quirk:** the script's final summary line prints `×`/`→`, which raises `UnicodeEncodeError` under the default Windows `cp1252` console — this happens *after* the PNG is already saved, so it's cosmetic, not a failed run. Set `PYTHONIOENCODING=utf-8` before the command to see the full output without the traceback.

**Wildlife map pin lookup:** `page-stats.php` checks for `{stem}_s.png` via `file_exists()` and uses it when present; falls back to the card image. `_s.png` files are optional — the fallback looks reasonable; add a pin crop when the full card image crops badly at 48px.

**Wildlife map river corridor lines:** the same `corridors.geojson` static asset used on `/cleanups/#cleanups-map` (see Archive Page) is fetched and drawn under the wildlife pins here too — lines only, no cumulative-impact pins, since this map is about sightings, not debris totals. `$_corridor_lines_url` is set right after the Leaflet enqueue block, only when `$_wl_markers` is non-empty (no point loading it for an empty map) and `corridors.geojson` exists. The JS block is a near-duplicate of the archive map's line-rendering snippet (fetch → `L.geoJSON` → `bringToBack()` → attribution) rather than a shared helper — the two maps are otherwise independent Leaflet instances with no existing shared JS to hook into. `fitBounds` still only considers wildlife markers, not the corridor lines — the lines are context for whatever's already in view, not something the map should re-zoom to show in full (several corridors run well outside the Guelph area).

**Footer anchor links** (`#debris`, `#hours`) work because `.dirCL-sec` has `scroll-margin-top: 110px`.

### Photos Page (`/photos/`)

Only attachments with `_glc_gallery = '1'` meta appear. Flagging workflow: Media Library → click photo → attachment modal → Gallery row → tick "Feature in photo gallery" → click **Save**. Implemented via `attachment_fields_to_edit` / `attachment_fields_to_save` hooks.

**Important — must click Save:** the flag is only persisted when you click the **Save** button in the attachment sidebar. Checking the box and then clicking **Select/Insert** (in the block editor media modal) does NOT save custom fields — that flow uses `wp_ajax_save_attachment` which bypasses `attachment_fields_to_save`. Always flag images from Media Library directly, not from within a post's Insert Media dialog.

**Attachment lookup:** `[glc_gallery]` queries all `_glc_gallery=1` images globally (not filtered by `post_parent`), so images with any `post_parent` value are included.

**Gallery thumbnail Option B (not yet done):** register `glc-thumb` image size with `crop: ['center', 'top']` in `functions.php`, use it in the gallery shortcode instead of `medium`, run "Regenerate Thumbnails". Worth doing when the gallery grows large enough that payload matters.

### Videos Page (`/videos/`)

Self-hosted clips from the Media Library, rendered by `[glc_video_gallery]` in `page-videos.php`. **The photo and video galleries are one engine** — see **Shared gallery engine** below.

**Flagging workflow:** identical to photos, and subject to the same *must click Save* caveat — Media Library → click the video → Gallery row → tick "Feature in video gallery" → click **Save**. The flag is `_glc_video_gallery`, a **separate meta key** from the photo gallery's `_glc_gallery`, so a clip can never leak into `/photos/`.

**The checkbox is mime-scoped.** `glc_gallery_flag_for_attachment()` in `admin.php` decides which checkbox an attachment gets: images get "Feature in photo gallery", videos get "Feature in video gallery", everything else (PDFs, audio) gets neither. Previously the photo checkbox rendered on *every* attachment type — if you add a third gallery, extend that one function rather than adding another `attachment_fields_to_edit` filter.

**Thumbnails — why there is no poster image:** WordPress does not extract poster frames from uploaded video (that needs `ffmpeg` on the host, which the shared host doesn't have). Each tile therefore embeds the clip itself with `preload="metadata"` and a **`#t=0.5` media fragment** — browsers seek to that timestamp and paint the frame, downloading only the file header rather than the whole clip. If a featured image *has* been set on the attachment (`_thumbnail_id`), it wins over the extracted frame. Do not "fix" this by loading full clips into the grid.

**Duration pill** reads `length_formatted` from `wp_get_attachment_metadata()` (getID3 fills it at upload time). It sits **top right** — the bottom edge is taken by the hover caption, and stacking the two collides.

**Upload size:** clips are capped by `upload_max_filesize` (currently 64M — see the PHP upload limit note at the top of the theme section). Long clips need either a raised limit or trimming before upload; video also eats host disk and bandwidth in a way photos don't, so keep the gallery curated rather than exhaustive.

**Aspect ratio:** tiles inherit the photo grid's `4 / 3` box with `object-fit: cover`, so vertical phone video is centre-cropped in the grid and plays full-frame in the lightbox. Video tiles use `object-position: center` (not the photos' `center top`) because clips are framed mid-screen.

### Crew at Work Page (`/see-us-in-action/`)

A combined **recent-media landing page** — `page-see-us-in-action.php` — sitting in front of `/photos/` and `/videos/`, not replacing them. Two `<section>`s: the newest 20 photos, then the newest 10 videos, each headed by a section title + an `.glc-spot-view-all` link ("All photos →" / "All videos →") through to the full standalone gallery.

**Name vs. slug:** the page was first built as "See Us In Action" and renamed to "Crew at Work" after launch. The **file name and URL slug stay `see-us-in-action`** (renaming the template file would drop the page's `_wp_page_template` binding and 404 the URL); only the label moved. The `<h1>` is **not** hardcoded like `page-photos.php` / `page-videos.php` — it echoes `get_the_title()`, so the heading (and, via `title-tag`, the `<title>`) is renamed entirely from the WP page editor with no code change. Template Name header is "Crew at Work".

- **No new shortcode.** The template is just `[glc_gallery limit="20"]` + `[glc_video_gallery limit="10"]`. The `limit` attr (see the Shortcodes table) makes each shortcode swap `glc_gallery_group_by_year()` for `glc_gallery_recent_grouping()` — one flat, **tab-less** bucket (keyed `0`) of the newest N by `sort_date`. Everything downstream (`glc_gallery_flatten()`, `glc_gallery_render_tabs()` → `''` for a one-bucket set, the grid loop, `glc_gallery_render_script()`, the lightbox) runs unchanged. Two independent gallery instances (random IDs) coexist on the page; each `keydown` handler early-returns on `lb.hidden`, so only the open lightbox responds.
- **CSS:** reuses `.glc-photos-wrap` chrome + the entire gallery/lightbox shell. New rules are only `.glc-media-sec` / `.glc-media-sec-head` / `.glc-media-sec-title` and a `.glc-media-wrap .glc-gallery-wrap` top-margin trim (no tab strip to leave room for). The see-all links reuse `.glc-spot-view-all` from the front-page spotlight.
- **Not a CPT archive** — a page template, so **no rewrite flush** on deploy. Needs a WP page (slug `see-us-in-action`, template "Crew at Work") and a primary-menu entry. `glc_nav_fallback()` links it (labelled "Crew at Work") when the page exists.

### Shared gallery engine

`[glc_gallery]` and `[glc_video_gallery]` are the same object with a different medium. A handful of helpers in `shortcodes.php` hold everything common — **change behaviour there, not in one shortcode**, or the two galleries drift:

| Helper | Does |
|---|---|
| `glc_gallery_collect( $mime, $flag_key )` | Queries flagged attachments of one mime family, resolves parent metadata (cleanup date, site label, permalink), enforces submission repost consent, derives year + `sort_date` |
| `glc_gallery_group_by_year( $items )` | `[ year => items ]`, years descending, items by `sort_date` descending |
| `glc_gallery_recent_grouping( $items, $limit )` | Same `[ key => items ]` shape but one bucket keyed `0` — newest `$limit` by `sort_date`, no year split. Used by `[glc_gallery]` / `[glc_video_gallery]` when `limit>0` (the Crew at Work wall) |
| `glc_gallery_flatten( $by_year )` | Flat list for lightbox navigation + per-year offsets for `data-global-idx` |
| `glc_gallery_render_tabs( $by_year, $label )` | Year tab strip; returns `''` for a single year |
| `glc_gallery_render_script( $id, $items, $is_video )` | Tab switching + lightbox. `$is_video` swaps the `<img>` for a `<video>`, pauses/reloads on navigate and close, hands arrow keys back to the player when it has focus so they seek instead of changing clip, and adds the player to the Tab focus ring |

The video gallery reuses the photo CSS wholesale (`.glc-gallery-wrap`, `-tabs`, `-grid`, `-thumb-btn`, and the whole `.glc-lb-*` lightbox shell). Video-only CSS is just `.glc-video-thumb-btn`, `.glc-vid-play`, `.glc-vid-dur`, and `.glc-lb-video`.

**`.glc-lb-video` needs `width: auto`** — same reason as the wildlife card images: proportional scaling only kicks in when width is auto, otherwise a vertical clip gets stretched to the wrapper width.

**The `<video>` is inside the focus trap.** The lightbox traps Tab among its own controls; the player is pushed into that ring (photos aren't — an `<img>` has nothing to operate) so keyboard users can reach play/seek at all. Removing it from `focusables` silently makes the player keyboard-unreachable.

**Unparented media hides the "View outing" link.** `glc_gallery_render_script()` sets `lbLink.hidden` when an item has no published parent, and rebuilds the Tab focus ring from the *visible* controls — a Media Library upload with no cleanup behind it would otherwise show a dead href and trap a tab stop.

### Submit a Cleanup Page

"Number of People" is in section 2 (The Cleanup), not section 3 (What You Collected) — it belongs with outing details, not debris. Has `?` tooltip: "Used to calculate volunteer hours".

### WordPress Pages Required

| Title | Slug | Template | Notes |
|---|---|---|---|
| Home | `home` | (default) | Blank — set as static front page |
| Photos | `photos` | Photos | Blank — template calls `[glc_gallery]` |
| Videos | `videos` | Videos | Blank — template calls `[glc_video_gallery]` |
| Crew at Work | `see-us-in-action` | Crew at Work | Blank — template calls `[glc_gallery limit="20"]` + `[glc_video_gallery limit="10"]`; slug stays `see-us-in-action` (do not rename the file) |
| Stats | `stats` | Stats | Blank — template is self-contained |
| Submit a Cleanup | `submit-cleanup` | (default) | Blank — template handles layout |
| Privacy Policy | `privacy-policy` | (default) | Blank — template handles content |
| Report an Issue | `report-issue` | (default) | Blank — template handles layout |
| Join our Crew | `join-crew` | Join our Crew | Blank — template handles layout |

**Do not create a page with slug `events`** — it collides with the `glc_event` archive rewrite.

### Accessibility

- **Focus styles:** Global `:focus-visible` (3px solid gold, 2px offset) at end of `style.css`. Form inputs use gold outline on `:focus` — the old `outline: none` was a WCAG 2.4.7 failure.
- **Community badge contrast:** `.glc-community-badge` and `.glc-fp-label` use `--glc-green-dark` (not `--glc-green`) for WCAG AA.
- **Social header links:** `aria-label` on each `<a>` (icon-only links have no accessible name otherwise), SVG with `aria-hidden="true" focusable="false"`. `.glc-insta-link` has `min-width/height: 24px; padding: 2px` for 24×24 touch target. Same `aria-label` pattern on the footer pair.
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
- [ ] **Videos + Crew at Work rollout** — `[glc_video_gallery]` + `page-videos.php` and the combined `page-see-us-in-action.php` (published as **Crew at Work** at `/see-us-in-action/`, under a "Media" nav dropdown) shipped in plugin 1.3.1 / theme 1.4.3. The Crew at Work page is live; remaining:
  1. Re-upload the theme zip after the Crew at Work rename (theme 1.4.4) so the `<h1>` / nav label / Template Name follow the new name
  2. Pages → Crew at Work: set the page **Title** to "Crew at Work" (leave the slug `see-us-in-action`) — the `<h1>` now echoes the page title
  3. Appearance → Menus: rename the "See Us In Action" link to "Crew at Work"
  4. Confirm **Videos** page exists (slug `videos`, Template **Videos**) and at least one clip is flagged "Feature in video gallery" → **Save**
  5. Verify `/videos/`: tile shows a first frame + duration, lightbox plays, arrow keys move between clips, Esc closes and the clip stops
  6. Verify `/see-us-in-action/`: heading reads "Crew at Work", newest ≤20 photos then newest ≤10 videos, **no year tabs**, both lightboxes work independently, "All photos →" / "All videos →" links land on `/photos/` and `/videos/`
  - No rewrite flush needed — both are page templates, not CPT archives.
- [ ] **Donate / support page** — E-transfer or PayPal link, honest note that tax receipts aren't available pre-incorporation. **Blocked on:** deciding on a dedicated e-transfer email or PayPal account separate from `info@`.
- [ ] Consider physical badge ("Watershed Steward" patch) for top contributors at year-end — award based on cleanups logged (3+), not weight.
- [ ] **Gallery thumbnail Option B** — register `glc-thumb` custom image size with `crop: ['center', 'top']`, update gallery shortcode, run "Regenerate Thumbnails". Do when gallery load time becomes a concern.
