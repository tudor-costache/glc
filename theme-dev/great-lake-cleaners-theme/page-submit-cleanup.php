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
            <?php esc_html_e( 'Did a cleanup on a local waterway? We want to count it. Every bag removed from an Ontario riverbank is one fewer that reaches the Great Lakes.', 'great-lake-cleaners' ); ?>
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

            <div class="glc-sidebar-card glc-sidebar-card--tips">
                <h2 class="glc-sidebar-heading"><?php esc_html_e( 'Tips for logging', 'great-lake-cleaners' ); ?></h2>
                <ul class="glc-sidebar-tips">
                    <li>
                        <span class="glc-tip-icon"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/images/icon-scale.svg" alt="" width="20" height="20" style="vertical-align:-0.2em;flex-shrink:0;width:1.3em;height:1.3em" aria-hidden="true"></span>
                        <span><?php esc_html_e( 'Weight is most useful if you have a fish scale. A filled kitchen bag is roughly 5–8 kg.', 'great-lake-cleaners' ); ?></span>
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
            </div>

        </aside><!-- .glc-submit-sidebar -->
    </div><!-- .glc-submit-layout -->

</div><!-- .glc-submit-page-wrap -->
</div><!-- .glc-fp-wrapper -->

<?php get_footer(); ?>
