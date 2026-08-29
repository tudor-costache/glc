<?php
/**
 * Template Name: Crew at Work
 *
 * Combined recent-media wall for /see-us-in-action/ — the newest photos and the
 * newest videos on one page, each linking through to the full /photos/ and
 * /videos/ galleries. (File name keeps the original see-us-in-action slug so the
 * published page's URL and template binding stay intact; the visible heading is
 * whatever the WP page is titled — rename it there, not here.)
 *
 * [glc_gallery limit] / [glc_video_gallery limit] drop the year tabs and cap the
 * grid at the most recent N (see glc_gallery_recent_grouping() in the plugin's
 * shortcodes.php). Everything else — grid, lightbox, keyboard nav — is the same
 * shared gallery engine the standalone /photos/ and /videos/ pages use.
 *
 * Reuses the .glc-photos-wrap chrome; only the per-medium section header with
 * its "All photos / All videos" link is new (.glc-media-*).
 */
get_header();

// Heading follows the WP page title so it can be renamed from the editor with
// no code change; fall back to a literal only if the page somehow has no title.
$glc_media_title = get_the_title( get_queried_object_id() );
if ( '' === trim( (string) $glc_media_title ) ) {
    $glc_media_title = 'Crew at Work';
}
?>

<div class="glc-photos-wrap glc-media-wrap">

    <h1 class="glc-photos-heading"><?php echo esc_html( $glc_media_title ); ?></h1>
    <p class="glc-photos-intro">The newest moments from the trails and the water.</p>

    <section class="glc-media-sec" aria-labelledby="glc-media-photos-h">
        <div class="glc-media-sec-head">
            <h2 id="glc-media-photos-h" class="glc-media-sec-title">Latest photos</h2>
            <a class="glc-spot-view-all" href="<?php echo esc_url( home_url( '/photos/' ) ); ?>">
                All photos &rarr;
            </a>
        </div>
        <?php echo do_shortcode( '[glc_gallery limit="20"]' ); ?>
    </section>

    <section class="glc-media-sec" aria-labelledby="glc-media-videos-h">
        <div class="glc-media-sec-head">
            <h2 id="glc-media-videos-h" class="glc-media-sec-title">Latest videos</h2>
            <a class="glc-spot-view-all" href="<?php echo esc_url( home_url( '/videos/' ) ); ?>">
                All videos &rarr;
            </a>
        </div>
        <?php echo do_shortcode( '[glc_video_gallery limit="10"]' ); ?>
    </section>

</div>

<?php get_footer();
