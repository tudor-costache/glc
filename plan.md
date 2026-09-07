# Great Lake Cleaners — Plans for Unreleased Work

Design documents for work that has been **thought through but not built**. Nothing
here is deployed; nothing here is in `CLAUDE.md` yet.

**The split:** `CLAUDE.md` documents the site *as it is* — how it works, and which
decisions must not be re-broken. This file documents what we intend to build, and
why the approach is the one chosen. When a plan ships, the parts a future session
needs in order to not break it move into `CLAUDE.md`, and the plan is struck through
here with a one-line "done" note (same pattern as the Next Steps checklist).

> Not to be confused with `plan.md` in the **ISP** repo, which holds the server-wide
> security audit for the shared VPS. This one is site work only.

**Contents**

1. ~~[Community accounts & cleaner profile pages](#1-community-accounts--cleaner-profile-pages)~~ — **built**, plugin 1.5.0 / theme 1.6.0

---

# 1. ~~Community accounts & cleaner profile pages~~ — BUILT

**Status: built, not yet deployed.** Plugin `1.5.0`, theme `1.6.0`, both
repacked. What a future session needs in order not to break it has moved into
`CLAUDE.md` (*Community Accounts & Cleaner Profiles*); the rest of this section
is kept as the design record — the reasoning behind the choices, and the four
open questions in §1.15 and how they were resolved.

**Deploy checklist** lives in `CLAUDE.md` → Next Steps. The one step that is easy
to miss: **create the WP page** (title *Account*, slug `account`, template
*Account*) — every entry point checks for it, so until then the feature is
invisible rather than broken — then deactivate/reactivate the plugin to flush the
`/cleaners/` rewrite.

**Where the build differs from the plan below**, and why:

| Plan said | Built | Why |
|---|---|---|
| Link is `?glc_login={user_hash}&t={token}` | `?glc_login={16-hex selector}&t={token}` | A per-issue random selector rather than a stable per-user hash: nothing in the URL identifies the account across links, and the lookup is the same single meta query. The three login metas are also deleted *before* the token is compared, so a wrong guess can't be retried against the same selector |
| `acct` bucket checked only when creating | Both buckets checked on **every** request; only the relevant one is hit | Checking `acct` lazily makes the throttle message itself the account-existence oracle the identical-copy rule exists to prevent. Cost: two creations from one IP also pause sign-ins from that IP for ten minutes |
| (not covered) | No magic link is ever issued for a non-`glc_cleaner` account | A passwordless 15-minute link for a staff account turns inbox access into site takeover. Same `'sent'` reply and same rate-limit charge, so it is not an oracle for staff addresses |
| (not covered) | `pre_option_users_can_register` forced to `0` | §1.6's "hard requirement" made structural instead of documented — it is the one setting whose accidental flip silently undoes the whole identity model |
| `'delete_with_user' => false` on the CPT | That, **plus** reassigning the user's `attachment` posts to author 0 | `attachment` is a core post type that *is* deleted with its author, and a signed-in cleaner's uploads are authored by them. Without the second half, deleting an account strips the photos off cleanup records that themselves survive |
| (not covered) | `glc_set_post_author()` writes `post_author` via `$wpdb` | `wp_insert_post()`/`wp_update_post()` treat `post_author => 0` as "not supplied" and substitute the current user — so "post without credit" would have credited anyway |
| (not covered) | `parse_query` + `redirect_canonical` short-circuit on the route | A rewrite to a bare custom query var otherwise falls through to the blog home, and `redirect_canonical()` bounces `/cleaners/meg/` at the front page |
| Byline change in two templates | Three — `header.php` too | The meta description built its own byline from `glc_submitter_name`, so an opt-out's real name would have reappeared in search results |

**§1.15 open questions, as resolved:** (1) display name defaults to what they
typed, freely editable, public by default with a one-click hide — as
recommended; (2) exact GPS, consistent with the rest of the site; (3) an account
unlocks nothing beyond the profile in v1; (4) `/cleaners/` kept.

---

## Original design record

## 1.1 What we're building

An **optional** account for people who submit cleanups. Nobody is ever required to
have one — the anonymous path through `[glc_submit_form]` keeps working exactly as it
does today, unchanged. What an account adds:

- A public profile at **`/cleaners/meg/`** — Meg's cleanups, her cumulative totals,
  and a map of where they happened. Reachable as **`/meg`** too (301 → the canonical
  URL; see §1.4).
- Submissions made while signed in are automatically attributed to that profile.
- Past submissions made anonymously under the same email address can be **claimed**
  once that email is verified.
- A small private dashboard: display name, profile slug, visibility, and the
  submissions still awaiting review.

What it deliberately does **not** add: comments, following, messaging, leaderboards,
points, or any writeable surface beyond the cleanup form that already exists. Every
one of those is a moderation obligation, and the org is one person.

## 1.2 Why this is more delicate than it looks

Three things in the current codebase are load-bearing and interact badly with
"WordPress users are now a public thing":

**(a) Username enumeration is deliberately closed** (`functions.php`). The REST users
collection is dropped and `/author/…` archives are 404'd at `template_redirect`
priority 0. A public profile page must not quietly re-open either.

**(b) ~~The REST filter trusts `is_user_logged_in()`.~~ — DONE, theme 1.5.3.** The
filter used to hand the users collection to anyone merely logged in, which was a safe
proxy for "an editor using the block editor" only because every account on the site is
staff. A `glc_cleaner` holds `read` and nothing else, and would have inherited the full
user list — the exact leak the filter exists to prevent. Now gated on a capability:

```php
    if ( current_user_can( 'edit_posts' ) ) return $endpoints;
```

**Why `edit_posts` and not the `list_users` this plan originally called for:**
`list_users` is administrator-only, so gating on it would unset the route for editors
too — and unsetting happens *before* core's own permission check, so it would have
re-broken the block-editor author field that the exception exists for. Leaving the
route registered for content roles hands the decision back to core, which still
requires `list_users` for the full listing. Editors get the author lookup, not the
roster; subscribers and any future public account never see the route at all. Strictly
tighter than before, with the documented editor case intact.

**(c) `wp-login.php` is public.** Adding accounts adds credentials worth stuffing. The
mitigations below (opaque `user_login`, email-based sign-in, magic links, registration
only through our own guarded form) exist so the new accounts never hand an attacker a
guessable username.

## 1.3 Identity model — the decision that drives everything else

| Concern | Choice | Why |
|---|---|---|
| `user_login` | Opaque: `cleaner_` + 12 hex chars, generated, never shown | A login slug that is never published cannot be enumerated or stuffed. Keeps (a) true by construction rather than by filter |
| `user_nicename` | Set to the same opaque value | `user_nicename` is what an author archive would expose. If author archives are ever re-enabled by accident, they leak nothing |
| Sign-in identifier | **Email address** | WP core already authenticates by email (`wp_authenticate_email_password`), so this is free |
| Public handle | `glc_profile_slug` user meta | Fully decoupled from any credential. Renameable without touching the account |
| Display name | `display_name` | Shown on the profile and on cleanup cards |
| Password | **None by default — magic link** (§1.6) | Nothing to leak, nothing to reset, nothing to stuff |

**Invariant:** the profile slug and the login are two different strings and must never
be derived from one another. `sanitize_title( $display_name )` for the slug is fine;
`sanitize_user( $email )` for the login is not.

## 1.4 URL shape

Canonical: **`/cleaners/{slug}/`**, via a real rewrite rule.

```php
add_rewrite_rule( '^cleaners/([a-z0-9][a-z0-9-]{1,28}[a-z0-9])/?$',
                  'index.php?glc_cleaner=$matches[1]', 'top' );
add_filter( 'query_vars', fn( $v ) => array_merge( $v, [ 'glc_cleaner' ] ) );
```

**Why not a root-level `^([a-z0-9-]+)/?$` rule for `/meg`:** it would shadow every
WordPress page on the site. WP's own page rule is the catch-all
`(.?.+?)/?$ → pagename=$matches[1]` sitting at the very bottom of the rules array, so
a rule added with `'bottom'` never fires and a rule added with `'top'` swallows
`/photos/`, `/stats/`, `/join-crew/` and the rest. There is no priority that threads
that needle.

**How `/meg` works anyway — the 404 fallback.** An unknown single segment already
resolves to a 404 by the time `template_redirect` runs. Claim it only then:

```php
add_action( 'template_redirect', function () {          // default priority 10 —
    if ( ! is_404() ) return;                           // AFTER the author-archive
    global $wp;                                         // 404 hook at priority 0
    $seg = trim( (string) $wp->request, '/' );
    if ( $seg === '' || strpos( $seg, '/' ) !== false ) return;
    $user = glc_user_by_profile_slug( $seg );
    if ( ! $user || ! glc_profile_is_public( $user->ID ) ) return;
    wp_safe_redirect( glc_profile_url( $user ), 301 );
    exit;
} );
```

Nothing existing can break: the hook only ever runs on a request that was already
going to 404, and it only ever redirects to a URL we generated.

**Reserved slugs** — validated on save, case-insensitively:

```
home cleanups events cleaners cleanup-submission photos videos see-us-in-action
stats submit-cleanup report-issue join-crew privacy-policy about blog feed author
admin login logout register account dashboard wp-admin wp-login wp-content
wp-includes wp-json cleanup-best-practices glc greatlakecleaners official staff team
```

Plus anything that is currently a published page slug (a `get_page_by_path()` check at
validation time), and anything already taken by another cleaner.

Slug rules: 3–30 chars, `[a-z0-9-]`, no leading/trailing hyphen, no `--`, not
all-numeric (keeps `/cleaners/2026/` from reading like an archive).

## 1.5 Files

**New — plugin**

| File | Holds |
|---|---|
| `includes/accounts.php` | Role, user creation, magic-link auth, slug validation, profile resolution, rewrite + `template_include`, `[glc_account]`, dashboard handling |

Loaded from `great-lake-cleaners.php` after `events.php`. It depends on
`security.php` helpers, which already load first.

**New — theme**

| File | Holds |
|---|---|
| `glc-profile.php` | Public profile template. Located via `locate_template()`; the plugin ships a minimal inline fallback so it does not hard-depend on the theme (same spirit as every other CPT template living in the theme) |
| `page-account.php` | "Your Account" dashboard shell — Template Name `Account`, renders `[glc_account]`, mirrors `page-submit-cleanup.php`'s two-column shell |

**Modified**

| File | Change |
|---|---|
| `functions.php` (theme) | ~~Tighten the `rest_endpoints` filter~~ (done, 1.5.3); bump `GLC_THEME_VERSION` to `1.6.0`; add `glc-profile` / `glc-account` body classes; add profile + account links to `glc_nav_fallback()` |
| `style.css` | Bump `Version:` to `1.6.0` (together with the constant — browsers cache by that query string); add the `.glc-profile-*` / `.glc-acct-*` block |
| `header.php` | Sign in / Your account link inside `.glc-header-social`, not `.glc-header-actions` directly (that wrapper is `flex-direction: column`) — and note it is `display:none` below 768px, so mobile relies on the footer link |
| `footer.php` | Account link in `.glc-footer-base`, after Privacy Policy |
| `includes/submission.php` | `'author'` in `supports`; **`'delete_with_user' => false`**; set `post_author` when signed in; prefill the form for a signed-in user; "credit me / post anonymously" checkbox |
| `includes/security.php` | Two new rate-limit buckets, `acct` and `login` |
| `includes/shortcodes.php` | `[glc_map]` gains an `author` attribute (§1.9) |
| `archive-cleanup_event.php` | Card byline links to the profile when the post has one |
| `single-glc_submission.php` | Same for the "Submitted by …" byline |
| `page-privacy-policy.php` | New "Accounts" section + bumped "Last updated" |
| `site_audit.py` | New checks (§1.13) |
| `great-lake-cleaners.php` | `require_once` the new include; bump `GLC_VERSION` to `1.5.0` |

## 1.6 Authentication — magic link

**Recommended.** No password is ever set, stored, or reset.

1. Visitor enters an email on `/account/`.
2. Handler (nonce, honeypot, rate-limited) generates
   `$token = wp_generate_password( 32, false )`, stores
   `hash_hmac( 'sha256', $token, wp_salt( 'auth' ) )` plus an expiry in user meta
   (`glc_login_token`, `glc_login_expires`), and emails a link:
   `/account/?glc_login={user_hash}&t={token}`.
3. The link is **single-use** (meta deleted on consumption) and expires in
   **15 minutes**.
4. On consumption: `hash_equals()` against the stored hash, check expiry, then
   `wp_set_auth_cookie( $user_id, false )` and redirect to `/account/` clean of query
   args so the token never sits in history or a `Referer`.

**Registration is the same flow**, with the account created first. There is no
separate "sign up" versus "sign in" button — one email field, and we either create the
account or email an existing one. The response is **identical either way** ("Check
your email for a sign-in link") so the form is not an account-existence oracle.

**Hard requirement:** leave WordPress's own `users_can_register` **off**, and never
turn it on. Accounts are created only by `wp_insert_user()` inside our handler. Any
other setting gives the site a second, unguarded registration surface at
`wp-login.php?action=register` with none of the guards below.

**Guards** (all from `security.php`, per the "fix it there, not in one form" rule):

| Guard | Value |
|---|---|
| Nonce | `glc_account` |
| Honeypot | `glc_url` — same field name as the other four forms |
| Rate limit — request a link | bucket `login`, 3 per IP / 10 min, global cap 60/h |
| Rate limit — create account | bucket `acct`, 2 per IP / 10 min, global cap 20/h |
| `glc_rate_limit_hit()` | Only after the mail actually sends |
| Email | `sanitize_email( glc_clean_text( …, 200 ) )` then `is_email()` |
| Display name | `glc_clean_text( …, 60 )` |
| Recipient | Always `$user->user_email` — never a `$_POST` value |

That last row is the one that differs from the existing four forms: they all mail a
hardcoded `info@` address. This one mails the visitor, which makes it the site's first
user-addressed mail. The address must come from the database record we just looked up,
never from the POST body, or the form becomes an open relay for arbitrary text.

**Alternative considered — password accounts.** Cheaper to write (WP does it all), but
it adds a credential worth stealing to a site whose login page is public, drags in a
reset flow with its own email tokens anyway, and hands the org password-support
questions. The data behind an account is a list of public cleanups; that does not
justify holding anyone's password. If a password path is ever wanted, add it as a
second convenience, keep the magic link, and never make `wp-login.php` the documented
entry point.

## 1.7 Role and admin lockout

```php
add_role( 'glc_cleaner', 'Cleaner', [ 'read' => true ] );
```

`read` and nothing else. Deliberately **no `edit_posts`** — `glc_submission` uses
`capability_type => 'post'`, so granting it would put the whole submission queue in
their wp-admin.

Three more locks, all in `accounts.php`:

```php
add_filter( 'show_admin_bar', fn( $s ) => current_user_can( 'edit_posts' ) ? $s : false );

add_action( 'admin_init', function () {                    // no wp-admin at all
    if ( wp_doing_ajax() || current_user_can( 'edit_posts' ) ) return;
    wp_safe_redirect( home_url( '/account/' ) );
    exit;
} );

add_filter( 'login_redirect', … );                          // never land in wp-admin
```

Role creation runs on activation (`glc_activate`). Role removal is **not** done on
deactivation — pulling it would orphan every account's capabilities during a routine
plugin update.

## 1.8 Linking submissions to accounts

**Canonical link: `post_author` on `glc_submission`.** Not a `glc_user_id` meta key —
`post_author` is an indexed column, `WP_Query`'s `author` argument uses it directly,
and the admin author dropdown (once `'author'` is in `supports`) gives a way to fix a
mis-attribution by hand. A parallel meta key would be a second source of truth to keep
in sync, and this codebase has already paid that tax with the `glc_`-prefixed dual
keys.

Three cases:

| Case | Behaviour |
|---|---|
| Signed in, "credit me" ticked (default) | `post_author = $user_id`; name/email fields prefilled and read-only |
| Signed in, "post anonymously" ticked | `post_author = 0`; `glc_submitter_name` still recorded for the org's records, but the card byline renders "Community member" |
| Not signed in | Exactly today's behaviour. No prompt beyond one quiet line offering an account |

**Claiming past submissions.** The moment an email is first verified, look for
published or pending `glc_submission` posts whose `glc_email` matches (lowercased,
trimmed) and whose `post_author` is 0. The email was just proven, so auto-claim is
safe and is the whole point of the feature — a confirmation prompt here would be pure
friction. Set `post_author`, email the org a one-line notice, and show the user
"3 earlier cleanups added to your profile."

**Account deletion.** `'delete_with_user' => false` on the CPT is not optional —
without it, `wp_delete_user()` with a null `$reassign` **destroys the person's
published cleanup records**, which are public data every site total depends on. On
deletion: posts stay, `post_author` resets to 0, `glc_submitter_name` is cleared, the
cards read "Community member", and the cleanup keeps counting. Say that in plain
language next to the delete button.

## 1.9 The profile page

**Route → template:**

```php
add_filter( 'template_include', function ( $template ) {
    if ( ! get_query_var( 'glc_cleaner' ) ) return $template;
    $found = locate_template( 'glc-profile.php' );
    return $found ?: GLC_PLUGIN_DIR . 'templates/profile-fallback.php';
} );
```

The template resolves the user via `glc_user_by_profile_slug()`. A miss — or a hidden
profile viewed by anyone but its owner — is a **404. Not a redirect, and not a "this
profile is private" page**: either of those confirms the slug exists, which is the
same enumeration leak we closed for usernames.

**Query — scoped, not global.** `glc_get_all_cleanups()` pulls every cleanup on the
site with `posts_per_page => -1`; a profile has no business loading all of it:

```php
function glc_user_cleanups( $user_id, $statuses = [ 'publish' ] ) {
    return get_posts( [
        'post_type'      => 'glc_submission',
        'post_status'    => $statuses,
        'author'         => (int) $user_id,
        'posts_per_page' => -1,
    ] );
}
```

Sorted by `glc_cleanup_field( $p, 'cleanup_date' )` descending — the same `strcmp` on
`YYYY-MM-DD` used everywhere else. Public profile: `publish` only. Owner viewing their
own: `publish` + `pending`, with pending rows marked "Awaiting review" and **excluded
from the totals**. The site's rule is that pending submissions count for nothing, and
a personal page is not the place to start diverging from the public numbers.

Only `glc_submission` posts appear. `cleanup_event` posts are org-run outings authored
by an admin with no participant list; crediting a crew member on one would need a
whole participants model. If it is ever wanted, the cheap version is an admin
reassigning that post's author — which is why `glc_user_cleanups()` takes its post
type as a constant it can later widen, not as a hardcoded string sprinkled through the
template.

**Totals** — `glc_user_impact_stats( $user_id )` returning the same shape as
`glc_get_impact_stats()` (`cleanups`, `weight_kg`, `hours`, `recycled`, `corridors`)
so the strip markup is shared. Do **not** change `glc_get_impact_stats()`'s signature
to take an author: the footer strip and the archive both call it, and it is the one
place site-wide totals are defined.

**Layout** (reuses existing components throughout — almost no new CSS):

```
.glc-fp-wrapper > .glc-profile-wrap
  header .glc-profile-header      display name, "Cleaner since <month year>",
                                  corridor badges (.glc-corridor-badge)
  .glc-profile-stats              4 stat cards — the .glc-ih-card icon-left shape from
                                  [glc_impact_highlights], same PNGs
  .glc-profile-map                [glc_map author="123" height="360px"
                                            corridors="1" corridor_pins="0"]
  .glc-archive-grid               their cleanups — the archive's existing card markup
  .glc-wave-divider               before the footer, per the site's wave motif
```

**`[glc_map author="N"]` — the one shortcode change.** In all-events mode, filter the
event list by `post_author` before the dedup loop. Single-event mode (`post_id`) is
untouched. Corridor **lines** stay on for context; corridor **pins** go off, for the
same reason the front-page hero turns them off — a gold cumulative-impact pin on a
personal page is summarising someone else's work. `markers` stays `1`, and no
clustering: one person's sites are few.

Empty state: a profile with zero published cleanups renders the header and a single
line ("No cleanups published yet") — no map, no stats strip.

**No `noindex`** — these are public pages we want found. But set a proper document
title from the template; the query-var route produces none on its own.

## 1.10 Dashboard (`/account/`)

One page, `[glc_account]`, three states:

1. **Signed out** — one email field + "Email me a sign-in link", plus a display-name
   field shown only on first use. Identical success copy in every case.
2. **Link consumed** — welcome line, claim result if any, then state 3.
3. **Signed in** — display name; profile slug (live availability hint, always
   re-validated server side); visibility toggle (public / hidden); pending
   submissions; a "Submit a cleanup" button; sign out; delete account (typed
   confirmation plus the plain-language note that cleanups stay published and
   un-credited).

All writes go through one `admin-post` handler with its own nonce. Nothing
account-related is exposed to `wp_ajax_nopriv_`.

## 1.11 Meta keys

**User meta**

| Key | Notes |
|---|---|
| `glc_profile_slug` | Public handle. Uniqueness enforced by a `get_users()` meta lookup inside the save handler |
| `glc_profile_public` | `'1'` / `'0'`. Default `'1'` |
| `glc_email_verified` | `'1'` once a magic link is consumed |
| `glc_login_token` | HMAC of the pending token — never the token itself |
| `glc_login_expires` | Unix timestamp |
| `glc_joined_date` | `YYYY-MM-DD`, for "Cleaner since" |

**On `glc_submission`**

| Field | Where |
|---|---|
| Owning account | `post_author` (0 = anonymous) |
| `glc_credit_anonymous` | `'1'` when a signed-in user chose to post without credit |

No new *shared* keys, so nothing in `glc_get_impact_stats()`,
`[glc_impact_highlights]`, or the `?impact=` filter needs touching.

## 1.12 Build order

Each phase is independently deployable and leaves the site working.

**Phase 1 — plumbing, invisible to visitors**
- ~~Tighten the `rest_endpoints` filter~~ — **done, theme 1.5.3**, shipped ahead of the
  rest of this plan because it is a live hole the day accounts exist. Gated on
  `edit_posts`; see §1.2(b) for why not `list_users`
- `glc_cleaner` role, admin-bar suppression, wp-admin redirect
- `'author'` in `glc_submission` supports; **`'delete_with_user' => false`**
- New `acct` / `login` rate-limit buckets
- ✔ Check: an existing admin session is unaffected; `/wp-json/wp/v2/users` still
  closed logged-out; the submission form behaves identically

**Phase 2 — accounts exist**
- `accounts.php`: user creation, magic-link issue/consume, verification,
  claim-on-verify
- `/account/` page + `page-account.php` + `[glc_account]` states 1 and 2
- ✔ Check: register → receive link → consume → signed in; a second consumption of the
  same link fails; an expired link fails; identical copy for a new and an existing
  email

**Phase 3 — profiles**
- Rewrite rule, query var, `template_include`, slug validation + reserved list
- `glc-profile.php`, `glc_user_cleanups()`, `glc_user_impact_stats()`
- `[glc_map author="N"]`
- Root-level `/meg` 301 fallback
- ✔ Check: `/cleaners/meg/` renders; `/meg` 301s to it; `/cleaners/nobody/` 404s; a
  hidden profile 404s to everyone but its owner; `/photos/` and every other page still
  resolve

**Phase 4 — attribution**
- Prefilled form for signed-in users, credit/anonymous choice, `post_author` on insert
- Profile links from archive cards and the `single-glc_submission.php` byline
- Header + footer links
- ✔ Check: a signed-in submission lands on the profile once published; an anonymous
  one does not

**Phase 5 — dashboard, policy, audit**
- `[glc_account]` state 3 in full, including deletion
- Privacy policy section + "Last updated" bump
- `site_audit.py` checks (§1.13)
- ✔ Check: full audit run, zero failures

**Deploy, every phase:** `powershell -File repack.ps1`, upload both zips, then
**deactivate and reactivate the plugin** — Phase 3 adds a rewrite rule and
`/cleaners/…` 404s until the flush fires.

## 1.13 `site_audit.py` additions

Passive:

- `/cleaners/<known-slug>/` → 200, display name present in the body
- `/cleaners/definitely-not-a-user/` → 404
- `/<known-slug>` → 301 whose `Location` is the canonical `/cleaners/…` URL
- **Regression guard on the enumeration fixes**, which is the point of the whole
  section: `/wp-json/wp/v2/users` still closed, `?author=1` still 404, and — new — the
  profile page's HTML contains no `user_login`-shaped value and no `/author/` link
- `/account/` → 200 and its sign-in form carries a nonce field
- `wp-login.php?action=register` → **not** 200 (proves `users_can_register` is off)

`--post` (side-effecting, tagged `GLC-AUDIT-TEST` like the rest):

- Request a sign-in link for a throwaway address; assert the response copy is
  byte-identical to the copy for a known-existing address (no account oracle)
- Fire the request four times; assert the fourth is rate-limited

## 1.14 Risks

| Risk | Mitigation |
|---|---|
| ~~Public accounts inherit the REST users collection~~ | **Closed in theme 1.5.3**, ahead of the rest of this plan — §1.2(b) |
| **`wp_delete_user()` deletes published cleanups** | `'delete_with_user' => false` on the CPT, Phase 1 |
| Root-level `/meg` shadowing pages | Never a rewrite rule; only a 404-time redirect (§1.4) |
| A slug collides with a page added later | The reserved list checks live pages at validation time; a page added *after* a slug is taken wins the URL, and the profile is still reachable at `/cleaners/…` — acceptable, and the reason the prefix is canonical |
| Magic-link mail lands in spam | The site already leans on `wp_mail` for four forms; if deliverability is a problem it is a problem everywhere and belongs in a separate SMTP plan |
| Registration becomes a spam vector | `acct` bucket at 2/IP/10 min plus a 20/hour global cap; an account is useless until a link is consumed; unverified accounts older than 7 days swept by a daily cron |
| Someone registers a slug impersonating the org | Reserved list covers `glc`, `greatlakecleaners`, `official`, `staff`, `team` and near-variants; slugs stay admin-editable |
| Scope creep into a social network | Explicit non-goals in §1.1 |

## 1.15 Open questions — decide before Phase 2

1. **Real names, or a handle?** Cards already publish `glc_submitter_name` as typed, so
   real names are the status quo — but a profile aggregates every cleanup one person
   has done onto a single page with a map, which is a meaningfully different exposure.
   Recommendation: display name defaults to what they typed on the form, is freely
   editable, and the profile is public by default with a one-click switch to hidden.
2. **Should a profile map show exact GPS?** Cleanup sites are public access points and
   are already pinned individually on `/cleanups/`. Aggregating one person's sites is
   still a pattern of life. Recommendation: ship exact, consistent with the rest of the
   site; revisit if anyone raises it.
3. **Does an account unlock anything beyond the profile** — editing a submission before
   review, say? Recommendation: no, not in v1. It turns the pending queue into a
   two-writer surface.
4. **Is `/cleaners/` the right word?** `/crew/` matches "Join our Crew", but crew signup
   is a different, email-only thing today. Decide before Phase 3 — changing it
   afterwards means redirects.
