<?php
/**
 * Great Lake Cleaners — Waterway Issue Reporting
 *
 * Provides the [glc_report_form] shortcode.
 * Reports are sent via wp_mail() to the GLC contact address.
 * No custom post type — reports are email-only.
 *
 * Flow:
 *   Stage 1: Triage card — city issues routed to Guelph ArcGIS tool,
 *            waterway issues reveal the form.
 *   Stage 2: Form — date spotted, waterway, location, GPS, description,
 *            photos (up to 3), optional name and email.
 */

defined( 'ABSPATH' ) || exit;

define( 'GLC_REPORT_EMAIL', 'info@greatlakecleaners.ca' );

// ── [glc_report_form] shortcode ───────────────────────────────────────────────

add_shortcode( 'glc_report_form', 'glc_render_report_form' );

function glc_render_report_form() {
    $result = glc_maybe_handle_report();
    ob_start();

    // ── Success state ─────────────────────────────────────────────────────────
    if ( $result === 'success' ) {
        ?>
        <div class="glc-submit-success glc-report-success" role="alert">
            <p class="glc-submit-receipt">
                <?php esc_html_e( 'Report received — thanks for looking out for the waterway.', 'great-lake-cleaners' ); ?>
            </p>
            <h2><?php esc_html_e( "Let's keep our waters clean.", 'great-lake-cleaners' ); ?></h2>
            <p>
                <?php esc_html_e( "We log every waterway issue and prioritize it on our next outing. If you left your email we'll follow up once we've had eyes on it.", 'great-lake-cleaners' ); ?>
            </p>
            <p style="margin-top:16px;">
                <?php esc_html_e( 'If this looks like an active environmental spill or hazard, please contact:', 'great-lake-cleaners' ); ?>
            </p>
            <ul class="glc-report-escalate-list">
                <li>
                    <strong><?php esc_html_e( 'GRCA Spills Action Centre:', 'great-lake-cleaners' ); ?></strong>
                    <a href="tel:1-800-265-6613">1-800-265-6613</a>
                    <?php esc_html_e( '(24 hr)', 'great-lake-cleaners' ); ?>
                </li>
                <li>
                    <strong><?php esc_html_e( 'Environment and Climate Change Canada:', 'great-lake-cleaners' ); ?></strong>
                    <a href="tel:1-800-268-6060">1-800-268-6060</a>
                </li>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    // ── Error state ───────────────────────────────────────────────────────────
    $error = is_string( $result ) ? $result : '';

    $city_url = 'https://experience.arcgis.com/experience/aa79df9526ab4c99914adc950eca9141';
    $privacy_url = home_url( '/privacy-policy/' );
    ?>

    <!-- ── Stage 1: Triage ─────────────────────────────────────────────── -->
    <div class="glc-triage-block" id="glc-triage">

        <p class="glc-triage-intro">
            <?php esc_html_e( 'Before you report, help us make sure it gets to the right hands.', 'great-lake-cleaners' ); ?>
        </p>

        <div class="glc-triage-cards">

            <!-- City card -->
            <div class="glc-triage-card glc-triage-card--city">
                <div class="glc-triage-card-icon" aria-hidden="true">🏙️</div>
                <div class="glc-triage-card-body">
                    <h2 class="glc-triage-card-heading">
                        <?php esc_html_e( 'City or municipal issue', 'great-lake-cleaners' ); ?>
                    </h2>
                    <p class="glc-triage-card-desc">
                        <?php esc_html_e( 'Litter in a park, illegal dumping on a street or trail should be reported to your municipality. Most have a 311 line or an online reporting tool.', 'great-lake-cleaners' ); ?>
                    </p>
                    <p class="glc-triage-card-desc" style="margin-top:12px;font-size:0.9em;">
                        <?php esc_html_e( 'In Guelph? Use the City\'s online tool to report:', 'great-lake-cleaners' ); ?>
                    </p>
                    <a href="<?php echo esc_url( $city_url ); ?>"
                       class="glc-btn-primary glc-triage-btn"
                       target="_blank"
                       rel="noopener noreferrer">
                        <?php esc_html_e( 'Report to the City of Guelph', 'great-lake-cleaners' ); ?>
                        <span class="glc-triage-ext-icon" aria-label="<?php esc_attr_e( '(opens in new tab)', 'great-lake-cleaners' ); ?>">↗</span>
                    </a>
                </div>
            </div>

            <!-- Waterway card -->
            <div class="glc-triage-card glc-triage-card--waterway">
                <div class="glc-triage-card-icon" aria-hidden="true">🌊</div>
                <div class="glc-triage-card-body">
                    <h2 class="glc-triage-card-heading">
                        <?php esc_html_e( 'Waterway issue', 'great-lake-cleaners' ); ?>
                    </h2>
                    <p class="glc-triage-card-desc">
                        <?php esc_html_e( 'Let us know about problems along a river, creek, or shoreline. We run regular cleanups across loal waterways, help where we can, and involve other organizations when we can\'t.', 'great-lake-cleaners' ); ?>
                    </p>
                    <button type="button"
                            class="glc-btn-outline glc-triage-btn glc-triage-reveal-btn"
                            onclick="glcRevealReportForm(this)"
                            aria-expanded="false"
                            aria-controls="glc-report-form-section">
                        <?php esc_html_e( 'Report a waterway issue', 'great-lake-cleaners' ); ?> ↓
                    </button>
                </div>
            </div>

        </div><!-- .glc-triage-cards -->
    </div><!-- .glc-triage-block -->

    <!-- ── Stage 2: Form ───────────────────────────────────────────────── -->
    <div class="glc-report-form-section" id="glc-report-form-section" <?php echo $error ? '' : 'hidden'; ?>>

        <div class="glc-report-form-header">
            <h2 class="glc-report-form-heading">
                <?php esc_html_e( 'Waterway Issue Report', 'great-lake-cleaners' ); ?>
            </h2>
            <p class="glc-report-form-subhead">
                <?php esc_html_e( "Tell us what you saw and where. We'll log it and prioritize it on our next outings.", 'great-lake-cleaners' ); ?>
            </p>
        </div>

        <?php if ( $error ) : ?>
            <div class="glc-form-error" role="alert">
                <?php echo esc_html( $error ); ?>
            </div>
        <?php endif; ?>

        <form id="glc-report-form" class="glc-submit-form glc-report-form"
              method="post"
              enctype="multipart/form-data"
              novalidate>

            <?php wp_nonce_field( 'glc_report_issue', 'glc_report_nonce' ); ?>

            <!-- ① Contact (optional) -->
            <fieldset class="glc-form-section">
                <legend class="glc-form-legend">
                    <span class="glc-form-legend-num" aria-hidden="true">1</span>
                    <?php esc_html_e( 'About You', 'great-lake-cleaners' ); ?>
                    <span class="glc-form-legend-optional"><?php esc_html_e( '— optional', 'great-lake-cleaners' ); ?></span>
                </legend>

                <div class="glc-field-row">
                    <div class="glc-field glc-field--half">
                        <label for="glc_reporter_name">
                            <span class="glc-label-text"><?php esc_html_e( 'Your name', 'great-lake-cleaners' ); ?></span>
                        </label>
                        <input type="text" id="glc_reporter_name" name="glc_reporter_name"
                               value="<?php echo esc_attr( $_POST['glc_reporter_name'] ?? '' ); ?>"
                               placeholder="<?php esc_attr_e( 'Jane Smith', 'great-lake-cleaners' ); ?>"
                               autocomplete="name">
                        <span class="glc-field-note"><?php esc_html_e( 'So we can address our follow-up.', 'great-lake-cleaners' ); ?></span>
                    </div>
                    <div class="glc-field glc-field--half">
                        <label for="glc_reporter_email">
                            <span class="glc-label-text"><?php esc_html_e( 'Email', 'great-lake-cleaners' ); ?></span>
                        </label>
                        <input type="email" id="glc_reporter_email" name="glc_reporter_email"
                               value="<?php echo esc_attr( $_POST['glc_reporter_email'] ?? '' ); ?>"
                               placeholder="<?php esc_attr_e( 'jane@example.com', 'great-lake-cleaners' ); ?>"
                               autocomplete="email">
                        <span class="glc-field-note"><?php esc_html_e( "We'll follow up once we've had eyes on it.", 'great-lake-cleaners' ); ?></span>
                    </div>
                </div>

                <div class="glc-field-row" style="margin-top:16px;">
                    <div class="glc-field glc-field--full">
                        <label class="glc-checkbox-label">
                            <input type="checkbox" name="glc_wants_to_help" value="1"
                                   <?php checked( ! empty( $_POST['glc_wants_to_help'] ) ); ?>>
                            <span><?php esc_html_e( 'I want to help clean up this area', 'great-lake-cleaners' ); ?></span>
                        </label>
                        <span class="glc-field-note"><?php esc_html_e( "We'll keep you in mind when we're planning an outing nearby.", 'great-lake-cleaners' ); ?></span>
                    </div>
                </div>
            </fieldset>

            <!-- ② The Issue -->
            <fieldset class="glc-form-section">
                <legend class="glc-form-legend">
                    <span class="glc-form-legend-num" aria-hidden="true">2</span>
                    <?php esc_html_e( 'The Issue', 'great-lake-cleaners' ); ?>
                </legend>

                <div class="glc-field-row">
                    <div class="glc-field glc-field--half">
                        <label for="glc_issue_date">
                            <span class="glc-label-text"><?php esc_html_e( 'Date spotted', 'great-lake-cleaners' ); ?><span class="glc-required" aria-hidden="true">*</span></span>
                        </label>
                        <input type="date" id="glc_issue_date" name="glc_issue_date" required
                               value="<?php echo esc_attr( $_POST['glc_issue_date'] ?? date( 'Y-m-d' ) ); ?>"
                               max="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
                    </div>
                    <div class="glc-field glc-field--half">
                        <label for="glc_issue_waterway">
                            <span class="glc-label-text"><?php esc_html_e( 'Waterway', 'great-lake-cleaners' ); ?><span class="glc-required" aria-hidden="true">*</span></span>
                        </label>
                        <input type="text" id="glc_issue_waterway" name="glc_issue_waterway" required maxlength="200"
                               placeholder="<?php esc_attr_e( 'e.g. Speed River, Grand River', 'great-lake-cleaners' ); ?>"
                               value="<?php echo esc_attr( $_POST['glc_issue_waterway'] ?? '' ); ?>">
                    </div>
                </div>

                <div class="glc-field-row" style="margin-top:16px;">
                    <div class="glc-field glc-field--full">
                        <label for="glc_issue_description">
                            <span class="glc-label-text"><?php esc_html_e( 'What did you see?', 'great-lake-cleaners' ); ?><span class="glc-required" aria-hidden="true">*</span></span>
                        </label>
                        <textarea id="glc_issue_description" name="glc_issue_description"
                                  rows="5" required
                                  placeholder="<?php esc_attr_e( 'Describe the issue — type of debris, approximate quantity, any hazard concern (e.g. chemicals, sharp materials). The more specific, the better.', 'great-lake-cleaners' ); ?>"><?php echo esc_textarea( $_POST['glc_issue_description'] ?? '' ); ?></textarea>
                    </div>
                </div>

            </fieldset>

            <!-- ③ Location -->
            <fieldset class="glc-form-section">
                <legend class="glc-form-legend">
                    <span class="glc-form-legend-num" aria-hidden="true">3</span>
                    <?php esc_html_e( 'Location', 'great-lake-cleaners' ); ?>
                </legend>

                <div class="glc-field-row">
                    <div class="glc-field glc-field--full">
                        <label for="glc_issue_location">
                            <span class="glc-label-text"><?php esc_html_e( 'Location description', 'great-lake-cleaners' ); ?><span class="glc-required" aria-hidden="true">*</span></span>
                        </label>
                        <input type="text" id="glc_issue_location" name="glc_issue_location" required
                               value="<?php echo esc_attr( $_POST['glc_issue_location'] ?? '' ); ?>"
                               placeholder="<?php esc_attr_e( 'e.g. nearest park / intersection', 'great-lake-cleaners' ); ?>">
                    </div>
                </div>

                <div class="glc-field-row" style="margin-top:16px;">
                    <div class="glc-field glc-field--geo">
                        <label>
                            <span class="glc-label-text"><?php esc_html_e( 'GPS coordinates', 'great-lake-cleaners' ); ?></span>
                        </label>
                        <div class="glc-geo-row">
                            <input type="number" id="glc_issue_lat" name="glc_issue_lat"
                                   step="0.000001" min="42" max="57"
                                   value="<?php echo esc_attr( $_POST['glc_issue_lat'] ?? '' ); ?>"
                                   placeholder="<?php esc_attr_e( 'Latitude', 'great-lake-cleaners' ); ?>">
                            <input type="number" id="glc_issue_lon" name="glc_issue_lon"
                                   step="0.000001" min="-95" max="-74"
                                   value="<?php echo esc_attr( $_POST['glc_issue_lon'] ?? '' ); ?>"
                                   placeholder="<?php esc_attr_e( 'Longitude', 'great-lake-cleaners' ); ?>">
                            <button type="button" class="glc-geo-btn"
                                    onclick="glcDetectReportLocation(this)">
                                📍 <?php esc_html_e( 'Use my location', 'great-lake-cleaners' ); ?>
                            </button>
                        </div>
                        <span class="glc-field-note">
                            <?php esc_html_e( 'Optional but very helpful for pinpointing the spot. Tap "Use my location" if you\'re there now, or enter coordinates from Google Maps.', 'great-lake-cleaners' ); ?>
                        </span>
                    </div>
                </div>
            </fieldset>

            <!-- ④ Photos -->
            <fieldset class="glc-form-section">
                <legend class="glc-form-legend">
                    <span class="glc-form-legend-num" aria-hidden="true">4</span>
                    <?php esc_html_e( 'Photos', 'great-lake-cleaners' ); ?>
                    <span class="glc-form-legend-optional"><?php esc_html_e( '— optional', 'great-lake-cleaners' ); ?></span>
                </legend>

                <div class="glc-field-row">
                    <div class="glc-field">
                        <label for="glc_report_photos">
                            <span class="glc-label-text"><?php esc_html_e( 'Upload up to 3 photos', 'great-lake-cleaners' ); ?></span>
                        </label>
                        <input type="file" id="glc_report_photos" name="glc_report_photos[]"
                               accept="image/jpeg,image/png,image/webp"
                               multiple>
                        <span class="glc-field-note">
                            <?php esc_html_e( 'Max 8 MB per photo. A clear photo of the issue and its surroundings is incredibly helpful.', 'great-lake-cleaners' ); ?>
                        </span>
                    </div>
                </div>
            </fieldset>

            <!-- Honeypot — hidden from real users, bots fill it in -->
            <div class="glc-hp-field" aria-hidden="true">
                <label for="glc_url_r">Website</label>
                <input type="text" id="glc_url_r" name="glc_url" tabindex="-1" autocomplete="off">
            </div>

            <!-- Submit -->
            <div class="glc-form-submit-row">
                <button type="submit" name="glc_submit_report" class="glc-btn-primary glc-btn-submit">
                    <?php esc_html_e( 'Submit Report', 'great-lake-cleaners' ); ?>
                </button>
                <p class="glc-form-privacy-note">
                    <?php
                    printf(
                        wp_kses(
                            __( 'Your contact information is optional and never shared publicly. See our <a href="%s">Privacy Policy</a>.', 'great-lake-cleaners' ),
                            [ 'a' => [ 'href' => [] ] ]
                        ),
                        esc_url( $privacy_url )
                    );
                    ?>
                </p>
            </div>

        </form>
    </div><!-- .glc-report-form-section -->

    <script>
    function glcRevealReportForm(btn) {
        var section = document.getElementById('glc-report-form-section');
        if (!section) return;
        section.removeAttribute('hidden');
        btn.setAttribute('aria-expanded', 'true');
        btn.textContent = '<?php echo esc_js( __( 'Report a waterway issue ↓', 'great-lake-cleaners' ) ); ?>';
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    function glcDetectReportLocation(btn) {
        if (!navigator.geolocation) {
            alert('<?php echo esc_js( __( 'Geolocation is not supported by your browser.', 'great-lake-cleaners' ) ); ?>');
            return;
        }
        btn.disabled = true;
        btn.textContent = '<?php echo esc_js( __( 'Detecting…', 'great-lake-cleaners' ) ); ?>';
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                document.getElementById('glc_issue_lat').value = pos.coords.latitude.toFixed(6);
                document.getElementById('glc_issue_lon').value = pos.coords.longitude.toFixed(6);
                btn.disabled = false;
                btn.innerHTML = '\u2713 <?php echo esc_js( __( 'Location set', 'great-lake-cleaners' ) ); ?>';
            },
            function() {
                btn.disabled = false;
                btn.innerHTML = '📍 <?php echo esc_js( __( 'Use my location', 'great-lake-cleaners' ) ); ?>';
                alert('<?php echo esc_js( __( 'Could not detect location. Please enter coordinates manually.', 'great-lake-cleaners' ) ); ?>');
            }
        );
    }
    </script>
    <?php
    return ob_get_clean();
}


// ── Handle form POST ──────────────────────────────────────────────────────────

function glc_maybe_handle_report() {
    if ( ! isset( $_POST['glc_submit_report'] ) ) return null;

    if ( ! isset( $_POST['glc_report_nonce'] )
        || ! wp_verify_nonce( $_POST['glc_report_nonce'], 'glc_report_issue' ) ) {
        return 'Security check failed. Please refresh and try again.';
    }

    // Honeypot — bots fill in fields humans never see
    if ( ! empty( $_POST['glc_url'] ) ) return null;

    // Rate limit — max 5 reports per IP per 10 minutes
    // Counter increments only just before wp_mail() — validation failures don't burn a slot
    $ip_key   = 'glc_rep_rate_' . md5( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
    $attempts = (int) get_transient( $ip_key );
    if ( $attempts >= 5 ) {
        return 'Too many reports from your connection. Please wait a few minutes and try again.';
    }

    $date        = sanitize_text_field( $_POST['glc_issue_date']        ?? '' );
    $waterway    = sanitize_text_field( $_POST['glc_issue_waterway']    ?? '' );
    $description = sanitize_textarea_field( $_POST['glc_issue_description'] ?? '' );
    $location    = sanitize_text_field( $_POST['glc_issue_location']    ?? '' );

    if ( ! $date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        return 'Please enter a valid date.';
    }
    if ( strtotime( $date ) > time() ) {
        return 'Date spotted cannot be in the future.';
    }
    if ( ! $waterway ) {
        return 'Please enter the waterway name.';
    }
    if ( ! $description ) {
        return 'Please describe the issue.';
    }
    if ( ! $location ) {
        return 'Please describe the location.';
    }

    $name         = sanitize_text_field( $_POST['glc_reporter_name']    ?? '' );
    $email        = sanitize_email(      $_POST['glc_reporter_email']   ?? '' );
    $wants_help   = ! empty( $_POST['glc_wants_to_help'] );
    $lat   = $_POST['glc_issue_lat'] !== '' ? (float) ( $_POST['glc_issue_lat'] ?? '' ) : '';
    $lon   = $_POST['glc_issue_lon'] !== '' ? (float) ( $_POST['glc_issue_lon'] ?? '' ) : '';

    // Build plain-text email body
    $body  = "A waterway issue has been reported via greatlakecleaners.ca.\n\n";
    $body .= "──────────────────────────────\n";
    $body .= sprintf( "Reporter:    %s\n", $name  ?: '(anonymous)' );
    $body .= sprintf( "Email:       %s\n", $email ?: '(none given)' );
    $body .= sprintf( "Wants to help cleanup: %s\n", $wants_help ? 'Yes' : 'No' );
    $body .= "──────────────────────────────\n";
    $body .= sprintf( "Date spotted: %s\n", $date );
    $body .= sprintf( "Waterway:     %s\n", $waterway );
    $body .= sprintf( "Location:     %s\n", $location );
    $body .= sprintf( "GPS:          %s, %s\n",
        $lat !== '' ? $lat : 'not provided',
        $lon !== '' ? $lon : 'not provided'
    );
    $body .= "\nDescription:\n" . $description . "\n";

    // Handle photo attachments
    $attachments = [];
    if ( ! empty( $_FILES['glc_report_photos']['name'][0] ) ) {
        $allowed  = [ 'image/jpeg', 'image/png', 'image/webp' ];
        $max_size = 8 * 1024 * 1024;
        $count    = min( 3, count( $_FILES['glc_report_photos']['name'] ) );
        for ( $i = 0; $i < $count; $i++ ) {
            if ( $_FILES['glc_report_photos']['error'][$i] !== UPLOAD_ERR_OK ) continue;
            if ( $_FILES['glc_report_photos']['size'][$i]  > $max_size )       continue;
            if ( ! in_array( $_FILES['glc_report_photos']['type'][$i], $allowed, true ) ) continue;
            // Move to a temp location wp_mail can attach from
            $tmp  = $_FILES['glc_report_photos']['tmp_name'][$i];
            $dest = get_temp_dir() . 'glc-report-' . uniqid() . '-' . sanitize_file_name( $_FILES['glc_report_photos']['name'][$i] );
            if ( move_uploaded_file( $tmp, $dest ) ) {
                $attachments[] = $dest;
            }
        }
        if ( $attachments ) {
            $body .= "\n" . count( $attachments ) . " photo(s) attached.\n";
        }
    }

    $subject = sprintf(
        '[Great Lake Cleaners] Waterway issue reported: %s on %s',
        $waterway, $date
    );

    $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
    if ( $email ) {
        $reply_name = $name ?: 'Reporter';
        $headers[]  = sprintf( 'Reply-To: %s <%s>', $reply_name, $email );
    }

    // Increment rate limit counter only here, after all validation passes
    set_transient( $ip_key, $attempts + 1, 10 * MINUTE_IN_SECONDS );
    $sent = wp_mail( GLC_REPORT_EMAIL, $subject, $body, $headers, $attachments );

    // Clean up temp files
    foreach ( $attachments as $f ) {
        if ( file_exists( $f ) ) @unlink( $f );
    }

    if ( ! $sent ) {
        return 'There was a problem sending your report. Please try again or email us directly at ' . GLC_REPORT_EMAIL . '.';
    }

    return 'success';
}
