<?php
/**
 * Great Lake Cleaners — page-account.php
 *
 * Template Name: Account
 *
 * Shell for the page with slug 'account'. Renders [glc_account], which handles
 * all three states itself: signed out (one email field), just-signed-in
 * (welcome plus any claim result), and the dashboard.
 *
 * Mirrors page-submit-cleanup.php's two-column layout so the account page reads
 * as part of the same "Get Involved" family rather than as a bolted-on login.
 */

get_header();

$acct_user = function_exists( 'glc_current_cleaner' ) ? glc_current_cleaner() : null;
?>

<div class="glc-fp-wrapper">
<div class="glc-submit-page-wrap">

    <header class="glc-submit-page-header">
        <span class="glc-fp-label"><?php esc_html_e( 'Get Involved', 'great-lake-cleaners' ); ?></span>
        <h1 class="glc-submit-page-h1">
            <?php echo $acct_user
                ? esc_html__( 'Your Account', 'great-lake-cleaners' )
                : esc_html__( 'Sign In', 'great-lake-cleaners' ); ?>
        </h1>
        <p class="glc-submit-page-intro">
            <?php if ( $acct_user ) : ?>
            <?php esc_html_e( 'Your display name, your profile address, and the cleanups still waiting to be reviewed.', 'great-lake-cleaners' ); ?>
            <?php else : ?>
            <?php esc_html_e( 'An account collects your cleanups on one public page, with your totals and a map of where they happened. It is completely optional — submitting without one works exactly the same, and always will.', 'great-lake-cleaners' ); ?>
            <?php endif; ?>
        </p>
    </header>

    <div class="glc-submit-layout">

        <div class="glc-submit-form-col">
            <?php echo do_shortcode( '[glc_account]' ); ?>
        </div>

        <aside class="glc-submit-sidebar" aria-label="<?php esc_attr_e( 'About accounts', 'great-lake-cleaners' ); ?>">

            <div class="glc-sidebar-card">
                <h2 class="glc-sidebar-heading"><?php esc_html_e( 'How signing in works', 'great-lake-cleaners' ); ?></h2>
                <ol class="glc-sidebar-steps">
                    <li>
                        <strong><?php esc_html_e( 'You enter your email', 'great-lake-cleaners' ); ?></strong>
                        <span><?php esc_html_e( 'One field, whether or not you have been here before.', 'great-lake-cleaners' ); ?></span>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'We email you a link', 'great-lake-cleaners' ); ?></strong>
                        <span><?php esc_html_e( 'It signs you in, works once, and expires after 15 minutes.', 'great-lake-cleaners' ); ?></span>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'That is it', 'great-lake-cleaners' ); ?></strong>
                        <span><?php esc_html_e( 'There is no password to choose, forget, or have stolen.', 'great-lake-cleaners' ); ?></span>
                    </li>
                </ol>
            </div>

            <div class="glc-sidebar-card glc-sidebar-card--tips">
                <h2 class="glc-sidebar-heading"><?php esc_html_e( 'What an account changes', 'great-lake-cleaners' ); ?></h2>
                <ul class="glc-sidebar-tips">
                    <li>
                        <span class="glc-tip-icon">📍</span>
                        <span><?php esc_html_e( 'Cleanups you submit while signed in appear on your profile once reviewed.', 'great-lake-cleaners' ); ?></span>
                    </li>
                    <li>
                        <span class="glc-tip-icon">🔗</span>
                        <span><?php esc_html_e( 'Cleanups you already submitted under the same email are added automatically the first time you sign in.', 'great-lake-cleaners' ); ?></span>
                    </li>
                    <li>
                        <span class="glc-tip-icon">🙈</span>
                        <span><?php esc_html_e( 'You can hide the profile at any time, or post a single cleanup without credit.', 'great-lake-cleaners' ); ?></span>
                    </li>
                    <li>
                        <span class="glc-tip-icon">🗑️</span>
                        <span><?php esc_html_e( 'Deleting the account removes the credit, never the cleanup — the public record and the site totals stay intact.', 'great-lake-cleaners' ); ?></span>
                    </li>
                </ul>
            </div>

        </aside><!-- .glc-submit-sidebar -->
    </div><!-- .glc-submit-layout -->

</div><!-- .glc-submit-page-wrap -->
</div><!-- .glc-fp-wrapper -->

<?php get_footer(); ?>
