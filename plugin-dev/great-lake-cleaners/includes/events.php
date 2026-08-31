<?php
/**
 * Great Lake Cleaners — Community Events
 *
 * glc_event CPT for announcing upcoming cleanups, plus the RSVP form.
 * Distinct from cleanup_event, which is a record of a *completed* cleanup.
 * A past event can point at its resulting cleanup report via linked_cleanup_id.
 *
 * Shared meta keys with cleanup_event (deliberate): site_name, gps_lat, gps_lon
 * — so [glc_map post_id=N] single-event mode works on events unchanged.
 * Stats aggregation queries by post type, so events never count toward totals.
 */
defined( 'ABSPATH' ) || exit;

// ── Register post type ────────────────────────────────────────────────────────

add_action( 'init', 'glc_register_event_post_type' );
function glc_register_event_post_type() {
    register_post_type( 'glc_event', [
        'labels' => [
            'name'               => 'Events',
            'singular_name'      => 'Event',
            'add_new'            => 'Add New Event',
            'add_new_item'       => 'Add New Event',
            'edit_item'          => 'Edit Event',
            'view_item'          => 'View Event',
            'all_items'          => 'All Events',
            'search_items'       => 'Search Events',
            'not_found'          => 'No events found.',
            'not_found_in_trash' => 'No events in trash.',
        ],
        'public'        => true,
        'show_in_menu'  => true,
        'menu_position' => 6,
        'menu_icon'     => 'dashicons-calendar-alt',
        'supports'      => [ 'title', 'editor', 'thumbnail' ],
        'has_archive'   => 'events',
        'rewrite'       => [ 'slug' => 'events' ],
        'show_in_rest'  => true,
    ] );
}

// ── Date helpers ──────────────────────────────────────────────────────────────

/**
 * Normalised YYYY-MM-DD event date, or '' if missing/unparseable.
 */
function glc_event_date( $post_id ) {
    $d = get_post_meta( $post_id, 'event_date', true );
    if ( $d && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
        $ts = strtotime( $d );
        $d  = $ts ? date( 'Y-m-d', $ts ) : '';
    }
    return $d ?: '';
}

/**
 * An event stays "upcoming" through the end of its calendar day in the
 * site timezone. Undated events count as past so they never pin to the
 * top of the upcoming list.
 */
function glc_event_is_past( $post_id ) {
    $d = glc_event_date( $post_id );
    if ( ! $d ) return true;
    return strcmp( $d, current_time( 'Y-m-d' ) ) < 0;
}

/**
 * Display string for the event's time range, e.g. "9:00 am – 11:00 am".
 */
function glc_event_time_range( $post_id ) {
    $fmt = function( $t ) {
        $ts = $t ? strtotime( $t ) : false;
        return $ts ? date( 'g:i a', $ts ) : '';
    };
    $start = $fmt( get_post_meta( $post_id, 'event_start_time', true ) );
    $end   = $fmt( get_post_meta( $post_id, 'event_end_time',   true ) );
    if ( $start && $end ) return $start . ' – ' . $end;
    return $start ?: '';
}

/**
 * Published upcoming events, soonest first (start time breaks date ties).
 */
function glc_get_upcoming_events( $limit = -1 ) {
    $posts = get_posts( [
        'post_type'      => 'glc_event',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    ] );
    $upcoming = array_values( array_filter( $posts, function( $p ) {
        return ! glc_event_is_past( $p->ID );
    } ) );
    usort( $upcoming, function( $a, $b ) {
        $cmp = strcmp( glc_event_date( $a->ID ), glc_event_date( $b->ID ) );
        if ( $cmp !== 0 ) return $cmp;
        return strcmp(
            get_post_meta( $a->ID, 'event_start_time', true ),
            get_post_meta( $b->ID, 'event_start_time', true )
        );
    } );
    return $limit > 0 ? array_slice( $upcoming, 0, $limit ) : $upcoming;
}

/**
 * Published past events, most recent first.
 */
function glc_get_past_events() {
    $posts = get_posts( [
        'post_type'      => 'glc_event',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    ] );
    $past = array_values( array_filter( $posts, function( $p ) {
        return glc_event_is_past( $p->ID );
    } ) );
    usort( $past, function( $a, $b ) {
        return strcmp(
            glc_event_date( $b->ID ) ?: '0000-00-00',
            glc_event_date( $a->ID ) ?: '0000-00-00'
        );
    } );
    return $past;
}

// ── Meta box ──────────────────────────────────────────────────────────────────

add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'glc_event_details',
        'Event Details',
        'glc_event_meta_box_cb',
        'glc_event',
        'normal',
        'high'
    );
} );

function glc_event_meta_box_cb( $post ) {
    wp_nonce_field( 'glc_save_event_meta', 'glc_event_nonce' );

    $m = function( $key ) use ( $post ) {
        return esc_attr( get_post_meta( $post->ID, $key, true ) );
    };

    $linked = (int) get_post_meta( $post->ID, 'linked_cleanup_id', true );
    $cleanups = get_posts( [
        'post_type'      => 'cleanup_event',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_key'       => 'cleanup_date',
        'orderby'        => 'meta_value',
        'order'          => 'DESC',
    ] );

    $rsvp_parties = (int) get_post_meta( $post->ID, 'rsvp_parties', true );
    $rsvp_count   = (int) get_post_meta( $post->ID, 'rsvp_count',   true );
    ?>
    <style>
    #glc_event_details .inside { padding: 12px 0 4px; }
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
    .glc-meta-grid input[type=time],
    .glc-meta-grid select,
    .glc-meta-grid textarea { width: 100%; box-sizing: border-box; }
    .glc-meta-grid textarea { height: 64px; resize: vertical; }
    .glc-field-note { font-size: 11px; color: #666; margin-top: 3px; }
    .glc-rsvp-readout { grid-column: 1 / -1; font-size: 12px; color: #555;
        background: #f6f7f7; border: 1px solid #e0e0da; border-radius: 4px; padding: 8px 12px; }
    </style>

    <div class="glc-meta-grid">

        <!-- When -->
        <div class="glc-meta-section">When</div>

        <div class="glc-half">
            <label for="glc_event_date">Event Date</label>
            <input type="date" id="glc_event_date" name="glc_event_date"
                   value="<?php echo $m('event_date'); ?>">
            <p class="glc-field-note">Event counts as upcoming through the end of this day</p>
        </div>

        <div class="glc-half">
            <label for="glc_event_start_time">Start Time</label>
            <input type="time" id="glc_event_start_time" name="glc_event_start_time"
                   value="<?php echo $m('event_start_time'); ?>">
        </div>

        <div class="glc-half">
            <label for="glc_event_end_time">End Time (optional)</label>
            <input type="time" id="glc_event_end_time" name="glc_event_end_time"
                   value="<?php echo $m('event_end_time'); ?>">
        </div>

        <!-- Where -->
        <div class="glc-meta-section">Where</div>

        <div class="glc-full">
            <label for="glc_site_name">Site Name</label>
            <input type="text" id="glc_site_name" name="glc_site_name"
                   placeholder="e.g. Royal City Park – Speed River"
                   value="<?php echo $m('site_name'); ?>">
        </div>

        <div class="glc-half">
            <label for="glc_gps_lat">GPS Latitude</label>
            <input type="number" id="glc_gps_lat" name="glc_gps_lat"
                   step="any" placeholder="43.5448"
                   value="<?php echo $m('gps_lat'); ?>">
            <p class="glc-field-note">Shows a "Where to Meet" map pin on the event page</p>
        </div>

        <div class="glc-half">
            <label for="glc_gps_lon">GPS Longitude</label>
            <input type="number" id="glc_gps_lon" name="glc_gps_lon"
                   step="any" placeholder="-80.2482"
                   value="<?php echo $m('gps_lon'); ?>">
            <p class="glc-field-note">Negative for west</p>
        </div>

        <div class="glc-full">
            <label for="glc_meeting_point">Meeting Point</label>
            <textarea id="glc_meeting_point" name="glc_meeting_point"
                      placeholder="e.g. Gordon St. bridge parking lot — look for the gold flag"><?php
                echo esc_textarea( get_post_meta( $post->ID, 'meeting_point', true ) );
            ?></textarea>
        </div>

        <!-- Details -->
        <div class="glc-meta-section">Details</div>

        <div class="glc-full">
            <label for="glc_what_to_bring">What to Bring / Notes</label>
            <textarea id="glc_what_to_bring" name="glc_what_to_bring"
                      placeholder="e.g. Closed-toe shoes and water — we supply gloves, grabbers, and bags"><?php
                echo esc_textarea( get_post_meta( $post->ID, 'what_to_bring', true ) );
            ?></textarea>
        </div>

        <!-- After the event -->
        <div class="glc-meta-section">After the Event</div>

        <div class="glc-full">
            <label for="glc_linked_cleanup_id">Linked Cleanup Report</label>
            <select id="glc_linked_cleanup_id" name="glc_linked_cleanup_id">
                <option value="0">— None —</option>
                <?php foreach ( $cleanups as $c ) :
                    $cd = get_post_meta( $c->ID, 'cleanup_date', true );
                ?>
                <option value="<?php echo (int) $c->ID; ?>" <?php selected( $linked, $c->ID ); ?>>
                    <?php echo esc_html( ( $cd ? $cd . ' — ' : '' ) . $c->post_title ); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <p class="glc-field-note">Once the cleanup is logged, link it here — past events show a "See the results" link</p>
        </div>

        <div class="glc-rsvp-readout">
            <strong>RSVPs so far:</strong>
            <?php if ( $rsvp_parties ) : ?>
                <?php echo (int) $rsvp_parties; ?> <?php echo 1 === $rsvp_parties ? 'party' : 'parties'; ?>,
                <?php echo (int) $rsvp_count; ?> <?php echo 1 === $rsvp_count ? 'person' : 'people'; ?> total
            <?php else : ?>
                none yet
            <?php endif; ?>
            — full details arrive by email at info@greatlakecleaners.ca
        </div>

    </div><!-- .glc-meta-grid -->
    <?php
}

// ── Save meta box ─────────────────────────────────────────────────────────────

add_action( 'save_post_glc_event', function( $post_id ) {
    if ( ! isset( $_POST['glc_event_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['glc_event_nonce'], 'glc_save_event_meta' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $text_fields = [
        'event_date'       => 'sanitize_text_field',
        'event_start_time' => 'sanitize_text_field',
        'event_end_time'   => 'sanitize_text_field',
        'site_name'        => 'sanitize_text_field',
        'meeting_point'    => 'sanitize_textarea_field',
        'what_to_bring'    => 'sanitize_textarea_field',
    ];

    $number_fields = [ 'gps_lat', 'gps_lon' ];

    foreach ( $text_fields as $key => $sanitizer ) {
        if ( isset( $_POST[ 'glc_' . $key ] ) ) {
            $val = $sanitizer( wp_unslash( $_POST[ 'glc_' . $key ] ) );
            // Normalise the date to YYYY-MM-DD — the upcoming/past split strcmp's it
            if ( 'event_date' === $key && $val && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $val ) ) {
                $ts  = strtotime( $val );
                $val = $ts ? date( 'Y-m-d', $ts ) : '';
            }
            update_post_meta( $post_id, $key, $val );
        }
    }

    foreach ( $number_fields as $key ) {
        if ( isset( $_POST[ 'glc_' . $key ] ) && $_POST[ 'glc_' . $key ] !== '' ) {
            update_post_meta( $post_id, $key, (float) $_POST[ 'glc_' . $key ] );
        } elseif ( isset( $_POST[ 'glc_' . $key ] ) ) {
            delete_post_meta( $post_id, $key );
        }
    }

    if ( isset( $_POST['glc_linked_cleanup_id'] ) ) {
        $linked = absint( $_POST['glc_linked_cleanup_id'] );
        if ( $linked ) {
            update_post_meta( $post_id, 'linked_cleanup_id', $linked );
        } else {
            delete_post_meta( $post_id, 'linked_cleanup_id' );
        }
    }
} );

// ── RSVP AJAX handler ─────────────────────────────────────────────────────────

add_action( 'wp_ajax_glc_event_rsvp',        'glc_handle_event_rsvp' );
add_action( 'wp_ajax_nopriv_glc_event_rsvp', 'glc_handle_event_rsvp' );

function glc_handle_event_rsvp() {
    if ( ! check_ajax_referer( 'glc_event_rsvp', 'nonce', false ) ) {
        wp_send_json_error( 'Security check failed — please reload and try again.' );
    }

    // Honeypot — fake success, no email
    if ( ! empty( $_POST['glc_url'] ) ) {
        wp_send_json_success( "You're in! See you at the river." );
    }

    // Rate limit: 3 per 10 min per IP, plus the site-wide hourly ceiling in
    // security.php (set higher for RSVPs — they spike legitimately the day an
    // event is announced).
    $rate = glc_rate_limit_check( 'rsvp', 3 );
    if ( true !== $rate ) {
        wp_send_json_error( $rate );
    }

    $event_id = absint( $_POST['glc_rsvp_event_id'] ?? 0 );
    $event    = $event_id ? get_post( $event_id ) : null;
    if ( ! $event || 'glc_event' !== $event->post_type || 'publish' !== $event->post_status ) {
        wp_send_json_error( 'That event could not be found — please reload the page.' );
    }
    if ( glc_event_is_past( $event_id ) ) {
        wp_send_json_error( 'This event has already happened.' );
    }

    $name  = glc_clean_text( $_POST['glc_rsvp_name'] ?? '', 100 );
    $email = sanitize_email( glc_clean_text( $_POST['glc_rsvp_email'] ?? '', 200 ) );
    $party = glc_clean_int( $_POST['glc_rsvp_party'] ?? 1, 20, 1 );

    if ( ! $name ) {
        wp_send_json_error( 'Please enter your name.' );
    }
    if ( ! $email || ! is_email( $email ) ) {
        wp_send_json_error( 'Please enter a valid email address.' );
    }

    $event_date = glc_event_date( $event_id );
    $to         = 'info@greatlakecleaners.ca';
    $subject    = 'RSVP — ' . $event->post_title
                . ( $event_date ? " ({$event_date})" : '' )
                . " — {$name}";
    $body       = "New RSVP via the website.\n\n"
                . "Event: {$event->post_title}\n"
                . ( $event_date ? "Date:  {$event_date}\n" : '' )
                . 'Link:  ' . get_permalink( $event_id ) . "\n\n"
                . "Name:  {$name}\n"
                . "Email: {$email}\n"
                . 'Party: ' . $party . ' ' . ( 1 === $party ? 'person' : 'people' ) . "\n"
                . 'Sent:  ' . wp_date( 'F j, Y \a\t g:i a T' ) . "\n";
    $headers    = [
        'Content-Type: text/plain; charset=UTF-8',
        "Reply-To: {$name} <{$email}>",
    ];

    $sent = wp_mail( $to, $subject, $body, $headers );

    if ( ! $sent ) {
        wp_send_json_error( 'Something went wrong — please email us directly at info@greatlakecleaners.ca.' );
    }

    glc_rate_limit_hit( 'rsvp' );

    // Aggregate headcount for the "N people coming" line — social proof only
    update_post_meta( $event_id, 'rsvp_count',   (int) get_post_meta( $event_id, 'rsvp_count',   true ) + $party );
    update_post_meta( $event_id, 'rsvp_parties', (int) get_post_meta( $event_id, 'rsvp_parties', true ) + 1 );

    wp_send_json_success( "You're in! See you at the river — we'll email you if anything changes." );
}

// ── [glc_event_rsvp] shortcode ────────────────────────────────────────────────

add_shortcode( 'glc_event_rsvp', 'glc_shortcode_event_rsvp' );

function glc_shortcode_event_rsvp( $atts ) {
    static $loaded = false;

    $atts     = shortcode_atts( [ 'post_id' => 0 ], $atts, 'glc_event_rsvp' );
    $event_id = absint( $atts['post_id'] ) ?: get_the_ID();

    if ( ! $event_id || 'glc_event' !== get_post_type( $event_id ) || glc_event_is_past( $event_id ) ) {
        return '';
    }

    $ajax_url = admin_url( 'admin-ajax.php' );
    $nonce    = wp_create_nonce( 'glc_event_rsvp' );

    ob_start();
    ?>
    <div class="glc-ev-rsvp">

        <form class="glc-ev-rsvp-form" novalidate
              data-nonce="<?php echo esc_attr( $nonce ); ?>"
              data-action="<?php echo esc_url( $ajax_url ); ?>">

            <input type="hidden" name="glc_rsvp_event_id" value="<?php echo (int) $event_id; ?>">
            <input type="text" name="glc_url" class="glc-join-honeypot"
                   autocomplete="off" tabindex="-1" aria-hidden="true">

            <div class="glc-field-row">
                <div class="glc-field glc-field--half">
                    <label for="glc_rsvp_name">
                        <span class="glc-label-text"><?php esc_html_e( 'Name', 'great-lake-cleaners' ); ?><span class="glc-required" aria-hidden="true">*</span></span>
                    </label>
                    <input type="text" id="glc_rsvp_name" name="glc_rsvp_name"
                           required autocomplete="name"
                           placeholder="<?php esc_attr_e( 'Your name', 'great-lake-cleaners' ); ?>">
                </div>
                <div class="glc-field glc-field--half">
                    <label for="glc_rsvp_email">
                        <span class="glc-label-text"><?php esc_html_e( 'Email', 'great-lake-cleaners' ); ?><span class="glc-required" aria-hidden="true">*</span></span>
                    </label>
                    <input type="email" id="glc_rsvp_email" name="glc_rsvp_email"
                           required autocomplete="email"
                           placeholder="<?php esc_attr_e( 'your@email.com', 'great-lake-cleaners' ); ?>">
                </div>
            </div>

            <div class="glc-field-row">
                <div class="glc-field glc-field--half">
                    <label for="glc_rsvp_party">
                        <span class="glc-label-text"><?php esc_html_e( 'How many of you are coming?', 'great-lake-cleaners' ); ?></span>
                    </label>
                    <input type="number" id="glc_rsvp_party" name="glc_rsvp_party"
                           min="1" max="20" step="1" value="1" inputmode="numeric">
                </div>
            </div>

            <div class="glc-form-submit-row">
                <button type="submit" class="glc-btn-primary glc-btn-submit">
                    <?php esc_html_e( "I'll be there", 'great-lake-cleaners' ); ?>
                </button>
                <p class="glc-form-privacy-note">
                    <?php printf(
                        wp_kses( __( 'We\'ll only use your email for updates about this event. See our <a href="%s">Privacy Policy</a>.', 'great-lake-cleaners' ), [ 'a' => [ 'href' => [] ] ] ),
                        esc_url( home_url( '/privacy-policy/' ) )
                    ); ?>
                </p>
            </div>

            <p class="glc-ev-rsvp-msg" role="status"></p>

        </form>

    </div><!-- .glc-ev-rsvp -->
    <?php if ( ! $loaded ) : $loaded = true; ?>
    <script>
    (function () {
        document.querySelectorAll('.glc-ev-rsvp-form').forEach(function (frm) {
            var msg = frm.querySelector('.glc-ev-rsvp-msg');

            frm.addEventListener('submit', function (e) {
                e.preventDefault();
                var btn  = frm.querySelector('[type="submit"]');
                var orig = btn.textContent;
                btn.disabled = true;
                btn.textContent = '<?php echo esc_js( __( 'Sending…', 'great-lake-cleaners' ) ); ?>';
                msg.textContent = '';
                msg.className = 'glc-ev-rsvp-msg';

                var fd = new FormData(frm);
                fd.append('action', 'glc_event_rsvp');
                fd.append('nonce',  frm.dataset.nonce);

                fetch(frm.dataset.action, { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        msg.classList.add(res.success ? 'glc-ev-rsvp-msg--ok' : 'glc-ev-rsvp-msg--err');
                        msg.textContent = res.data || (res.success ? "You're in!" : 'Something went wrong.');
                        if (res.success) {
                            frm.reset();
                            btn.disabled = true;
                        }
                    })
                    .catch(function () {
                        msg.classList.add('glc-ev-rsvp-msg--err');
                        msg.textContent = 'Connection error — please try again.';
                    })
                    .finally(function () {
                        if (!frm.querySelector('.glc-ev-rsvp-msg--ok')) {
                            btn.disabled = false;
                            btn.textContent = orig;
                        }
                    });
            });
        });
    })();
    </script>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}
