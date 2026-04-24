<?php
/**
 * Great Lake Cleaners — page-report-issue.php
 *
 * WordPress Template Name: Report an Issue
 *
 * Loaded automatically for the page with slug 'report-issue'.
 * Renders the [glc_report_form] shortcode inside a designed page shell.
 *
 * The page has two stages:
 *   1. Triage — routes city issues to their municipality's tool, waterway issues to the form.
 *   2. Form  — collects waterway issue details, emailed to info@greatlakecleaners.ca.
 */

get_header();
?>

<div class="glc-fp-wrapper">
<div class="glc-submit-page-wrap glc-report-page-wrap">

    <!-- Page header -->
    <header class="glc-submit-page-header">
        <span class="glc-fp-label"><?php esc_html_e( 'Waterways', 'great-lake-cleaners' ); ?></span>
        <h1 class="glc-submit-page-h1"><?php esc_html_e( 'Report an Issue', 'great-lake-cleaners' ); ?></h1>
        <p class="glc-submit-page-intro">
            <?php esc_html_e( 'We\'re all doing our part to keep our waterways clean. If you see something that shouldn\'t be there, getting it documented is the first step to getting it out. Our municipalities have resources for cleaning up illegal dumping in city parks and streets — and for waterway-specific issues, we can help or connect you with the right organization.', 'great-lake-cleaners' ); ?>
        </p>
    </header>

    <!-- Two-column layout: form left, sidebar right -->
    <div class="glc-submit-layout">

        <!-- Form / triage column -->
        <div class="glc-submit-form-col">
            <?php echo do_shortcode( '[glc_report_form]' ); ?>
        </div>

        <!-- Sidebar column -->
        <aside class="glc-submit-sidebar" aria-label="<?php esc_attr_e( 'Reporting guidance', 'great-lake-cleaners' ); ?>">

            <div class="glc-sidebar-card">
                <h2 class="glc-sidebar-heading"><?php esc_html_e( 'What happens next?', 'great-lake-cleaners' ); ?></h2>
                <ol class="glc-sidebar-steps">
                    <li>
                        <strong><?php esc_html_e( 'We log it', 'great-lake-cleaners' ); ?></strong>
                        <span><?php esc_html_e( 'Every waterway report goes into our notes.', 'great-lake-cleaners' ); ?></span>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'We visit', 'great-lake-cleaners' ); ?></strong>
                        <span><?php esc_html_e( 'We prioritize reported issues on our next outing to that waterway and assess what\'s needed.', 'great-lake-cleaners' ); ?></span>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'We escalate when needed', 'great-lake-cleaners' ); ?></strong>
                        <span><?php esc_html_e( 'Hazardous material or large-scale dumping gets flagged to the GRCA or the city.', 'great-lake-cleaners' ); ?></span>
                    </li>
                </ol>
            </div>

            <div class="glc-sidebar-card glc-sidebar-card--tips">
                <h2 class="glc-sidebar-heading"><?php esc_html_e( 'Tips for a good report', 'great-lake-cleaners' ); ?></h2>
                <ul class="glc-sidebar-tips">
                    <li>
                        <span class="glc-tip-icon">📍</span>
                        <span><?php esc_html_e( 'Location is the most important detail — a bridge name, park, or street intersection narrows it down fast.', 'great-lake-cleaners' ); ?></span>
                    </li>
                    <li>
                        <span class="glc-tip-icon">📸</span>
                        <span><?php esc_html_e( 'A photo of the issue and one of the surroundings is worth a thousand words. Shoot before you leave the spot.', 'great-lake-cleaners' ); ?></span>
                    </li>
                    <li>
                        <span class="glc-tip-icon">⚠️</span>
                        <span>
                            <?php esc_html_e( 'If the issue looks like an active spill or chemical hazard, call the', 'great-lake-cleaners' ); ?>
                            <strong><?php esc_html_e( 'GRCA Spills Line', 'great-lake-cleaners' ); ?></strong>
                            <?php esc_html_e( 'at', 'great-lake-cleaners' ); ?>
                            <a href="tel:1-800-265-6613">1-800-265-6613</a>
                            <?php esc_html_e( '(24 hr).', 'great-lake-cleaners' ); ?>
                        </span>
                    </li>
                    <li>
                        <span class="glc-tip-icon">🐾</span>
                        <span><?php esc_html_e( 'Don\'t put yourself at risk — report from a safe distance. Notes on what you couldn\'t see up close are still useful.', 'great-lake-cleaners' ); ?></span>
                    </li>
                </ul>
            </div>

        </aside><!-- .glc-submit-sidebar -->
    </div><!-- .glc-submit-layout -->

</div><!-- .glc-report-page-wrap -->
</div><!-- .glc-fp-wrapper -->

<?php get_footer(); ?>
