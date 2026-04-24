<?php
/**
 * Great Lake Cleaners — index.php
 *
 * WordPress fallback template. Used when no more-specific template
 * (front-page.php, archive-cleanup_event.php, single-cleanup_event.php,
 * page.php, etc.) matches the current request.
 */

get_header(); ?>

<div class="glc-container glc-index-content">

    <?php if ( have_posts() ) : ?>

        <?php if ( is_home() && ! is_front_page() ) : ?>
            <header class="glc-page-header">
                <h1 class="glc-page-title"><?php single_post_title(); ?></h1>
            </header>
        <?php endif; ?>

        <div class="glc-post-grid">
        <?php while ( have_posts() ) : the_post(); ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class( 'glc-card' ); ?>>

                <?php if ( has_post_thumbnail() ) : ?>
                    <a class="glc-card-thumb" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
                        <?php the_post_thumbnail( 'medium_large' ); ?>
                    </a>
                <?php endif; ?>

                <div class="glc-card-body">
                    <h2 class="glc-card-title">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h2>
                    <div class="glc-card-meta">
                        <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
                            <?php echo esc_html( get_the_date() ); ?>
                        </time>
                    </div>
                    <div class="glc-card-excerpt">
                        <?php the_excerpt(); ?>
                    </div>
                    <a class="glc-btn glc-btn--outline" href="<?php the_permalink(); ?>">
                        <?php esc_html_e( 'Read more', 'great-lake-cleaners' ); ?>
                    </a>
                </div>

            </article>

        <?php endwhile; ?>
        </div>

        <?php the_posts_pagination( [
            'mid_size'  => 2,
            'prev_text' => '&larr; ' . __( 'Older', 'great-lake-cleaners' ),
            'next_text' => __( 'Newer', 'great-lake-cleaners' ) . ' &rarr;',
        ] ); ?>

    <?php else : ?>

        <div class="glc-no-results">
            <h2><?php esc_html_e( 'Nothing here yet.', 'great-lake-cleaners' ); ?></h2>
            <p><?php esc_html_e( 'Check back soon — cleanups are coming.', 'great-lake-cleaners' ); ?></p>
        </div>

    <?php endif; ?>

</div><!-- .glc-container -->

<?php get_footer(); ?>
