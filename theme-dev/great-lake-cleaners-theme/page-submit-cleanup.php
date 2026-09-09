<?php
/**
 * Great Lake Cleaners — page-submit-cleanup.php
 *
 * WordPress Template Name: Submit a Cleanup
 *
 * Loaded automatically for the page with slug 'submit-cleanup'.
 * Renders the [glc_submit_form] shortcode inside a designed page shell
 * that matches the front-page aesthetic — no floating form on bare white.
 */

get_header();
?>

<div class="glc-fp-wrapper">
<div class="glc-submit-page-wrap">

    <!-- Page header -->
    <header class="glc-submit-page-header">
        <span class="glc-fp-label"><?php esc_html_e( 'Get Involved', 'great-lake-cleaners' ); ?></span>
        <h1 class="glc-submit-page-h1"><?php esc_html_e( 'Submit a Cleanup', 'great-lake-cleaners' ); ?></h1>
        <p class="glc-submit-page-intro">
            <?php printf(
                wp_kses(
                    /* translators: %s: link to the cleanup best-practices page */
                    __( 'Did a cleanup on a local waterway? We want to count it. Every bag removed from an Ontario riverbank is one fewer that reaches the Great Lakes. Review <a href="%s">our tips</a> to ensure you have a safe and engaging experience.', 'great-lake-cleaners' ),
                    [ 'a' => [ 'href' => [] ] ]
                ),
                esc_url( home_url( '/cleanup-best-practices/' ) )
            ); ?>
        </p>
    </header>

    <!-- Two-column layout: form left, sidebar right -->
    <div class="glc-submit-layout">

        <!-- Form column -->
        <div class="glc-submit-form-col">
            <?php echo do_shortcode( '[glc_submit_form]' ); ?>
        </div>

        <!-- Sidebar column -->
        <aside class="glc-submit-sidebar" aria-label="<?php esc_attr_e( 'Submission tips', 'great-lake-cleaners' ); ?>">

            <div class="glc-sidebar-card">
                <h2 class="glc-sidebar-heading"><?php esc_html_e( 'What happens next?', 'great-lake-cleaners' ); ?></h2>
                <ol class="glc-sidebar-steps">
                    <li>
                        <strong><?php esc_html_e( 'We review it', 'great-lake-cleaners' ); ?></strong>
                        <span><?php esc_html_e( 'We validate your submission and may reach out to thank you or review it with you.', 'great-lake-cleaners' ); ?></span>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'It goes on the map', 'great-lake-cleaners' ); ?></strong>
                        <span><?php esc_html_e( 'Your cleanup appears in the archive and the live map on our home page.', 'great-lake-cleaners' ); ?></span>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'Stats update', 'great-lake-cleaners' ); ?></strong>
                        <span><?php esc_html_e( 'Your cleanup stats are added to the community totals. Thanks for doing your part!', 'great-lake-cleaners' ); ?></span>
                    </li>
                </ol>
            </div>

            <?php if ( get_page_by_path( 'account' ) && function_exists( 'glc_account_url' ) ) : ?>
            <div class="glc-sidebar-card">
                <h2 class="glc-sidebar-heading"><?php esc_html_e( 'Do you need an account?', 'great-lake-cleaners' ); ?></h2>
                <ul class="glc-sidebar-tips">
                    <li>
                        <span class="glc-tip-icon">📧</span>
                        <span><?php esc_html_e( 'No. Your cleanups are linked to the email you enter on the form — an account is not required for them to count.', 'great-lake-cleaners' ); ?></span>
                    </li>
                    <li>
                        <span class="glc-tip-icon">🗺️</span>
                        <span><?php printf(
                            wp_kses(
                                /* translators: %s: link to the account / sign-in page */
                                __( 'A free crew account gathers every cleanup from that email onto one public profile, with your totals and a map. %s — it is a link we email you, no password.', 'great-lake-cleaners' ),
                                [ 'a' => [ 'href' => [] ] ]
                            ),
                            '<a href="' . esc_url( glc_account_url() ) . '">' . esc_html__( 'Create one or sign in', 'great-lake-cleaners' ) . '</a>'
                        ); ?></span>
                    </li>
                    <li>
                        <span class="glc-tip-icon">🔒</span>
                        <span><?php esc_html_e( 'Your email stays private — we only use it to sign you in.', 'great-lake-cleaners' ); ?></span>
                    </li>
                </ul>
            </div>
            <?php endif; ?>

            <div class="glc-sidebar-card glc-sidebar-card--tips">
                <h2 class="glc-sidebar-heading"><?php esc_html_e( 'Tips for logging', 'great-lake-cleaners' ); ?></h2>
                <ul class="glc-sidebar-tips">
                    <li>
                        <span class="glc-tip-icon"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/images/icon-scale.svg" alt="" width="20" height="20" style="vertical-align:-0.2em;flex-shrink:0;width:1.3em;height:1.3em" aria-hidden="true"></span>
                        <span><?php esc_html_e( 'Weight is most useful if you have a fish scale. A kitchen bag filled with shoreline debris (Styrofoam, plastics) is about 2–3 kg.', 'great-lake-cleaners' ); ?></span>
                    </li>
                    <li>
                        <span class="glc-tip-icon">🥫</span>
                        <span><?php esc_html_e( 'Count recycling separately from garbage: cans, bottles, and other recyclables go here.', 'great-lake-cleaners' ); ?></span>
                    </li>
                    <li>
                        <span class="glc-tip-icon"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/images/icon-timer.svg" alt="" width="20" height="20" style="vertical-align:-0.2em;flex-shrink:0;width:1.3em;height:1.3em" aria-hidden="true"></span>
                        <span><?php esc_html_e( 'If you fill in duration and number of people, person-hours are calculated automatically.', 'great-lake-cleaners' ); ?></span>
                    </li>
                    <li>
                        <span class="glc-tip-icon">📍</span>
                        <span><?php esc_html_e( 'For location, a nearby park name or street intersection is enough — or tap "Use my location" to set GPS automatically.', 'great-lake-cleaners' ); ?></span>
                    </li>
                </ul>
                <p class="glc-sidebar-note">
                    <a href="<?php echo esc_url( home_url( '/cleanup-best-practices/' ) ); ?>"><?php esc_html_e( 'Full cleanup best practices →', 'great-lake-cleaners' ); ?></a>
                </p>
            </div>

        </aside><!-- .glc-submit-sidebar -->
    </div><!-- .glc-submit-layout -->

</div><!-- .glc-submit-page-wrap -->
</div><!-- .glc-fp-wrapper -->

<?php get_footer(); ?>
