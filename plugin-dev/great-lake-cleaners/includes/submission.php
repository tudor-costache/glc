<?php
/**
 * Great Lake Cleaners — Community Submission System
 *
 * Registers the `glc_submission` CPT and provides the [glc_submit_form]
 * shortcode for public cleanup submissions.
 *
 * Fields captured (mirrors the Daily Log tracker):
 *   Outing:     date, waterway, site name, duration
 *   Garbage:    bags, weight_kg, notes
 *   Recycling:  cans, bottles
 *   Large items: tires_removed, bikes_removed, carts_removed
 *   Volunteers: count, hours
 *   Notable:    notable_finds, instagram_url
 *   Contact:    submitter_name, email, phone
 *   Consent:    photo_repost_ok
 *   Media:      up to 5 photos
 */

defined( 'ABSPATH' ) || exit;


// ── 1. Register post type ─────────────────────────────────────────────────────

add_action( 'init', 'glc_register_submission_post_type' );
function glc_register_submission_post_type() {
    register_post_type( 'glc_submission', [
        'labels' => [
            'name'               => 'Community Submissions',
            'singular_name'      => 'Submission',
            'edit_item'          => 'Review Submission',
            'all_items'          => 'All Submissions',
            'not_found'          => 'No submissions yet.',
            'not_found_in_trash' => 'No submissions in trash.',
        ],
        'public'              => false,   // not in nav/search/sitemaps
        'publicly_queryable'  => true,    // enables front-end permalinks
        'exclude_from_search' => true,    // keep out of search results
        'query_var'           => true,    // allow ?glc_submission= queries
        'rewrite'             => [ 'slug' => 'cleanup-submission', 'with_front' => false ],
        'show_ui'             => true,
        'show_in_menu'    => true,
        'menu_position'   => 6,
        'menu_icon'       => 'dashicons-upload',
        // 'author' carries the owning account (accounts.php). It is the single
        // source of truth for "whose cleanup is this" — an indexed column
        // WP_Query's `author` arg reads directly, and an admin dropdown for
        // fixing a mis-attribution by hand. A parallel glc_user_id meta key
        // would be a second thing to keep in sync, and this codebase has
        // already paid that tax with the glc_-prefixed dual keys.
        //
        // ⚠ 'author' also renders the core Author metabox on EVERY submission,
        // and wp_dropdown_users() has no entry for author 0 — so publishing an
        // anonymous submission from the review queue would stamp the reviewing
        // admin onto it. glc_normalize_submission_author() (accounts.php, on
        // wp_insert_post_data) resets any non-glc_cleaner author back to 0 on
        // every write path. Do not remove that filter while this stays here.
        'supports'        => [ 'title', 'editor', 'thumbnail', 'author' ],
        'capability_type' => 'post',
        // ⚠ NOT optional. wp_delete_user() with a null $reassign deletes every
        // post the account owns — here, published community cleanup records
        // that every public total on the site counts. Deleting an account must
        // orphan the cleanups (post_author back to 0), never destroy them.
        'delete_with_user' => false,
        'show_in_rest'    => false,
    ] );
}
add_action( 'glc_activate', 'glc_register_submission_post_type' );


// ── 2. Admin list columns ─────────────────────────────────────────────────────

add_filter( 'manage_glc_submission_posts_columns', function( $cols ) {
    return [
        'cb'            => $cols['cb'],
        'title'         => 'Submitter / Location',
        'glc_waterway'  => 'Waterway',
        'glc_corridor'  => 'Corridor',
        'glc_date'      => 'Date',
        'glc_email'     => 'Email',
        'glc_bags'      => 'Bags',
        'glc_recycling' => 'Recycling',
        'glc_consent'   => 'Photo OK',
        'glc_photos'    => 'Photos',
        'date'          => 'Submitted',
    ];
} );

add_action( 'manage_glc_submission_posts_custom_column', function( $col, $post_id ) {
    switch ( $col ) {
        case 'glc_waterway':
            echo esc_html( get_post_meta( $post_id, 'glc_waterway', true ) ?: '—' );
            break;
        case 'glc_corridor':
            echo esc_html( get_post_meta( $post_id, 'glc_corridor', true ) ?: '—' );
            break;
        case 'glc_date':
            echo esc_html( get_post_meta( $post_id, 'glc_cleanup_date', true ) ?: '—' );
            break;
        case 'glc_email':
            $e = get_post_meta( $post_id, 'glc_email', true );
            echo $e ? '<a href="mailto:' . esc_attr( $e ) . '">' . esc_html( $e ) . '</a>' : '—';
            break;
        case 'glc_bags':
            echo esc_html( get_post_meta( $post_id, 'glc_bags', true ) ?: '—' );
            break;
        case 'glc_recycling':
            $c = (int) get_post_meta( $post_id, 'glc_cans',    true );
            $b = (int) get_post_meta( $post_id, 'glc_bottles', true );
            echo ( $c + $b ) > 0 ? esc_html( $c . ' cans / ' . $b . ' bottles' ) : '—';
            break;
        case 'glc_consent':
            echo get_post_meta( $post_id, 'glc_photo_repost_ok', true ) === '1' ? '✅' : '—';
            break;
        case 'glc_photos':
            $ids = get_post_meta( $post_id, 'glc_photo_ids', true );
            echo $ids ? count( (array) $ids ) . ' photo(s)' : '—';
            break;
    }
}, 10, 2 );


// ── 3. Admin meta box ─────────────────────────────────────────────────────────

add_action( 'add_meta_boxes', function() {
    add_meta_box( 'glc_submission_details', 'Submission Details',
        'glc_submission_meta_box_cb', 'glc_submission', 'normal', 'high' );
} );

function glc_submission_meta_box_cb( $post ) {
    wp_nonce_field( 'glc_save_submission_meta', 'glc_submission_nonce' );

    $m = function( $key ) use ( $post ) {
        return esc_attr( get_post_meta( $post->ID, $key, true ) );
    };
    ?>
    <style>
    #glc_submission_details .inside { padding: 12px 0 4px; }
    .glc-sub-meta-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px 20px; }
    .glc-sub-meta-grid .glc-full { grid-column: 1 / -1; }
    .glc-sub-meta-section { grid-column: 1 / -1; margin: 8px 0 2px;
        font-size: 11px; font-weight: 600; text-transform: uppercase;
        letter-spacing: .06em; color: #1a4a6b; border-bottom: 1px solid #e0e0da; padding-bottom: 4px; }
    .glc-sub-meta-grid label { display: block; font-weight: 600; font-size: 12px; margin-bottom: 4px; color: #333; }
    .glc-sub-meta-grid input[type=text],
    .glc-sub-meta-grid input[type=number],
    .glc-sub-meta-grid input[type=date],
    .glc-sub-meta-grid input[type=email],
    .glc-sub-meta-grid input[type=url],
    .glc-sub-meta-grid textarea { width: 100%; box-sizing: border-box; }
    .glc-sub-meta-grid textarea { height: 64px; resize: vertical; }
    .glc-field-note { font-size: 11px; color: #666; margin-top: 3px; }
    </style>

    <div class="glc-sub-meta-grid">

        <!-- Contact -->
        <div class="glc-sub-meta-section">Contact</div>

        <div>
            <label for="gsm_name">Name</label>
            <input type="text" id="gsm_name" name="glc_submitter_name" maxlength="100"
                   value="<?php echo $m('glc_submitter_name'); ?>">
        </div>
        <div>
            <label for="gsm_email">Email</label>
            <input type="email" id="gsm_email" name="glc_email" maxlength="200"
                   value="<?php echo $m('glc_email'); ?>">
        </div>
        <div><!-- spacer --></div>

        <!-- Cleanup -->
        <div class="glc-sub-meta-section">Cleanup</div>

        <div>
            <label for="gsm_date">Cleanup Date</label>
            <input type="date" id="gsm_date" name="glc_cleanup_date"
                   value="<?php echo $m('glc_cleanup_date'); ?>">
        </div>
        <div>
            <label for="gsm_waterway">Waterway</label>
            <input type="text" id="gsm_waterway" name="glc_waterway" maxlength="200"
                   placeholder="e.g. Speed River"
                   value="<?php echo $m('glc_waterway'); ?>">
        </div>
        <div>
            <label for="gsm_corridor">Corridor</label>
            <input type="text" id="gsm_corridor" name="glc_corridor" maxlength="200"
                   placeholder="e.g. Speed River"
                   value="<?php echo $m('glc_corridor'); ?>">
            <p class="glc-field-note">Shown as badge on the post. Set after review.</p>
        </div>
        <div>
            <label for="gsm_duration">Duration (min)</label>
            <input type="number" id="gsm_duration" name="glc_duration_min" min="1" max="999" step="1"
                   value="<?php echo $m('glc_duration_min'); ?>">
        </div>
        <div>
            <label for="gsm_volunteers">Volunteers</label>
            <input type="number" id="gsm_volunteers" name="glc_volunteers" min="1" max="999" step="1"
                   value="<?php echo $m('glc_volunteers'); ?>">
        </div>
        <div>
            <label for="gsm_hours">Person-Hours</label>
            <input type="number" id="gsm_hours" name="glc_hours" min="0" step="0.25"
                   value="<?php echo $m('glc_hours'); ?>">
        </div>

        <!-- GPS -->
        <div>
            <label for="gsm_lat">GPS Latitude</label>
            <input type="number" id="gsm_lat" name="glc_gps_lat" step="0.000001" min="-90" max="90"
                   value="<?php echo $m('glc_gps_lat'); ?>">
        </div>
        <div>
            <label for="gsm_lon">GPS Longitude</label>
            <input type="number" id="gsm_lon" name="glc_gps_lon" step="0.000001" min="-180" max="180"
                   value="<?php echo $m('glc_gps_lon'); ?>">
        </div>
        <div><!-- spacer --></div>

        <!-- Debris -->
        <div class="glc-sub-meta-section">Debris</div>

        <div>
            <label for="gsm_bags">Bags</label>
            <input type="number" id="gsm_bags" name="glc_bags" min="0" max="999" step="1"
                   value="<?php echo $m('glc_bags'); ?>">
        </div>
        <div>
            <label for="gsm_weight">Weight (kg)</label>
            <input type="number" id="gsm_weight" name="glc_weight_kg" min="0" step="0.1"
                   value="<?php echo $m('glc_weight_kg'); ?>">
        </div>
        <div>
            <label for="gsm_notes">Garbage Notes</label>
            <input type="text" id="gsm_notes" name="glc_garbage_notes" maxlength="300"
                   value="<?php echo $m('glc_garbage_notes'); ?>">
        </div>
        <div>
            <label for="gsm_tires">Tires Removed</label>
            <input type="number" id="gsm_tires" name="glc_tires_removed" min="0" max="999" step="1"
                   value="<?php echo $m('glc_tires_removed'); ?>">
        </div>
        <div>
            <label for="gsm_bikes">Bikes Removed</label>
            <input type="number" id="gsm_bikes" name="glc_bikes_removed" min="0" max="999" step="1"
                   value="<?php echo $m('glc_bikes_removed'); ?>">
        </div>
        <div>
            <label for="gsm_carts">Shopping Carts Removed</label>
            <input type="number" id="gsm_carts" name="glc_carts_removed" min="0" max="999" step="1"
                   value="<?php echo $m('glc_carts_removed'); ?>">
        </div>

        <!-- Recycling -->
        <div class="glc-sub-meta-section">Recycling</div>

        <div>
            <label for="gsm_cans">Cans (#)</label>
            <input type="number" id="gsm_cans" name="glc_cans" min="0" max="9999" step="1"
                   value="<?php echo $m('glc_cans'); ?>">
        </div>
        <div>
            <label for="gsm_bottles">Bottles (#)</label>
            <input type="number" id="gsm_bottles" name="glc_bottles" min="0" max="9999" step="1"
                   value="<?php echo $m('glc_bottles'); ?>">
        </div>
        <div><!-- spacer --></div>

        <!-- Notes -->
        <div class="glc-sub-meta-section">Notes & Documentation</div>

        <div class="glc-full">
            <label for="gsm_notable">Notable Finds</label>
            <textarea id="gsm_notable" name="glc_notable_finds" maxlength="1000"><?php echo esc_textarea( get_post_meta( $post->ID, 'glc_notable_finds', true ) ); ?></textarea>
        </div>
        <div class="glc-full">
            <label for="gsm_wildlife">Wildlife Observed</label>
            <textarea id="gsm_wildlife" name="glc_wildlife_obs" maxlength="500"><?php echo esc_textarea( get_post_meta( $post->ID, 'glc_wildlife_obs', true ) ); ?></textarea>
        </div>
        <div class="glc-full">
            <label for="gsm_insta">Instagram URL</label>
            <input type="url" id="gsm_insta" name="glc_instagram_url" maxlength="500"
                   placeholder="https://www.instagram.com/p/..."
                   value="<?php echo $m('glc_instagram_url'); ?>">
        </div>

        <!-- Consent -->
        <div class="glc-sub-meta-section">Photo Consent</div>

        <div class="glc-full">
            <label style="display:flex;align-items:center;gap:8px;font-weight:normal;">
                <input type="checkbox" name="glc_photo_repost_ok" value="1"
                       <?php checked( get_post_meta( $post->ID, 'glc_photo_repost_ok', true ), '1' ); ?>>
                Submitter consented to photo reposting
            </label>
        </div>

    </div><!-- .glc-sub-meta-grid -->

    <?php
    // Submitted photos — read-only
    $photo_ids = get_post_meta( $post->ID, 'glc_photo_ids', true );
    if ( ! empty( $photo_ids ) ) {
        echo '<h4 style="margin-top:1.5em;">Submitted Photos</h4>';
        echo '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:.5em;">';
        foreach ( (array) $photo_ids as $att_id ) {
            $thumb = wp_get_attachment_image( $att_id, [140,140], false, ['style'=>'border-radius:6px;object-fit:cover;cursor:zoom-in;'] );
            $full  = wp_get_attachment_url( $att_id );
            if ( $thumb && $full ) {
                echo '<a href="' . esc_url( $full ) . '" class="glc-lb-trigger"'
                   . ' data-src="' . esc_attr( $full ) . '" onclick="glcLbOpen(this);return false;">'
                   . $thumb . '</a>';
            }
        }
        echo '</div>';
        ?>
        <div id="glc-lb" onclick="glcLbClose()"
             style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:999999;
                    align-items:center;justify-content:center;cursor:zoom-out;">
            <img id="glc-lb-img" src="" alt=""
                 style="max-width:92vw;max-height:92vh;border-radius:6px;box-shadow:0 4px 32px rgba(0,0,0,.6);">
        </div>
        <script>
        function glcLbOpen(a) {
            var lb = document.getElementById('glc-lb');
            document.getElementById('glc-lb-img').src = a.dataset.src;
            lb.style.display = 'flex';
        }
        function glcLbClose() {
            document.getElementById('glc-lb').style.display = 'none';
            document.getElementById('glc-lb-img').src = '';
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') glcLbClose();
        });
        </script>
        <?php
    }
}

// ── 3b. Save meta box ─────────────────────────────────────────────────────────

add_action( 'save_post_glc_submission', function( $post_id ) {
    if ( ! isset( $_POST['glc_submission_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['glc_submission_nonce'], 'glc_save_submission_meta' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $text_fields = [
        'glc_submitter_name' => 'sanitize_text_field',
        'glc_email'          => 'sanitize_email',
        'glc_cleanup_date'   => 'sanitize_text_field',
        'glc_waterway'       => 'sanitize_text_field',
        'glc_corridor'       => 'sanitize_text_field',
        'glc_garbage_notes'  => 'sanitize_text_field',
        'glc_notable_finds'  => 'sanitize_textarea_field',
        'glc_wildlife_obs'   => 'sanitize_textarea_field',
        'glc_instagram_url'  => 'esc_url_raw',
    ];
    $number_fields = [
        'glc_duration_min', 'glc_volunteers', 'glc_hours',
        'glc_bags', 'glc_weight_kg', 'glc_cans', 'glc_bottles',
        'glc_tires_removed', 'glc_bikes_removed', 'glc_carts_removed',
        'glc_gps_lat', 'glc_gps_lon',
    ];

    // wp_unslash() before every sanitizer: WordPress slash-escapes the
    // superglobals, so without it an apostrophe in a site name is stored with a
    // leading backslash, gaining another on every re-save of the same record.
    foreach ( $text_fields as $key => $fn ) {
        if ( isset( $_POST[ $key ] ) ) {
            update_post_meta( $post_id, $key, $fn( wp_unslash( $_POST[ $key ] ) ) );
        }
    }
    // glc_clean_float() rather than a bare (float) cast — "1e999" casts to INF,
    // which would silently poison every cumulative total it is added to.
    foreach ( $number_fields as $key ) {
        if ( isset( $_POST[ $key ] ) && $_POST[ $key ] !== '' ) {
            update_post_meta( $post_id, $key, glc_clean_float( $_POST[ $key ], -1000000, 1000000 ) );
        } elseif ( isset( $_POST[ $key ] ) ) {
            delete_post_meta( $post_id, $key );
        }
    }

    // Derived stats keys read by glc_get_impact_stats()
    $cans    = glc_clean_int( $_POST['glc_cans']    ?? 0, 100000 );
    $bottles = glc_clean_int( $_POST['glc_bottles'] ?? 0, 100000 );
    update_post_meta( $post_id, 'items_recycled', $cans + $bottles );
    if ( isset( $_POST['glc_weight_kg'] ) && $_POST['glc_weight_kg'] !== '' ) {
        update_post_meta( $post_id, 'weight_kg', glc_clean_float( $_POST['glc_weight_kg'], 0, 100000 ) );
    }
    // Shared wildlife key — queried by page-stats.php across both CPTs
    update_post_meta( $post_id, 'wildlife_obs', glc_clean_textarea( $_POST['glc_wildlife_obs'] ?? '', 500 ) );
    // Shared tires/bikes/carts keys — matching cleanup_event's field names, queried by
    // [glc_impact_highlights] and the /cleanups/ ?impact= filter across both CPTs
    update_post_meta( $post_id, 'tires_removed', glc_clean_int( $_POST['glc_tires_removed'] ?? 0, 999 ) );
    update_post_meta( $post_id, 'bikes_removed', glc_clean_int( $_POST['glc_bikes_removed'] ?? 0, 999 ) );
    update_post_meta( $post_id, 'carts_removed', glc_clean_int( $_POST['glc_carts_removed'] ?? 0, 999 ) );

    // Checkbox
    update_post_meta( $post_id, 'glc_photo_repost_ok', isset( $_POST['glc_photo_repost_ok'] ) ? '1' : '0' );
} );


// ── 4. Admin notice ───────────────────────────────────────────────────────────

add_action( 'admin_notices', function() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'glc_submission' ) return;
    $pending = wp_count_posts( 'glc_submission' )->pending ?? 0;
    if ( $pending > 0 ) {
        printf(
            '<div class="notice notice-info"><p><strong>%d pending submission%s</strong> awaiting review. '
            . 'Publish to count in stats, or trash to remove.</p></div>',
            (int) $pending, $pending === 1 ? '' : 's'
        );
    }
} );


// ── 5. [glc_submit_form] shortcode ───────────────────────────────────────────

add_shortcode( 'glc_submit_form', 'glc_render_submit_form' );

function glc_render_submit_form() {
    $result = glc_maybe_handle_submission();
    ob_start();

    if ( $result === 'success' ) {
        // Build receipt from submitted POST data (still available after processing).
        $r_bags     = glc_clean_int(   $_POST['glc_bags']          ?? 0, 999 );
        $r_weight   = glc_clean_float( $_POST['glc_weight_kg']     ?? 0, 0, 100000 );
        $r_waterway = glc_clean_text(  $_POST['glc_waterway']      ?? '', 200 );
        $r_date     = glc_clean_text(  $_POST['glc_cleanup_date']  ?? '', 10 );
        $r_location = $r_waterway;
        $r_date_fmt = ( $r_date && strtotime( $r_date ) )
            ? date_i18n( 'F j', strtotime( $r_date ) )
            : '';

        $receipt_parts = [];
        if ( $r_bags )     $receipt_parts[] = $r_bags . ' bag' . ( $r_bags !== 1 ? 's' : '' );
        if ( $r_weight )   $receipt_parts[] = number_format( $r_weight, 1 ) . ' kg';
        if ( $r_location ) $receipt_parts[] = $r_location;
        if ( $r_date_fmt ) $receipt_parts[] = $r_date_fmt;
        ?>
        <div class="glc-submit-success">
            <?php if ( ! empty( $receipt_parts ) ) : ?>
            <p class="glc-submit-receipt">
                <?php echo esc_html(
                    /* translators: %s: comma-separated receipt summary e.g. "3 bags, 6.0 kg, Parkwood Gardens, April 3" */
                    sprintf( __( 'You submitted: %s', 'great-lake-cleaners' ),
                        implode( ', ', $receipt_parts ) )
                ); ?>
            </p>
            <?php endif; ?>
            <img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/stylized-thankyou.jpg' ); ?>"
                 alt="<?php esc_attr_e( 'A heron and kayaker on a clean river, with a bag of collected cans on a dock', 'great-lake-cleaners' ); ?>"
                 class="glc-submit-success-img">
            <h2><?php esc_html_e( 'Cleanup submitted — thank you!', 'great-lake-cleaners' ); ?></h2>
            <p><?php esc_html_e( "We'll review it and add it to the map. Every cleanup counts toward protecting the watershed.", 'great-lake-cleaners' ); ?></p>
        </div>
    <?php return ob_get_clean(); }

    $error       = '';
    $error_field = '';
    if ( is_array( $result ) ) {
        $error       = $result['message'];
        $error_field = $result['field'];
    } elseif ( is_string( $result ) && $result !== 'success' ) {
        $error = $result;
    }

    // A signed-in cleaner gets their account's name and email prefilled and
    // locked. The lock is a UI convenience only — glc_maybe_handle_submission()
    // reads both values off the user record, never off the POST body, so a
    // scripted post can't credit someone else's account with a third party's
    // name attached.
    $acct = glc_current_cleaner();

    // wp_unslash() so a value echoed back after a validation error shows what
    // the visitor typed, not an apostrophe that has sprouted a backslash.
    $v = function( $key, $default = '' ) {
        return esc_attr( wp_unslash( (string) ( $_POST[ $key ] ?? $default ) ) );
    };

    // $fa(id) → aria-invalid + aria-describedby attrs when that field errored
    // $fe(id) → the error <span> to render after the input
    $fa = function( $field_id ) use ( $error_field ) {
        return $error_field === $field_id
            ? ' aria-invalid="true" aria-describedby="glc-err-' . esc_attr( $field_id ) . '"'
            : '';
    };
    $fe = function( $field_id ) use ( $error_field, $error ) {
        return $error_field === $field_id
            ? '<span id="glc-err-' . esc_attr( $field_id ) . '" class="glc-field-error" role="alert">' . esc_html( $error ) . '</span>'
            : '';
    };

    ?>

    <div class="glc-submit-wrap">
        <?php if ( $error && ! $error_field ) : ?>
        <div class="glc-form-error-banner" role="alert"><?php echo esc_html( $error ); ?></div>
        <?php endif; ?>

        <form id="glc-submit-form" class="glc-submit-form" method="post" enctype="multipart/form-data" novalidate>
            <?php wp_nonce_field( 'glc_submit_cleanup', 'glc_submit_nonce' ); ?>

            <!-- 1. About You -->
            <fieldset class="glc-form-section">
                <legend class="glc-form-legend">
                    <span class="glc-form-legend-num">1</span>
                    <?php esc_html_e( 'About You', 'great-lake-cleaners' ); ?>
                </legend>
                <?php if ( $acct ) : ?>
                <p class="glc-acct-signedin-note">
                    <?php
                    printf(
                        /* translators: %s: the signed-in cleaner's display name */
                        esc_html__( 'Signed in as %s — this cleanup will be added to your profile once it is reviewed.', 'great-lake-cleaners' ),
                        '<strong>' . esc_html( $acct->display_name ) . '</strong>'
                    );
                    ?>
                </p>
                <?php endif; ?>

                <div class="glc-field-row">
                    <div class="glc-field glc-field--half">
                        <label for="glc_submitter_name"><span class="glc-label-text"><?php esc_html_e( 'Your Name', 'great-lake-cleaners' ); ?><span class="glc-required" aria-label="required">*</span></span></label>
                        <input type="text" id="glc_submitter_name" name="glc_submitter_name" required maxlength="100" autocomplete="name"<?php echo $acct ? ' readonly' : ''; ?> value="<?php echo $acct ? esc_attr( $acct->display_name ) : $v('glc_submitter_name'); ?>"<?php echo $fa('glc_submitter_name'); ?>>
                        <?php echo $fe('glc_submitter_name'); ?>
                    </div>
                    <div class="glc-field glc-field--half">
                        <label for="glc_email"><span class="glc-label-text"><?php esc_html_e( 'Email', 'great-lake-cleaners' ); ?><span class="glc-tooltip" aria-label="<?php esc_attr_e( 'Optional — so we can say thanks', 'great-lake-cleaners' ); ?>" tabindex="0">?<span class="glc-tooltip-text"><?php esc_html_e( 'Optional — so we can say thanks', 'great-lake-cleaners' ); ?></span></span></span></label>
                        <input type="email" id="glc_email" name="glc_email" maxlength="200" autocomplete="email"<?php echo $acct ? ' readonly' : ''; ?> value="<?php echo $acct ? esc_attr( $acct->user_email ) : $v('glc_email'); ?>">
                    </div>

                </div>

                <?php if ( $acct ) : ?>
                <div class="glc-field glc-acct-credit">
                    <label class="glc-checkbox-label" for="glc_post_anonymously">
                        <input type="checkbox" id="glc_post_anonymously" name="glc_post_anonymously" value="1"<?php checked( ! empty( $_POST['glc_post_anonymously'] ) ); ?>>
                        <span><?php esc_html_e( 'Post this one without credit', 'great-lake-cleaners' ); ?></span>
                    </label>
                    <p class="glc-field-note">
                        <?php esc_html_e( 'Leave unticked and the cleanup appears on your public profile. Tick it and we still get your name for our records, but the public card reads "Community member" and the cleanup stays off your profile.', 'great-lake-cleaners' ); ?>
                    </p>
                </div>
                <?php elseif ( get_page_by_path( 'account' ) ) : ?>
                <?php // Guarded on the page existing, like every other account
                      // entry point — until it is created the feature is
                      // invisible rather than a dead link. ?>
                <p class="glc-field-note glc-acct-offer">
                    <?php
                    printf(
                        /* translators: %s: link to the account page */
                        esc_html__( 'Optional: %s to collect your cleanups on a public profile. Submitting without one works exactly the same.', 'great-lake-cleaners' ),
                        '<a href="' . esc_url( glc_account_url() ) . '">' . esc_html__( 'sign in or make an account', 'great-lake-cleaners' ) . '</a>'
                    );
                    ?>
                </p>
                <?php endif; ?>
            </fieldset>

            <!-- 2. The Cleanup -->
            <fieldset class="glc-form-section">
                <legend class="glc-form-legend">
                    <span class="glc-form-legend-num">2</span>
                    <?php esc_html_e( 'The Cleanup', 'great-lake-cleaners' ); ?>
                </legend>
                <div class="glc-field-row">
                    <div class="glc-field glc-field--half">
                        <label for="glc_cleanup_date"><span class="glc-label-text"><?php esc_html_e( 'Date', 'great-lake-cleaners' ); ?><span class="glc-required" aria-label="required">*</span></span></label>
                        <input type="date" id="glc_cleanup_date" name="glc_cleanup_date" required max="<?php echo esc_attr( date('Y-m-d') ); ?>" value="<?php echo $v('glc_cleanup_date'); ?>"<?php echo $fa('glc_cleanup_date'); ?>>
                        <?php echo $fe('glc_cleanup_date'); ?>
                    </div>
                    <div class="glc-field glc-field--half">
                        <label for="glc_duration_min"><?php esc_html_e( 'Duration (minutes)', 'great-lake-cleaners' ); ?></label>
                        <input type="number" id="glc_duration_min" name="glc_duration_min" min="1" max="999" step="1" placeholder="e.g. 60" value="<?php echo $v('glc_duration_min'); ?>">
                    </div>
                    <div class="glc-field glc-field--half">
                        <label for="glc_volunteers"><span class="glc-label-text"><?php esc_html_e( 'Number of People', 'great-lake-cleaners' ); ?><span class="glc-tooltip" aria-label="<?php esc_attr_e( 'Used to calculate volunteer hours', 'great-lake-cleaners' ); ?>" tabindex="0">?<span class="glc-tooltip-text"><?php esc_html_e( 'Used to calculate volunteer hours', 'great-lake-cleaners' ); ?></span></span></span></label>
                        <input type="number" id="glc_volunteers" name="glc_volunteers" min="1" max="999" step="1" value="<?php echo $v('glc_volunteers','1'); ?>">
                    </div>
                    <div class="glc-field glc-field--half">
                        <label for="glc_waterway"><span class="glc-label-text"><?php esc_html_e( 'Waterway', 'great-lake-cleaners' ); ?><span class="glc-tooltip" aria-label="<?php esc_attr_e( 'e.g. Speed River, Grand River — or a nearby location name if unsure', 'great-lake-cleaners' ); ?>" tabindex="0">?<span class="glc-tooltip-text"><?php esc_html_e( 'e.g. Speed River, Grand River — or a nearby location name if unsure', 'great-lake-cleaners' ); ?></span></span></span></label>
                        <input type="text" id="glc_waterway" name="glc_waterway" maxlength="200" placeholder="<?php esc_attr_e( 'e.g. Speed River, Grand River', 'great-lake-cleaners' ); ?>" value="<?php echo $v('glc_waterway'); ?>">
                    </div>
                    <div class="glc-field glc-field--full glc-field--geo">
                        <label><span class="glc-label-text"><?php esc_html_e( 'GPS Location', 'great-lake-cleaners' ); ?><span class="glc-tooltip" aria-label="<?php esc_attr_e( 'Optional — helps us place your cleanup on the map accurately', 'great-lake-cleaners' ); ?>" tabindex="0">?<span class="glc-tooltip-text"><?php esc_html_e( 'Optional — helps us place your cleanup on the map accurately', 'great-lake-cleaners' ); ?></span></span></span></label>
                        <div class="glc-geo-row">
                            <input type="number" id="glc_gps_lat" name="glc_gps_lat" step="0.000001" min="-90" max="90" placeholder="<?php esc_attr_e( 'Latitude', 'great-lake-cleaners' ); ?>" value="<?php echo $v('glc_gps_lat'); ?>">
                            <input type="number" id="glc_gps_lon" name="glc_gps_lon" step="0.000001" min="-180" max="180" placeholder="<?php esc_attr_e( 'Longitude', 'great-lake-cleaners' ); ?>" value="<?php echo $v('glc_gps_lon'); ?>">
                            <button type="button" class="glc-geo-btn" onclick="glcDetectLocation(this)" aria-label="<?php esc_attr_e( 'Detect my location', 'great-lake-cleaners' ); ?>">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3"/><circle cx="12" cy="12" r="9" stroke-dasharray="2 3"/></svg>
                                <?php esc_html_e( 'Use my location', 'great-lake-cleaners' ); ?>
                            </button>
                        </div>
                        <p class="glc-field-note"><?php esc_html_e( 'From Google Maps on your phone: tap the blue dot → coordinates appear at top. Or tap the button to auto-detect.', 'great-lake-cleaners' ); ?></p>
                    </div>
                </div>
            </fieldset>

            <!-- 3. What You Collected -->
            <fieldset class="glc-form-section">
                <legend class="glc-form-legend">
                    <span class="glc-form-legend-num">3</span>
                    <?php esc_html_e( 'What You Collected', 'great-lake-cleaners' ); ?>
                </legend>

                <div class="glc-sub-group">
                    <p class="glc-section-subhead"><?php esc_html_e( 'Garbage', 'great-lake-cleaners' ); ?></p>
                    <div class="glc-field-row glc-field-row--3col">
                        <div class="glc-field glc-field--third">
                            <label for="glc_bags"><?php esc_html_e( 'Bags (#)', 'great-lake-cleaners' ); ?></label>
                            <input type="number" id="glc_bags" name="glc_bags" min="0" max="999" step="1" placeholder="0" value="<?php echo $v('glc_bags'); ?>">
                        </div>
                        <div class="glc-field glc-field--third">
                            <label for="glc_weight_kg"><?php esc_html_e( 'Approx. Weight (kg)', 'great-lake-cleaners' ); ?></label>
                            <input type="number" id="glc_weight_kg" name="glc_weight_kg" min="0" max="9999" step="0.1" placeholder="0.0" value="<?php echo $v('glc_weight_kg'); ?>">
                        </div>
                        <div class="glc-field glc-field--third">
                            <label for="glc_garbage_notes"><span class="glc-label-text"><?php esc_html_e( 'Garbage Notes', 'great-lake-cleaners' ); ?><span class="glc-tooltip" aria-label="<?php esc_attr_e( 'Types, composition, etc.', 'great-lake-cleaners' ); ?>" tabindex="0">?<span class="glc-tooltip-text"><?php esc_html_e( 'Types, composition, etc.', 'great-lake-cleaners' ); ?></span></span></span></label>
                            <input type="text" id="glc_garbage_notes" name="glc_garbage_notes" maxlength="300" value="<?php echo $v('glc_garbage_notes'); ?>">
                        </div>
                    </div>
                </div>

                <div class="glc-sub-group">
                    <p class="glc-section-subhead"><?php esc_html_e( 'Recycling', 'great-lake-cleaners' ); ?></p>
                    <div class="glc-field-row">
                        <div class="glc-field glc-field--half">
                            <label for="glc_cans"><?php esc_html_e( 'Cans (#)', 'great-lake-cleaners' ); ?></label>
                            <input type="number" id="glc_cans" name="glc_cans" min="0" max="9999" step="1" placeholder="0" value="<?php echo $v('glc_cans'); ?>">
                        </div>
                        <div class="glc-field glc-field--half">
                            <label for="glc_bottles"><?php esc_html_e( 'Bottles (#)', 'great-lake-cleaners' ); ?></label>
                            <input type="number" id="glc_bottles" name="glc_bottles" min="0" max="9999" step="1" placeholder="0" value="<?php echo $v('glc_bottles'); ?>">
                        </div>
                    </div>
                </div>

                <div class="glc-sub-group glc-sub-group--last">
                    <p class="glc-section-subhead"><?php esc_html_e( 'Large Items', 'great-lake-cleaners' ); ?></p>
                    <div class="glc-field-row glc-field-row--3col">
                        <div class="glc-field glc-field--third">
                            <label for="glc_tires_removed"><?php esc_html_e( 'Tires Removed (#)', 'great-lake-cleaners' ); ?></label>
                            <input type="number" id="glc_tires_removed" name="glc_tires_removed" min="0" max="999" step="1" placeholder="0" value="<?php echo $v('glc_tires_removed'); ?>">
                        </div>
                        <div class="glc-field glc-field--third">
                            <label for="glc_bikes_removed"><?php esc_html_e( 'Bikes Removed (#)', 'great-lake-cleaners' ); ?></label>
                            <input type="number" id="glc_bikes_removed" name="glc_bikes_removed" min="0" max="999" step="1" placeholder="0" value="<?php echo $v('glc_bikes_removed'); ?>">
                        </div>
                        <div class="glc-field glc-field--third">
                            <label for="glc_carts_removed"><?php esc_html_e( 'Shopping Carts Removed (#)', 'great-lake-cleaners' ); ?></label>
                            <input type="number" id="glc_carts_removed" name="glc_carts_removed" min="0" max="999" step="1" placeholder="0" value="<?php echo $v('glc_carts_removed'); ?>">
                        </div>
                    </div>
                </div>

            </fieldset>

            <!-- 4. Notable Finds & Field Log -->
            <fieldset class="glc-form-section">
                <legend class="glc-form-legend">
                    <span class="glc-form-legend-num">4</span>
                    <?php esc_html_e( 'Notable Finds & Field Log', 'great-lake-cleaners' ); ?>
                </legend>
                <div class="glc-field-row">
                    <div class="glc-field">
                        <label for="glc_notable_finds"><?php esc_html_e( 'Notable or Unusual Finds', 'great-lake-cleaners' ); ?><span class="glc-field-note"><?php esc_html_e( 'Large items, anything out of the ordinary', 'great-lake-cleaners' ); ?></span></label>
                        <textarea id="glc_notable_finds" name="glc_notable_finds" rows="3" maxlength="1000"><?php echo esc_textarea( wp_unslash( (string) ( $_POST['glc_notable_finds'] ?? '' ) ) ); ?></textarea>
                    </div>
                    <div class="glc-field">
                        <label for="glc_wildlife_obs"><?php esc_html_e( 'Wildlife Observed', 'great-lake-cleaners' ); ?><span class="glc-field-note"><?php esc_html_e( 'Birds, turtles, fish, mammals — anything you spotted', 'great-lake-cleaners' ); ?></span></label>
                        <textarea id="glc_wildlife_obs" name="glc_wildlife_obs" rows="3" maxlength="500"><?php echo esc_textarea( wp_unslash( (string) ( $_POST['glc_wildlife_obs'] ?? '' ) ) ); ?></textarea>
                    </div>
                    <div class="glc-field">
                        <label for="glc_instagram_url"><?php esc_html_e( 'Instagram Post URL', 'great-lake-cleaners' ); ?><span class="glc-field-note"><?php esc_html_e( "If you posted about it — we'll link it from your cleanup entry", 'great-lake-cleaners' ); ?></span></label>
                        <input type="url" id="glc_instagram_url" name="glc_instagram_url" maxlength="500" placeholder="https://www.instagram.com/p/..." value="<?php echo $v('glc_instagram_url'); ?>">
                    </div>
                </div>
            </fieldset>

            <!-- 5. Photos -->
            <fieldset class="glc-form-section">
                <legend class="glc-form-legend">
                    <span class="glc-form-legend-num">5</span>
                    <?php esc_html_e( 'Photos', 'great-lake-cleaners' ); ?>
                </legend>
                <div class="glc-field-row">
                    <div class="glc-field">
                        <label for="glc_photos"><?php esc_html_e( 'Upload Photos', 'great-lake-cleaners' ); ?><span class="glc-field-note"><?php esc_html_e( 'Optional — up to 5 images (JPG, PNG, WebP, max 8 MB each)', 'great-lake-cleaners' ); ?></span></label>
                        <input type="file" id="glc_photos" name="glc_photos[]" accept="image/jpeg,image/png,image/webp" multiple>
                    </div>
                    <div class="glc-field glc-consent-field">
                        <label class="glc-checkbox-label">
                            <input type="checkbox" name="glc_photo_repost_ok" value="1" <?php checked( isset( $_POST['glc_photo_repost_ok'] ) ); ?>>
                            <span><?php esc_html_e( 'Great Lake Cleaners may repost and feature these photos on Instagram and the website', 'great-lake-cleaners' ); ?></span>
                        </label>
                        <p class="glc-field-note" style="margin-top:.4rem;padding-left:1.6rem;"><?php esc_html_e( "We'll credit you by name. Unchecked = photos for internal records only.", 'great-lake-cleaners' ); ?></p>
                    </div>
                </div>
            </fieldset>

            <!-- Honeypot — hidden from real users, bots fill it in -->
            <div class="glc-hp-field" aria-hidden="true">
                <label for="glc_url">Website</label>
                <input type="text" id="glc_url" name="glc_url" tabindex="-1" autocomplete="off">
            </div>

            <!-- Submit -->
            <div class="glc-form-submit-row">
                <button type="submit" name="glc_submit_cleanup" class="glc-btn-primary glc-btn-submit">
                    <?php esc_html_e( 'Submit Cleanup', 'great-lake-cleaners' ); ?>
                </button>
                <p class="glc-form-privacy-note">
                    <?php
                    $privacy_url = esc_url( home_url( '/privacy-policy/' ) );
                    printf(
                        /* translators: %s: privacy policy URL */
                        wp_kses(
                            __( 'Submissions are reviewed before appearing publicly. Your contact information is never shared. See our <a href="%s">Privacy Policy</a>.', 'great-lake-cleaners' ),
                            [ 'a' => [ 'href' => [] ] ]
                        ),
                        $privacy_url
                    );
                    ?>
                </p>
            </div>

        </form>
    </div>
    <script>
    function glcDetectLocation(btn) {
        if (!navigator.geolocation) {
            alert('<?php echo esc_js( __( 'Geolocation is not supported by your browser.', 'great-lake-cleaners' ) ); ?>');
            return;
        }
        btn.disabled = true;
        btn.textContent = '<?php echo esc_js( __( 'Detecting…', 'great-lake-cleaners' ) ); ?>';
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                document.getElementById('glc_gps_lat').value = pos.coords.latitude.toFixed(6);
                document.getElementById('glc_gps_lon').value = pos.coords.longitude.toFixed(6);
                btn.disabled = false;
                btn.innerHTML = '\u2713 <?php echo esc_js( __( 'Location set', 'great-lake-cleaners' ) ); ?>';
            },
            function() {
                btn.disabled = false;
                btn.innerHTML = '<?php echo esc_js( __( 'Use my location', 'great-lake-cleaners' ) ); ?>';
                alert('<?php echo esc_js( __( 'Could not detect location. Please enter coordinates manually.', 'great-lake-cleaners' ) ); ?>');
            }
        );
    }
    </script>
    <?php
    return ob_get_clean();
}


// ── 6. Handle form POST ───────────────────────────────────────────────────────

function glc_maybe_handle_submission() {
    if ( ! isset( $_POST['glc_submit_cleanup'] ) ) return null;

    if ( ! isset( $_POST['glc_submit_nonce'] )
        || ! wp_verify_nonce( $_POST['glc_submit_nonce'], 'glc_submit_cleanup' ) ) {
        return 'Security check failed. Please refresh and try again.';
    }

    // Honeypot — bots fill in fields humans never see
    if ( ! empty( $_POST['glc_url'] ) ) return null;

    // Rate limit — 5 per IP per 10 min, plus the site-wide hourly ceiling in
    // security.php that a rotating-IP script can't step around. Counters
    // increment only after the post is created, so a visitor who mistypes their
    // date doesn't burn a slot.
    $rate = glc_rate_limit_check( 'sub', 5 );
    if ( true !== $rate ) return $rate;

    // Account attribution. Three cases, and the only difference between them is
    // one integer on the post row:
    //   signed in, credit (the default) -> post_author = the account
    //   signed in, "no credit" ticked   -> post_author = 0, glc_credit_anonymous = 1
    //   signed out                      -> post_author = 0, exactly as before
    //
    // wp_insert_post() defaults post_author to the *current user* when the key
    // is absent, so it is passed explicitly in every case -- otherwise a
    // signed-in visitor who asked not to be credited would be credited anyway.
    $acct      = glc_current_cleaner();
    $post_anon = $acct && ! empty( $_POST['glc_post_anonymously'] );
    $author_id = ( $acct && ! $post_anon ) ? (int) $acct->ID : 0;

    $name     = glc_clean_text( $_POST['glc_submitter_name'] ?? '', 100 );
    $date     = glc_clean_text( $_POST['glc_cleanup_date']   ?? '', 10 );
    $waterway = glc_clean_text( $_POST['glc_waterway']       ?? '', 200 );

    // The form renders name and email read-only for a signed-in cleaner, but
    // read-only is markup, not a guard -- both come off the user record here so
    // a scripted post can't file a third party's name against this account.
    if ( $acct ) {
        $name = glc_clean_text( $acct->display_name, 100 );
    }

    if ( ! $name )     return [ 'field' => 'glc_submitter_name', 'message' => 'Please enter your name.' ];
    if ( ! $date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) return [ 'field' => 'glc_cleanup_date', 'message' => 'Please enter a valid cleanup date.' ];
    if ( strtotime( $date ) > time() ) return [ 'field' => 'glc_cleanup_date', 'message' => 'Cleanup date cannot be in the future.' ];

    // Every value below is length- or range-bounded server side. The form's
    // maxlength/min/max attributes are a UI courtesy only — a script POSTs
    // whatever it likes, and these values land in post meta and in the email
    // body sent to the org.
    $email         = $acct
        ? sanitize_email( $acct->user_email )
        : sanitize_email( glc_clean_text( $_POST['glc_email'] ?? '', 200 ) );
    $phone         = glc_clean_text(     $_POST['glc_phone']         ?? '', 40 );
    $site_name     = glc_clean_text(     $_POST['glc_site_name']     ?? '', 200 );
    $garbage_notes = glc_clean_text(     $_POST['glc_garbage_notes'] ?? '', 300 );
    $notable       = glc_clean_textarea( $_POST['glc_notable_finds'] ?? '', 1000 );
    $wildlife_obs  = glc_clean_textarea( $_POST['glc_wildlife_obs']  ?? '', 500 );
    $duration_min  = glc_clean_int(      $_POST['glc_duration_min']  ?? 0, 1440 );   // a day
    $bags          = glc_clean_int(      $_POST['glc_bags']          ?? 0, 999 );
    $weight_kg     = glc_clean_float(    $_POST['glc_weight_kg']     ?? 0, 0, 100000 );
    $cans          = glc_clean_int(      $_POST['glc_cans']          ?? 0, 100000 );
    $bottles       = glc_clean_int(      $_POST['glc_bottles']       ?? 0, 100000 );
    $tires_removed = glc_clean_int(      $_POST['glc_tires_removed'] ?? 0, 999 );
    $bikes_removed = glc_clean_int(      $_POST['glc_bikes_removed'] ?? 0, 999 );
    $carts_removed = glc_clean_int(      $_POST['glc_carts_removed'] ?? 0, 999 );
    $volunteers    = glc_clean_int(      $_POST['glc_volunteers']    ?? 1, 500, 1 );
    $hours_input   = glc_clean_float(    $_POST['glc_hours']         ?? 0, 0, 10000 );
    $repost_ok     = isset( $_POST['glc_photo_repost_ok'] ) ? '1' : '0';
    $gps_lat       = glc_clean_coord( $_POST['glc_gps_lat'] ?? '',  -90,  90 );
    $gps_lon       = glc_clean_coord( $_POST['glc_gps_lon'] ?? '', -180, 180 );
    // Protocols restricted to http/https — esc_url_raw's default list also
    // permits mailto:, tel: and ftp:, none of which belong in a post link.
    $instagram_url = esc_url_raw( glc_clean_text( $_POST['glc_instagram_url'] ?? '', 500 ), [ 'http', 'https' ] );

    // Person-hours: prefer duration × volunteers if entered, else use manual hours
    $person_hours = $duration_min > 0
        ? round( ( $duration_min / 60 ) * $volunteers, 2 )
        : $hours_input;

    $post_id = wp_insert_post( [
        'post_type'    => 'glc_submission',
        'post_status'  => 'pending',
        'post_title'   => sprintf( '%s (%s)', $waterway ?: 'Waterway cleanup', $date ),
        'post_content' => $notable,
        'post_author'  => $author_id,
    ] );

    if ( is_wp_error( $post_id ) ) return 'Could not save your submission. Please try again.';

    // wp_insert_post() treats post_author => 0 as "not supplied" and substitutes
    // the current user, so a signed-in cleaner who ticked "post without credit"
    // -- or an admin testing this form -- would be credited anyway. Write the
    // column directly to actually mean $author_id. (glc_normalize_submission_author()
    // catches the admin case too, but not a cleaner opting out: the substituted
    // ID is the cleaner's own and passes its role check.)
    if ( function_exists( 'glc_set_post_author' ) ) {
        glc_set_post_author( $post_id, $author_id );
    }

    $meta = [
        'glc_submitter_name'  => $name,
        'glc_email'           => $email,
        'glc_phone'           => $phone,
        'glc_cleanup_date'    => $date,
        'glc_waterway'        => $waterway,
        'glc_site_name'       => $site_name,
        'glc_duration_min'    => $duration_min,
        'glc_bags'            => $bags,
        'glc_weight_kg'       => $weight_kg,
        'glc_garbage_notes'   => $garbage_notes,
        'glc_cans'            => $cans,
        'glc_bottles'         => $bottles,
        'glc_tires_removed'   => $tires_removed,
        'glc_bikes_removed'   => $bikes_removed,
        'glc_carts_removed'   => $carts_removed,
        // Keys matching cleanup_event CPT — counted by glc_get_impact_stats()
        // and [glc_impact_highlights] without special-casing the post type
        'items_recycled'      => $cans + $bottles,
        'weight_kg'           => $weight_kg,
        'tires_removed'       => $tires_removed,
        'bikes_removed'       => $bikes_removed,
        'carts_removed'       => $carts_removed,
        'glc_volunteers'      => $volunteers,
        'glc_hours'           => $person_hours,
        'glc_notable_finds'   => $notable,
        'glc_wildlife_obs'    => $wildlife_obs,
        'wildlife_obs'        => $wildlife_obs,  // shared key — queried by page-stats.php across both CPTs
        'glc_instagram_url'   => $instagram_url,
        'glc_photo_repost_ok' => $repost_ok,
        'glc_gps_lat'         => $gps_lat,
        'glc_gps_lon'         => $gps_lon,
        // '1' only when a signed-in cleaner opted out of credit. The public
        // byline then reads "Community member" (glc_submission_credit()) even
        // though glc_submitter_name is still on file for the org.
        'glc_credit_anonymous' => $post_anon ? '1' : '0',
    ];
    foreach ( $meta as $key => $val ) update_post_meta( $post_id, $key, $val );

    // Photo uploads.
    //
    // This is the one place an anonymous visitor writes a file into the media
    // library, so the type check has to be on the bytes, not on the client's
    // claimed Content-Type — that header is attacker-chosen and proves nothing.
    // glc_validate_image_upload() sniffs the file and reconciles its extension;
    // the `mimes` whitelist then stops wp_handle_upload() falling back to
    // get_allowed_mime_types(), which would accept PDFs, ZIPs and MP4s too.
    $photo_ids = [];
    $photos    = glc_normalize_file_array( 'glc_photos', 5 );
    if ( $photos ) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        $max = 8 * 1024 * 1024;
        foreach ( $photos as $photo ) {
            $valid = glc_validate_image_upload( $photo, $max );
            if ( ! $valid ) continue;

            $photo['name'] = $valid['name'];
            $photo['type'] = $valid['type'];

            $uploaded = wp_handle_upload( $photo, [
                'test_form' => false,
                'mimes'     => glc_allowed_image_mimes(),
            ] );
            if ( isset( $uploaded['file'] ) ) {
                $att_id = wp_insert_attachment( [
                    'post_mime_type' => $uploaded['type'],
                    'post_title'     => $valid['name'],
                    'post_status'    => 'inherit',
                    'post_parent'    => $post_id,
                ], $uploaded['file'], $post_id );
                if ( ! is_wp_error( $att_id ) ) {
                    wp_update_attachment_metadata( $att_id, wp_generate_attachment_metadata( $att_id, $uploaded['file'] ) );
                    $photo_ids[] = $att_id;
                    if ( ! get_post_thumbnail_id( $post_id ) ) set_post_thumbnail( $post_id, $att_id );
                }
            }
        }
        if ( $photo_ids ) update_post_meta( $post_id, 'glc_photo_ids', $photo_ids );
    }

    // Admin notification — rate limit counters bump only here, after all
    // validation has passed and the submission actually exists.
    glc_rate_limit_hit( 'sub' );
    wp_mail(
        get_option( 'admin_email' ),
        sprintf( '[Great Lake Cleaners] New submission: %s on %s', $waterway, $date ),
        sprintf(
            "A new cleanup submission has arrived.\n\nSubmitter:  %s\nEmail:      %s\n"
            . "Account:    %s\n"
            . "Waterway:   %s\nDate:       %s\nLocation:   %s\nDuration:   %d min\n"
            . "Bags:       %d\nWeight:     %.1f kg\nCans: %d  Bottles: %d\n"
            . "Tires: %d  Bikes: %d  Shopping carts: %d\n"
            . "Volunteers: %d  Person-hours: %.2f\nGPS:        %s, %s\nPhoto consent: %s\n"
            . "Wildlife:   %s\n\nReview:\n%s",
            $name, $email ?: '(none)',
            $acct
                ? ( $post_anon
                    ? $acct->display_name . ' (asked not to be credited publicly)'
                    : $acct->display_name )
                : '(no account -- anonymous submission)',
            $waterway, $date, $site_name ?: '(not given)', $duration_min,
            $bags, $weight_kg, $cans, $bottles,
            $tires_removed, $bikes_removed, $carts_removed,
            $volunteers, $person_hours,
            $gps_lat !== '' ? $gps_lat : 'n/a', $gps_lon !== '' ? $gps_lon : 'n/a',
            $repost_ok === '1' ? 'Yes — may repost' : 'No',
            $wildlife_obs ?: '(none)',
            admin_url( 'post.php?post=' . $post_id . '&action=edit' )
        )
    );

    return 'success';
}
