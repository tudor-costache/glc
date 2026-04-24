<?php
defined( 'ABSPATH' ) || exit;

// ── AJAX handler ─────────────────────────────────────────────────────────────

add_action( 'wp_ajax_glc_crew_signup',        'glc_handle_crew_signup' );
add_action( 'wp_ajax_nopriv_glc_crew_signup', 'glc_handle_crew_signup' );

function glc_handle_crew_signup() {
    if ( ! check_ajax_referer( 'glc_crew_signup', 'nonce', false ) ) {
        wp_send_json_error( 'Security check failed — please reload and try again.' );
    }

    // Honeypot
    if ( ! empty( $_POST['glc_url'] ) ) {
        wp_send_json_success( "You're on the list! We'll reach out before our next outing." );
    }

    // Rate limit: 3 per 10 min per IP
    $ip_hash  = substr( md5( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ), 0, 16 );
    $rate_key = 'glc_crew_' . $ip_hash;
    $attempts = (int) get_transient( $rate_key );
    if ( $attempts >= 3 ) {
        wp_send_json_error( 'Too many submissions — please try again in a few minutes.' );
    }

    $name  = sanitize_text_field( wp_unslash( $_POST['glc_crew_name']  ?? '' ) );
    $email = sanitize_email(      wp_unslash( $_POST['glc_crew_email'] ?? '' ) );

    if ( ! $email || ! is_email( $email ) ) {
        wp_send_json_error( 'Please enter a valid email address.' );
    }

    $to      = 'info@greatlakecleaners.ca';
    $subject = 'Crew signup' . ( $name ? " — {$name}" : " — {$email}" );
    $body    = "New crew signup via the website.\n\n"
             . 'Name:  ' . ( $name ?: '(not provided)' ) . "\n"
             . "Email: {$email}\n"
             . 'Date:  ' . wp_date( 'F j, Y \a\t g:i a T' ) . "\n";
    $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
    $headers[] = $name ? "Reply-To: {$name} <{$email}>" : "Reply-To: {$email}";

    $sent = wp_mail( $to, $subject, $body, $headers );

    if ( ! $sent ) {
        wp_send_json_error( 'Something went wrong — please email us directly at info@greatlakecleaners.ca.' );
    }

    set_transient( $rate_key, $attempts + 1, 10 * MINUTE_IN_SECONDS );
    wp_send_json_success( "You're on the list! We'll reach out before our next outing." );
}

// ── [glc_join_crew] shortcode ─────────────────────────────────────────────────

add_shortcode( 'glc_join_crew', 'glc_shortcode_join_crew' );

function glc_shortcode_join_crew() {
    static $loaded = false;
    $ajax_url = admin_url( 'admin-ajax.php' );
    $nonce    = wp_create_nonce( 'glc_crew_signup' );

    ob_start();
    ?>
    <div class="glc-join-wrap">

        <form class="glc-join-form" novalidate
              data-nonce="<?php echo esc_attr( $nonce ); ?>"
              data-action="<?php echo esc_url( $ajax_url ); ?>">

            <input type="text" name="glc_url" class="glc-join-honeypot"
                   autocomplete="off" tabindex="-1" aria-hidden="true">

            <div class="glc-form-section">
                <div class="glc-form-legend">
                    <span class="glc-form-legend-num">1</span>
                    <?php esc_html_e( 'About You', 'great-lake-cleaners' ); ?>
                </div>
                <div class="glc-field-row">
                    <div class="glc-field glc-field--half">
                        <label for="glc_crew_name">
                            <span class="glc-label-text"><?php esc_html_e( 'Name', 'great-lake-cleaners' ); ?><span class="glc-field-optional"><?php esc_html_e( 'optional', 'great-lake-cleaners' ); ?></span></span>
                        </label>
                        <input type="text" id="glc_crew_name" name="glc_crew_name"
                               autocomplete="name" placeholder="<?php esc_attr_e( 'Your name', 'great-lake-cleaners' ); ?>">
                    </div>
                    <div class="glc-field glc-field--half">
                        <label for="glc_crew_email">
                            <span class="glc-label-text"><?php esc_html_e( 'Email', 'great-lake-cleaners' ); ?><span class="glc-required" aria-hidden="true">*</span></span>
                        </label>
                        <input type="email" id="glc_crew_email" name="glc_crew_email"
                               required autocomplete="email"
                               placeholder="<?php esc_attr_e( 'your@email.com', 'great-lake-cleaners' ); ?>">
                    </div>
                </div>
            </div><!-- .glc-form-section -->

            <div class="glc-form-submit-row">
                <button type="submit" class="glc-btn-primary glc-btn-submit">
                    <?php esc_html_e( 'Join our Crew', 'great-lake-cleaners' ); ?>
                </button>
                <p class="glc-form-privacy-note">
                    <?php printf(
                        wp_kses( __( 'We\'ll only use your email to notify you of upcoming cleanups. See our <a href="%s">Privacy Policy</a>.', 'great-lake-cleaners' ), [ 'a' => [ 'href' => [] ] ] ),
                        esc_url( home_url( '/privacy-policy/' ) )
                    ); ?>
                </p>
            </div>

            <p class="glc-join-msg" role="status"></p>

        </form>

    </div><!-- .glc-join-wrap -->
    <?php if ( ! $loaded ) : $loaded = true; ?>
    <script>
    (function () {
        var frm = document.querySelector('.glc-join-form');
        if (!frm) return;
        var msg = frm.querySelector('.glc-join-msg');

        frm.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn  = frm.querySelector('[type="submit"]');
            var orig = btn.textContent;
            btn.disabled = true;
            btn.textContent = '<?php echo esc_js( __( 'Sending…', 'great-lake-cleaners' ) ); ?>';
            msg.textContent = '';
            msg.className = 'glc-join-msg';

            var fd = new FormData(frm);
            fd.append('action', 'glc_crew_signup');
            fd.append('nonce',  frm.dataset.nonce);

            fetch(frm.dataset.action, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    msg.classList.add(res.success ? 'glc-join-msg--ok' : 'glc-join-msg--err');
                    msg.textContent = res.data || (res.success ? "You're on the list!" : 'Something went wrong.');
                    if (res.success) {
                        frm.reset();
                        frm.querySelector('[type="submit"]').disabled = true;
                    }
                })
                .catch(function () {
                    msg.classList.add('glc-join-msg--err');
                    msg.textContent = 'Connection error — please try again.';
                })
                .finally(function () {
                    if (!frm.querySelector('.glc-join-msg--ok')) {
                        btn.disabled = false;
                        btn.textContent = orig;
                    }
                });
        });
    })();
    </script>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}
