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
 * its "All photos / All videos" link is new (.glc-media-*). Each header row is
 * followed by the same .glc-wave-divider the front page stacks between its
 * sections, so this page keeps the site-wide wave rule instead of a plain line.
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
    <p class="glc-photos-intro">See snapshots of our latest moments from the trails and the water we captured during our recent cleanups.</p>

    <section class="glc-media-sec" aria-labelledby="glc-media-photos-h">
        <div class="glc-media-sec-head">
            <h2 id="glc-media-photos-h" class="glc-media-sec-title">Latest photos</h2>
            <a class="glc-spot-view-all" href="<?php echo esc_url( home_url( '/photos/' ) ); ?>">
                All photos &rarr;
            </a>
        </div>
        <div class="glc-wave-divider" aria-hidden="true">
            <svg viewBox="0 0 1200 22" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0,11 C150,3 300,19 450,11 C600,3 750,19 900,11 C1050,3 1200,19 1200,11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <path d="M0,16 C150,8 300,24 450,16 C600,8 750,24 900,16 C1050,8 1200,24 1200,16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity="0.45"/>
            </svg>
        </div>
        <?php echo do_shortcode( '[glc_gallery limit="24"]' ); ?>
    </section>

    <section class="glc-media-sec" aria-labelledby="glc-media-videos-h">
        <div class="glc-media-sec-head">
            <h2 id="glc-media-videos-h" class="glc-media-sec-title">Latest videos</h2>
            <a class="glc-spot-view-all" href="<?php echo esc_url( home_url( '/videos/' ) ); ?>">
                All videos &rarr;
            </a>
        </div>
        <div class="glc-wave-divider" aria-hidden="true">
            <svg viewBox="0 0 1200 22" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0,11 C150,3 300,19 450,11 C600,3 750,19 900,11 C1050,3 1200,19 1200,11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <path d="M0,16 C150,8 300,24 450,16 C600,8 750,24 900,16 C1050,8 1200,24 1200,16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity="0.45"/>
            </svg>
        </div>
        <?php echo do_shortcode( '[glc_video_gallery limit="10"]' ); ?>
    </section>

</div>

<?php get_footer();
