<?php
/**
 * Great Lake Cleaners — community accounts and public cleaner profiles.
 *
 * An OPTIONAL account for people who submit cleanups. The anonymous path
 * through [glc_submit_form] is untouched and stays the default; an account only
 * adds a public profile at /cleaners/{slug}/, automatic attribution of new
 * submissions, and a claim of past ones made under the same (now verified)
 * email address.
 *
 * Three things in this codebase are load-bearing here and must not be re-broken:
 *
 *   1. Username enumeration is deliberately closed (theme functions.php: the
 *      REST users collection is gated on edit_posts, /author/ archives 404 at
 *      template_redirect priority 0). Nothing below re-opens either. The public
 *      handle is a separate string from the credential -- see the identity model.
 *
 *   2. wp-login.php is public, so an account must not add a guessable
 *      credential. user_login is opaque and never published, sign-in is by
 *      emailed magic link, and there is no password anyone knows.
 *
 *   3. glc_submission carries 'delete_with_user' => false (submission.php).
 *      Deleting an account must orphan its cleanups, never delete them -- every
 *      public total on the site counts those posts.
 *
 * Identity model, which drives everything else:
 *
 *   user_login      cleaner_ + 12 hex, generated, never shown. A login slug that
 *                   is never published cannot be enumerated or stuffed.
 *   user_nicename   the same opaque value, so that if author archives are ever
 *                   re-enabled by accident they leak nothing.
 *   sign-in id      the email address (core already authenticates by email).
 *   public handle   glc_profile_slug user meta -- fully decoupled from any
 *                   credential, and renameable without touching the account.
 *
 * INVARIANT: the profile slug and the login are two different strings and must
 * never be derived from one another. sanitize_title( $display_name ) for the
 * slug is fine; sanitize_user( $email ) for the login is not.
 */

defined( 'ABSPATH' ) || exit;

define( 'GLC_CLEANER_ROLE', 'glc_cleaner' );
define( 'GLC_LOGIN_TTL',    15 * MINUTE_IN_SECONDS );


// ── 1. The role ───────────────────────────────────────────────────────────────

/**
 * `read` and nothing else.
 *
 * Deliberately NO edit_posts: glc_submission uses capability_type => 'post', so
 * granting it would drop the entire community submission queue -- other
 * people's names, emails and phone numbers -- into a self-registered visitor's
 * wp-admin. It is also the capability the REST users-collection filter is gated
 * on, so handing it out would undo the enumeration fix.
 *
 * Added on init rather than only on activation so an in-place file update can't
 * leave an install with accounts whose role no longer exists. Roles live in one
 * autoloaded option, so the get_role() check is free.
 */
add_action( 'init', 'glc_register_cleaner_role', 1 );
function glc_register_cleaner_role() {
    if ( get_role( GLC_CLEANER_ROLE ) ) return;
    add_role( GLC_CLEANER_ROLE, 'Cleaner', [ 'read' => true ] );
}

// Role removal is deliberately NOT hooked to deactivation: pulling it would
// strip every account's capabilities during a routine plugin update.


// ── 2. Keeping cleaners out of wp-admin ───────────────────────────────────────

add_filter( 'show_admin_bar', function ( $show ) {
    return current_user_can( 'edit_posts' ) ? $show : false;
} );

/**
 * A cleaner has no business in wp-admin; /account/ is their whole surface.
 *
 * admin-ajax.php and admin-post.php both fire admin_init, and admin-post.php is
 * exactly where the account dashboard writes go -- redirecting those would break
 * the feature this file exists for.
 */
add_action( 'admin_init', function () {
    if ( wp_doing_ajax() || current_user_can( 'edit_posts' ) ) return;

    $script = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
    if ( '' === $script && isset( $_SERVER['SCRIPT_NAME'] ) ) {
        $script = basename( (string) $_SERVER['SCRIPT_NAME'] );
    }
    if ( in_array( $script, [ 'admin-post.php', 'admin-ajax.php' ], true ) ) return;

    wp_safe_redirect( glc_account_url() );
    exit;
} );

// A cleaner who somehow reaches wp-login.php and authenticates lands on
// /account/, never on a wp-admin screen they'd immediately be bounced out of.
add_filter( 'login_redirect', function ( $redirect_to, $requested, $user ) {
    if ( ! $user instanceof WP_User )    return $redirect_to;
    if ( user_can( $user, 'edit_posts' ) ) return $redirect_to;
    return glc_account_url();
}, 10, 3 );


// ── 3. There is exactly one registration surface ──────────────────────────────

/**
 * WordPress's own open registration stays off, permanently.
 *
 * Turning it on would give the site a second sign-up route at
 * wp-login.php?action=register with none of the guards in glc_request_signin():
 * no honeypot, no rate limit, no opaque login, and a mailed password. Accounts
 * are created only by wp_insert_user() inside this file.
 *
 * This is a filter rather than a note in the docs because it is the one setting
 * whose accidental flip silently undoes the identity model. The Settings ->
 * General checkbox still renders; it just no longer has an effect.
 */
add_filter( 'pre_option_users_can_register', '__return_zero' );


// ── 4. Identity helpers ───────────────────────────────────────────────────────

/** The /account/ dashboard URL (a WP page with slug `account`). */
function glc_account_url() {
    $page = get_page_by_path( 'account' );
    return $page ? get_permalink( $page ) : home_url( '/account/' );
}

/** Canonical public profile URL for a user, or '' when they have no slug. */
function glc_profile_url( $user ) {
    $user_id = $user instanceof WP_User ? $user->ID : (int) $user;
    $slug    = (string) get_user_meta( $user_id, 'glc_profile_slug', true );
    return $slug ? home_url( '/cleaners/' . $slug . '/' ) : '';
}

/** Hidden profiles 404 for everyone but their owner. Default is public. */
function glc_profile_is_public( $user_id ) {
    return '0' !== (string) get_user_meta( (int) $user_id, 'glc_profile_public', true );
}

/**
 * Resolve a public handle to its account.
 *
 * The rewrite rule already constrains the slug, but a direct ?glc_cleaner=
 * query bypasses the rewrite entirely, so the shape is re-checked here before
 * it reaches a meta lookup.
 *
 * @return WP_User|null
 */
function glc_user_by_profile_slug( $slug ) {
    $slug = strtolower( trim( (string) $slug ) );
    if ( ! preg_match( '/^[a-z0-9][a-z0-9-]{1,28}[a-z0-9]$/', $slug ) ) return null;

    $users = get_users( [
        'meta_key'    => 'glc_profile_slug',
        'meta_value'  => $slug,
        'number'      => 1,
        'count_total' => false,
    ] );
    return $users ? $users[0] : null;
}

/**
 * The current visitor's cleaner account, or null.
 *
 * Role-scoped on purpose: staff editing the site are logged in too, and the
 * submit form should not offer to credit an admin's cleanup to a profile that
 * does not exist.
 *
 * @return WP_User|null
 */
function glc_current_cleaner() {
    if ( ! is_user_logged_in() ) return null;
    $user = wp_get_current_user();
    if ( ! $user || ! $user->exists() ) return null;
    return in_array( GLC_CLEANER_ROLE, (array) $user->roles, true ) ? $user : null;
}

/**
 * Set a post's author, including to 0.
 *
 * wp_insert_post() and wp_update_post() treat post_author => 0 as "not
 * supplied" and substitute get_current_user_id(), so a signed-in cleaner who
 * ticked "post without credit" would be credited anyway, and orphaning a
 * deleted account's cleanups would silently re-assign them to whoever ran the
 * deletion. Writing the column is the only way to actually mean zero.
 */
function glc_set_post_author( $post_id, $user_id ) {
    global $wpdb;
    $post_id = (int) $post_id;
    $user_id = (int) $user_id;
    if ( ! $post_id ) return;
    if ( (int) get_post_field( 'post_author', $post_id ) === $user_id ) return;

    $wpdb->update( $wpdb->posts, [ 'post_author' => $user_id ], [ 'ID' => $post_id ] );
    clean_post_cache( $post_id );
}


// ── 5. Profile slugs ──────────────────────────────────────────────────────────

/**
 * Handles nobody may take.
 *
 * Covers the site's own routes (so a profile can never shadow a real page),
 * the WordPress surface, and names that would let someone pass themselves off
 * as the organisation. Matched case-insensitively; published page slugs are
 * checked separately and live, at validation time.
 */
function glc_reserved_profile_slugs() {
    return [
        'home', 'cleanups', 'events', 'cleaners', 'cleanup-submission', 'photos',
        'videos', 'see-us-in-action', 'stats', 'submit-cleanup', 'report-issue',
        'join-crew', 'privacy-policy', 'about', 'blog', 'feed', 'author',
        'admin', 'login', 'logout', 'register', 'account', 'dashboard',
        'wp-admin', 'wp-login', 'wp-content', 'wp-includes', 'wp-json',
        'cleanup-best-practices', 'glc', 'greatlakecleaners', 'great-lake-cleaners',
        'official', 'staff', 'team', 'support', 'donate', 'contact',
    ];
}

/**
 * @return true|string True when the slug may be used, else a visitor-facing reason.
 */
function glc_validate_profile_slug( $slug, $user_id = 0 ) {
    $slug = strtolower( trim( (string) $slug ) );

    if ( strlen( $slug ) < 3 || strlen( $slug ) > 30 ) {
        return 'Your profile address needs to be between 3 and 30 characters.';
    }
    if ( ! preg_match( '/^[a-z0-9][a-z0-9-]*[a-z0-9]$/', $slug ) ) {
        return 'Use lowercase letters, numbers and hyphens only, starting and ending with a letter or number.';
    }
    if ( false !== strpos( $slug, '--' ) ) {
        return 'Two hyphens in a row are not allowed.';
    }
    // An all-numeric handle reads like a date archive: /cleaners/2026/.
    if ( preg_match( '/^[0-9]+$/', $slug ) ) {
        return 'Your profile address needs at least one letter.';
    }
    if ( in_array( $slug, glc_reserved_profile_slugs(), true ) ) {
        return 'That profile address is reserved. Please choose another.';
    }
    // A slug that matches a live page would still resolve to the page at the
    // root level, so the /{slug} shortcut would silently do nothing.
    if ( get_page_by_path( $slug ) ) {
        return 'That profile address is already used by a page on this site.';
    }

    $taken = glc_user_by_profile_slug( $slug );
    if ( $taken && (int) $taken->ID !== (int) $user_id ) {
        return 'That profile address is already taken.';
    }

    return true;
}

/**
 * A free slug derived from a display name.
 *
 * Derived from the DISPLAY NAME, never from the email or the login -- see the
 * invariant at the top of this file.
 */
function glc_generate_profile_slug( $display_name ) {
    $base = sanitize_title( (string) $display_name );
    $base = preg_replace( '/[^a-z0-9-]/', '', strtolower( $base ) );
    $base = trim( preg_replace( '/-+/', '-', (string) $base ), '-' );

    if ( strlen( $base ) < 3 || preg_match( '/^[0-9]+$/', $base ) ) {
        $base = 'cleaner';
    }
    $base = substr( $base, 0, 24 );

    if ( true === glc_validate_profile_slug( $base ) ) return $base;

    for ( $i = 2; $i < 200; $i++ ) {
        $try = $base . '-' . $i;
        if ( true === glc_validate_profile_slug( $try ) ) return $try;
    }
    return 'cleaner-' . bin2hex( random_bytes( 3 ) );
}


// ── 6. Creating an account, and the magic link ────────────────────────────────

/**
 * Create a cleaner account for an address that has none.
 *
 * @return WP_User|WP_Error
 */
function glc_create_cleaner( $email, $display_name ) {
    // An opaque login. Never shown anywhere, never derived from the email, and
    // regenerated on the (astronomically unlikely) collision.
    do {
        $login = 'cleaner_' . bin2hex( random_bytes( 6 ) );
    } while ( username_exists( $login ) );

    $display_name = glc_clean_text( $display_name, 60 );
    if ( '' === $display_name ) {
        $display_name = 'Cleaner';
    }

    // A password IS set, because WordPress requires one -- it is long, random,
    // and nobody, including us, ever learns it. Sign-in is by emailed link, so
    // there is nothing here to phish, reset or stuff.
    $user_id = wp_insert_user( [
        'user_login'    => $login,
        // user_nicename is what an author archive would expose. Pinning it to
        // the opaque login means even a re-enabled author archive leaks nothing.
        'user_nicename' => $login,
        'user_email'    => $email,
        'user_pass'     => wp_generate_password( 64, true, true ),
        'display_name'  => $display_name,
        'nickname'      => $display_name,
        'role'          => GLC_CLEANER_ROLE,
    ] );

    if ( is_wp_error( $user_id ) ) return $user_id;

    update_user_meta( $user_id, 'glc_profile_slug',   glc_generate_profile_slug( $display_name ) );
    update_user_meta( $user_id, 'glc_profile_public', '1' );
    update_user_meta( $user_id, 'glc_joined_date',    current_time( 'Y-m-d' ) );

    return get_user_by( 'id', $user_id );
}

/**
 * Issue and email a single-use sign-in link.
 *
 * The URL carries a random per-issue selector and the token itself; only an
 * HMAC of the token is stored, so a database read never yields a usable link.
 * Issuing a new link invalidates any outstanding one.
 *
 * The recipient is ALWAYS $user->user_email, read back off the account record.
 * This is the site's first user-addressed mail -- the other four forms all go to
 * a hardcoded info@ address -- and the address must never come from the POST
 * body, or the form becomes an open relay for arbitrary text.
 */
function glc_issue_login_link( WP_User $user ) {
    $selector = bin2hex( random_bytes( 8 ) );
    $token    = wp_generate_password( 32, false );

    update_user_meta( $user->ID, 'glc_login_selector', $selector );
    update_user_meta( $user->ID, 'glc_login_token', hash_hmac( 'sha256', $token, wp_salt( 'auth' ) ) );
    update_user_meta( $user->ID, 'glc_login_expires', time() + GLC_LOGIN_TTL );

    $url = add_query_arg(
        [ 'glc_login' => $selector, 't' => $token ],
        glc_account_url()
    );

    $body = "Hi " . $user->display_name . ",\n\n"
          . "Here is your sign-in link for Great Lake Cleaners:\n\n"
          . $url . "\n\n"
          . "It works once and expires in 15 minutes. If you didn't ask for it,\n"
          . "you can ignore this email — nothing has changed on your account.\n\n"
          . "— Great Lake Cleaners\n"
          . home_url( '/' ) . "\n";

    return wp_mail(
        $user->user_email,
        'Your Great Lake Cleaners sign-in link',
        $body,
        [ 'Content-Type: text/plain; charset=UTF-8' ]
    );
}

/**
 * Handle the one signed-out write: "email me a sign-in link".
 *
 * Returns 'sent' on success, or a visitor-facing error string. The response for
 * a brand-new address and for an existing one is deliberately identical, so the
 * form is not an account-existence oracle.
 *
 * Both rate-limit buckets are CHECKED on every request and only the relevant one
 * is HIT, which is what keeps that true: if `acct` were checked only when a
 * creation was actually needed, a throttled response would itself reveal that
 * the address was new. The cost is that two account creations from one IP also
 * pause sign-ins from that IP for ten minutes; that is the right trade.
 */
function glc_request_signin() {
    if ( ! isset( $_POST['glc_account_signin'] ) ) return null;

    if ( ! isset( $_POST['glc_account_nonce'] )
        || ! wp_verify_nonce( $_POST['glc_account_nonce'], 'glc_account' ) ) {
        return 'Security check failed. Please refresh and try again.';
    }

    // Honeypot — answer exactly as if it had worked.
    if ( ! empty( $_POST['glc_url'] ) ) return 'sent';

    $throttled = glc_rate_limit_check( 'login', 3 );
    if ( true !== $throttled ) return $throttled;
    $throttled = glc_rate_limit_check( 'acct', 2 );
    if ( true !== $throttled ) return $throttled;

    $email = sanitize_email( glc_clean_text( $_POST['glc_account_email'] ?? '', 200 ) );
    if ( ! $email || ! is_email( $email ) ) {
        return [ 'field' => 'glc_account_email', 'message' => 'Please enter a valid email address.' ];
    }

    $user    = get_user_by( 'email', $email );
    $created = false;

    if ( $user && ! in_array( GLC_CLEANER_ROLE, (array) $user->roles, true ) ) {
        // Some other kind of account already holds this address. Staff in
        // particular must never get a passwordless 15-minute link: it would
        // turn read access to an inbox into full control of the site, for an
        // account whose whole point is that it is password-protected. Staff
        // sign in at wp-login.php.
        //
        // The answer is the same 'sent' the happy path gives, and the rate
        // limit is charged the same way, so this branch is invisible from
        // outside -- it must not become an oracle for which addresses are
        // staff addresses.
        glc_rate_limit_hit( 'login' );
        return 'sent';
    }

    if ( ! $user ) {
        $user = glc_create_cleaner( $email, glc_clean_text( $_POST['glc_account_name'] ?? '', 60 ) );
        if ( is_wp_error( $user ) ) {
            return 'We could not set that up right now. Please try again shortly.';
        }
        $created = true;
    }

    if ( ! glc_issue_login_link( $user ) ) {
        return 'We could not send that email. Please try again, or contact info@greatlakecleaners.ca.';
    }

    // Counters bump only after the mail actually went out, so a typo never
    // burns a slot.
    glc_rate_limit_hit( 'login' );
    if ( $created ) glc_rate_limit_hit( 'acct' );

    return 'sent';
}


// ── 7. Consuming a link ───────────────────────────────────────────────────────

/**
 * Trade ?glc_login=<selector>&t=<token> for an auth cookie.
 *
 * Runs early on template_redirect so the cookie is set before any output, and
 * always ends in a redirect to a clean /account/ — the token must not survive in
 * the address bar, browser history, or a Referer header on the next request.
 * Whatever the new session needs to say is left in a short-lived transient
 * instead of a query argument.
 */
add_action( 'template_redirect', 'glc_maybe_consume_login_link', 1 );
function glc_maybe_consume_login_link() {
    if ( empty( $_GET['glc_login'] ) || empty( $_GET['t'] ) ) return;

    $selector = preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_GET['glc_login'] ) );
    $token    = glc_clean_text( $_GET['t'], 64 );
    $target   = glc_account_url();

    $fail = function ( $reason ) use ( $target ) {
        set_transient( 'glc_login_fail_' . md5( glc_client_ip() ), $reason, 5 * MINUTE_IN_SECONDS );
        wp_safe_redirect( $target );
        exit;
    };

    if ( 16 !== strlen( $selector ) || '' === $token ) {
        $fail( 'That sign-in link is not valid. Please request a new one.' );
    }

    $users = get_users( [
        'meta_key'    => 'glc_login_selector',
        'meta_value'  => $selector,
        'number'      => 1,
        'count_total' => false,
    ] );
    if ( ! $users ) {
        $fail( 'That sign-in link has already been used or has expired. Please request a new one.' );
    }
    $user = $users[0];

    $stored  = (string) get_user_meta( $user->ID, 'glc_login_token', true );
    $expires = (int) get_user_meta( $user->ID, 'glc_login_expires', true );

    // Single use: the link is spent whether or not it turns out to be valid, so
    // a wrong token can't be retried against the same selector.
    delete_user_meta( $user->ID, 'glc_login_selector' );
    delete_user_meta( $user->ID, 'glc_login_token' );
    delete_user_meta( $user->ID, 'glc_login_expires' );

    if ( ! $stored || ! hash_equals( $stored, hash_hmac( 'sha256', $token, wp_salt( 'auth' ) ) ) ) {
        $fail( 'That sign-in link is not valid. Please request a new one.' );
    }
    if ( $expires < time() ) {
        $fail( 'That sign-in link has expired. Please request a new one.' );
    }

    $first_time = '1' !== (string) get_user_meta( $user->ID, 'glc_email_verified', true );
    update_user_meta( $user->ID, 'glc_email_verified', '1' );

    wp_set_current_user( $user->ID );
    wp_set_auth_cookie( $user->ID, false );

    // The address has just been proven, which is the whole point of the link, so
    // matching past anonymous submissions are claimed without a further prompt.
    $claimed = $first_time ? glc_claim_submissions( $user ) : 0;

    set_transient( 'glc_welcome_' . $user->ID, [
        'first'   => $first_time,
        'claimed' => $claimed,
    ], 5 * MINUTE_IN_SECONDS );

    wp_safe_redirect( $target );
    exit;
}


// ── 8. Claiming past submissions ──────────────────────────────────────────────

/**
 * Attach previously-anonymous submissions made under this (now verified) email.
 *
 * Only unowned posts are touched, and a submission whose author explicitly
 * asked not to be credited is skipped -- that opt-out was a decision about the
 * public page, and verifying an address later does not reverse it.
 *
 * @return int Number of cleanups claimed.
 */
function glc_claim_submissions( WP_User $user ) {
    $email = strtolower( trim( $user->user_email ) );
    if ( ! $email ) return 0;

    // No 'author' argument here on purpose. WP_Query reads author => 0 as "no
    // author filter at all", not as "the anonymous author", so asking for it
    // would quietly widen this to every submission with a matching email —
    // including ones already owned by somebody else. The glc_email match is
    // narrow enough on its own, and the ownership test is done in the loop,
    // where it actually means what it says.
    $posts = get_posts( [
        'post_type'      => 'glc_submission',
        'post_status'    => [ 'publish', 'pending' ],
        'posts_per_page' => 200,
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'AND',
            [ 'key' => 'glc_email', 'value' => $email, 'compare' => '=' ],
            [
                'relation' => 'OR',
                [ 'key' => 'glc_credit_anonymous', 'value' => '1', 'compare' => '!=' ],
                [ 'key' => 'glc_credit_anonymous', 'compare' => 'NOT EXISTS' ],
            ],
        ],
    ] );

    $claimed = 0;
    foreach ( $posts as $pid ) {
        if ( (int) get_post_field( 'post_author', $pid ) !== 0 ) continue;
        glc_set_post_author( $pid, $user->ID );
        $claimed++;
    }

    if ( $claimed ) {
        wp_mail(
            'info@greatlakecleaners.ca',
            'Cleanups claimed by a new account',
            sprintf(
                "%s (%s) verified their email and claimed %d earlier submission(s).\n\nProfile: %s\n",
                $user->display_name,
                $user->user_email,
                $claimed,
                glc_profile_url( $user ) ?: '(no slug)'
            ),
            [ 'Content-Type: text/plain; charset=UTF-8' ]
        );
    }

    return $claimed;
}

/**
 * An account being deleted must leave its cleanups -- and their photos -- standing.
 *
 * Two separate hazards, and only one of them is covered by the CPT flag:
 *
 *   The posts. 'delete_with_user' => false keeps them, but it leaves
 *   post_author pointing at a user row that no longer exists, so the
 *   reassignment to 0 has to happen here.
 *
 *   The photos. `attachment` is a core post type that IS deleted with its
 *   author, and a signed-in cleaner's uploads are authored by them -- so
 *   without this, deleting an account would strip the images off cleanup
 *   records that themselves survive. wp_delete_user() fires this action before
 *   it runs its own sweep, so handing the attachments to author 0 first is what
 *   keeps them out of that query.
 *
 * Hooked on delete_user rather than living only in the dashboard handler, so an
 * admin deleting the account from wp-admin gets identical behaviour.
 */
add_action( 'delete_user', 'glc_orphan_user_submissions' );
function glc_orphan_user_submissions( $user_id ) {
    $user_id = (int) $user_id;
    if ( ! $user_id ) return;

    $posts = get_posts( [
        'post_type'      => 'glc_submission',
        'post_status'    => 'any',
        'author'         => $user_id,
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ] );

    foreach ( $posts as $pid ) {
        glc_set_post_author( $pid, 0 );
        update_post_meta( $pid, 'glc_submitter_name', '' );
        update_post_meta( $pid, 'glc_email', '' );
    }

    // A cleaner can only ever have created an attachment through the submission
    // form, so every one of them belongs to a cleanup record that is staying.
    $attachments = get_posts( [
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'author'         => $user_id,
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ] );

    foreach ( $attachments as $aid ) {
        glc_set_post_author( $aid, 0 );
    }
}


// ── 9. Routing: /cleaners/{slug}/ and the /{slug} shortcut ────────────────────

add_action( 'init', function () {
    add_rewrite_rule(
        '^cleaners/([a-z0-9][a-z0-9-]{1,28}[a-z0-9])/?$',
        'index.php?glc_cleaner=$matches[1]',
        'top'
    );
} );

add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'glc_cleaner';
    return $vars;
} );

/**
 * The rewrite hands WP a query var it has no post query for, so the main query
 * would otherwise fall through to the blog home -- loading ten posts for
 * nothing, reporting is_home(), and letting redirect_canonical() bounce the URL
 * at the front page. Short-circuit all three; the profile template runs its own
 * scoped queries.
 */
add_action( 'parse_query', function ( $q ) {
    if ( ! $q->is_main_query() || ! $q->get( 'glc_cleaner' ) ) return;
    $q->is_home     = false;
    $q->is_archive  = false;
    $q->is_singular = false;
    $q->set( 'post__in', [ 0 ] );
    $q->set( 'posts_per_page', 1 );
} );

add_filter( 'redirect_canonical', function ( $redirect ) {
    return get_query_var( 'glc_cleaner' ) ? false : $redirect;
} );

/**
 * The queried profile, or null.
 *
 * A miss, or a hidden profile viewed by anyone but its owner, returns null and
 * the request 404s. Deliberately NOT a redirect and NOT a "this profile is
 * private" page: either of those confirms that the slug exists, which is the
 * same enumeration leak closed for usernames.
 *
 * @return WP_User|null
 */
function glc_profile_queried_user() {
    static $cache = false;
    if ( false !== $cache ) return $cache;

    $slug = (string) get_query_var( 'glc_cleaner' );
    if ( '' === $slug ) return $cache = null;

    $user = glc_user_by_profile_slug( $slug );
    if ( ! $user ) return $cache = null;
    if ( ! glc_profile_is_public( $user->ID ) && get_current_user_id() !== (int) $user->ID ) {
        return $cache = null;
    }
    return $cache = $user;
}

// The main query found no posts, so WP::handle_404() has already 404'd this
// request. Restore the 200 for a profile that does resolve; leave the 404 in
// place for one that does not, so the theme's 404.php renders.
add_action( 'template_redirect', function () {
    if ( ! get_query_var( 'glc_cleaner' ) ) return;

    global $wp_query;
    if ( ! glc_profile_queried_user() ) {
        $wp_query->set_404();
        status_header( 404 );
        nocache_headers();
        return;
    }
    $wp_query->is_404 = false;
    status_header( 200 );
}, 2 );

add_filter( 'template_include', function ( $template ) {
    if ( ! get_query_var( 'glc_cleaner' ) )  return $template;
    if ( ! glc_profile_queried_user() )      return $template;   // already 404'd

    $found = locate_template( 'glc-profile.php' );
    return $found ?: GLC_PLUGIN_DIR . 'templates/profile-fallback.php';
} );

// The query-var route produces no document title of its own.
add_filter( 'document_title_parts', function ( $parts ) {
    if ( get_query_var( 'glc_cleaner' ) ) {
        $user = glc_profile_queried_user();
        if ( $user ) $parts['title'] = $user->display_name . ' — Cleanups';
        return $parts;
    }
    // The dashboard is a private surface; there is nothing here to index.
    if ( is_page( 'account' ) ) $parts['title'] = 'Your Account';
    return $parts;
} );

add_filter( 'wp_robots', function ( $robots ) {
    if ( is_page( 'account' ) ) {
        $robots['noindex'] = true;
        unset( $robots['index'] );
    }
    return $robots;
} );

/**
 * /meg -> 301 -> /cleaners/meg/
 *
 * Why this is a 404-time redirect and never a rewrite rule: a root-level
 * ^([a-z0-9-]+)/?$ rule would shadow every page on the site. WP's own page rule
 * is the catch-all (.?.+?)/?$ sitting at the very bottom of the rules array, so
 * a rule added with 'bottom' never fires and one added with 'top' swallows
 * /photos/, /stats/, /join-crew/ and the rest. There is no priority that
 * threads that needle.
 *
 * At default priority, so it runs AFTER the theme's author-archive 404 (which
 * is at priority 0) and only ever on a request that was already going to 404.
 */
add_action( 'template_redirect', function () {
    if ( ! is_404() ) return;

    global $wp;
    $seg = trim( (string) $wp->request, '/' );
    if ( '' === $seg || false !== strpos( $seg, '/' ) ) return;

    $user = glc_user_by_profile_slug( $seg );
    if ( ! $user || ! glc_profile_is_public( $user->ID ) ) return;

    $url = glc_profile_url( $user );
    if ( ! $url ) return;

    wp_safe_redirect( $url, 301 );
    exit;
} );

// Keep the dashboard out of any page cache, and out of a shared proxy's.
add_action( 'template_redirect', function () {
    if ( is_user_logged_in() && is_page( 'account' ) ) nocache_headers();
}, 3 );


// ── 10. Profile data ──────────────────────────────────────────────────────────

/**
 * Post types a profile aggregates.
 *
 * Only community submissions. cleanup_event posts are org-run outings authored
 * by an admin with no participant list, and crediting a crew member on one
 * would need a whole participants model. Kept as a constant here rather than a
 * string sprinkled through the template so widening it later is one edit.
 */
function glc_profile_post_types() {
    return [ 'glc_submission' ];
}

/**
 * One account's cleanups, newest first.
 *
 * Deliberately not glc_get_all_cleanups(), which pulls every cleanup on the
 * site with posts_per_page => -1; a profile has no business loading all of it.
 */
function glc_user_cleanups( $user_id, $statuses = [ 'publish' ] ) {
    $user_id = (int) $user_id;
    // author => 0 means "no author filter" to WP_Query, which would return
    // everybody's cleanups. There is no such thing as user zero's profile.
    if ( $user_id <= 0 ) return [];

    $posts = get_posts( [
        'post_type'      => glc_profile_post_types(),
        'post_status'    => $statuses,
        'author'         => $user_id,
        'posts_per_page' => -1,
    ] );

    // Same strcmp on YYYY-MM-DD used everywhere else on the site.
    usort( $posts, function ( $a, $b ) {
        return strcmp(
            glc_cleanup_field( $b, 'cleanup_date' ) ?: '0000-00-00',
            glc_cleanup_field( $a, 'cleanup_date' ) ?: '0000-00-00'
        );
    } );

    return $posts;
}

/**
 * Per-account totals, in the same shape glc_get_impact_stats() returns so the
 * stat-card markup is shared.
 *
 * glc_get_impact_stats() is deliberately NOT given an author argument: the
 * footer strip and the archive both call it, and it is the one place site-wide
 * totals are defined.
 *
 * Published posts only. Pending submissions count for nothing anywhere else on
 * the site, and a personal page is not the place to start diverging from the
 * public numbers.
 */
function glc_user_impact_stats( $user_id ) {
    $posts     = glc_user_cleanups( $user_id, [ 'publish' ] );
    $weight    = 0.0;
    $hours     = 0.0;
    $recycled  = 0;
    $corridors = [];

    foreach ( $posts as $p ) {
        $weight   += (float) glc_cleanup_field( $p, 'weight_kg' );
        $hours    += (float) glc_cleanup_field( $p, 'hours' );
        $recycled += (int)   glc_cleanup_field( $p, 'items_recycled' );

        $slug = glc_corridor_slug( glc_cleanup_field( $p, 'corridor' ) );
        if ( $slug ) $corridors[ $slug ] = true;
    }

    $table = glc_corridor_table();

    return [
        'cleanups'       => count( $posts ),
        'weight_kg'      => $weight,
        'hours'          => $hours,
        'recycled'       => $recycled,
        'corridors'      => count( $corridors ),
        // Extra to glc_get_impact_stats()'s shape — the profile header shows the
        // corridors by name as badges, not just a count.
        'corridor_names' => array_values( array_intersect_key( $table, $corridors ) ),
    ];
}


// ── 11. Bylines ───────────────────────────────────────────────────────────────

/**
 * Who to credit for a submission, and whether that credit links anywhere.
 *
 * Four cases, in order: an explicit opt-out, an owned post with a visible
 * profile, an owned post with a hidden one, and a plain anonymous submission.
 *
 * @return array{name:string,url:string}
 */
function glc_submission_credit( $post_id ) {
    $post_id = (int) $post_id;
    $typed   = (string) get_post_meta( $post_id, 'glc_submitter_name', true );

    if ( '1' === (string) get_post_meta( $post_id, 'glc_credit_anonymous', true ) ) {
        return [ 'name' => __( 'Community member', 'great-lake-cleaners' ), 'url' => '' ];
    }

    $author = (int) get_post_field( 'post_author', $post_id );
    if ( $author > 0 ) {
        $user = get_user_by( 'id', $author );
        if ( $user ) {
            $name = $user->display_name ?: $typed;
            $url  = glc_profile_is_public( $author ) ? glc_profile_url( $user ) : '';
            return [ 'name' => $name, 'url' => $url ];
        }
    }

    return [ 'name' => $typed, 'url' => '' ];
}


// ── 12. [glc_account] ─────────────────────────────────────────────────────────

add_shortcode( 'glc_account', 'glc_shortcode_account' );

function glc_shortcode_account() {
    $user = glc_current_cleaner();
    ob_start();

    if ( $user ) {
        glc_render_account_dashboard( $user );
    } else {
        glc_render_account_signin();
    }

    return ob_get_clean();
}

/** State 1: signed out — one email field, one identical answer either way. */
function glc_render_account_signin() {
    $result = glc_request_signin();

    if ( 'sent' === $result ) {
        ?>
        <div class="glc-submit-success glc-acct-sent">
            <h2><?php esc_html_e( 'Check your email', 'great-lake-cleaners' ); ?></h2>
            <p><?php esc_html_e( 'If that address can be signed in, a link is on its way. It works once and expires in 15 minutes.', 'great-lake-cleaners' ); ?></p>
            <p class="glc-field-note"><?php esc_html_e( 'Nothing arriving? Check your spam folder, or email info@greatlakecleaners.ca and we will sort it out.', 'great-lake-cleaners' ); ?></p>
        </div>
        <?php
        return;
    }

    $error       = '';
    $error_field = '';
    if ( is_array( $result ) ) {
        $error       = $result['message'];
        $error_field = $result['field'];
    } elseif ( is_string( $result ) ) {
        $error = $result;
    }

    // A failed link consumption redirects here with nothing in the URL; the
    // reason is parked in a short transient keyed by IP instead.
    $fail_key = 'glc_login_fail_' . md5( glc_client_ip() );
    $failed   = get_transient( $fail_key );
    if ( $failed ) {
        delete_transient( $fail_key );
        if ( ! $error ) $error = $failed;
    }
    ?>
    <div class="glc-submit-wrap glc-acct-wrap">
        <?php if ( $error && ! $error_field ) : ?>
        <div class="glc-form-error-banner" role="alert"><?php echo esc_html( $error ); ?></div>
        <?php endif; ?>

        <form class="glc-submit-form glc-acct-form" method="post" novalidate>
            <?php wp_nonce_field( 'glc_account', 'glc_account_nonce' ); ?>

            <fieldset class="glc-form-section">
                <legend class="glc-form-legend">
                    <span class="glc-form-legend-num">1</span>
                    <?php esc_html_e( 'Sign in, or start an account', 'great-lake-cleaners' ); ?>
                </legend>

                <p class="glc-field-note">
                    <?php esc_html_e( 'There is no password. Enter your email and we will send you a link that signs you in — the same field whether you have been here before or not.', 'great-lake-cleaners' ); ?>
                </p>

                <div class="glc-field">
                    <label for="glc_account_email"><span class="glc-label-text"><?php esc_html_e( 'Email', 'great-lake-cleaners' ); ?><span class="glc-required" aria-label="required">*</span></span></label>
                    <input type="email" id="glc_account_email" name="glc_account_email" required maxlength="200" autocomplete="email"
                           value="<?php echo esc_attr( wp_unslash( (string) ( $_POST['glc_account_email'] ?? '' ) ) ); ?>"
                           <?php echo 'glc_account_email' === $error_field ? 'aria-invalid="true" aria-describedby="glc-err-email"' : ''; ?>>
                    <?php if ( 'glc_account_email' === $error_field ) : ?>
                    <span id="glc-err-email" class="glc-field-error" role="alert"><?php echo esc_html( $error ); ?></span>
                    <?php endif; ?>
                </div>

                <div class="glc-field">
                    <label for="glc_account_name"><span class="glc-label-text"><?php esc_html_e( 'Display name', 'great-lake-cleaners' ); ?></span></label>
                    <input type="text" id="glc_account_name" name="glc_account_name" maxlength="60" autocomplete="name"
                           value="<?php echo esc_attr( wp_unslash( (string) ( $_POST['glc_account_name'] ?? '' ) ) ); ?>">
                    <p class="glc-field-note"><?php esc_html_e( 'Only used if this is your first time — it is the name shown on your profile, and you can change it later.', 'great-lake-cleaners' ); ?></p>
                </div>

                <div class="glc-hp-field" aria-hidden="true">
                    <label for="glc_url_acct">Leave this field empty</label>
                    <input type="text" id="glc_url_acct" name="glc_url" tabindex="-1" autocomplete="off">
                </div>

                <div class="glc-form-submit-row">
                    <button type="submit" name="glc_account_signin" value="1" class="glc-btn-primary glc-btn-submit">
                        <?php esc_html_e( 'Email me a sign-in link', 'great-lake-cleaners' ); ?>
                    </button>
                </div>
            </fieldset>
        </form>

        <p class="glc-field-note glc-acct-optional">
            <?php esc_html_e( 'An account is entirely optional. Submitting a cleanup without one works exactly the same, and always will.', 'great-lake-cleaners' ); ?>
        </p>
    </div>
    <?php
}

/** States 2 and 3: freshly signed in, and the dashboard proper. */
function glc_render_account_dashboard( WP_User $user ) {
    $welcome = get_transient( 'glc_welcome_' . $user->ID );
    if ( $welcome ) delete_transient( 'glc_welcome_' . $user->ID );

    $notice = get_transient( 'glc_acct_notice_' . $user->ID );
    if ( $notice ) delete_transient( 'glc_acct_notice_' . $user->ID );

    $slug        = (string) get_user_meta( $user->ID, 'glc_profile_slug', true );
    $is_public   = glc_profile_is_public( $user->ID );
    $profile_url = glc_profile_url( $user );
    $pending     = glc_user_cleanups( $user->ID, [ 'pending' ] );
    $published   = glc_user_cleanups( $user->ID, [ 'publish' ] );
    $submit_page = get_page_by_path( 'submit-cleanup' );
    ?>
    <div class="glc-acct-wrap">

        <?php if ( is_array( $welcome ) ) : ?>
        <div class="glc-acct-welcome" role="status">
            <h2><?php echo esc_html( sprintf(
                /* translators: %s: display name */
                $welcome['first'] ? __( 'Welcome, %s', 'great-lake-cleaners' ) : __( 'Welcome back, %s', 'great-lake-cleaners' ),
                $user->display_name
            ) ); ?></h2>
            <?php if ( ! empty( $welcome['claimed'] ) ) : ?>
            <p><?php echo esc_html( sprintf(
                /* translators: %d: number of cleanups */
                _n( '%d earlier cleanup added to your profile.', '%d earlier cleanups added to your profile.', (int) $welcome['claimed'], 'great-lake-cleaners' ),
                (int) $welcome['claimed']
            ) ); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ( is_array( $notice ) && ! empty( $notice['message'] ) ) : ?>
        <div class="<?php echo 'error' === ( $notice['type'] ?? '' ) ? 'glc-form-error-banner' : 'glc-acct-notice'; ?>" role="alert">
            <?php echo esc_html( $notice['message'] ); ?>
        </div>
        <?php endif; ?>

        <div class="glc-acct-summary">
            <p class="glc-acct-identity">
                <strong><?php echo esc_html( $user->display_name ); ?></strong>
                <span><?php echo esc_html( $user->user_email ); ?></span>
            </p>
            <?php if ( $profile_url ) : ?>
            <p class="glc-acct-profile-link">
                <?php if ( $is_public ) : ?>
                <a href="<?php echo esc_url( $profile_url ); ?>"><?php echo esc_html( $profile_url ); ?></a>
                <?php else : ?>
                <span class="glc-acct-hidden-tag"><?php esc_html_e( 'Profile hidden', 'great-lake-cleaners' ); ?></span>
                <a href="<?php echo esc_url( $profile_url ); ?>"><?php esc_html_e( 'Preview it', 'great-lake-cleaners' ); ?></a>
                <?php endif; ?>
            </p>
            <?php endif; ?>
            <p class="glc-acct-counts">
                <?php echo esc_html( sprintf(
                    /* translators: %d: number of published cleanups */
                    _n( '%d published cleanup', '%d published cleanups', count( $published ), 'great-lake-cleaners' ),
                    count( $published )
                ) ); ?>
            </p>
        </div>

        <form class="glc-submit-form glc-acct-form" method="post"
              action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="glc_account_save">
            <?php wp_nonce_field( 'glc_account_save', 'glc_account_save_nonce' ); ?>

            <fieldset class="glc-form-section">
                <legend class="glc-form-legend">
                    <span class="glc-form-legend-num">1</span>
                    <?php esc_html_e( 'Your profile', 'great-lake-cleaners' ); ?>
                </legend>

                <div class="glc-field-row">
                    <div class="glc-field glc-field--half">
                        <label for="glc_display_name"><span class="glc-label-text"><?php esc_html_e( 'Display name', 'great-lake-cleaners' ); ?><span class="glc-required" aria-label="required">*</span></span></label>
                        <input type="text" id="glc_display_name" name="glc_display_name" required maxlength="60"
                               value="<?php echo esc_attr( $user->display_name ); ?>">
                        <p class="glc-field-note"><?php esc_html_e( 'Shown on your profile and on every cleanup card you are credited on.', 'great-lake-cleaners' ); ?></p>
                    </div>
                    <div class="glc-field glc-field--half">
                        <label for="glc_profile_slug"><span class="glc-label-text"><?php esc_html_e( 'Profile address', 'great-lake-cleaners' ); ?><span class="glc-required" aria-label="required">*</span></span></label>
                        <div class="glc-acct-slug-row">
                            <span class="glc-acct-slug-prefix"><?php echo esc_html( home_url( '/cleaners/' ) ); ?></span>
                            <input type="text" id="glc_profile_slug" name="glc_profile_slug" required maxlength="30"
                                   pattern="[a-z0-9][a-z0-9-]{1,28}[a-z0-9]"
                                   value="<?php echo esc_attr( $slug ); ?>"
                                   aria-describedby="glc-slug-hint">
                        </div>
                        <p class="glc-field-note" id="glc-slug-hint" data-default="<?php esc_attr_e( '3–30 characters: lowercase letters, numbers and hyphens.', 'great-lake-cleaners' ); ?>"><?php esc_html_e( '3–30 characters: lowercase letters, numbers and hyphens.', 'great-lake-cleaners' ); ?></p>
                    </div>
                </div>

                <div class="glc-field">
                    <label class="glc-checkbox-label" for="glc_profile_public">
                        <input type="checkbox" id="glc_profile_public" name="glc_profile_public" value="1" <?php checked( $is_public ); ?>>
                        <span><?php esc_html_e( 'Show my profile publicly', 'great-lake-cleaners' ); ?></span>
                    </label>
                    <p class="glc-field-note"><?php esc_html_e( 'Untick and your profile address returns a "not found" page for everyone but you. Your cleanups stay published and keep counting toward the site totals either way.', 'great-lake-cleaners' ); ?></p>
                </div>

                <div class="glc-form-submit-row">
                    <button type="submit" class="glc-btn-primary glc-btn-submit"><?php esc_html_e( 'Save changes', 'great-lake-cleaners' ); ?></button>
                </div>
            </fieldset>
        </form>

        <section class="glc-acct-section">
            <h2 class="glc-acct-h2"><?php esc_html_e( 'Awaiting review', 'great-lake-cleaners' ); ?></h2>
            <?php if ( $pending ) : ?>
            <ul class="glc-acct-pending">
                <?php foreach ( $pending as $p ) :
                    $d = glc_cleanup_field( $p, 'cleanup_date' ); ?>
                <li>
                    <span class="glc-acct-pending-site"><?php echo esc_html( glc_cleanup_field( $p, 'site_name' ) ); ?></span>
                    <?php if ( $d && strtotime( $d ) ) : ?>
                    <span class="glc-acct-pending-date"><?php echo esc_html( date_i18n( 'F j, Y', strtotime( $d ) ) ); ?></span>
                    <?php endif; ?>
                    <span class="glc-community-badge"><?php esc_html_e( 'Awaiting review', 'great-lake-cleaners' ); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <p class="glc-field-note"><?php esc_html_e( 'Pending cleanups do not appear on your profile and are not counted in any total until they are reviewed.', 'great-lake-cleaners' ); ?></p>
            <?php else : ?>
            <p class="glc-field-note"><?php esc_html_e( 'Nothing waiting — everything you have submitted has been reviewed.', 'great-lake-cleaners' ); ?></p>
            <?php endif; ?>

            <p class="glc-acct-actions">
                <?php if ( $submit_page ) : ?>
                <a class="glc-btn-primary glc-btn-submit" href="<?php echo esc_url( get_permalink( $submit_page ) ); ?>"><?php esc_html_e( 'Submit a cleanup', 'great-lake-cleaners' ); ?></a>
                <?php endif; ?>
                <a class="glc-acct-signout" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Sign out', 'great-lake-cleaners' ); ?></a>
            </p>
        </section>

        <section class="glc-acct-section glc-acct-danger">
            <h2 class="glc-acct-h2"><?php esc_html_e( 'Delete this account', 'great-lake-cleaners' ); ?></h2>
            <p>
                <?php esc_html_e( 'Your cleanups stay published — they are part of the public record and every site total counts them. What goes is the credit: the cards will read "Community member", your name and email are removed from them, and this profile disappears. It cannot be undone.', 'great-lake-cleaners' ); ?>
            </p>
            <form class="glc-acct-delete-form" method="post"
                  action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="glc_account_save">
                <input type="hidden" name="glc_account_action" value="delete">
                <?php wp_nonce_field( 'glc_account_save', 'glc_account_save_nonce' ); ?>
                <div class="glc-field">
                    <label for="glc_delete_confirm"><span class="glc-label-text"><?php esc_html_e( 'Type DELETE to confirm', 'great-lake-cleaners' ); ?></span></label>
                    <input type="text" id="glc_delete_confirm" name="glc_delete_confirm" autocomplete="off" maxlength="10">
                </div>
                <button type="submit" class="glc-acct-delete-button"><?php esc_html_e( 'Delete my account', 'great-lake-cleaners' ); ?></button>
            </form>
        </section>
    </div>

    <script>
    (function () {
        var input = document.getElementById('glc_profile_slug');
        var hint  = document.getElementById('glc-slug-hint');
        if (!input || !hint) return;

        var fallback = hint.getAttribute('data-default') || '';
        var nonce    = <?php echo wp_json_encode( wp_create_nonce( 'glc_check_slug' ) ); ?>;
        var ajaxUrl  = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
        var current  = <?php echo wp_json_encode( $slug ); ?>;
        var timer;

        function say(text, state) {
            hint.textContent = text;
            hint.className = 'glc-field-note' + (state ? ' glc-acct-slug-' + state : '');
        }

        // A hint only. The save handler re-validates from scratch and is the
        // only thing that decides whether a slug is actually allowed.
        function check() {
            var value = input.value.trim().toLowerCase();
            if (value === current || value === '') { say(fallback, ''); return; }
            if (!/^[a-z0-9][a-z0-9-]{1,28}[a-z0-9]$/.test(value) || value.indexOf('--') !== -1) {
                say(<?php echo wp_json_encode( __( 'Lowercase letters, numbers and single hyphens only.', 'great-lake-cleaners' ) ); ?>, 'bad');
                return;
            }
            var fd = new FormData();
            fd.append('action', 'glc_check_slug');
            fd.append('nonce', nonce);
            fd.append('slug', value);
            fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j && j.success) { say(j.data, 'ok'); }
                    else { say((j && j.data) || fallback, 'bad'); }
                })
                .catch(function () { say(fallback, ''); });
        }

        input.addEventListener('input', function () {
            input.value = input.value.toLowerCase();
            clearTimeout(timer);
            timer = setTimeout(check, 350);
        });
    })();
    </script>
    <?php
}

/**
 * Live availability hint for the slug field.
 *
 * wp_ajax_ only — never wp_ajax_nopriv_. Nothing account-related is exposed to
 * anonymous callers, and this answers only about handles that are public URLs
 * anyway.
 */
add_action( 'wp_ajax_glc_check_slug', function () {
    if ( ! check_ajax_referer( 'glc_check_slug', 'nonce', false ) ) {
        wp_send_json_error( 'Security check failed — please reload the page.' );
    }
    if ( ! glc_current_cleaner() ) {
        wp_send_json_error( 'Not available.' );
    }

    $slug   = glc_clean_text( $_POST['slug'] ?? '', 30 );
    $result = glc_validate_profile_slug( $slug, get_current_user_id() );

    if ( true === $result ) {
        wp_send_json_success( sprintf( __( '%s is available.', 'great-lake-cleaners' ), $slug ) );
    }
    wp_send_json_error( $result );
} );


// ── 13. Dashboard writes ──────────────────────────────────────────────────────

/**
 * Every signed-in account write, behind one nonce.
 *
 * admin-post rather than an inline POST-to-self so the browser lands on a GET
 * afterwards — a refresh must never replay "delete my account". Registered
 * without its _nopriv_ counterpart: a signed-out caller has nothing to write.
 */
add_action( 'admin_post_glc_account_save', 'glc_handle_account_save' );
function glc_handle_account_save() {
    $user = glc_current_cleaner();
    if ( ! $user ) {
        wp_safe_redirect( glc_account_url() );
        exit;
    }

    if ( ! isset( $_POST['glc_account_save_nonce'] )
        || ! wp_verify_nonce( $_POST['glc_account_save_nonce'], 'glc_account_save' ) ) {
        glc_account_notice( $user->ID, 'error', 'Security check failed. Please try again.' );
        wp_safe_redirect( glc_account_url() );
        exit;
    }

    if ( 'delete' === ( $_POST['glc_account_action'] ?? '' ) ) {
        if ( 'DELETE' !== strtoupper( trim( (string) wp_unslash( $_POST['glc_delete_confirm'] ?? '' ) ) ) ) {
            glc_account_notice( $user->ID, 'error', 'Type DELETE in the box to confirm.' );
            wp_safe_redirect( glc_account_url() );
            exit;
        }

        // glc_orphan_user_submissions() runs on the delete_user action and hands
        // the cleanups back to post_author 0 first; delete_with_user => false on
        // the CPT is what stops WordPress deleting them outright.
        require_once ABSPATH . 'wp-admin/includes/user.php';
        $user_id = (int) $user->ID;
        wp_logout();
        wp_delete_user( $user_id );

        wp_safe_redirect( home_url( '/' ) );
        exit;
    }

    $display = glc_clean_text( $_POST['glc_display_name'] ?? '', 60 );
    $slug    = strtolower( glc_clean_text( $_POST['glc_profile_slug'] ?? '', 30 ) );
    $public  = ! empty( $_POST['glc_profile_public'] ) ? '1' : '0';

    if ( '' === $display ) {
        glc_account_notice( $user->ID, 'error', 'Please enter a display name.' );
        wp_safe_redirect( glc_account_url() );
        exit;
    }

    // Re-validated here regardless of what the live hint said client side.
    $valid = glc_validate_profile_slug( $slug, $user->ID );
    if ( true !== $valid ) {
        glc_account_notice( $user->ID, 'error', $valid );
        wp_safe_redirect( glc_account_url() );
        exit;
    }

    wp_update_user( [ 'ID' => $user->ID, 'display_name' => $display ] );
    update_user_meta( $user->ID, 'glc_profile_slug',   $slug );
    update_user_meta( $user->ID, 'glc_profile_public', $public );

    glc_account_notice( $user->ID, 'ok', 'Saved.' );
    wp_safe_redirect( glc_account_url() );
    exit;
}

function glc_account_notice( $user_id, $type, $message ) {
    set_transient( 'glc_acct_notice_' . (int) $user_id,
        [ 'type' => $type, 'message' => $message ], 5 * MINUTE_IN_SECONDS );
}


// ── 14. Sweeping unverified accounts ──────────────────────────────────────────

/**
 * An account nobody ever signed into is a registration attempt, not a person.
 *
 * Deleted after 7 days, and only when it owns nothing — the guard matters
 * because a claim runs at verification time, so a swept account should never
 * have cleanups attached, and if one somehow does, it stays.
 */
add_action( 'init', function () {
    if ( ! wp_next_scheduled( 'glc_sweep_unverified_accounts' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'glc_sweep_unverified_accounts' );
    }
} );

add_action( 'glc_sweep_unverified_accounts', 'glc_sweep_unverified_accounts' );
function glc_sweep_unverified_accounts() {
    $users = get_users( [
        'role'       => GLC_CLEANER_ROLE,
        'number'     => 50,
        'fields'     => 'ID',
        'meta_query' => [
            'relation' => 'OR',
            [ 'key' => 'glc_email_verified', 'compare' => 'NOT EXISTS' ],
            [ 'key' => 'glc_email_verified', 'value' => '1', 'compare' => '!=' ],
        ],
        'date_query' => [
            [ 'column' => 'user_registered', 'before' => gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ],
        ],
    ] );

    if ( ! $users ) return;

    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ( $users as $uid ) {
        if ( glc_user_cleanups( $uid, [ 'publish', 'pending', 'draft' ] ) ) continue;
        wp_delete_user( (int) $uid );
    }
}


// ── 15. Sitemap: making /cleaners/{slug}/ discoverable ────────────────────────

/**
 * Public profile URLs for the sitemap, ordered and deduplicated.
 *
 * Only profiles worth submitting to a search engine:
 *
 *   - at least one PUBLISHED cleanup. A profile with nothing on it is a thin
 *     page, and an unverified account that never consumed its link has nothing
 *     by definition -- so this also keeps the 7-day sweep's leftovers out
 *     without needing to test for them.
 *   - not hidden. Filtered through glc_profile_is_public() rather than a
 *     meta_query, because "public" means "the meta is not '0'", including the
 *     common case where the row does not exist at all. Restating that as a
 *     query is how the two definitions drift apart.
 *   - actually has a handle. glc_profile_url() returns '' without one, which is
 *     also what quietly excludes an admin who authored a submission by hand:
 *     staff accounts have no glc_profile_slug.
 *
 * One query for the authors, then per-user meta reads that are already primed
 * for anything the page renders. Cached per request because the index and the
 * sitemap itself both ask.
 *
 * @return string[] Canonical profile URLs.
 */
function glc_sitemap_profile_urls() {
    static $cache = null;
    if ( null !== $cache ) return $cache;

    global $wpdb;

    $types = glc_profile_post_types();
    $slots = implode( ',', array_fill( 0, count( $types ), '%s' ) );

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $slots is
    // a generated list of %s placeholders, and $types is passed to prepare().
    $author_ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT post_author FROM {$wpdb->posts}
          WHERE post_status = 'publish'
            AND post_author > 0
            AND post_type IN ( $slots )",
        $types
    ) );

    $urls = [];
    foreach ( $author_ids as $uid ) {
        $uid = (int) $uid;
        if ( ! glc_profile_is_public( $uid ) ) continue;
        $url = glc_profile_url( $uid );
        if ( $url ) $urls[ $url ] = true;
    }

    $urls = array_keys( $urls );
    sort( $urls );   // a stable order, so page 2 means the same thing twice

    return $cache = $urls;
}

/**
 * A sitemap provider for public cleaner profiles.
 *
 * /cleaners/{slug}/ is a rewrite onto a query var, not a post type, so core
 * generates nothing for it -- profiles were reachable only by crawling a byline
 * link on /cleanups/, which in practice means the ones near the top of page 1.
 * This publishes them properly, at wp-sitemap-cleaners-1.xml.
 *
 * Note what this is NOT: the theme drops core's `users` provider, which emitted
 * /author/<login slug>/ URLs. That is the credential-side identifier and stays
 * unpublished. This provider emits the identity-side one -- a handle the cleaner
 * chose, on a page built to be shared. Two different namespaces; the profile
 * being discoverable and the login being opaque are not in tension.
 *
 * No rewrite flush needed: core's sitemap rules already match any provider name
 * (^wp-sitemap-([a-z]+?)-(\d+?)\.xml$).
 */
if ( class_exists( 'WP_Sitemaps_Provider' ) ) :

class GLC_Cleaner_Sitemap_Provider extends WP_Sitemaps_Provider {

    public $name        = 'cleaners';
    public $object_type = 'cleaner';

    public function get_url_list( $page_num, $object_subtype = '' ) {
        $per_page = wp_sitemaps_get_max_urls( $this->object_type );
        $slice    = array_slice(
            glc_sitemap_profile_urls(),
            ( max( 1, (int) $page_num ) - 1 ) * $per_page,
            $per_page
        );

        $list = [];
        foreach ( $slice as $url ) {
            $list[] = [ 'loc' => $url ];
        }
        return $list;
    }

    public function get_max_num_pages( $object_subtype = '' ) {
        $total = count( glc_sitemap_profile_urls() );
        // 0 keeps the provider out of the index entirely while nobody has a
        // public profile yet -- core 404s an empty sitemap, so advertising one
        // would be worse than advertising none.
        if ( ! $total ) return 0;

        return (int) ceil( $total / wp_sitemaps_get_max_urls( $this->object_type ) );
    }
}

add_action( 'init', function () {
    if ( function_exists( 'wp_register_sitemap_provider' ) ) {
        wp_register_sitemap_provider( 'cleaners', new GLC_Cleaner_Sitemap_Provider() );
    }
}, 20 );

endif;
