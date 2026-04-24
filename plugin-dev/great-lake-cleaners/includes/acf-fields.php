<?php
/**
 * Great Lake Cleaners — Cleanup Event Meta Box
 *
 * Native WordPress meta box replacing the previous ACF dependency.
 * All fields save to the same meta keys the importer, shortcodes,
 * and theme stats strip already read — no data migration needed.
 */
defined( 'ABSPATH' ) || exit;

// ── Register meta box ─────────────────────────────────────────────────────────

add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'glc_cleanup_details',
        'Cleanup Details',
        'glc_cleanup_meta_box_cb',
        'cleanup_event',
        'normal',
        'high'
    );
} );

// ── Render meta box ───────────────────────────────────────────────────────────

function glc_cleanup_meta_box_cb( $post ) {
    wp_nonce_field( 'glc_save_cleanup_meta', 'glc_cleanup_nonce' );

    $m = function( $key ) use ( $post ) {
        return esc_attr( get_post_meta( $post->ID, $key, true ) );
    };
    ?>
    <style>
    #glc_cleanup_details .inside { padding: 12px 0 4px; }
    .glc-meta-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px 20px; }
    .glc-meta-grid .glc-full { grid-column: 1 / -1; }
    .glc-meta-grid .glc-half { grid-column: span 1; }
    .glc-meta-section { grid-column: 1 / -1; margin: 8px 0 2px;
        font-size: 11px; font-weight: 600; text-transform: uppercase;
        letter-spacing: .06em; color: #1a4a6b; border-bottom: 1px solid #e0e0da; padding-bottom: 4px; }
    .glc-meta-grid label { display: block; font-weight: 600; font-size: 12px;
        margin-bottom: 4px; color: #333; }
    .glc-meta-grid input[type=text],
    .glc-meta-grid input[type=number],
    .glc-meta-grid input[type=date],
    .glc-meta-grid textarea { width: 100%; box-sizing: border-box; }
    .glc-meta-grid textarea { height: 64px; resize: vertical; }
    .glc-field-note { font-size: 11px; color: #666; margin-top: 3px; }
    </style>

    <div class="glc-meta-grid">

        <!-- Site -->
        <div class="glc-meta-section">Site</div>

        <div class="glc-half">
            <label for="glc_cleanup_date">Cleanup Date</label>
            <input type="date" id="glc_cleanup_date" name="glc_cleanup_date"
                   value="<?php echo $m('cleanup_date'); ?>">
        </div>

        <div style="grid-column:span 2;">
            <label for="glc_site_name">Site Name</label>
            <input type="text" id="glc_site_name" name="glc_site_name"
                   placeholder="e.g. Royal City Park – Speed River"
                   value="<?php echo $m('site_name'); ?>">
        </div>

        <div class="glc-half">
            <label for="glc_corridor">Corridor</label>
            <input type="text" id="glc_corridor" name="glc_corridor"
                   placeholder="e.g. Speed River"
                   value="<?php echo $m('corridor'); ?>">
            <p class="glc-field-note">Waterway system — used to count distinct corridors</p>
        </div>

        <div class="glc-half">
            <label for="glc_gps_lat">GPS Latitude</label>
            <input type="number" id="glc_gps_lat" name="glc_gps_lat"
                   step="any" placeholder="43.5448"
                   value="<?php echo $m('gps_lat'); ?>">
            <p class="glc-field-note">e.g. 43.5520</p>
        </div>

        <div class="glc-half">
            <label for="glc_gps_lon">GPS Longitude</label>
            <input type="number" id="glc_gps_lon" name="glc_gps_lon"
                   step="any" placeholder="-80.2482"
                   value="<?php echo $m('gps_lon'); ?>">
            <p class="glc-field-note">e.g. -80.2330 (negative for west)</p>
        </div>

        <!-- People -->
        <div class="glc-meta-section">People</div>

        <div class="glc-half">
            <label for="glc_volunteers">Volunteers</label>
            <input type="number" id="glc_volunteers" name="glc_volunteers"
                   min="1" step="1"
                   value="<?php echo $m('volunteers'); ?>">
        </div>

        <div class="glc-half">
            <label for="glc_hours">Volunteer Hours</label>
            <input type="number" id="glc_hours" name="glc_hours"
                   min="0" step="0.5"
                   value="<?php echo $m('hours'); ?>">
            <p class="glc-field-note">Person-hours (duration × people)</p>
        </div>

        <!-- Debris -->
        <div class="glc-meta-section">Debris Collected</div>

        <div class="glc-half">
            <label for="glc_bags">Bags</label>
            <input type="number" id="glc_bags" name="glc_bags"
                   min="0" step="0.5"
                   value="<?php echo $m('bags'); ?>">
        </div>

        <div class="glc-half">
            <label for="glc_weight_kg">Weight (kg)</label>
            <input type="number" id="glc_weight_kg" name="glc_weight_kg"
                   min="0" step="0.5"
                   value="<?php echo $m('weight_kg'); ?>">
        </div>

        <div class="glc-half">
            <label for="glc_items_recycled">Items Recycled</label>
            <input type="number" id="glc_items_recycled" name="glc_items_recycled"
                   min="0" step="1"
                   value="<?php echo $m('items_recycled'); ?>">
            <p class="glc-field-note">Cans + bottles</p>
        </div>

        <div class="glc-half">
            <label for="glc_tires_removed">Tires Removed</label>
            <input type="number" id="glc_tires_removed" name="glc_tires_removed"
                   min="0" step="1"
                   value="<?php echo $m('tires_removed'); ?>">
            <p class="glc-field-note">Car, truck, bicycle tires</p>
        </div>

        <div class="glc-half">
            <label for="glc_hazards_removed">Hazardous Waste Removed</label>
            <input type="number" id="glc_hazards_removed" name="glc_hazards_removed"
                   min="0" step="1"
                   value="<?php echo $m('hazards_removed'); ?>">
            <p class="glc-field-note">Paint cans, motor oil, appliances, e-waste, etc.</p>
        </div>

        <div class="glc-full">
            <label for="glc_notable_finds">Notable Finds</label>
            <input type="text" id="glc_notable_finds" name="glc_notable_finds"
                   placeholder="e.g. Shopping cart, tire, propane tank"
                   value="<?php echo $m('notable_finds'); ?>">
        </div>

        <!-- Restoration -->
        <div class="glc-meta-section">Restoration</div>

        <div class="glc-half">
            <label for="glc_species_planted">Native Species Planted</label>
            <input type="number" id="glc_species_planted" name="glc_species_planted"
                   min="0" step="1"
                   value="<?php echo $m('species_planted'); ?>">
        </div>

        <div class="glc-half">
            <label for="glc_meters_bank_cleared">Metres of Bank Cleared</label>
            <input type="number" id="glc_meters_bank_cleared" name="glc_meters_bank_cleared"
                   min="0" step="0.5"
                   value="<?php echo $m('meters_bank_cleared'); ?>">
        </div>

        <!-- Nature -->
        <div class="glc-meta-section">Nature</div>

        <div class="glc-full">
            <label for="glc_wildlife_obs">Wildlife Observed</label>
            <input type="text" id="glc_wildlife_obs" name="glc_wildlife_obs"
                   placeholder="e.g. Great blue heron, 2 wood ducks"
                   value="<?php echo $m('wildlife_obs'); ?>">
        </div>

        <!-- Documentation -->
        <div class="glc-meta-section">Documentation</div>

        <div class="glc-full">
            <label for="glc_instagram_url">Instagram Post URL</label>
            <input type="url" id="glc_instagram_url" name="glc_instagram_url"
                   placeholder="https://www.instagram.com/p/..."
                   value="<?php echo $m('instagram_url'); ?>">
            <p class="glc-field-note">Link to the Instagram field log for this cleanup</p>
        </div>

    </div><!-- .glc-meta-grid -->
    <?php
}

// ── Save meta box ─────────────────────────────────────────────────────────────

add_action( 'save_post_cleanup_event', function( $post_id ) {
    if ( ! isset( $_POST['glc_cleanup_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['glc_cleanup_nonce'], 'glc_save_cleanup_meta' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $text_fields = [
        'cleanup_date'  => 'sanitize_text_field',
        'site_name'     => 'sanitize_text_field',
        'corridor'      => 'sanitize_text_field',
        'notable_finds' => 'sanitize_text_field',
        'wildlife_obs'  => 'sanitize_text_field',
        'instagram_url' => 'esc_url_raw',
    ];

    $number_fields = [
        'gps_lat', 'gps_lon', 'volunteers', 'hours',
        'bags', 'weight_kg', 'items_recycled',
        'tires_removed', 'hazards_removed',
        'species_planted', 'meters_bank_cleared',
    ];

    foreach ( $text_fields as $key => $sanitizer ) {
        if ( isset( $_POST[ 'glc_' . $key ] ) ) {
            update_post_meta( $post_id, $key, $sanitizer( $_POST[ 'glc_' . $key ] ) );
        }
    }

    foreach ( $number_fields as $key ) {
        if ( isset( $_POST[ 'glc_' . $key ] ) && $_POST[ 'glc_' . $key ] !== '' ) {
            update_post_meta( $post_id, $key, (float) $_POST[ 'glc_' . $key ] );
        } elseif ( isset( $_POST[ 'glc_' . $key ] ) ) {
            delete_post_meta( $post_id, $key );
        }
    }
} );
