<?php
/**
 * The template for displaying single excursion blog posts
 *
 * @package Life_Travel
 */

get_header();

// Verify if user has completed order for this excursion
$can_view_exclusive = life_travel_verify_excursion_participant(get_the_ID());
?>

<main id="primary" class="site-main single-excursion">
    <div class="container">
        <?php
        while (have_posts()) :
            the_post();
            ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <header class="entry-header">
                    <?php the_title('<h1 class="entry-title">', '</h1>'); ?>

                    <div class="entry-meta">
                        <span class="posted-on">
                            <?php echo esc_html__('Posté le ', 'life-travel') . get_the_date(); ?>
                        </span>
                        <span class="byline">
                            <?php echo esc_html__('par ', 'life-travel') . get_the_author(); ?>
                        </span>
                        <?php
                        // Display categories
                        $categories_list = get_the_category_list(', ');
                        if ($categories_list) {
                            echo '<span class="cat-links">' . esc_html__('dans ', 'life-travel') . $categories_list . '</span>';
                        }
                        ?>
                    </div><!-- .entry-meta -->
                </header><!-- .entry-header -->

                <?php if (has_post_thumbnail()) : ?>
                    <div class="post-thumbnail">
                        <?php the_post_thumbnail('life-travel-large'); ?>
                    </div><!-- .post-thumbnail -->
                <?php endif; ?>

                <div class="entry-content">
                    <?php the_content(); ?>
                    
                    <?php
                    // Display video if available (ACF field)
                    $video_url = get_field('excursion_video');
                    if ($video_url) {
                        echo '<div class="excursion-video">';
                        echo '<h3>' . esc_html__('Vidéo de l\'excursion', 'life-travel') . '</h3>';
                        
                        // Check if it's YouTube or Vimeo URL
                        if (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false || 
                            strpos($video_url, 'vimeo.com') !== false) {
                            echo wp_oembed_get($video_url);
                        } elseif (substr($video_url, -4) === '.mp4') {
                            // MP4 video
                            echo '<video controls><source src="' . esc_url($video_url) . '" type="video/mp4"></video>';
                        }
                        
                        echo '</div>';
                    }
                    
                    // Display public gallery if available (ACF field)
                    $public_gallery = get_field('excursion_public_gallery');
                    if ($public_gallery) {
                        echo '<div class="excursion-gallery">';
                        echo '<h3>' . esc_html__('Galerie publique', 'life-travel') . '</h3>';
                        echo '<div class="gallery-grid">';
                        
                        foreach ($public_gallery as $image) {
                            echo '<div class="gallery-item">';
                            echo '<a href="' . esc_url($image['url']) . '" data-lightbox="gallery">';
                            echo '<img src="' . esc_url($image['sizes']['medium']) . '" alt="' . esc_attr($image['alt']) . '">';
                            echo '</a>';
                            echo '</div>';
                        }
                        
                        echo '</div>';
                        echo '</div>';
                    }
                    
                    // Display exclusive gallery for verified participants only
                    if ($can_view_exclusive) {
                        $exclusive_gallery = get_field('excursion_exclusive_gallery');
                        if ($exclusive_gallery) {
                            echo '<div class="excursion-exclusive">';
                            echo '<h3>' . esc_html__('Galerie exclusive (réservée aux participants)', 'life-travel') . '</h3>';
                            echo do_shortcode('[lt_exclusive_gallery id="' . get_the_ID() . '"]');
                            echo '</div>';
                        }
                    }
                    ?>
                </div><!-- .entry-content -->

                <footer class="entry-footer">
                    <?php
                    // Display tags
                    $tags_list = get_the_tag_list('', ', ');
                    if ($tags_list) {
                        echo '<div class="tags-links">' . esc_html__('Tags: ', 'life-travel') . $tags_list . '</div>';
                    }
                    ?>
                    
                    <div class="excursion-share">
                        <h3><?php echo esc_html__('Partagez cette excursion', 'life-travel'); ?></h3>
                        <div class="share-buttons">
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo esc_url(get_permalink()); ?>" target="_blank" class="share-facebook">
                                <span class="screen-reader-text"><?php echo esc_html__('Partager sur Facebook', 'life-travel'); ?></span>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2C6.477 2 2 6.477 2 12C2 17.523 6.477 22 12 22C17.523 22 22 17.523 22 12C22 6.477 17.523 2 12 2Z" fill="#1877F2"/>
                                    <path d="M15.5 8.5H13.5C13.5 8.5 13.5 7.5 13.5 7C13.5 6.5 14 6 14.5 6H15.5V4H13C11.5 4 10.5 5.5 10.5 7V8.5H8.5V11H10.5V20H13.5V11H15.5V8.5Z" fill="white"/>
                                </svg>
                            </a>
                            <a href="https://twitter.com/intent/tweet?url=<?php echo esc_url(get_permalink()); ?>&text=<?php echo esc_attr(get_the_title()); ?>" target="_blank" class="share-twitter">
                                <span class="screen-reader-text"><?php echo esc_html__('Partager sur Twitter', 'life-travel'); ?></span>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2C6.477 2 2 6.477 2 12C2 17.523 6.477 22 12 22C17.523 22 22 17.523 22 12C22 6.477 17.523 2 12 2Z" fill="#1DA1F2"/>
                                    <path d="M19 7.5C18.4 7.8 17.8 7.9 17.1 8C17.8 7.6 18.3 7 18.5 6.2C17.9 6.6 17.2 6.8 16.5 7C15.9 6.4 15.1 6 14.2 6C12.4 6 11 7.4 11 9.2C11 9.5 11 9.7 11.1 9.9C8.3 9.8 5.9 8.6 4.2 6.7C3.9 7.1 3.8 7.6 3.8 8.1C3.8 9.1 4.3 9.9 5.1 10.4C4.6 10.4 4.1 10.3 3.7 10.1C3.7 10.1 3.7 10.1 3.7 10.2C3.7 11.7 4.8 13 6.2 13.2C6 13.3 5.7 13.3 5.4 13.3C5.2 13.3 5 13.3 4.8 13.2C5.2 14.4 6.3 15.3 7.6 15.3C6.5 16.1 5.2 16.5 3.7 16.5C3.5 16.5 3.2 16.5 3 16.5C4.3 17.3 5.9 17.8 7.5 17.8C14.1 17.8 17.8 12.3 17.8 7.7C17.8 7.6 17.8 7.4 17.8 7.3C18.5 6.9 19 6.3 19.5 5.7" fill="white"/>
                                </svg>
                            </a>
                            <a href="https://api.whatsapp.com/send?text=<?php echo esc_url(get_permalink()); ?>" target="_blank" class="share-whatsapp">
                                <span class="screen-reader-text"><?php echo esc_html__('Partager sur WhatsApp', 'life-travel'); ?></span>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2C6.477 2 2 6.477 2 12C2 17.523 6.477 22 12 22C17.523 22 22 17.523 22 12C22 6.477 17.523 2 12 2Z" fill="#25D366"/>
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M16.5 13.5C16.3 13.4 15.3 12.9 15.1 12.8C14.9 12.7 14.8 12.7 14.7 12.9C14.6 13.1 14.2 13.5 14.1 13.6C14 13.7 13.9 13.7 13.7 13.6C13.5 13.5 12.8 13.3 12.1 12.6C11.5 12.1 11.1 11.5 11 11.3C10.9 11.1 11 11 11.1 10.9C11.2 10.8 11.3 10.7 11.4 10.6C11.5 10.5 11.5 10.4 11.6 10.3C11.7 10.2 11.7 10.1 11.6 10C11.6 9.9 11.1 8.9 11 8.5C10.9 8.1 10.8 8.1 10.7 8.1C10.6 8.1 10.5 8.1 10.4 8.1C10.3 8.1 10.1 8.1 9.9 8.3C9.7 8.5 9.2 9 9.2 10C9.2 11 9.9 12 10 12.1C10.1 12.2 11.1 13.7 12.6 14.2C14.1 14.7 14.1 14.5 14.4 14.5C14.7 14.5 15.5 14 15.6 13.6C15.7 13.2 15.7 12.8 15.7 12.7C15.6 12.7 15.6 12.6 15.5 12.6L16.5 13.5ZM12.2 17.5H12.1C11 17.5 9.9 17.2 8.9 16.6L8.7 16.5L6.7 17.1L7.3 15.2L7.2 15C6.5 14 6.2 12.8 6.2 11.6C6.2 8.5 8.7 6 11.7 6C13.2 6 14.6 6.6 15.6 7.6C16.6 8.6 17.2 10 17.2 11.5C17.3 14.6 14.8 17.1 12.2 17.5ZM16.4 7.8C15.2 6.6 13.5 6 11.7 6C8.2 6 5.3 8.9 5.3 12.4C5.3 13.8 5.7 15.1 6.4 16.2L5.5 19L8.4 18.1C9.5 18.7 10.6 19 11.8 19H11.9C15.4 19 18.3 16.1 18.3 12.6C18.2 10.7 17.6 9 16.4 7.8Z" fill="white"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </footer><!-- .entry-footer -->
                
                <?php
                // Comments section with restricted access
                if (comments_open() || get_comments_number()) {
                    comments_template();
                }
                ?>
            </article><!-- #post-<?php the_ID(); ?> -->
            <?php
        endwhile;
        ?>
    </div><!-- .container -->
</main><!-- #main -->

<?php
get_footer();
