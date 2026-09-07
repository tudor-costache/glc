<?php
/**
 * Great Lake Cleaners — shared input-security helpers.
 *
 * Everything the four public, unauthenticated entry points need in common:
 *
 *   [glc_submit_form]   submission.php   creates a pending post + uploads photos
 *   [glc_report_form]   report.php       emails info@ with photo attachments
 *   [glc_join_crew]     crew-signup.php  emails info@
 *   [glc_event_rsvp]    events.php       emails info@
 *   [glc_account]       accounts.php     creates a user + emails the VISITOR
 *
 * All five are reachable by anyone, so the guards below are the only thing
 * between a bot and either the media library or the org's inbox. Keep the
 * helpers here rather than duplicating them — a fix applied to one form and
 * not the others is the failure mode this file exists to prevent.
 */

defined( 'ABSPATH' ) || exit;


// ── Client identity ───────────────────────────────────────────────────────────

/**
 * The IP to rate-limit against.
 *
 * Deliberately REMOTE_ADDR only. X-Forwarded-For / X-Real-IP are attacker-
 * supplied on a host that doesn't sit behind a proxy that overwrites them, so
 * trusting them would hand every bot an unlimited supply of rate-limit buckets.
 * If the site ever moves behind Cloudflare, restore the real IP into
 * REMOTE_ADDR at the host or mu-plugin level instead of reading a header here.
 */
function glc_client_ip() {
    return isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
}


// ── Rate limiting ─────────────────────────────────────────────────────────────

/**
 * Site-wide hourly ceilings, checked alongside the per-IP limit.
 *
 * The per-IP limit stops one visitor hammering a form; it does nothing about a
 * botnet or a rotating-IP script, where every request looks like a first
 * attempt. These are the circuit breaker for that case — set well above any
 * plausible real day so they only ever trip during abuse.
 *
 * @param string $bucket Form identifier.
 * @return int Requests per hour across all visitors.
 */
function glc_rate_limit_global_cap( $bucket ) {
    $caps = [
        'sub'   => 40,  // community cleanup submissions
        'rep'   => 40,  // waterway issue reports
        'crew'  => 40,  // crew signups
        'rsvp'  => 80,  // event RSVPs — spike legitimately when an event is announced
        'login' => 60,  // magic-link sign-in requests (accounts.php)
        'acct'  => 20,  // new account creations — deliberately the tightest ceiling
                        // on the site: an account is a durable object, and a
                        // registration flood is the one abuse that outlives the
                        // hour it happened in
    ];
    return (int) apply_filters( 'glc_rate_limit_global_cap', $caps[ $bucket ] ?? 40, $bucket );
}

/**
 * Whether this request is allowed through.
 *
 * @param string $bucket Short form identifier ('sub', 'rep', 'crew', 'rsvp',
 *                       'login', 'acct').
 * @param int    $ip_max Requests allowed per IP per 10 minutes.
 * @return true|string   True when allowed, else a visitor-facing message.
 */
function glc_rate_limit_check( $bucket, $ip_max ) {
    $ip_key = 'glc_rl_' . $bucket . '_' . md5( glc_client_ip() );
    if ( (int) get_transient( $ip_key ) >= $ip_max ) {
        return 'Too many submissions from your connection. Please wait a few minutes and try again.';
    }

    $global_key = 'glc_rl_all_' . $bucket;
    if ( (int) get_transient( $global_key ) >= glc_rate_limit_global_cap( $bucket ) ) {
        return 'We are receiving an unusual number of submissions right now. Please try again shortly, or email info@greatlakecleaners.ca.';
    }

    return true;
}

/**
 * Record one successful use of a form.
 *
 * Called only after validation passes and the side effect (mail or post) has
 * been attempted — a visitor who mistypes their email shouldn't burn a slot.
 */
function glc_rate_limit_hit( $bucket ) {
    $ip_key = 'glc_rl_' . $bucket . '_' . md5( glc_client_ip() );
    set_transient( $ip_key, (int) get_transient( $ip_key ) + 1, 10 * MINUTE_IN_SECONDS );

    $global_key = 'glc_rl_all_' . $bucket;
    set_transient( $global_key, (int) get_transient( $global_key ) + 1, HOUR_IN_SECONDS );
}


// ── Value clamping ────────────────────────────────────────────────────────────

/**
 * Sanitize a single-line text field and cap its length.
 *
 * The `maxlength` attributes on the forms are a UI courtesy — nothing stops a
 * script POSTing a megabyte into any of them. Every free-text field that ends
 * up in post meta or an email body goes through here or its textarea sibling.
 *
 * wp_unslash() matters as much as the cap: WordPress slash-escapes every
 * superglobal on load, so without it an apostrophe is stored as \' and gains
 * another backslash on each admin re-save.
 */
function glc_clean_text( $raw, $max = 200 ) {
    $val = sanitize_text_field( wp_unslash( (string) $raw ) );
    return function_exists( 'mb_substr' ) ? mb_substr( $val, 0, $max ) : substr( $val, 0, $max );
}

/** Multi-line equivalent of glc_clean_text(). */
function glc_clean_textarea( $raw, $max = 2000 ) {
    $val = sanitize_textarea_field( wp_unslash( (string) $raw ) );
    return function_exists( 'mb_substr' ) ? mb_substr( $val, 0, $max ) : substr( $val, 0, $max );
}

/**
 * A bounded float.
 *
 * (float) alone accepts "1e999", which becomes INF and poisons every cumulative
 * total it is ever added to — the stats pages would render "INF kg" with no way
 * to tell which record did it.
 */
function glc_clean_float( $raw, $min, $max, $default = 0.0 ) {
    if ( ! is_scalar( $raw ) || (string) $raw === '' ) return $default;
    $val = (float) $raw;
    if ( ! is_finite( $val ) ) return $default;
    return max( $min, min( $max, $val ) );
}

/** A bounded non-negative integer. */
function glc_clean_int( $raw, $max, $min = 0 ) {
    if ( ! is_scalar( $raw ) ) return $min;
    $val = (int) glc_clean_float( $raw, $min, $max, $min );
    return max( $min, min( $max, $val ) );
}

/**
 * A latitude/longitude, or '' when not supplied or out of range.
 *
 * Returns '' rather than 0 for a blank field — 0,0 is a real coordinate in the
 * Gulf of Guinea and would drop a map pin there.
 */
function glc_clean_coord( $raw, $min, $max ) {
    if ( ! isset( $raw ) || ! is_scalar( $raw ) || (string) $raw === '' ) return '';
    $val = (float) $raw;
    if ( ! is_finite( $val ) || $val < $min || $val > $max ) return '';
    return $val;
}


// ── Upload validation ─────────────────────────────────────────────────────────

/**
 * Image types the public forms accept, in WordPress's ext => mime shape.
 *
 * Passed to wp_handle_upload() as its `mimes` whitelist so the upload is checked
 * against these three rather than the whole of get_allowed_mime_types() — which
 * would let an anonymous visitor drop PDFs, ZIPs, Office documents and MP4s into
 * the media library, publicly served, without ever logging in.
 */
function glc_allowed_image_mimes() {
    return [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'webp'         => 'image/webp',
    ];
}

/**
 * Verify an uploaded file really is one of the image types we accept.
 *
 * $_FILES['type'] is whatever the client put in the multipart Content-Type
 * header — a bot sets "image/jpeg" on anything, so checking it proves nothing.
 * This reads the bytes on disk instead, and cross-checks that the claimed
 * filename extension agrees, so a stored file can never be a PDF wearing a .jpg
 * name (or a JPEG served as .pdf).
 *
 * @param array $file      One entry in $_FILES shape: name, tmp_name, size, error.
 * @param int   $max_bytes Size ceiling.
 * @return array|false     [ 'name' => corrected filename, 'type' => real mime ] or false.
 */
function glc_validate_image_upload( $file, $max_bytes = 8388608 ) {
    if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) return false;
    if ( ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_OK )            return false;

    // Trust the filesystem, not the reported size.
    $size = @filesize( $file['tmp_name'] );
    if ( false === $size || $size <= 0 || $size > $max_bytes ) return false;

    // Content sniff: getimagesize() parses the actual header and returns false
    // for anything that isn't a real raster image.
    $info = @getimagesize( $file['tmp_name'] );
    if ( ! is_array( $info ) || empty( $info['mime'] ) ) return false;

    $allowed = glc_allowed_image_mimes();
    if ( ! in_array( $info['mime'], array_values( $allowed ), true ) ) return false;

    // Extension must agree with the bytes. wp_check_filetype_and_ext() also
    // rewrites a wrong-but-recoverable extension (photo.png holding JPEG data
    // becomes photo.jpg) so the stored file is never served under a lying name.
    $name    = sanitize_file_name( (string) ( $file['name'] ?? 'upload' ) );
    $checked = wp_check_filetype_and_ext( $file['tmp_name'], $name, $allowed );

    if ( empty( $checked['type'] ) || empty( $checked['ext'] ) ) return false;
    if ( $checked['type'] !== $info['mime'] )                    return false;

    if ( ! empty( $checked['proper_filename'] ) ) {
        $name = $checked['proper_filename'];
    }

    return [ 'name' => $name, 'type' => $checked['type'] ];
}

/**
 * Normalise PHP's multi-file $_FILES layout into a list of single-file arrays.
 *
 * PHP pivots multiple uploads into parallel arrays (name[], tmp_name[], …);
 * every consumer here wants them the other way round.
 *
 * @param string $field Form field name.
 * @param int    $limit Maximum files to return.
 */
function glc_normalize_file_array( $field, $limit ) {
    if ( empty( $_FILES[ $field ] ) || ! is_array( $_FILES[ $field ]['name'] ?? null ) ) return [];

    $out   = [];
    $count = min( $limit, count( $_FILES[ $field ]['name'] ) );
    for ( $i = 0; $i < $count; $i++ ) {
        if ( '' === ( $_FILES[ $field ]['name'][ $i ] ?? '' ) ) continue;
        $out[] = [
            'name'     => $_FILES[ $field ]['name'][ $i ],
            'type'     => $_FILES[ $field ]['type'][ $i ],
            'tmp_name' => $_FILES[ $field ]['tmp_name'][ $i ],
            'error'    => $_FILES[ $field ]['error'][ $i ],
            'size'     => $_FILES[ $field ]['size'][ $i ],
        ];
    }
    return $out;
}
