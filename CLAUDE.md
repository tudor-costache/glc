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

## Support tooling — the `SupportScripts` repo

The Python tooling for this project lives in a **separate repo**, `SupportScripts`
(sibling directory `../SupportScripts`, i.e.
`C:\Users\Tudor\Documents\GitHub\SupportScripts`). Full docs are in that repo's
`README.md` / `CLAUDE.md`; this is the index.

| Script | Job | Where it runs |
|---|---|---|
| `tracker_to_csv.py` | Google Sheet (`Daily Log`) → `cleanups/cleanups.csv` for **Tools → Import Cleanups CSV**. Config + `credentials.json` live in `SupportScripts`. | Run from `SupportScripts`; `-o ../glc/cleanups/cleanups.csv` to land the file here. |
| `monthly_infographic.py` | Monthly impact infographic for Instagram (HTML template → headless-Chromium screenshot). Reads the **raw** tracker CSV. | Self-contained in `SupportScripts` (assets + output next to the script). |
| `prepare_wildlife_asset.py` | Background-removal + crop for `/stats/` wildlife card images and `_s.png` map-pin crops; also the plain tire/bike/cart icons. | Anywhere — takes explicit `input`/`output` paths. |
| `prepare_corridors_geojson.py` | Offline fetch of river/creek geometry (OHN + OSM) → `plugin-dev/great-lake-cleaners/assets/corridors.geojson`. | **Must run with this repo as the working directory** — output path is hard-coded, cwd-relative, no override flag. |
| `site_audit.py` | Read-only security/health audit of production; `--post` exercises the public forms. Run after every deploy. | Anywhere (`--base` sets the target). The invariants it guards are documented throughout this file. |
| `resize_uploads.py` | Batch-downscale images in a directory, in place. | Anywhere — takes a directory arg. |

`cleanup_report.py` (the old single-event card generator) was never used and was
dropped in this move.

**When the site changes what it guarantees, `site_audit.py`'s checks are the
regression tests that must be updated to match** — the script is in the other
repo but its spec is here.

---

## Outing Tracker — the data-model contract

The shared spec for the Google Sheet that `tracker_to_csv.py` and
`monthly_infographic.py` (both in `SupportScripts`) read. It lives **here**, not
in that repo: the scripts own the code, this file owns the column meanings. The
`COL_*` index constants at the top of both scripts mirror the table below — **if
the Sheet grows a column, update all three.**

**Primary source:** the Google Sheet, native format only (not an `.xlsx` parked in Drive).
**Local backup:** `Great_Lake_Cleaners_Outing_Tracker.xlsx`.
**Tab:** `Daily Log`.

### Column layout (0-based)

| Idx | Col | Field | Notes |
|---|---|---|---|
| 0 | A | Date | Store as a date value, not text; scripts normalise to `YYYY-MM-DD` |
| 1 | B | Location / Corridor | Must match exactly for same-site merging |
| 2 | C | Duration (min) | |
| 3 | D | Bags (#) | Garbage |
| 4 | E | Weight (kg) | Garbage |
| 5 | F | Notes | Imported into `post_content` |
| 6 | G | Cans (#) | Recycling |
| 7 | H | Bottles (#) | Recycling |
| 8 | I | Recyclables Weight (kg) | Cans + bottles; tracked separately, not added to debris weight |
| 9 | J | Number of people | Volunteers |
| 10 | K | Notable / Unusual Finds | |
| 11 | L | Latitude | GPS — enter once per new site; blank = no map pin |
| 12 | M | Longitude | Negative for Ontario |
| 13 | N | Instagram Post URL | Field-log link |
| 14 | O | Corridor | Matched against known corridor names for badge display |
| 15 | P | Tires (#) | Car/truck tires only — feeds `[glc_impact_highlights]`. Bicycle tires were counted here until split to Bikes 2026-08 |
| 16 | Q | Bikes (#) | Whole bicycles removed — feeds `[glc_impact_highlights]`. Column added 2026-08, after column P |

**Volunteer hours** = duration × people (70 min × 2 people = 2.33 h).

### Sheet rows → `cleanup_event` posts (`tracker_to_csv.py` merge)

Same date + same location → one event, numeric fields summed. Same date +
different location → separate events. Volunteer hours = Σ person-hours;
Instagram URL and GPS = first non-empty. On upload, **Tools → Import Cleanups
CSV** then skips any row whose date + site pair already exists as a post.

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

**Live since August 2026.** `/events/` is deployed and the first hosted
community cleanup is **World Cleanup Day** (`/events/world-cleanup-day/`,
post ID 811), with a working RSVP form. The rewrite flush was done at
rollout; a further deactivate/reactivate is only needed if the archive
404s again. After the cleanup happens, log the `cleanup_event` as usual and
set **Linked Cleanup Report** on the event so it shows "See the results →".

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
| `[glc_map]` | Leaflet map. Attrs: `height`, `post_id` (single-event mode), `limit` (markers per cluster), `cluster_radius` (km), `corridors` (river corridor lines, see below), `corridor_pins` (default `1` — the gold cumulative-impact pins that come with `corridors`; `0` = lines only, no extra marker layer), `corridor_bounds` (default `1` — whether corridor pins can widen the map's fit-to-bounds zoom; no effect when `corridor_pins="0"`, since there's nothing to include either way), `markers` (default `1` — render individual site pins; `0` for a corridor-pins-only view), `author` (restrict to one account's cleanups — the cleaner-profile map; all-events mode only, and `0` means "no filter", not "the anonymous author"), `zoom_offset` (default `1` — how many levels tighter than the guaranteed-fit zoom the multi-marker view opens at; the hero passes `2`, see Front Page). Clustering is greedy: markers sorted by impact score (kg + bags×2), each joins nearest anchor within radius. Hero uses `limit="5" cluster_radius="10" corridors="1" corridor_pins="0" zoom_offset="2"` — site pins plus river lines, no gold pins, one extra zoom level (see Front Page for why); archive uses `limit="7" cluster_radius="10" corridors="1" markers="0"` — corridor pins (lines + pins) only, no site pins. |
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
| `[glc_account]` | The whole `/account/` surface — sign-in request, welcome, and dashboard. See **Community accounts** below. |

---

### Public form security — `includes/security.php`

Five entry points accept input from anyone, with no login: `[glc_submit_form]`
(creates a pending post + uploads photos), `[glc_report_form]` (emails info@ with
attachments), `[glc_join_crew]` and `[glc_event_rsvp]` (both email info@), and
`[glc_account]` (creates a user and emails **the visitor** — see below).
`security.php` holds every guard they share — **fix things there, not in one
form**, or the five drift apart.

| Helper | Use |
|---|---|
| `glc_rate_limit_check( $bucket, $ip_max )` / `glc_rate_limit_hit( $bucket )` | Per-IP limit **and** a site-wide hourly ceiling (`glc_rate_limit_global_cap()`, filterable). Check before doing the work; `hit` only after the mail/post succeeds, so a validation failure never burns a slot |
| `glc_clean_text` / `glc_clean_textarea` | Sanitize + `wp_unslash` + **length cap** |
| `glc_clean_int` / `glc_clean_float` / `glc_clean_coord` | Range-bounded numerics; reject INF/NaN |
| `glc_validate_image_upload( $file, $max )` | Content-sniffs the bytes and reconciles the extension |
| `glc_allowed_image_mimes()` | JPEG/PNG/WebP, in WP's `ext => mime` shape — pass as `mimes` to `wp_handle_upload()` |
| `glc_normalize_file_array( $field, $limit )` | PHP's parallel `name[]/tmp_name[]` arrays → a list of single-file arrays |

Invariants worth not re-breaking:

- **`$_FILES['type']` is the client's Content-Type header — it proves nothing.**
  A bot sets `image/jpeg` on any file. Both upload paths previously trusted it,
  which let an anonymous visitor put arbitrary WordPress-allowed types (PDF, ZIP,
  MP4, Office docs) into `wp-content/uploads`, and mail arbitrary file content to
  info@. Always validate via `glc_validate_image_upload()`.
- **Pass `mimes` to `wp_handle_upload()`.** Without it, it falls back to
  `get_allowed_mime_types()` — far wider than three image types.
- **`maxlength` on an input is a UI courtesy.** Every free-text field that reaches
  post meta or an email body needs a server-side cap.
- **The per-IP rate limit alone is not enough** — a rotating-IP script looks like
  a first attempt every time. The global cap is the circuit breaker; keep both.
- **`glc_client_ip()` reads `REMOTE_ADDR` only.** `X-Forwarded-For` is attacker-
  supplied unless a proxy overwrites it. If the site moves behind Cloudflare,
  restore the real IP at the host/mu-plugin level, not by reading a header here.
- **Every `$_POST` read needs `wp_unslash()` before its sanitizer** (the
  `glc_clean_*` helpers do it). Without it an apostrophe is stored with a leading
  backslash and gains another on each admin re-save.
- **Map popups are HTML strings passed to `bindPopup()`** — run every
  interpolated value through the `esc()` helper defined at the top of the map
  script. Site names arrive from community submissions.
- **No `JSON_UNESCAPED_SLASHES` on JSON-LD** (`header.php`). Escaped slashes are
  valid JSON-LD; the flag would let a `</script>` in a post title close the block
  and run the rest as markup.

Email safety: four of the five forms send to a **hardcoded** address — there is
no user-controlled recipient there. Attacker-supplied values only reach the
subject, body, and `Reply-To`, and `sanitize_text_field` / `sanitize_email` strip
newlines, so header injection is not reachable. Keep it that way: never build a
`To:` from input, and never pass a raw `$_POST` value into `$headers`.

`[glc_account]` is the **one exception, and the only user-addressed mail on the
site**. Its recipient must always be `$user->user_email`, read back off the
account record we just looked up — never the `$_POST` value, even though they
are usually the same string. Taking it from the POST body turns the sign-in form
into an open relay for arbitrary text.

Rate-limit buckets: `sub` / `rep` / `crew` / `rsvp` as before, plus `login`
(3 per IP / 10 min, 60/h global) and `acct` (2 per IP / 10 min, 20/h global).

**Response headers are the host's, not the theme's.** Production serves
`Strict-Transport-Security`, a `Content-Security-Policy` (allowlisting
`*.basemaps.cartocdn.com` for map tiles and `secure.gravatar.com`, with
`'unsafe-inline'`/`'unsafe-eval'` on `script-src`), `X-Frame-Options`,
`X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` and
`X-XSS-Protection: 0` from the Apache config. **The theme sends none of them, on
purpose.** Re-adding them there was tried twice and failed twice:

| | attempt | result |
|---|---|---|
| theme 1.5.0 | unconditional `header()` calls | every header arrived **twice** |
| theme 1.5.1 | skip any header already in `headers_list()` | still twice |
| theme 1.5.2 | send nothing; host owns them | **one copy each — verified live** |

The 1.5.1 attempt failed because Apache uses `Header always set`, which writes to
`err_headers_out` *after* PHP has finished. `headers_list()` cannot see it, and it
does not replace PHP's copy either — so both go out. **There is no reliable way
for PHP to detect them**, which is why exactly one layer has to own the headers,
and that layer is the host (it is also the only one that can serve HSTS and a
CSP). If the site moves to a host without that config, add them to the new
vhost/`.htaccess` — not to `functions.php`.

Whoever owns them: **`Permissions-Policy` must keep `geolocation=(self)`.** The
submit-cleanup and report-issue forms both have a "Use my location" button backed
by `navigator.geolocation`. `site_audit.py` asserts this specifically.

A theme-side CSP is still off the table regardless: the theme and plugin emit
inline `<script>` throughout, so a restrictive policy needs nonces threaded
through all of them first.

**Username enumeration is closed in `functions.php` — on four channels, not
two.** The first two are below; the other two, found open later, are in the
block after them. WordPress
leaks account login slugs through two default channels, and a live check found both open
(`/wp-json/wp/v2/users` returned the full list; `/?author=1` 301'd to
`/author/<slug>/`). Usernames are half a credential-stuffing attempt and
`wp-login.php` is public, so the `rest_endpoints` filter drops the users
collection for everyone who can't `edit_posts` and `template_redirect` at
**priority 0** 404s author archives. The priority matters:
`redirect_canonical()` is itself on `template_redirect`, and it is what performs
the leaking redirect, so this has to answer first. Nothing on the site links to
an author archive.

**The REST gate is a capability, and must stay one.** It was
`is_user_logged_in()` through theme 1.5.2 — safe only by accident, because every
account on this site is staff, so "logged in" and "trusted" happened to coincide.
A public-signup role (the `glc_cleaner` account — `read` only) would have
inherited the entire user list and undone this whole fix. Tightened to a
capability in 1.5.3.
`edit_posts` rather than `list_users` because the unset happens *before* core's
own permission check: `list_users` is administrator-only, so gating on it would
strip the route from editors and break the block editor's author field, which is
the one case this exception exists for. Leaving the route registered for content
roles defers the decision to core, which still requires `list_users` for the full
listing — an editor gets the author lookup, not the roster. **Never widen this
back to "logged in."**

**⚠ Two more routes republished the same author archive URL** — found open by
`site_audit.py` on 2026-09-07, each disclosing `tudor`, and closed in **theme
1.6.2**. Neither was touched by the `rest_endpoints` filter or the
author-archive 404, and that is the trap: the archive they point at 404s, so it
*looked* handled, but the **name inside the URL** was handed out anyway — which
is the entire thing those two fixes exist to prevent. Fixing the archive was
never the same thing as fixing the disclosure.

| Route | Was | Fix (both in `functions.php`) |
|---|---|---|
| `wp-json/oembed/1.0/embed?url=<any post>` | 200, public, unauthenticated; `author_url: …/author/tudor/` + `author_name`, in `format=json` **and** `format=xml` | `oembed_response_data` unsets both fields |
| `wp-sitemap-users-1.xml` | body served (404 status); `<loc>…/author/tudor/</loc>` | `wp_sitemaps_add_provider` returns `false` for `users`, so the route stops existing rather than serving an empty document |

`author_name` goes along with `author_url` because for the staff account the
display name is `Tudor` and the login is `tudor` — leaving it is the same
disclosure one `sanitize_title()` away. Both fields are optional in the oEmbed
spec; dropping them costs a byline on an embed card elsewhere and nothing else.

**This is why the accounts work made them worth chasing.** A `cleaner_<hex>`
login stayed out of both routes only because `glc_submission` is registered
`public => false`: core's users sitemap lists authors with published posts in
`public => true` types, and oEmbed answers for any *viewable* post. Flipping
that one flag — an innocuous-looking change to get submissions into site search
— would have started publishing every cleaner's login slug through both routes
at once. The audit asserts both continuously so that can't land quietly.

**Sitemaps must answer 200, not 404 — theme 1.6.3.** Every sitemap URL on the
site was serving a valid document with an HTTP 404 status (measured 2026-09-07,
before any of that day's work):

```
wp-sitemap.xml                 404   valid <sitemapindex>
wp-sitemap-posts-page-1.xml    404   valid <urlset>, 13 URLs
wp-sitemap.xsl                 200   stylesheet route, unaffected
?foo=bar  /  ?paged=1          200   any other fall-through query
```

A crawler reads 404 as "this does not exist" and discards the document, so
`robots.txt` was advertising a `Sitemap:` URL that told Google nothing — the
site effectively had no sitemap, which also would have silently swallowed the
new cleaner-profile sitemap.

**`WP_Sitemaps::render_sitemaps()` contains no `status_header( 200 )`** — it
renders and exits on whatever status the request already carried, and
`WP::handle_404()` had stamped a 404 on it first (the sitemap query matches no
posts, and the front page here is static, so nothing else in the request said
otherwise). Both `status_header()` calls in that core file are 404s (sitemaps
disabled, empty URL list) and both still work, because they run *after* the
filter below and set the status explicitly.

The fix is a `pre_handle_404` short-circuit, **scoped to a sitemap that will
actually render** — `index`, or a provider genuinely in the registry. Bypassing
unconditionally would turn `?sitemap=anything` into a soft 404 (a 200 serving
the theme's 404 page), which is worse for a crawler than the original bug.
`site_audit.py`'s **Sitemaps** section is the regression test: it walks the
index, and any body that parses as a sitemap must come back 200. It judges by
content, not URL, so a legitimately absent or empty sitemap can still 404.

### `site_audit.py` — live checks against production

One script (in the `SupportScripts` repo — `../SupportScripts/site_audit.py`)
replaces the ad-hoc curl loops. Run it after every deploy; the exit code is 1 if
anything failed, so it can gate one. It is location-independent — the default
target is production; `--base` overrides.

```
python ../SupportScripts/site_audit.py                       # passive, read-only
python ../SupportScripts/site_audit.py --post                # + one real submission per form
python ../SupportScripts/site_audit.py --post --only report  # just one surface
python ../SupportScripts/site_audit.py --base http://localhost:8080
```

**Passive** covers header hygiene (including *duplicate* headers, which is how
the 1.5.0/1.5.1 problem was caught), HSTS and the HTTPS redirect, exposed files,
username enumeration, the REST surface, sitemap status codes,
`corridors.geojson` gzip/caching, page health, rendered-HTML encoding, the
accounts surface, and community-byline attribution (no staff name on a
`glc_submission` card or single page).

*Username enumeration* is four checks, not two: the REST users collection and
`/?author=1` as before, plus **oEmbed** (`find_post_url()` discovers a real post
to ask about, then both `format=json` and `format=xml` are scanned for an
`/author/…` URL — the JSON body's escaped slashes are unescaped first) and the
**users sitemap** (fetched directly *and* via the index, because these routes
answer with a 404 status while still serving the real document, so an index that
looks like it failed proves nothing). Every slug any of the four discloses lands
in one `leaked` set, which the closing WARN reports together.

*Accounts* covers `/account/` plus its nonce field, that it is `noindex` and not
publicly cacheable, open registration closed, `/crew/<unknown>/` 404, that
reserved handles (`admin`, `glc`, `official`, …) do not resolve, that an unknown
root slug stays a 404 rather than becoming a redirect, and that `/photos` is not
shadowed by the `/{slug}` shortcut. Two forged magic links (an unknown 16-hex
selector and a malformed one) must each end in a redirect that sets **no
`wordpress_logged_in` cookie** and **drops the token** — the handler must never
leave a live token in history or a `Referer`. Both anonymous-write surfaces are
probed by GET (nothing is registered to run, so it stays read-only):
`admin-ajax.php?action=glc_check_slug` and
`admin-post.php?action=glc_account_save` must both ignore the caller — a
redirect to `/account/` from the latter would mean the handler actually ran.

Then, for a profile slug **discovered** from the archive rather than hardcoded:
that the profile renders, that `/{slug}` 301s to the canonical URL, that it is
*not* `noindex` (the mirror of `/account/`, which must be), that it renders no
email address outside the org's own domain, and that the page leaks no
`cleaner_…` login slug and no `/author/` link. That last one is the regression
guard the whole accounts section exists for.

**Community byline never shows a staff name.** `check_submission_bylines()`
walks the **whole** `/cleanups/` archive (not just page 1 — community
submissions sit pages deep behind the org's own `cleanup_event` posts) plus
every `/cleanup-submission/{slug}/` single page it links to, and scans the
byline elements — `.glc-archive-card-submitter` on the card, `.glc-single-sub-byline`
("Submitted by …") on the single page — for the staff display name `Tudor`
(case-insensitive, the same sentinel the username-enumeration checks track as a
login slug). A hit means a `glc_submission` row has a non-`glc_cleaner`
`post_author` and either `glc_normalize_submission_author()` or the
`glc_submission_credit()` role gate has regressed. FAIL, not WARN — a wrong
byline on a community cleanup is exactly the credibility problem the accounts
model was built to avoid. INFO (not FAIL) while the archive has no published
community submission to check.

Everything account-shaped degrades to INFO while `/account/` is still a 404, so
the script stays useful before the rollout ships.

**`--post` has real side effects** — pending posts in the database and mail in
info@ — so it is off by default, every payload is tagged `GLC-AUDIT-TEST` plus a
timestamp, and a 10-minute cooldown stamp (`.site_audit_last_post`, gitignored)
stops a second run. That guard exists because **piping the script through
`head`/`sed` re-runs it from the top and fires the POSTs again** — which is
exactly how the first session produced three sets of test data. Redirect to a
file instead; `--force` overrides.

`--post --only signin` is the account-oracle test: it POSTs the same address
twice (creating, then finding) and asserts the rendered reply is byte-identical,
then confirms the fourth request inside ten minutes is throttled. It creates real
unverified accounts named `GLC-AUDIT-TEST <stamp>` addressed at `example.com`
(RFC 2606 — nothing reaches a real person); the plugin's own cron sweeps them
after 7 days.

Three more probes run **first**, and they are free — `glc_request_signin()`
answers each one before it creates an account, sends mail, or charges a
rate-limit slot, so the throttle sequence after them still starts from zero.
Keep that ordering if any of them moves:

| Probe | Asserts |
|---|---|
| Honeypot filled (`glc_url`) | the reply is byte-identical to the genuine "sent" copy from step 1 — a distinguishable answer tells a bot which field to leave alone |
| `glc_account_nonce=deadbeef` | the one signed-out write on the site is not nonce-free |
| `…@example.com\r\nBcc: …` in the address | rejected outright. This is the site's only *user-addressed* mail, so a newline surviving into the recipient or headers is the difference between a sign-in form and an open relay |

`outcome()` therefore recognises three result shapes, not two: the success div,
the `.glc-form-error-banner`, and the inline `.glc-field-error` span a
*field*-level error renders instead (the invalid-address case).

The most valuable single check is the **spoofed-upload test**: it submits a PDF
with `Content-Type: image/jpeg` and then confirms via the media REST endpoint
that the file never landed. That is the regression test for the
`glc_validate_image_upload()` hardening, and it is the one thing a passive scan
cannot distinguish from a legitimate photo.

**`\uXXXX` inside a PHP single-quoted string is not an escape.** `'Detecting\u2026'`
is a literal backslash-u, and `esc_js()` calls `stripslashes()` on it, so the
geolocation button rendered the visible text `Detectingu2026` (plugin 1.4.1 fixes
it; use the real `…` character, as `report.php` already did). `\u2713` written in
the *raw JS* outside the `<?php ?>` tag is fine — the browser resolves it. The
audit script's encoding check greps rendered output for a word running straight
into `uXXXX` to catch a recurrence.

### The VPS is shared — greatlakecleaners.ca is not alone on it

Both `greatlakecleaners.ca` and `savingsphase.ca` resolve to **167.114.129.162**
and run on the same Apache. That makes them one security surface in practice: a
foothold on either can reach the other unless the filesystem and service
boundaries actually hold. savingsphase is a Flask/waitress app on `127.0.0.1:5051`
behind a reverse-proxy vhost, running as the `tudor` user, with Postgres and a
Google OAuth client secret behind it; WordPress here runs under Apache's own user.
Neither should be able to read the other's credentials, and neither should be able
to write into the other's document root.

Verified so far: Apache answers **421 Misdirected Request** when asked for the GLC
host on the savingsphase certificate, so vhost isolation holds at the HTTP layer,
and port 5051 is not reachable from outside. The filesystem side is unverified and
has to be checked on the box — it cannot be probed over HTTP.

The analysis, the server-level checks, and the port of this script to a Flask app
live in **`plan.md` in the `ISP` repo** (`# Server-wide security audit`); confirmed
defects on that side go in its **`bug.md`**, not in the plan. Anything learned here
that applies to both sites belongs there too — savingsphase.ca is currently sending
duplicate `X-Frame-Options` / `X-Content-Type-Options`, the same bug theme
1.5.0/1.5.1 had, found by the same check and now filed in that `bug.md`.



---

## Community Accounts & Cleaner Profiles — `includes/accounts.php`

**Built at plugin 1.5.0 / theme 1.6.0; public route renamed to `/crew/` at
plugin 1.5.3 / theme 1.6.4, before first deploy.** An **optional** account for
people who submit cleanups. The anonymous path through `[glc_submit_form]` is
unchanged and stays the default — nobody is ever required to have an account.
Everything lives in one plugin file (role, magic-link auth, slug validation,
routing, `[glc_account]`, dashboard writes, cron sweep) plus two theme templates.

**"crew" is the URL only.** The public profile lives at `/crew/{slug}/` and the
sitemap provider is `crew` (`wp-sitemap-crew-1.xml`). Everything internal keeps
the "cleaner" vocabulary it was built with — the `glc_cleaner` role, the
`glc_cleaner` query var, `GLC_CLEANER_ROLE`, every `glc_*_cleaner_*` helper, the
`.glc-profile` body class. Renaming those was considered and rejected as churn
with no user-facing payoff (the role string can't change without migrating
accounts anyway). A future session tidying "cleaner → crew" in the internals is
misreading this — leave them.

Deliberate non-goals: comments, following, messaging, leaderboards, points, or
any writeable surface beyond the cleanup form that already existed. Each of
those is a moderation obligation, and the org is one person.

### Identity model — this is what everything else hangs off

| Concern | Value | Why |
|---|---|---|
| `user_login` | `cleaner_` + 12 hex, generated, **never shown** | A login slug that is never published cannot be enumerated or stuffed. Keeps the enumeration fix true by construction, not by filter |
| `user_nicename` | the same opaque value | It is what an author archive would expose. If author archives are ever re-enabled by accident, they leak nothing |
| Sign-in identifier | email address | Core already authenticates by email |
| Public handle | `glc_profile_slug` user meta | Fully decoupled from any credential; renameable without touching the account |
| Display name | `display_name` — seeded from what they typed on the submit or sign-in form, editable from the dashboard | **No real-name requirement** — a first name or a pseudonym is equally fine. Shows on the profile and on every cleanup card they are credited on |
| Password | a 64-char random one nobody ever learns | WordPress requires the column. Sign-in is by emailed link, so there is nothing to phish, reset, or stuff — and a real password would only add a stuffable credential to a site whose `wp-login.php` is public, plus a reset flow with its own tokens, for data that is a list of public cleanups. If one is ever wanted, add it *beside* the magic link, never as a replacement |

**INVARIANT: the profile slug and the login are two different strings and must
never be derived from one another.** `sanitize_title( $display_name )` for the
slug is fine; `sanitize_user( $email )` for the login is not.

**`users_can_register` is forced off** by `add_filter( 'pre_option_users_can_register', '__return_zero' )`.
Not a note in the docs, a filter, because it is the one setting whose accidental
flip silently undoes all of the above: it opens a second sign-up route at
`wp-login.php?action=register` with no honeypot, no rate limit, an emailed
password, and a login slug derived from the email. The Settings → General
checkbox still renders; it just has no effect. `site_audit.py` asserts the route
is closed.

### Magic-link sign-in

One email field, and the reply is **byte-identical** whether the address is
brand new or already has an account — the form must never become an
account-existence oracle. `site_audit.py --post --only signin` asserts exactly
that by POSTing the same address twice and diffing the rendered result.

- Selector + token: the URL carries `?glc_login=<16 hex selector>&t=<32-char token>`;
  only `hash_hmac( 'sha256', $token, wp_salt('auth') )` is stored. A database
  read never yields a usable link, and issuing a new link invalidates the old.
- **Single use, spent on sight.** The three metas are deleted *before* the token
  is compared, so a wrong guess can't be retried against the same selector.
- 15 minutes, then dead.
- On consumption: `wp_set_auth_cookie()`, then **redirect to a clean `/account/`**
  so the token never sits in history or a `Referer`. Anything the new session
  needs to say (welcome, claim count, failure reason) travels in a short
  transient, never a query arg.

**Both rate-limit buckets are checked on every request; only the relevant one is
hit.** That asymmetry is load-bearing: if `acct` were checked only when a
creation was actually needed, a throttled reply would itself reveal that the
address was new. The price is that two account creations from one IP also pause
sign-ins from that IP for ten minutes — the right trade.

**A magic link is never issued for an account that isn't a `glc_cleaner`.** Staff
sign in at `wp-login.php`. A passwordless 15-minute link for an account that can
edit the site turns read access to an inbox into full control of the site. That
branch returns the same `'sent'` and charges the same rate limit, so it is
invisible from outside — keep it that way, or it becomes an oracle for which
addresses are staff addresses.

### Role and admin lockout

`add_role( 'glc_cleaner', 'Cleaner', [ 'read' => true ] )` — `read` and nothing
else. **Deliberately no `edit_posts`:** `glc_submission` is `capability_type => 'post'`,
so granting it would drop the entire submission queue — other people's names,
emails and phone numbers — into a self-registered visitor's wp-admin, and
`edit_posts` is also the capability the REST users-collection filter is gated on,
so handing it out would undo the enumeration fix.

Registered on `init` (guarded by `get_role()`) rather than only on activation, so
a file-only update can't leave accounts whose role no longer exists. Role removal
is **not** hooked to deactivation — pulling it would strip every account's
capabilities during a routine plugin update.

Three locks: `show_admin_bar` off below `edit_posts`; an `admin_init` redirect to
`/account/`; and a `login_redirect` that never lands a cleaner in wp-admin.
**The `admin_init` redirect must keep exempting `admin-post.php` and
`admin-ajax.php`** — both fire `admin_init`, and `admin-post.php` is exactly
where the dashboard writes go. Detected via `$GLOBALS['pagenow']` with a
`SCRIPT_NAME` fallback.

### Attribution — `post_author` on `glc_submission`

The canonical link is **`post_author`**, not a `glc_user_id` meta key: it is an
indexed column `WP_Query`'s `author` arg reads directly, and (with `'author'` in
`supports`) it gives an admin dropdown for fixing a mis-attribution by hand.
That same `'author'` support is also a landmine — see *`post_author` … is `0` or
a `glc_cleaner`* below — so the two fixes that defuse it
(`glc_set_post_author()` after the insert, `glc_normalize_submission_author()`
on the write) are load-bearing, not tidy-up.

| Case | Behaviour |
|---|---|
| Signed in, credit (default) | `post_author` = the account; name/email prefilled read-only |
| Signed in, "post without credit" | `post_author` = 0, `glc_credit_anonymous` = `'1'`; `glc_submitter_name` still recorded for the org, but every public byline reads "Community member" |
| Signed out | Exactly today's behaviour, plus one quiet line offering an account |

**`wp_insert_post()` / `wp_update_post()` treat `post_author => 0` as "not
supplied" and substitute `get_current_user_id()`.** So `glc_set_post_author()`
writes the column directly via `$wpdb` — without it, a signed-in visitor who
ticked "post without credit" would be credited anyway, and orphaning a deleted
account's cleanups would silently reassign them to whoever ran the deletion.
The `[glc_submit_form]` handler calls it unconditionally right after the insert
(plugin 1.5.2) — the earlier code passed `post_author => 0` and trusted it,
which is the bug that credited a logged-in admin for a stranger's submission.

**`post_author` on a `glc_submission` is `0` or a `glc_cleaner`, nothing else —
enforced, not assumed.** `glc_normalize_submission_author()`
(`wp_insert_post_data`, plugin 1.5.2) resets any non-cleaner ID to `0` on every
write path. It exists because `'author'` in `supports` renders the core Author
dropdown on every submission and `wp_dropdown_users()` has no `0` option, so
publishing an anonymous submission from the review queue would otherwise stamp
the reviewing admin onto it. `glc_submission_credit()` also role-gates the
author at read time, so a row broken before 1.5.2 shows the typed name again on
deploy — but the row is only actually repaired when re-saved (or
`wp post update <id> --post_author=0`).

**Read-only is markup, not a guard.** The handler takes name and email off the
user record, never the POST body, so a scripted post can't file a third party's
name against an account.

**Bylines** go through `glc_submission_credit( $post_id )` → `[ name, url ]`,
which handles all four cases in order (opt-out → owned+visible → owned+hidden →
plain anonymous). "Owned" means the `post_author` is a `glc_cleaner`; a
non-cleaner ID is treated as `0` (typed name), so a stray admin author never
reaches a card. `archive-cleanup_event.php`, `single-glc_submission.php` **and
`header.php`'s meta description** all use it — the meta description matters, or
an opt-out's real name reappears in search results.

**Claiming.** On first verification, published or pending submissions whose
`glc_email` matches (lowercased) and whose `post_author` is 0 are auto-claimed.
The address was just proven, so a confirmation prompt would be pure friction. A
submission carrying `glc_credit_anonymous = '1'` is **skipped** — that opt-out
was a decision about the public page, and verifying an address later doesn't
reverse it.

### Deleting an account — two separate hazards

**Neither is optional, and only one is covered by the CPT flag.**

1. **The posts.** `'delete_with_user' => false` on `glc_submission`. Without it,
   `wp_delete_user()` with a null `$reassign` **destroys published cleanup
   records** that every public total counts. Note that leaving the key *unset* is
   not the same as `false`: core deletes a post type with `delete_with_user`
   null that supports `'author'` — which `glc_submission` now does.
2. **The photos.** `attachment` is a core post type that **is** deleted with its
   author, and a signed-in cleaner's uploads are authored by them. So
   `glc_orphan_user_submissions()` (on the `delete_user` action, which fires
   *before* core's own sweep) hands both the submissions and the attachments to
   author 0. Without the attachment half, deleting an account strips the images
   off cleanup records that themselves survive.

Result: cleanups stay published, `post_author` resets to 0, name and email are
cleared, cards read "Community member", and the cleanup keeps counting. The
dashboard says that in plain language next to the delete button, and the privacy
policy repeats it.

### Routing

Canonical: **`/crew/{slug}/`**, a real rewrite rule added with `'top'`
(`^crew/([a-z0-9][a-z0-9-]{1,28}[a-z0-9])/?$` → `index.php?glc_cleaner=$1`). The
query var stays `glc_cleaner` — see *"crew" is the URL only* above.

Because the rewrite hands WP a query var with no post query behind it, three
things have to be corrected or the route misbehaves in non-obvious ways:

- `parse_query` sets `is_home = false` and `post__in => [0]` — otherwise the main
  query falls through to the **blog home**, loading ten posts for nothing.
- `redirect_canonical` is disabled for the route — with `is_home()` true it will
  happily bounce `/crew/meg/` to the front page.
- `template_redirect` (priority 2) restores `status_header( 200 )`, because
  `WP::handle_404()` has already 404'd a query that found no posts.

**A miss, or a hidden profile seen by anyone but its owner, is a 404 — not a
redirect, and not a "this profile is private" page.** Either of those confirms
the slug exists, which is the same enumeration leak closed for usernames.

**`/meg` → 301 → `/crew/meg/` is a 404-time redirect, never a rewrite rule.**
A root-level `^([a-z0-9-]+)/?$` rule would shadow every page on the site: WP's
own page rule is the catch-all `(.?.+?)/?$` at the very bottom of the rules
array, so a rule added with `'bottom'` never fires and one added with `'top'`
swallows `/photos/`, `/stats/`, `/join-crew/` and the rest. There is no priority
that threads that needle. The hook runs at default priority — **after** the
theme's author-archive 404 at priority 0 — and only ever on a request that was
already going to 404.

**Reserved slugs** (`glc_reserved_profile_slugs()`) cover the site's own routes,
the WordPress surface, and org-impersonating names (`glc`, `greatlakecleaners`,
`official`, `staff`, `team`). Validation also rejects anything that is currently
a published page slug (a live `get_page_by_path()` check) and anything another
cleaner holds. Shape: 3–30 chars, `[a-z0-9-]`, no leading/trailing hyphen, no
`--`, not all-numeric (keeps `/crew/2026/` from reading like an archive). The
reserved list carries **both `crew` and `cleaners`** — `crew` because it is the
route base, `cleaners` kept from the old scheme so a stale link or a future
`/cleaners` page can't collide with a handle.
The live availability hint on the dashboard is `wp_ajax_glc_check_slug` —
**`wp_ajax_` only, never `wp_ajax_nopriv_`** — and is a hint: the save handler
re-validates from scratch and is the only thing that decides.

### Templates and data

| File | Holds |
|---|---|
| `glc-profile.php` (theme) | The public profile design. Found via `locate_template()` |
| `templates/profile-fallback.php` (plugin) | Deliberately plain fallback for another theme. A change made only there will never be seen on the live site |
| `page-account.php` (theme) | Template Name **Account**; renders `[glc_account]` in `page-submit-cleanup.php`'s two-column shell |

`glc_user_cleanups( $user_id, $statuses )` is scoped by `author` —
**deliberately not `glc_get_all_cleanups()`**, which pulls every cleanup on the
site with `posts_per_page => -1`. `$user_id <= 0` returns `[]`, because
`author => 0` means "no author filter" to `WP_Query` and would return everyone's.

`glc_user_impact_stats()` returns `glc_get_impact_stats()`'s shape plus
`corridor_names` for the header badges. **Do not give `glc_get_impact_stats()` an
author argument** — the footer strip and the archive both call it, and it is the
one place site-wide totals are defined.

Only `glc_submission` posts appear (`glc_profile_post_types()`, a constant so
widening it later is one edit). `cleanup_event` posts are org-run outings
authored by an admin with no participant list; crediting a crew member on one
would need a whole participants model.

Public profile: `publish` only. Owner viewing their own: `publish` + `pending`,
with pending rows marked "Awaiting review" and **excluded from the totals** — a
personal page is not the place to diverge from the public numbers.

Profile map: `[glc_map author="N" corridors="1" corridor_pins="0"]` — corridor
**lines** for context, corridor **pins** off, for the same reason the front-page
hero turns them off: a gold cumulative-impact pin on a personal page summarises
somebody else's work. Individual site pins are **exact**, as everywhere else on
the site — aggregating one person's spots onto a single map was weighed as a
privacy question and judged fine (these are public access points, already pinned
on `/cleanups/`); revisit if anyone raises it.

**No `noindex`** — profiles are public pages we want found, so `header.php` gives
them a real meta description and `document_title_parts` a real title (the
query-var route produces neither on its own). `/account/` *is* `noindex`.

**Sitemap — `GLC_Cleaner_Sitemap_Provider` (plugin 1.5.1; provider name `crew`
since 1.5.3).** Not being `noindex` was never the same as being *discoverable*:
`/crew/{slug}/` is a rewrite onto a query var, not a post type, so core generates
nothing for it, and a profile was findable only by crawling a byline link on
`/cleanups/` — in practice the handful near the top of page 1. The provider
publishes them at `wp-sitemap-crew-1.xml` (its `$name` / `$object_type` are
`crew`; the class name stays `GLC_Cleaner_Sitemap_Provider`).

**Keep the two sitemap changes straight — they point opposite ways on purpose.**
The theme drops core's `users` provider because it published
`/author/<login slug>/`, the **credential-side** identifier. This one adds
`/crew/{handle}/`, the **identity-side** one the cleaner chose. A profile
being discoverable and a login being opaque are different namespaces, not a
contradiction. Anyone later "tidying up" one of these by reverting the other has
misread it.

`glc_sitemap_profile_urls()` lists a profile only when it has **at least one
published cleanup** (a thin page is not worth submitting, and it also keeps
never-verified accounts out without testing for them), is not hidden (filtered
through `glc_profile_is_public()`, never a re-stated `meta_query` — "public"
means the meta is not `'0'`, *including* the usual case where no row exists),
and has a handle at all (`glc_profile_url()` returns `''` without one, which is
also what excludes an admin who authored a submission by hand). `sort()` before
returning, so page 2 means the same thing on two consecutive requests.

`get_max_num_pages()` returns **0** while nobody qualifies, which keeps the
provider out of the index entirely — core 404s an empty sitemap, so advertising
one would be worse than advertising none. **No rewrite flush needed:** core's
sitemap rules already match any provider name
(`^wp-sitemap-([a-z]+?)-(\d+?)\.xml$`).

`site_audit.py` asserts both halves together — that the profiles sitemap exists
and lists the profile slug it discovered from the archive, and that it contains
no `/author/` URL and no `cleaner_…` login slug.

### Meta keys

**User meta:** `glc_profile_slug`, `glc_profile_public` (`'1'`/`'0'`, default
`'1'`), `glc_email_verified`, `glc_login_selector`, `glc_login_token` (an HMAC,
never the token), `glc_login_expires`, `glc_joined_date`.

**On `glc_submission`:** owning account is `post_author` (0 = anonymous);
`glc_credit_anonymous` is `'1'` when a signed-in user chose no credit. **No new
*shared* keys**, so nothing in `glc_get_impact_stats()`,
`[glc_impact_highlights]` or the `?impact=` filter needed touching.

### Deploying

Needs a WP page: **Account**, slug `account`, template "Account", blank body. The
header icon, footer link, nav fallback and `glc_account_url()` all check that the
page exists first, so the feature degrades to invisible until it is created.
Then **deactivate and reactivate the plugin** — `/crew/…` 404s until the
rewrite flush fires.

A daily cron (`glc_sweep_unverified_accounts`) deletes cleaner accounts older
than 7 days that never consumed a link and own nothing.

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

**Every multi-marker map is centred on Guelph (`43.545, -80.248`), never on the
fitted bounds.** GLC's sites are overwhelmingly local, and one outlier (Duchesnay
Creek in North Bay, Bayfield on Lake Huron) is enough to drag a bounds centre
halfway to Georgian Bay — the map then opened with every actual cleanup crowded
into a corner. Home base in the middle with a far pin off the edge reads far
better; the map still pans and zooms to reach it. Applies to `[glc_map]`
(`shortcodes.php`) and the `/stats/` wildlife map (`page-stats.php`), which each
keep their own `GLC_HOME` constant. The single-marker branch (`length === 1`,
i.e. a single-event map) still centres on its own pin.

**Use `getBoundsZoom()`, not `fitBounds()`, to pick that zoom** — one
`setView( GLC_HOME, getBoundsZoom(...) + zoomOffset )` and nothing else:

```js
var fitZoom = map.getBoundsZoom( L.latLngBounds( pts ), false, L.point( 80, 80 ) );
map.setView( GLC_HOME, fitZoom + zoomOffset );  // zoomOffset = [glc_map]'s zoom_offset attr, default 1
```

`zoom_offset` defaults to `1` (one level tighter than the guaranteed fit — still
inside the padding). The front-page hero passes `2`: its marker spread (North Bay
to Long Point) is wide enough that `+1` still opens too far out to read as a
Guelph map. Bumping the offset only tightens the zoom; the centre stays
`GLC_HOME`, so far pins just move further off the edge.

`getBoundsZoom()` is the identical calculation `fitBounds()` runs internally, but
it only computes — it never moves the map. Two separate traps make that matter,
both of which the obvious fit-then-pan-back version walks straight into:

- **`fitBounds()` doesn't apply its zoom synchronously.** When the zoom delta is
  within `zoomAnimationThreshold` (4 by default), Leaflet defers `_animateZoom()`
  to a `requestAnimationFrame`, so `map.getZoom()` on the very next line still
  returns the *old* zoom. The long-standing `map.setZoom( map.getZoom() + 1 )`
  tighten was therefore adding 1 to a stale reading — measured: bounds zoom 11,
  `getZoom()` still 12, map settling at **13**, two levels tighter than the "+1"
  the comment claimed. It only looked right because the archive map's spread
  (Guelph → North Bay) is a 6-level jump, over the threshold and so synchronous.
- **Panning back to Guelph lands ~a pixel off.** `setView()` routes a short move
  through `panBy()`, which does `offset.round()` — the centre came out ≈390 m
  from `GLC_HOME` at zoom 6. Sub-pixel, but it means the centre isn't literally
  home base.

**Padding is the *total*, not per-side.** `fitBounds( pts, { padding: [40, 40] } )`
adds 40 to each of the four sides, so the equivalent `getBoundsZoom()` argument is
`L.point( 80, 80 )`. `[glc_map]` uses 40/side → `[80, 80]`; the wildlife map uses
50/side → `[100, 100]`. Halving these by mistake silently zooms every map in a
level.

### Front Page

**Hours display on cards:** values under 1 hour display as minutes (`30 min`); at or above display as `1.5 h`. Applied in `front-page.php` and `archive-cleanup_event.php`.

**Portrait photos:** `object-position: center top` inline on the "Get Involved" photo keeps the volunteer's face in frame.

**Hero map shows river lines, but no gold corridor pins** — `[glc_map ... corridors="1" corridor_pins="0"]`. First attempt used plain `corridors="1"` with `corridor_bounds="0"`: gold corridor pins mixed with the navy site pins read as cluttered, and `corridor_bounds="0"` alone didn't fully fix it since the hero's own site markers (`limit="5" cluster_radius="10"`) can already span a wide area on their own when a recent high-impact cleanup happens to be far away — corridor pins on top of an already-wide view just added more noise regardless of whether they could *widen* it further. Dropped corridors entirely for one iteration, then brought back just the lines (`corridor_pins="0"`) once it was clear the actual complaint was the extra marker layer, not the rivers themselves — the lines alone read as context, not clutter.

**Hero map opens one zoom level tighter** — `zoom_offset="2"` (the shared default is `1`). The hero's marker spread runs North Bay down to Long Point, so the default `fitZoom + 1` still opened far enough out to show Michigan and Ottawa, and Guelph didn't read as the subject. The centre is unchanged (`GLC_HOME`); the extra level just pushes the outliers off the edge. See the Maps section for `zoom_offset`.

### Archive Page (`/cleanups/`)

Fetches all `cleanup_event` + published `glc_submission` posts, merges, sorts by date descending, paginates at 12. Map section has `id="cleanups-map"` and `scroll-margin-top: 110px` — the River Corridors footer stat links directly to it.

**Filters:** `?impact=tires|bikes|carts` (from `[glc_impact_highlights]`) and `?corridor={slug}` (from a gold corridor pin on the map, or hand-built — see below) both filter `$all_cleanups` and can combine (AND). `$any_filter = $impact_filter || $corridor_filter` gates the "show a back link, swap the empty-state message" behavior. `$corridor_filter` is validated against `glc_corridor_table()` (same table the map/geojson matching uses) via `array_key_exists()`, and each `$c` in `$all_cleanups` carries a `'corridor'` key (`glc_corridor_slug()` of the post's raw `corridor`/`glc_corridor` meta) so the filter can compare slugs, not free text. `$pagenum_link()` preserves both query args across pagination.

**The `#cleanups-map` section is always rendered** — not gated on `$any_filter`, and not gated on `$all_cleanups` being non-empty. It used to hide whenever any filter was active ("the map shows every cleanup location, not just the filtered subset"), but that reasoning applied to the old individual-site-pin map; the corridor-pins-only map (`markers="0"`, see below) summarizes every corridor regardless of the current filter, so it stays a valid "jump to a different corridor" point no matter how deep into a filtered/paginated view someone is — e.g. `/cleanups/page/4/?corridor=speed-river` still shows it at the bottom.

**River corridor overlay:** `[glc_map ... corridors="1"]` draws river/creek lines, plus (unless `corridor_pins="0"`) one gold diamond pin per corridor showing cumulative bags/kg/items recycled and a "View cleanups on the {corridor}" link to `/cleanups/?corridor={slug}`. `#cleanups-map` uses the full thing with `markers="0"` (corridor pins carry the summary, individual site pins are hidden entirely — see `markers` below); the front-page hero uses `corridor_pins="0"` (lines only, alongside its normal site pins — see Front Page for why); not passed on single-event maps or `glc_event` maps.

- **Corridor table:** `glc_corridor_table()` in `shortcodes.php` is the single source of truth for known corridor slugs — free-text `corridor` (`cleanup_event`) / `glc_corridor` (`glc_submission`) meta is matched against it via `glc_corridor_slug()` (trim/case/apostrophe-normalized), same spirit as `glc_stats_wildlife_img()`'s text-to-bucket matching. Add a new corridor by adding one row here **and** one row in `prepare_corridors_geojson.py`'s `CORRIDORS` list — the `slug` must match exactly in both places. Grand River is deliberately absent from this table (see below).
- **Line geometry** lives in `plugin-dev/great-lake-cleaners/assets/corridors.geojson`, prepared offline by `prepare_corridors_geojson.py` (in the `SupportScripts` repo) — not a live dependency. Its output path is hard-coded and cwd-relative, so **run it from this repo's root**: `python ../SupportScripts/prepare_corridors_geojson.py speed-river eramosa-river` — passing slugs patches just those into the existing file without re-querying (and re-rate-limiting against) everything else.
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

**Adding a new wildlife image — full workflow** (`prepare_wildlife_asset.py` is in
the `SupportScripts` repo; it takes explicit paths, so run it from anywhere and
point the output at this repo's theme assets):
1. Prepare the asset: `python ../SupportScripts/prepare_wildlife_asset.py input.png theme-dev/great-lake-cleaners-theme/assets/images/name.png --pin`
   - Outputs `name.png` (600px wide card image) + `name_s.png` (200×200px map pin crop)
   - Defaults: `--tolerance 28 --width 600 --pad 20 --pin-anchor right --pin-pad 8`
   - `--pin-anchor left` for left-facing animals; `--pin-anchor center` for symmetric subjects (e.g. nest/eggs)
   - `--pin-pad` = breathing room on the nose side in output pixels (default 8 ≈ 2px at 48px display); increase if the face feels cramped
2. Re-crop an existing asset's pin only: `python ../SupportScripts/prepare_wildlife_asset.py name.png name.png --pin-only`
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
- **CSS:** reuses `.glc-photos-wrap` chrome + the entire gallery/lightbox shell. New rules are only `.glc-media-sec` / `.glc-media-sec-head` / `.glc-media-sec-title`, a `.glc-media-wrap .glc-gallery-wrap` top-margin trim (no tab strip to leave room for), and a `.glc-media-sec .glc-wave-divider` margin. The see-all links reuse `.glc-spot-view-all` from the front-page spotlight.
- **Section rule is the site wave, not a plain line.** Each `.glc-media-sec-head` row is followed by the front page's `.glc-wave-divider` SVG (markup inlined in the template, same two-path wave the front page stacks between sections) — the earlier `border-bottom` on `.glc-media-sec-head` was replaced with it so this page doesn't break the wave motif used everywhere else.
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
| Account | `account` | Account | Blank — template calls `[glc_account]`. Required before the accounts feature is reachable at all |

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

- [x] ~~**Deploy plugin 1.5.1 + theme 1.6.2**~~ — **uploaded and verified live
  2026-09-07.** Carried the 1.4.1/1.4.2 backlog (`Detectingu2026`, map centring),
  the accounts/profiles code, the cleaner-profile sitemap provider, and the
  oEmbed + users-sitemap username leaks. `python site_audit.py` went from 3
  FAIL to **0 FAIL / 0 WARN**, and each leak was re-checked by mechanism rather
  than by the audit's own pass condition: oEmbed still answers 200 with a
  working embed card, just without `author_url` / `author_name`; the users
  sitemap is gone from the index and its URL now returns the theme's real 404
  page.

- [ ] **Deploy plugin 1.5.3 + theme 1.6.4** — one combined upload carrying three
  undeployed changes:

  1. **Public route renamed `/cleaners/` → `/crew/`** (plugin 1.5.3 / theme
     1.6.4). The four open questions that gated this were resolved 2026-09-08:
     real names or a handle → whatever they typed on the form, pseudonyms fine
     (already how it worked); exact GPS on the profile map → yes (already);
     account unlocks nothing beyond the profile → correct (already);
     **`/cleaners/` → `/crew/`** — the one that needed code. Done before first
     deploy, so there is no redirect to carry. All of it is in
     `accounts.php` (rewrite rule, `glc_profile_url()`, dashboard slug prefix,
     reserved list gains `crew`, sitemap provider name → `crew` →
     `wp-sitemap-crew-1.xml`) plus two doc comments in the theme. The internal
     `glc_cleaner` query var / role / helpers are unchanged — see *"crew" is the
     URL only*. `site_audit.py` was updated in the same pass (all `/cleaners/`
     probes → `/crew/`, `sitemap-cleaners` → `sitemap-crew`) — **that lives in
     the `SupportScripts` repo and needs its own commit.**
  2. **Community-submission attribution regression** (was plugin 1.5.2, now
     folded in). A published `glc_submission` showed a staff display name
     ("Tudor") as its byline because the anonymous `[glc_submit_form]` insert
     let `wp_insert_post()` substitute the logged-in admin for
     `post_author => 0`, and the core Author dropdown re-stamped the reviewing
     admin on publish. Fixes: `glc_set_post_author()` unconditional after the
     insert; `glc_normalize_submission_author()` (`wp_insert_post_data`) resets
     any non-`glc_cleaner` author to `0` on every write path;
     `glc_submission_credit()` role-gates at read time. See *Attribution —
     `post_author` on `glc_submission`*. Affected rows were repaired 2026-09-08.
  3. **Sitemap 404-status bug** (was theme 1.6.3, now folded in). Every sitemap
     served a valid document with a 404 status, so no crawler ever read one. See
     *Sitemaps must answer 200*.

  Deploy: `powershell -File repack.ps1`, upload both zips, **deactivate and
  reactivate the plugin** (the `/crew/` rewrite rule needs the flush).
  `site_audit.py` should go from its current failures (4 Sitemaps + any
  `/cleaners/` account probes) to zero.

- [ ] **Finish the accounts rollout** — the code is live but the feature is not:
  `/account/` still 404s, so nothing is reachable yet and the profile sitemap
  has nothing to list. Steps 2–5 below are what remain.

  Deploy order, and it matters:
  1. Upload both zips.
  2. **Create the WP page** — title *Account*, slug `account`, template
     *Account*, blank body. Every entry point checks the page exists first, so
     until this step the feature is simply invisible rather than broken.
  3. Add an **Account** item to the primary menu if you want it in the nav
     (`glc_nav_fallback()` already includes it, labelled by sign-in state).
  4. **Deactivate and reactivate the plugin** — `/crew/…` 404s until the
     rewrite flush fires.
  5. `python ../SupportScripts/site_audit.py` should finish with zero failures;
     the accounts section reports INFO (not FAIL) until somebody has a public
     profile. `python ../SupportScripts/site_audit.py --post --only signin >
     audit.txt 2>&1` exercises the sign-in surface for real — it creates
     `GLC-AUDIT-TEST` accounts.

  No DB migration. Existing anonymous submissions are untouched and keep
  counting; they gain an owner only if someone claims them by verifying the
  matching email.
- [x] ~~Videos + Crew at Work rollout~~ — **done.** `/videos/` serves 10 flagged
  clips, `/see-us-in-action/` is titled "Crew at Work" in both the `<h1>` and
  the nav, and the slug stayed `see-us-in-action`. Verified live 2026-08-30.
- [ ] **Donate / support page** — E-transfer or PayPal link, honest note that tax receipts aren't available pre-incorporation. **Blocked on:** deciding on a dedicated e-transfer email or PayPal account separate from `info@`.
- [ ] Consider physical badge ("Watershed Steward" patch) for top contributors at year-end — award based on cleanups logged (3+), not weight.
- [ ] **Gallery thumbnail Option B** — register `glc-thumb` custom image size with `crop: ['center', 'top']`, update gallery shortcode, run "Regenerate Thumbnails". Do when gallery load time becomes a concern.
