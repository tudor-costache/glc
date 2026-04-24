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
        'supports'        => [ 'title', 'editor', 'thumbnail' ],
        'capability_type' => 'post',
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
        'glc_instagram_url'  => 'esc_url_raw',
    ];
    $number_fields = [
        'glc_duration_min', 'glc_volunteers', 'glc_hours',
        'glc_bags', 'glc_weight_kg', 'glc_cans', 'glc_bottles',
        'glc_gps_lat', 'glc_gps_lon',
    ];

    foreach ( $text_fields as $key => $fn ) {
        if ( isset( $_POST[ $key ] ) ) {
            update_post_meta( $post_id, $key, $fn( $_POST[ $key ] ) );
        }
    }
    foreach ( $number_fields as $key ) {
        if ( isset( $_POST[ $key ] ) && $_POST[ $key ] !== '' ) {
            update_post_meta( $post_id, $key, (float) $_POST[ $key ] );
        } elseif ( isset( $_POST[ $key ] ) ) {
            delete_post_meta( $post_id, $key );
        }
    }

    // Derived stats keys read by glc_get_impact_stats()
    $cans    = absint( $_POST['glc_cans']    ?? 0 );
    $bottles = absint( $_POST['glc_bottles'] ?? 0 );
    update_post_meta( $post_id, 'items_recycled', $cans + $bottles );
    if ( isset( $_POST['glc_weight_kg'] ) && $_POST['glc_weight_kg'] !== '' ) {
        update_post_meta( $post_id, 'weight_kg', (float) $_POST['glc_weight_kg'] );
    }

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
        $r_bags     = absint(              $_POST['glc_bags']         ?? 0 );
        $r_weight   = (float)(             $_POST['glc_weight_kg']   ?? 0 );
        $r_waterway = sanitize_text_field( $_POST['glc_waterway']    ?? '' );
        $r_date     = sanitize_text_field( $_POST['glc_cleanup_date'] ?? '' );
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

    $error = ( is_string( $result ) && $result !== 'success' ) ? $result : '';

    $v = function( $key, $default = '' ) {
        return esc_attr( $_POST[ $key ] ?? $default );
    };

    ?>

    <div class="glc-submit-wrap">
        <?php if ( $error ) : ?>
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
                <div class="glc-field-row">
                    <div class="glc-field glc-field--half">
                        <label for="glc_submitter_name"><span class="glc-label-text"><?php esc_html_e( 'Your Name', 'great-lake-cleaners' ); ?><span class="glc-required" aria-label="required">*</span></span></label>
                        <input type="text" id="glc_submitter_name" name="glc_submitter_name" required maxlength="100" autocomplete="name" value="<?php echo $v('glc_submitter_name'); ?>">
                    </div>
                    <div class="glc-field glc-field--half">
                        <label for="glc_email"><span class="glc-label-text"><?php esc_html_e( 'Email', 'great-lake-cleaners' ); ?><span class="glc-tooltip" aria-label="<?php esc_attr_e( 'Optional — so we can say thanks', 'great-lake-cleaners' ); ?>" tabindex="0">?<span class="glc-tooltip-text"><?php esc_html_e( 'Optional — so we can say thanks', 'great-lake-cleaners' ); ?></span></span></span></label>
                        <input type="email" id="glc_email" name="glc_email" maxlength="200" autocomplete="email" value="<?php echo $v('glc_email'); ?>">
                    </div>

                </div>
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
                        <input type="date" id="glc_cleanup_date" name="glc_cleanup_date" required max="<?php echo esc_attr( date('Y-m-d') ); ?>" value="<?php echo $v('glc_cleanup_date'); ?>">
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

                <div class="glc-sub-group glc-sub-group--last">
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

            </fieldset>

            <!-- 4. Notable Finds & Field Log -->
            <fieldset class="glc-form-section">
                <legend class="glc-form-legend">
                    <span class="glc-form-legend-num">4</span>
                    <?php esc_html_e( 'Notable Finds & Field Log', 'great-lake-cleaners' ); ?>
                </legend>
                <div class="glc-field-row">
                    <div class="glc-field">
                        <label for="glc_notable_finds"><?php esc_html_e( 'Notable or Unusual Finds', 'great-lake-cleaners' ); ?><span class="glc-field-note"><?php esc_html_e( 'Large items, wildlife seen, anything worth sharing', 'great-lake-cleaners' ); ?></span></label>
                        <textarea id="glc_notable_finds" name="glc_notable_finds" rows="3" maxlength="1000"><?php echo esc_textarea( $_POST['glc_notable_finds'] ?? '' ); ?></textarea>
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
        btn.textContent = '<?php echo esc_js( __( 'Detecting\u2026', 'great-lake-cleaners' ) ); ?>';
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

    // Rate limit — max 5 submissions per IP per 10 minutes
    // Counter increments only just before wp_mail() — validation failures don't burn a slot
    $ip_key   = 'glc_sub_rate_' . md5( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
    $attempts = (int) get_transient( $ip_key );
    if ( $attempts >= 5 ) {
        return 'Too many submissions from your connection. Please wait a few minutes and try again.';
    }

    $name     = sanitize_text_field( $_POST['glc_submitter_name'] ?? '' );
    $date     = sanitize_text_field( $_POST['glc_cleanup_date']   ?? '' );
    $waterway = sanitize_text_field( $_POST['glc_waterway']       ?? '' );

    if ( ! $name )     return 'Please enter your name.';
    if ( ! $date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) return 'Please enter a valid cleanup date.';
    if ( strtotime( $date ) > time() ) return 'Cleanup date cannot be in the future.';

    $email         = sanitize_email(          $_POST['glc_email']           ?? '' );
    $phone         = sanitize_text_field(     $_POST['glc_phone']           ?? '' );
    $site_name     = sanitize_text_field(     $_POST['glc_site_name']       ?? '' );
    $duration_min  = absint(                  $_POST['glc_duration_min']    ?? 0 );
    $bags          = absint(                  $_POST['glc_bags']            ?? 0 );
    $weight_kg     = (float)(                 $_POST['glc_weight_kg']       ?? 0 );
    $garbage_notes = sanitize_text_field(     $_POST['glc_garbage_notes']   ?? '' );
    $cans          = absint(                  $_POST['glc_cans']            ?? 0 );
    $bottles       = absint(                  $_POST['glc_bottles']         ?? 0 );
    $volunteers    = max( 1, absint(          $_POST['glc_volunteers']      ?? 1 ) );
    $hours_input   = (float)(                 $_POST['glc_hours']           ?? 0 );
    $notable       = sanitize_textarea_field( $_POST['glc_notable_finds']   ?? '' );
    $instagram_url = esc_url_raw(             $_POST['glc_instagram_url']   ?? '' );
    $repost_ok     = isset( $_POST['glc_photo_repost_ok'] ) ? '1' : '0';
    $gps_lat       = isset( $_POST['glc_gps_lat'] ) && $_POST['glc_gps_lat'] !== '' ? (float) $_POST['glc_gps_lat'] : '';
    $gps_lon       = isset( $_POST['glc_gps_lon'] ) && $_POST['glc_gps_lon'] !== '' ? (float) $_POST['glc_gps_lon'] : '';

    // Person-hours: prefer duration × volunteers if entered, else use manual hours
    $person_hours = $duration_min > 0
        ? round( ( $duration_min / 60 ) * $volunteers, 2 )
        : $hours_input;

    $post_id = wp_insert_post( [
        'post_type'    => 'glc_submission',
        'post_status'  => 'pending',
        'post_title'   => sprintf( '%s (%s)', $waterway ?: 'Waterway cleanup', $date ),
        'post_content' => $notable,
    ] );

    if ( is_wp_error( $post_id ) ) return 'Could not save your submission. Please try again.';

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
        // Keys matching cleanup_event CPT — counted by glc_get_impact_stats()
        'items_recycled'      => $cans + $bottles,
        'weight_kg'           => $weight_kg,
        'glc_volunteers'      => $volunteers,
        'glc_hours'           => $person_hours,
        'glc_notable_finds'   => $notable,
        'glc_instagram_url'   => $instagram_url,
        'glc_photo_repost_ok' => $repost_ok,
        'glc_gps_lat'         => $gps_lat,
        'glc_gps_lon'         => $gps_lon,
    ];
    foreach ( $meta as $key => $val ) update_post_meta( $post_id, $key, $val );

    // Photo uploads
    $photo_ids = [];
    if ( ! empty( $_FILES['glc_photos']['name'][0] ) ) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        $allowed = [ 'image/jpeg', 'image/png', 'image/webp' ];
        $max     = 8 * 1024 * 1024;
        $count   = min( 5, count( $_FILES['glc_photos']['name'] ) );
        for ( $i = 0; $i < $count; $i++ ) {
            if ( $_FILES['glc_photos']['error'][$i] !== UPLOAD_ERR_OK ) continue;
            if ( $_FILES['glc_photos']['size'][$i]  > $max )            continue;
            if ( ! in_array( $_FILES['glc_photos']['type'][$i], $allowed, true ) ) continue;
            $_FILES['glc_photo_single'] = [
                'name'     => $_FILES['glc_photos']['name'][$i],
                'type'     => $_FILES['glc_photos']['type'][$i],
                'tmp_name' => $_FILES['glc_photos']['tmp_name'][$i],
                'error'    => $_FILES['glc_photos']['error'][$i],
                'size'     => $_FILES['glc_photos']['size'][$i],
            ];
            $uploaded = wp_handle_upload( $_FILES['glc_photo_single'], ['test_form'=>false] );
            if ( isset( $uploaded['file'] ) ) {
                $att_id = wp_insert_attachment( [
                    'post_mime_type' => $uploaded['type'],
                    'post_title'     => sanitize_file_name( $_FILES['glc_photos']['name'][$i] ),
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

    // Admin notification — increment rate limit counter only here, after all validation passes
    set_transient( $ip_key, $attempts + 1, 10 * MINUTE_IN_SECONDS );
    wp_mail(
        get_option( 'admin_email' ),
        sprintf( '[Great Lake Cleaners] New submission: %s on %s', $waterway, $date ),
        sprintf(
            "A new cleanup submission has arrived.\n\nSubmitter:  %s\nEmail:      %s\n"
            . "Waterway:   %s\nDate:       %s\nLocation:   %s\nDuration:   %d min\n"
            . "Bags:       %d\nWeight:     %.1f kg\nCans: %d  Bottles: %d\n"
            . "Volunteers: %d  Person-hours: %.2f\nGPS:        %s, %s\nPhoto consent: %s\n\nReview:\n%s",
            $name, $email ?: '(none)',
            $waterway, $date, $site_name ?: '(not given)', $duration_min,
            $bags, $weight_kg, $cans, $bottles,
            $volunteers, $person_hours,
            $gps_lat !== '' ? $gps_lat : 'n/a', $gps_lon !== '' ? $gps_lon : 'n/a',
            $repost_ok === '1' ? 'Yes — may repost' : 'No',
            admin_url( 'post.php?post=' . $post_id . '&action=edit' )
        )
    );

    return 'success';
}
