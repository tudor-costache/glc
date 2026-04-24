<?php
/**
 * Great Lake Cleaners — page.php
 *
 * Template for standard WordPress pages (About, Donate, Privacy Policy, etc.)
 * Uses the same centred narrow-column layout as the single event pages.
 */

get_header();

if ( have_posts() ) :
    the_post(); ?>

<div class="glc-fp-wrapper">
<div class="glc-page-wrap">

    <article id="post-<?php the_ID(); ?>" <?php post_class( 'glc-page-article' ); ?>>

        <header class="glc-page-header">
            <h1 class="glc-page-h1"><?php the_title(); ?></h1>
        </header>

        <?php if ( has_post_thumbnail() ) : ?>
        <div class="glc-page-thumb">
            <?php the_post_thumbnail( 'large', [ 'class' => 'glc-page-thumb-img' ] ); ?>
        </div>
        <?php endif; ?>

        <div class="glc-page-body">
            <?php the_content(); ?>
        </div>

    </article>

</div>
</div>

<?php endif; ?>
<?php get_footer(); ?>
