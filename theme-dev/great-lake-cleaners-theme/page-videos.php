<?php
/**
 * Template Name: Videos
 * Template for /videos/ — renders the [glc_video_gallery] shortcode with page chrome.
 * Mirrors page-photos.php; the .glc-videos-* wrapper classes share their rules
 * with the .glc-photos-* ones in style.css.
 */
get_header(); ?>

<div class="glc-videos-wrap">
    <h1 class="glc-videos-heading">Videos</h1>
    <p class="glc-videos-intro">Out on the water and along the banks:</p>
    <?php echo do_shortcode( '[glc_video_gallery]' ); ?>
</div>

<?php get_footer();
