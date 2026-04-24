<?php
/**
 * Template Name: Photos
 * Template for /photos/ — renders the [glc_gallery] shortcode with page chrome.
 */
get_header(); ?>

<div class="glc-photos-wrap">
    <h1 class="glc-photos-heading">Photos</h1>
    <p class="glc-photos-intro">See how we make a difference:</p>
    <?php echo do_shortcode( '[glc_gallery]' ); ?>
</div>

<?php get_footer();
