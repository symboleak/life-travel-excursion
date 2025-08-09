<?php
/**
 * The template for displaying all single posts
 *
 * @package Life_Travel
 */

get_header();

$is_excursion_post = has_category('excursion');
$can_access_exclusive = $is_excursion_post ? life_travel_verify_excursion_participant(get_the_ID()) : true;
?>

<main id="main" class="site-main">
    <?php while (have_posts()) : the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <header class="entry-header">
                <?php if (has_post_thumbnail()) : ?>
                    <div class="post-thumbnail">
                        <?php the_post_thumbnail('life-travel-large'); ?>
                    </div>
                <?php endif; ?>

                <div class="entry-meta">
                    <?php
                    // Display category
                    $categories = get_the_category();
                    if (!empty($categories)) {
                        echo '<span class="cat-links">';
                        echo esc_html($categories[0]->name);
                        echo '</span>';
                    }
                    ?>
                    <span class="posted-on">
                        <?php echo get_the_date(); ?>
                    </span>
                </div>

                <h1 class="entry-title"><?php the_title(); ?></h1>
                
                <?php if ($is_excursion_post) : ?>
                    <div class="excursion-meta">
                        <?php 
                        // Display excursion date if available
                        $excursion_date = get_field('excursion_date_completed');
                        if ($excursion_date) : 
                        ?>
                            <div class="excursion-date">
                                <span class="label"><?php _e('Date de l\'excursion:', 'life-travel'); ?></span>
                                <span class="value"><?php echo esc_html($excursion_date); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </header>

            <div class="entry-content">
                <?php
                // Show excursion highlights if available
                if ($is_excursion_post && have_rows('excursion_blog_highlights')) : 
                ?>
                    <div class="excursion-highlights">
                        <h3><?php _e('Points forts', 'life-travel'); ?></h3>
                        <div class="excursion-highlights-list">
                            <?php while (have_rows('excursion_blog_highlights')) : the_row(); ?>
                                <div class="highlight-item">
                                    <div class="highlight-icon">
                                        <?php $icon = get_sub_field('icon') ? get_sub_field('icon') : 'mountain'; ?>
                                        <img src="<?php echo esc_url(LIFE_TRAVEL_URI . '/assets/images/icons/' . $icon . '.svg'); ?>" alt="<?php echo esc_attr($icon); ?>">
                                    </div>
                                    <div class="highlight-content">
                                        <p><?php echo esc_html(get_sub_field('text')); ?></p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php 
                endif;
                
                // Display video if available for excursion posts
                if ($is_excursion_post) {
                    $video_url = get_field('excursion_video');
                    if ($video_url) {
                        echo '<div class="excursion-video-container">';
                        // Check if it's a YouTube or Vimeo URL
                        if (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
                            // YouTube embed
                            echo wp_oembed_get($video_url);
                        } elseif (strpos($video_url, 'vimeo.com') !== false) {
                            // Vimeo embed
                            echo wp_oembed_get($video_url);
                        } else {
                            // Direct video file
                            echo do_shortcode('[video src="' . esc_url($video_url) . '" width="100%" height="auto"]');
                        }
                        echo '</div>';
                    }
                }
                
                // Main content
                the_content();
                
                // Public gallery for all excursion posts
                if ($is_excursion_post) {
                    $public_gallery = get_field('excursion_public_gallery');
                    if ($public_gallery) {
                        echo '<div class="public-gallery">';
                        echo '<h3>' . __('Galerie de l\'excursion', 'life-travel') . '</h3>';
                        echo '<div class="gallery">';
                        foreach ($public_gallery as $image) {
                            echo '<figure class="gallery-item">';
                            echo '<a href="' . esc_url($image['url']) . '" data-lightbox="public-gallery">';
                            echo '<img src="' . esc_url($image['sizes']['medium']) . '" alt="' . esc_attr($image['alt']) . '">';
                            echo '</a>';
                            echo '</figure>';
                        }
                        echo '</div>';
                        echo '</div>';
                    }
                }
                
                // Exclusive content for excursion participants
                if ($is_excursion_post) {
                    echo '<div class="exclusive-content">';
                    echo '<h3>' . __('Contenu Exclusif Participants', 'life-travel') . '</h3>';
                    
                    if ($can_access_exclusive) {
                        // User can access exclusive content
                        echo do_shortcode('[lt_exclusive_gallery id="' . get_the_ID() . '"]');
                    } else {
                        // User cannot access exclusive content
                        echo '<div class="exclusive-content-restricted">';
                        echo '<p>' . __('Ce contenu est réservé aux participants de l\'excursion.', 'life-travel') . '</p>';
                        if (!is_user_logged_in()) {
                            echo '<p><a href="' . esc_url(wp_login_url(get_permalink())) . '" class="button">' . 
                                __('Se connecter', 'life-travel') . '</a></p>';
                        } else {
                            echo '<p><a href="' . esc_url(get_permalink(wc_get_page_id('shop'))) . '" class="button">' . 
                                __('Découvrir nos excursions', 'life-travel') . '</a></p>';
                        }
                        echo '</div>';
                    }
                    echo '</div>';
                }
                ?>
            </div>

            <footer class="entry-footer">
                <?php
                // Show tags
                $tags_list = get_the_tag_list('', ', ');
                if ($tags_list) {
                    echo '<div class="tags-links">';
                    echo '<span class="label">' . __('Tags:', 'life-travel') . '</span> ';
                    echo $tags_list;
                    echo '</div>';
                }
                
                // Show related posts for excursion category
                if ($is_excursion_post) {
                    $related_posts = new WP_Query(array(
                        'category_name' => 'excursion',
                        'posts_per_page' => 3,
                        'post__not_in' => array(get_the_ID()),
                        'orderby' => 'rand',
                    ));
                    
                    if ($related_posts->have_posts()) {
                        echo '<div class="related-posts">';
                        echo '<h3>' . __('Autres excursions', 'life-travel') . '</h3>';
                        echo '<div class="posts-grid">';
                        
                        while ($related_posts->have_posts()) {
                            $related_posts->the_post();
                            echo '<article class="post-card">';
                            if (has_post_thumbnail()) {
                                echo '<a href="' . get_permalink() . '" class="post-thumbnail">';
                                the_post_thumbnail('life-travel-medium');
                                echo '</a>';
                            }
                            echo '<div class="post-card-content">';
                            echo '<h4><a href="' . get_permalink() . '">' . get_the_title() . '</a></h4>';
                            echo '<div class="post-meta">';
                            echo '<span class="post-date">' . get_the_date() . '</span>';
                            echo '</div>';
                            echo '</div>';
                            echo '</article>';
                        }
                        
                        echo '</div>';
                        echo '</div>';
                        
                        wp_reset_postdata();
                    }
                }
                ?>
                
                <div class="post-navigation">
                    <?php
                    the_post_navigation(array(
                        'prev_text' => '<span class="nav-subtitle">' . __('Précédent:', 'life-travel') . '</span> <span class="nav-title">%title</span>',
                        'next_text' => '<span class="nav-subtitle">' . __('Suivant:', 'life-travel') . '</span> <span class="nav-title">%title</span>',
                    ));
                    ?>
                </div>
            </footer>
        </article>

        <?php
        // Only show comments if it's an excursion post and user can access, or if it's not an excursion post
        if (!$is_excursion_post || $can_access_exclusive) {
            // If comments are open or we have at least one comment, load up the comment template.
            if (comments_open() || get_comments_number()) {
                comments_template();
            }
        }
        ?>
    <?php endwhile; ?>
</main>

<?php
get_sidebar();
get_footer();
