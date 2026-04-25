# Great Lake Cleaners — Project Context

## Identity

**Organization:** Great Lake Cleaners  
**Tagline:** The lake starts here.  
**Mission:** Regular cleanups of Guelph's local waterways — by foot and paddle — that flow into the Great Lakes system via the Grand River and Lake Erie.  
**Location:** Guelph, Ontario, Canada  
**Stage:** Pre-incorporation, Phase 1 (personal/family effort, year one)

## Operating Corridors

- Speed River
- Eramosa River
- Hanlon Creek
- Guelph Lake area (secondary)

## Cleanup Methodology

- Shore cleanups on foot (dog walks = regular informal outings)
- Paddle cleanups on water
- Family outings — volunteers tracked per outing
- Deposit-return items (cans, bottles) collected for recycling — weight tracked separately from debris, not added to debris total
- Scrap metal (bikes, tires, etc.) folded into debris weight — no separate tracking
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
- **Local dev:** WPLocal (Windows) — used for development and testing
- **Production VPS:** OVHcloud Canada — **live at greatlakecleaners.ca** ✅
- **Stack:** Ubuntu 24, Apache 2.4, MySQL, PHP 8.3, Python 3
- **WordPress** for public site and cleanup event management

### Server Setup Notes
- UFW firewall enabled: ports 22 (OpenSSH), 80, 443 open
- fail2ban installed and active — protects SSH (5 failed attempts = 10 min ban)
- certbot + python3-certbot-apache installed; SSL auto-renews via systemd timer
- Apache modules enabled: `rewrite`, site config at `/etc/apache2/sites-available/greatlakecleaners.ca.conf`
- WordPress installed at `/var/www/html/wordpress`
- MySQL database: `wordpress`, user: `wpuser`@`localhost`
- SVG MIME type confirmed working: Apache serves `image/svg+xml` correctly — verified via `curl -I`

### Domains
- Registered at CanSpace
- Primary: greatlakecleaners.ca
- DNS: A record pointing to `167.114.129.162` (OVHcloud VPS IPv4); www/mail/ftp CNAMEs follow automatically
- SSL: Let's Encrypt (free, auto-renews via certbot)
- The `ownercheck` TXT record added during OVHcloud secondary DNS verification — can be left in place

### Contact Email
- **`info@greatlakecleaners.ca`** — live and configured ✅
- Referenced in `page-privacy-policy.php` via `$contact` variable (already updated)

### Post-Deployment Checklist (things stored in DB, not files)
When deploying to VPS, the following must be re-done in WP Admin — they do not travel with the theme/plugin zips:
- **Appearance → Customize → Site Identity** — site name, tagline, and Site Icon (favicon). Upload `glc-badge.png` (transparent background version preferred) and WordPress generates all favicon sizes automatically. **Important:** if a custom logo is set here it takes priority over the fallback `glc-badge.png` in `assets/images/`. Clear this setting to use the file-based fallback.
- **Settings → Reading** — set static front page to the "Home" page
- **Appearance → Menus** — rebuild primary and footer nav menus
- **Pages** — recreate "Home", "Submit a Cleanup", "Photos", and "Join our Crew" pages with correct slugs; set Photos template to "Photos" and Join our Crew template to "Join our Crew"
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
  great-lake-cleaners.php   — main loader, activation hook, transient-based rewrite flush,
                              Turnstile constants + glc_verify_turnstile() helper
  includes/
    post-type.php           — cleanup_event CPT registration
    acf-fields.php          — native WordPress meta box (replaces ACF)
    admin.php               — list table columns, sortable date, admin styles
    shortcodes.php          — [glc_stats], [glc_map], [glc_archive], [glc_gallery], [glc_timeline], [glc_references], [glc_impact_highlights]
    import.php              — Tools → Import Cleanups CSV
    submission.php          — glc_submission CPT + [glc_submit_form] shortcode
    report.php              — [glc_report_form] shortcode (email-only, no CPT)
    crew-signup.php         — [glc_join_crew] shortcode + AJAX handler for crew email signup
  assets/
    leaflet.css, leaflet.js — Leaflet v1.9.4, self-hosted
    chart.min.js            — Chart.js v4.4.6, self-hosted (MIT)
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
| Recyclables Weight (kg) | `recycled_weight_kg` | weight of cans + bottles — not added to debris total; stored for future surfacing |
| Tires Removed | `tires_removed` | integer count — feeds [glc_impact_highlights] |
| Hazardous Waste Removed | `hazards_removed` | paint cans, motor oil, appliances, e-waste, etc. — stored but not currently surfaced in any shortcode |
| Notable Finds | `notable_finds` | |
| Native Species Planted | `species_planted` | |
| Metres Bank Cleared | `meters_bank_cleared` | displayed as km if ≥ 1000 m |
| Wildlife Observed | `wildlife_obs` | |
| Instagram Post URL | `instagram_url` | link to field log |

**Editing:** Open post → scroll below editor → Cleanup Details → Update.  
**Titles** are display labels only — rename freely.  
**GPS:** Google Maps — phone: tap blue dot → coordinates at top. Desktop: right-click location → coordinates at top of context menu.  
**Date format:** Always `YYYY-MM-DD`. The archive sorts using `strcmp` on this format — display-format dates like `Mar 30` will be normalised at render time by `strtotime()`, but it's better to store them correctly.

### Community Submission Post Type: `glc_submission`

Public form via `[glc_submit_form]` shortcode. Submissions land as `pending`. Admin reviews in WP Admin → Submissions, publishes (counts in stats) or trashes. Email notification on each submission. Photos (up to 5) attached to post.

**CPT settings:** `publicly_queryable: true`, `exclude_from_search: true`, `query_var: true`, `rewrite slug: cleanup-submission`. Public single-post URLs resolve to `/cleanup-submission/{slug}/`.

**Stats counting:** Published submissions count fully toward all public stats — cleanup count, weight, items recycled, and volunteer hours. The goal is for community members to make a mark: their efforts and cleanups appear on the map and are reflected in every cumulative total alongside the org's own outings.

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

**`glc_get_impact_stats()`** is defined in `theme/functions.php` (not the plugin) and powers the footer stats strip on every page. It counts both `cleanup_event` and `glc_submission` posts for: cleanup count, weight_kg, items_recycled, and hours (`hours` for events, `glc_hours` for submissions). Corridors count is cleanup_event only (no equivalent field on submissions). If the footer stats and a shortcode show different totals, the most likely cause is this function missing a post type or using the wrong meta key for submissions.

Note: Phone field was removed from the public submission form. GPS coordinates are now collected via lat/lon inputs + browser geolocation button (requires HTTPS). Person-hours are calculated automatically from duration × volunteers. "Access Point" label replaced with plain "Location". "Number of People" field moved from section 3 ("What You Collected") into section 2 ("The Cleanup") where it belongs logically alongside Duration.

### Submission Form — Thank-You / Receipt State

After a successful submission, `[glc_submit_form]` shows:
1. A receipt line: *"You submitted: 3 bags, 6.0 kg, Parkwood Gardens, April 3"* — built from `$_POST` data still available after `glc_maybe_handle_submission()` returns `'success'`. Each part (bags, weight, location, date) is conditional — omitted if the field was empty.
2. The `stylized-thankyou.png` illustration.
3. The thank-you heading and body text.

CSS class: `.glc-submit-receipt` — green-tinted pill badge, defined in theme's `style.css`.

### Report an Issue (`[glc_report_form]`)

Public form via `[glc_report_form]` shortcode on page slug `report-issue`. **Email-only — no CPT, no admin review queue.** Reports go directly to `info@greatlakecleaners.ca` via `wp_mail()`.

**Two-stage flow:**
1. **Triage cards** — side-by-side. City issues routed to Guelph's ArcGIS tool (external link). Waterway issues reveal the form via JS (no page reload, smooth scroll).
2. **Form** — four sections: About You (optional), The Issue (date/waterway/description), Location (text + GPS), Photos (up to 3).

**Email delivery:** Photos attached directly to the outbound email via `wp_mail()` attachments (temp files, cleaned up after send). `Reply-To` header set to reporter's email if provided, so replies go directly to them.

**Success state** surfaces GRCA Spills Line (1-800-265-6613, 24 hr) and Environment Canada number for active hazards.

**Error handling:** validation errors re-show the form section open (not hidden) so the user doesn't need to click the triage button again. Required-field validation errors return `['field' => 'field_id', 'message' => '...']` from the handler — the form rendering uses this to set `aria-invalid="true"` and `aria-describedby="glc-err-{field_id}"` on the offending input and render an inline `.glc-field-error` span. Non-field errors (security, rate limit) return a plain string and show in the `.glc-form-error` banner at the top. Same pattern applies in `submission.php`.

**Destination email:** `GLC_REPORT_EMAIL` constant defined in `report.php` as `info@greatlakecleaners.ca`.

### Spam Protection

Both `[glc_submit_form]` and `[glc_report_form]` share the same three-layer defence. All checks run **before** `wp_mail()` is called — protecting mail server reputation (DKIM/SPF/rDNS setup on production).

**Defence chain (in order):**
1. **Nonce** (`wp_verify_nonce`) — WordPress built-in, already present
2. **Honeypot** — hidden `name="glc_url"` field, CSS-offscreen (`left: -9999px`, `opacity: 0`, `pointer-events: none`, `tabindex="-1"`). Handler silently returns `null` if non-empty — no error shown to bot.
3. **Rate limit** — WordPress transient keyed by hashed IP. Max 5 attempts per 10 minutes. Transient keys are form-specific: `glc_sub_rate_` (submission) and `glc_rep_rate_` (report). **Counter increments only just before `wp_mail()` is called** — failed validation attempts do not burn a slot. Returns user-visible error after limit hit. To reset manually: `wp transient delete --all` via WP-CLI, or `DELETE FROM wp_options WHERE option_name LIKE '_transient_glc_%';` in MySQL.
4. **Cloudflare Turnstile** — invisible widget (`data-size="invisible"`). Challenge fires silently on submit; token verified server-side via `https://challenges.cloudflare.com/turnstile/v0/siteverify`. Requires outbound HTTPS (port 443) from the server.
5. **Field validation** — required fields, date format/range checks
6. **`wp_mail()`** — only reached if all above pass

**Turnstile configuration:**
- Site key and secret key stored as constants in `great-lake-cleaners.php`: `GLC_TURNSTILE_SITE_KEY`, `GLC_TURNSTILE_SECRET_KEY`
- Keys are registered to `greatlakecleaners.ca` in the Cloudflare Turnstile dashboard — update both the dashboard domain and these constants if the domain changes
- Widget mode: **Invisible** (must match `data-size="invisible"` in HTML)
- Cloudflare JS enqueued via `wp_enqueue_scripts` only on pages containing either GLC form shortcode (checks `has_shortcode()` against post content)
- Shared verify helper: `glc_verify_turnstile( string $token ): bool` in `great-lake-cleaners.php`

**Required indicator CSS:** Both forms use `.glc-required` (defined in `style.css` as `color: var(--glc-red); flex-shrink: 0`). The `*` span must sit **inside** `.glc-label-text` to stay inline — placing it outside causes it to wrap to a new line in the column flex layout.

### Shortcodes

| Shortcode | Output |
|---|---|
| `[glc_stats]` | Cumulative totals banner |
| `[glc_map]` | Leaflet map of cleanup locations. Attributes: `height` (CSS value), `post_id` (single-event mode), `limit` (max markers per geographic cluster, 0 = no limit), `cluster_radius` (km radius for grouping nearby markers, 0 = no clustering). Hero uses `limit="5" cluster_radius="10"`, archive uses `limit="7" cluster_radius="10"`. Clustering is greedy: markers sorted by impact score (kg + bags×2), each joins the nearest cluster anchor within `cluster_radius` km or starts a new cluster — so dense areas (Guelph) are trimmed while sparse outliers (Rockwood, KW) show all their pins. |
| `[glc_archive]` | Paginated cleanup archive |
| `[glc_submit_form]` | Community submission form |
| `[glc_gallery]` | Photo gallery with year tabs + lightbox |
| `[glc_report_form]` | Waterway issue report (two-stage: triage → form → email) |
| `[glc_timeline]` | Cumulative debris (kg) + items recycled over time — dual Y-axis Chart.js line chart; includes cleanup_event + glc_submission data |
| `[glc_impact_highlights]` | Three stat cards (unique sites, tires, total cleanups) + cumulative person-hours chart — unique sites, hours, and total cleanups include glc_submission data; tires are cleanup_event only (no equivalent field on submissions) |
| `[glc_references]` | Wrapping shortcode — hides an inline reference list and replaces it with a gold-bordered trigger button. Clicking slides in a navy-headed panel from the right. Close via ✕, backdrop click, or Escape. Usage: `[glc_references]<ol>...</ol>[/glc_references]` in a Custom HTML block. Button label auto-counts `<li>` items: "Sources & References (12)". CSS/JS embedded once per page via static flag. |
| `[glc_join_crew]` | Email signup form — submits via AJAX to `crew-signup.php`. Sends notification to `info@greatlakecleaners.ca`. Rate limit: 3 attempts per IP per 10 minutes (transient key `glc_crew_{ip_hash}`). Honeypot + nonce protected. No CPT — email-only. |

---

## WordPress Theme: `great-lake-cleaners-theme`

**File:** `great-lake-cleaners-theme.zip`  
**Install:** Appearance → Themes → Upload → Activate.  
**PHP upload limit:** Default WordPress limit is too small for the theme zip. Set in `/etc/php/8.3/apache2/php.ini`: `upload_max_filesize = 64M`, `post_max_size = 64M`, `max_execution_time = 300`, then `sudo systemctl restart apache2`.

### Theme File Structure

```
great-lake-cleaners-theme/
  style.css                    — all styles; theme header
  functions.php                — enqueues, nav menus, theme support
  header.php                   — <head>, sticky nav, opens <div class="glc-main-outer"> and <main class="glc-main">
  footer.php                   — closes </main>, closes </div.glc-main-outer>, wave SVG, stats strip, <footer>
  front-page.php               — home page template
  page.php                     — standard page template
  page-photos.php              — Photos page template (Template Name: Photos) — calls [glc_gallery]
  page-submit-cleanup.php      — Submit a Cleanup page shell + sidebar
  page-report-issue.php        — Report an Issue page shell + sidebar
  page-join-crew.php           — Join our Crew page (Template Name: Join our Crew) — embeds [glc_join_crew]
  page-privacy-policy.php      — Privacy Policy (auto-generated content)
  archive-cleanup_event.php    — /cleanups/ archive
  single-cleanup_event.php     — individual cleanup event
  single-glc_submission.php    — individual community submission
  404.php
  assets/
    images/
      glc-badge.png            — shield logo (transparent bg)
      stylized-thankyou.png    — thank-you illustration (PNG — has transparency)
      stylized-paddler.jpg     — paddler illustration (JPG, 500px wide, ~85% quality)
      stylized-map-rivers-lake.jpg — map illustration (JPG, 500px wide, ~85% quality)
      cleanup_stylized.jpg     — cleanup illustration (JPG, 500px wide, ~85% quality)
      icon-bag.svg             — Twemoji wastebasket (CC-BY 4.0)
      icon-scale.svg           — Twemoji scales (CC-BY 4.0)
      icon-recycle.svg         — Twemoji recycle (CC-BY 4.0)
      icon-timer.svg           — Twemoji stopwatch (CC-BY 4.0)
      icon-wave.svg            — Twemoji wave (CC-BY 4.0)
      icon-bank.svg            — custom river bank / shoreline icon (Twemoji palette)
    js/
      nav.js                   — mobile menu toggle
```

### Visual Identity

- **Navy:** `#1a4a6b` (single value — `--glc-navy`)
- **Gold:** `#f5a623` (`--glc-gold`)
- **Green:** `--glc-green` (accent for pills, labels, stat labels)
- **Green dark:** `--glc-green-dark: #1a5e35` — ≈7:1 on green-light; used for `.glc-fp-label` and `.glc-community-badge` text (WCAG AA)
- **Green light:** `--glc-green-light` (backgrounds for cards, tips)
- **Off-white:** `--glc-off-white`
- **Border:** `--glc-border`
- **Muted:** `--glc-muted: #4d5760` (updated from `#666666` for WCAG AA contrast)
- **Body font:** Lato (`--glc-font-body`)
- **Display font:** Nunito (`--glc-font-display`)
- **Body text color:** `--glc-text`
- **Gutter color:** `#f0f0ee` — used for body background, `.glc-main-outer`, and wave footer gradient

### Page Layout Architecture

**This was hard-won through many iterations — do not change without understanding the full chain.**

The page uses a flex column layout to ensure the white content box always fills to the wave, with gray gutters visible on the sides:

```
<body>                        flex column, min-height: 100vh, background: #f0f0ee
  <header .glc-site-header>  sticky, full-width navy
  <div .glc-nav-bar>         full-width navy nav
  <div .glc-main-outer>      flex: 1, flex-direction: column, background: #f0f0ee
    <main .glc-main>         flex: 1, max-width: 1140px, margin: 0 auto, white bg
      (page content)
    </main>
  </div>
  <div .glc-wave-footer>     full-width, linear-gradient background (see below)
  <div .glc-stats-strip>     full-width navy
  <footer>                   full-width navy
</body>
```

**Why `.glc-main-outer` exists:** `<main>` is a centered `max-width: 1140px` column — it can never fill the full viewport width. Without the outer wrapper, short pages leave a gray band between the bottom of `<main>` and the wave. The outer div takes `flex: 1` on the body flex chain, and is itself a flex column so `<main>` can take `flex: 1` and stretch vertically to fill it. Gray body background shows in the side gutters around the centered white box.

**Why `min-height: 100%` does not work** as an alternative: `min-height: 100%` on a flex child only resolves when the parent has a definite CSS height — `flex: 1` alone does not count. The correct pattern is `display: flex; flex-direction: column` on the parent + `flex: 1` on the child, all the way up the chain.

**Wave footer gradient:** The wave sits outside `<main>` as a direct child of `body`. Its background must mirror the content column so the area above the wave crests looks consistent with the page layout (white centre, gray sides). Use:

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

570px = half of 1140px max-width. The stats strip and footer are intentionally full-width navy (no gutters).

**Do not** set `background: white` on `body` to solve gray-gap issues — this removes the gutters globally. **Do not** use `min-height` or negative margins to extend the content box — the flex chain is the correct mechanism.

### Header

- Sticky navy header with logo badge (135px, centered vertically)
- Brand name at 2.6rem, tagline in gold uppercase
- `::after` pseudo-element creates the wave transition below the header — **never add `overflow: hidden` to `<header>`**, it clips the wave
- Customizer logo setting must be cleared for file-based `glc-badge.png` to take effect

### Navigation Bar

- Sits below header in its own `.glc-nav-bar` div (same navy background)
- Font: 0.92rem, bold, uppercase, `padding: 10px 16px`
- Gap of 2px between items (no separator lines — tested, doesn't look good in practice)
- Gold bottom-border on active/hover item
- **Submenu** (`.sub-menu`) has **no** `border-top` — the gold top border was removed because it created double-underline artifacts where the nav item's gold bottom-border and the submenu border overlapped

### Footer Structure

1. `.glc-main-outer` closes
2. `.glc-wave-footer` — three-layer SVG wave with gradient background (see above)
3. `.glc-stats-strip` — full-width navy stats bar
4. `<footer>`:
   - `.glc-footer-inner` — nav menu (`padding: 20px 32px 16px`)
   - `.glc-footer-base` — copyright · org name · Privacy Policy · Instagram icon (tagline removed — redundant with header)

**Stats strip** — all five stats show a `+` superscript to indicate minimums: `17+ Cleanups · 188+ kg · 26+ Volunteer Hours · 388+ Items Recycled · 3+ River Corridors`. The `kg` and corridors values previously lacked `+`; this was inconsistent and has been corrected.

**Stats strip "Cleanups" label** is a hyperlink to the cleanups archive. Styled with `.glc-stat-lbl-link` — gold on hover, no underline.

**Instagram hover specificity:** Must be `.glc-footer-base a.glc-footer-insta:hover` to beat `.glc-footer-base a:hover`.

### Three-Layer Footer Wave

SVG viewBox `0 0 1200 80`. Paths:
- **Top layer:** `#5a9fc0` at 45% opacity — lightest, highest crest (y≈28), meets page content
- **Middle layer:** `#2d6a96` — mid blue, lower crest
- **Bottom layer:** `#1a4a6b` — exact footer navy, lowest crest, merges into footer

The `<footer>` has `margin-top: -2px` and the wave div has `margin-bottom: -2px` to close sub-pixel seams.

**Do not add `background` to `.glc-wave-footer` as a solid color** — it needs the gradient (see above) so the area above the wave crests is white in the centre and gray at the sides.

### Maps

Leaflet z-index bug resolved via `isolation: isolate` on all three map wrapper classes — this must be preserved. Leaflet's hardcoded internal pane z-indices (200–600) must be contained within a stacking context to prevent overlapping the sticky header.

### Button Palette

| Class | Resting state | Hover state |
|---|---|---|
| `.glc-btn-primary` | Navy fill, white text | Navy fill, gold text |
| `.glc-btn-outline` | Transparent, navy text, gold border | Gold fill, navy text |
| `.glc-submit-btn` (header) | Transparent, white text, gold border | Gold fill, navy text |
| `.glc-geo-btn` (form) | White fill, navy text, gold border | Gold fill, navy text |
| `.glc-btn-submit` (form) | Inherits `.glc-btn-primary` | Navy fill, gold text |

### Content Page Top Padding

Interior pages need top padding to clear the header wave (`::after` overhangs 38px below the header).

| Wrapper | Desktop padding-top | Mobile padding-top |
|---|---|---|
| `.glc-single-sub-wrap` (single event + submission) | 72px | 56px |
| `.glc-archive-wrap` (cleanups archive) | 72px | 56px |
| `.glc-main` (standard pages) | 0px (inner content padding handles it) | — |

### Front Page Structure

```
0. Hero (map + CTA)
1. Recent Cleanups strip — slim cards, no heading pill, no divider, social proof
2. About / Mission
3. Get Involved
4. Submit a Cleanup
```

Recent cleanups strip sits immediately after the hero with no `<hr>` separators. Cards are full-anchor `<a>` elements showing: date · site name · icon + stats (bags, kg, recycled, hours). No "See All Cleanups" button — covered by the hero CTA.

**Hours display on cards:** values under 1 hour display as minutes (e.g. `30 min`); values at or above 1 hour display as `1.5 h`. Applied in both `front-page.php` and `archive-cleanup_event.php`.

**About section heading:** "We're Making an Impact" (was: "The lake starts here" — removed duplicate of header tagline). Includes an **Our Impact** button linking to `/about/`.

**Get Involved CTA:** two-button row — **Follow on Instagram** (primary) + **Join our Crew** (outline, links to `/join-crew/`). Body copy: "Follow us on Instagram to see when and where we're heading out next, or sign up to join our cleanup crew."

### Archive Page (`/cleanups/`)

Left-aligned throughout (pill, heading, intro text, impact section). Fetches all `cleanup_event` and published `glc_submission` posts, merges, sorts by date descending, paginates at 12 per page. Bottom section: Leaflet map only.

### Single Event Pages

Both `single-cleanup_event.php` and `single-glc_submission.php` share `.glc-single-sub-wrap` and `.glc-single-event-map`. Both have `isolation: isolate` on the map wrapper. Layout: back link → header → featured image → blog body → stat tiles → finds → map.

**Volunteer count removed from single event header** — the "1 person / N people" byline was removed from `single-cleanup_event.php`. Hours in the stat tile is sufficient; volunteer count was redundant and visually noisy.

### Stat Tiles (single event pages)

Five tiles displayed as a flex row: Bags · Debris (kg) · Items Recycled · Hrs · Bank Cleared.

- **Bank Cleared** is a proper stat tile (not a pill) using `icon-bank.svg`. Auto-formats: values under 1000 m display as `500 m`; values at or above display as `2 km` (trailing zeros stripped via `rtrim`).
- The old emoji-based restoration pill row for bank cleared has been removed. The `🌱 native species planted` pill still renders below Notable Finds if that field is set.
- `.glc-sub-stat-lbl` has `text-align: center` — required so multi-word labels (ITEMS RECYCLED, BANK CLEARED) centre correctly when they wrap to two lines.

### Card Stat Tokens (front page + archive cards)

All stat tokens in `.glc-fp-slim-stats` and `.glc-fp-card-stats` use the `.glc-cs` span pattern:

```php
$ic = function( $icon, $val, $suffix = '' ) use ( $idir ) {
    return '<span class="glc-cs"><img src="' . $idir . '/' . $icon . '" alt="" width="18" height="18" aria-hidden="true">' . esc_html( $val ) . ( $suffix ? ' ' . $suffix : '' ) . '</span>';
};
```

CSS: `.glc-cs { display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }` — icon and text are locked to the same centre line. The old `vertical-align: -0.2em` hack is gone. Both stat containers use `flex-wrap: wrap` so tokens reflow on narrow cards.

### Photos Page (`/photos/`)

- **Template:** `page-photos.php` (Template Name: Photos) — must be selected in Page Attributes when creating the Photos page in WP Admin
- **Shortcode:** `[glc_gallery]` registered in `shortcodes.php`
- **Intro text:** "See how we make a difference:" — rendered as `.glc-photos-intro` paragraph in `page-photos.php` between the `<h1>` and the shortcode
- **Sources:** Images attached to published `cleanup_event` posts + images from published `glc_submission` posts where `glc_photo_repost_ok = '1'`
- **Gallery flag:** Only attachments with `_glc_gallery = '1'` meta appear in the gallery. All other attachments remain attached to their posts for documentation but are excluded from `/photos/`. This keeps the gallery curated — documentation shots (debris piles, before/after) stay on the outing post without cluttering the gallery.
- **Flagging workflow:** Media Library → click any photo → attachment modal → **Gallery** row at bottom → tick "Feature in photo gallery" → Save. Implemented via `attachment_fields_to_edit` / `attachment_fields_to_save` hooks in `admin.php`.
- **Attachment lookup:** Uses `get_posts( post_parent = $post_id, post_type = attachment, meta_query: _glc_gallery = 1 )` — only works reliably for images **uploaded while editing** the post. Images selected from the pre-existing Media Library may retain their original `post_parent` (0 or another post) and will be missed.
- **Sort order:** Within each year tab, all photos (org + community submissions) are sorted by cleanup date descending — community photos interleave by date rather than falling to the bottom.
- **Layout:** Year tabs (pill buttons, descending) → responsive grid (`auto-fill, minmax(220px, 1fr)`) → vanilla JS lightbox with keyboard nav (←/→/Esc) and backdrop-click to close
- **Thumbnail crop:** `object-position: center top` on `.glc-gallery-thumb-btn img` — anchors to the top of the image when CSS clips it to the `4/3` aspect-ratio container, preventing heads being cropped on portrait photos (Option A). **Option B (not yet implemented):** register a `glc-thumb` custom image size with `crop: ['center', 'top']` in `functions.php`, use it instead of `medium` in the gallery shortcode, then run "Regenerate Thumbnails". Produces a smaller file (exact crop dimensions vs. full medium size downloaded and clipped by CSS) — worth doing if the gallery grows large.
- **Caption overlay:** Site name shown on hover/focus via `.glc-thumb-caption`
- **Lightbox meta bar:** Shows site name · cleanup date · "View outing →" link to the source post
- **Empty state:** Renders a friendly message if no photos are flagged yet

### Submit a Cleanup Page

Two-column layout: form left, sidebar right. Sidebar has three cards: "What happens next?", "Tips for logging", "Our corridors".

Form section order:
1. **About You** — name, email
2. **The Cleanup** — date, duration, number of people, waterway, location, GPS
3. **What You Collected** — garbage (bags, weight, notes), recycling (cans, bottles)
4. **Notable Finds & Field Log** — notable finds textarea, Instagram URL
5. **Photos** — upload + consent checkbox

"Number of People" is in section 2 (not section 3) — it belongs with the outing details, not with what was collected. It has a `?` tooltip: "Used to calculate volunteer hours". "Location" (not "Access Point") — the tooltip says "e.g. Riverside Park, Waterloo Ave bridge". Privacy note under submit button links to `/privacy-policy/`.

### WordPress Pages Required

| Title | Slug | Template | Notes |
|---|---|---|---|
| Home | `home` | (default) | Blank — set as static front page |
| Photos | `photos` | Photos | Leave blank — template calls `[glc_gallery]` |
| Submit a Cleanup | `submit-cleanup` | (default) | Leave blank — template handles layout |
| Privacy Policy | `privacy-policy` | (default) | Leave blank — template handles content |
| Report an Issue | `report-issue` | (default) | Leave blank — template handles layout |
| Join our Crew | `join-crew` | Join our Crew | Leave blank — template handles layout + embeds [glc_join_crew] |

### Accessibility

- **Focus styles:** Global `:focus-visible` rule (3px solid `--glc-gold`, 2px offset) at end of `style.css`. Mouse users get `:focus:not(:focus-visible) { outline: none }`. Form inputs use gold outline on `:focus` (overrides the old `outline: none` which was a WCAG 2.4.7 failure). Tooltip uses `:focus-visible` only.
- **Reduced motion:** `@media (prefers-reduced-motion: reduce)` block at end of `style.css` sets all `transition-duration` and `animation-duration` to `0.01ms !important`.
- **Form error UX:** Required-field errors use `aria-invalid="true"` + `aria-describedby` pointing to a `.glc-field-error` inline span. Non-field errors (security/rate-limit) use a banner at the top. See error handling note in Report an Issue section above.
- **Full-card aria-label:** Front-page slim cards build a descriptive `aria-label` from site name, date, and stat values so screen readers get meaningful link text instead of just the site name.
- **Community badge contrast:** `.glc-community-badge` and `.glc-fp-label` use `--glc-green-dark` instead of `--glc-green` for WCAG AA compliance.
- **screen-reader-text utility:** `.screen-reader-text` class used on "opens in new tab" spans throughout. External links (Instagram, Field log, City report tool) all have this.

### Performance

HAR analysis (April 2026) identified and resolved the following:

- **Illustrations converted to JPG** — `stylized-paddler`, `stylized-map-rivers-lake`, `cleanup_stylized` exported from Lightroom as JPG 85% quality at 500px wide. `stylized-thankyou` kept as PNG (has transparency). Total image payload reduced from ~6.5 MB to ~700 KB (~89% reduction).
- **WordPress emoji system disabled** in `functions.php` via:
  ```php
  remove_action('wp_head', 'print_emoji_detection_script', 7);
  remove_action('wp_print_styles', 'print_emoji_styles');
  ```
  This prevents WordPress intercepting Unicode emoji and fetching SVGs from `s.w.org`. These two lines must be inside `after_setup_theme` with the priority `7` on the first call preserved.
- **Stat tile emoji replaced with local Twemoji SVGs** — the icon SVGs are downloaded from `s.w.org` (Twemoji, CC-BY 4.0) and served from `assets/images/`. Attribution required: "Emoji icons by Twemoji, licensed under CC BY 4.0" — add to footer or Privacy Policy page.
- **Icon CSS sizing** — `font-size` has no effect on `<img>` elements. Icon spans use explicit pixel dimensions: `.glc-sub-stat-icon` at `28px × 28px`, card stat icons at `18px × 18px`.

### Icon Implementation Pattern

Card stat icons use the `$ic` closure (see Card Stat Tokens above). Single-event stat tiles use direct `<img>` tags inside `.glc-sub-stat-icon` spans. **Never** mix PHP variable assignment in one `<?php ?>` block and then reference that variable inside an HTML attribute in a subsequent block. The correct pattern for inline echo:

```php
<?php
$idir = esc_url( get_template_directory_uri() ) . '/assets/images';
if ( $bags ) echo '<span class="glc-cs"><img src="' . $idir . '/icon-bag.svg" alt="" width="18" height="18" aria-hidden="true">' . esc_html( $bags ) . ' bags</span>';
?>
```

---

## Outing Tracker

**Primary source:** Google Sheet (native format only — not xlsx stored in Drive)  
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
| 5 | F | Notes | Imported into post_content |
| 6 | G | Cans (#) | Recycling |
| 7 | H | Bottles (#) | Recycling |
| 8 | I | Recyclables Weight (kg) | Weight of cans + bottles — tracked separately, not added to debris weight |
| 9 | J | Number of people | Volunteers |
| 10 | K | Notable / Unusual Finds | |
| 11 | L | Latitude | GPS — enter on first visit to a new site |
| 12 | M | Longitude | GPS — negative for Ontario |
| 13 | N | Instagram Post URL | Link to field log |
| 14 | O | Corridor | Matches known corridor names for badge display |
| 15 | P | Tires (#) | Count of tires removed — feeds [glc_impact_highlights] tire total |

**Volunteer hours** = duration × volunteers. 70 min × 2 people = 2.33h.  
**GPS:** enter once per new location. Blank = no map pin.  
**Date format:** Script normalises to `YYYY-MM-DD`. Store as date values in Google Sheets, not text.

---

## Python Script: `tracker_to_csv.py`

### Data Sources
1. **Google Sheets** — if `config.toml` present with `spreadsheet_id`
2. **Local xlsx** — if `--no-sheets` or `--xlsx` passed, or no config

### Config Files

**`config.toml`:**
```toml
spreadsheet_id   = "your-sheet-id-from-url"
credentials_file = "credentials.json"
```

**`credentials.json`** — service account key. Add to `.gitignore`. Share Sheet with `client_email` (Viewer).

### Usage

```bash
python tracker_to_csv.py                    # Google Sheets (default)
python tracker_to_csv.py --no-sheets        # local xlsx fallback
python tracker_to_csv.py --xlsx my.xlsx     # specific local file
python tracker_to_csv.py -o out.csv         # custom output path
```

### Sync Workflow

1. Log outings in Google Sheet
2. `python tracker_to_csv.py` → writes `cleanups/cleanups.csv`
3. WP Admin → Tools → Import Cleanups CSV → upload
4. Duplicate date+site pairs skipped automatically

### Merge Behaviour

Same date + same location → one event, totals summed. Same date + different location → separate events. Hours = sum of person-hours. Instagram URL = first non-empty. GPS = first non-empty lat/lon.

---

## Python Tool: `remove_background.py`

Removes solid or textured backgrounds from badge/logo images, producing a transparent PNG.

```bash
python remove_background.py input.png output.png [tolerance]
```

Samples background colour from four corners (median, robust to texture). Flood-fills inward from edges, making pixels within `tolerance` of the sampled colour transparent. Interior pixels untouched.

**Tolerance:** `15–20` clean white · `25–30` textured/linen (default: 28) · `30–35` heavy noise

**Requires:** Python 3, Pillow, NumPy

---

## Current File Inventory

| File | Status |
|---|---|
| `great-lake-cleaners-plugin.zip` | ✅ Installed and live on production |
| `great-lake-cleaners-theme.zip` | ✅ Installed and live on production |
| `tracker_to_csv.py` | ✅ Working — pulls from Google Sheets |
| `config.toml` | ✅ Configured |
| `credentials.json` | ✅ In place (never commit to version control) |
| `Great_Lake_Cleaners_Outing_Tracker.xlsx` | ✅ Local backup of Google Sheet |

---

## Next Steps

- [ ] **WP Admin:** Create "Join our Crew" page (slug: `join-crew`, template: Join our Crew)
- [ ] Add "Report an Issue" and "Join our Crew" to primary nav menu
- [ ] Add Twemoji attribution to footer or Privacy Policy: *"Emoji icons by [Twemoji](https://twemoji.twitter.com/), licensed under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)"*
- [ ] Build donate/e-transfer page
- [ ] Get a digital fish scale (~$15–20) for accurate weight logging
- [ ] Surface `recycled_weight_kg` publicly once sufficient data exists — best framing is item count + weight together with microplastic context
- [ ] Connect with OPIRG Speed River Project coordinator
- [ ] Consider physical badge ("Watershed Steward" patch) for top contributors at year-end — award based on cleanups logged (3+), not weight or volume
- [ ] **Gallery thumbnail Option B** — register `glc-thumb` custom image size with `crop: ['center', 'top']` in `functions.php`, update gallery shortcode to use it instead of `medium`, run "Regenerate Thumbnails". Reduces payload for portrait photos (server-side crop vs. CSS clip). Do when gallery is large enough that load time matters.