<?php
/**
 * Template Name: Galerie d'images Life Travel
 * Description: Template pour afficher une galerie d'images avec options avancées.
 * 
 * @package Life Travel
 * @version 1.0.0
 */

get_header();
?>

<main id="primary" class="site-main">
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        
        <?php
        // Bannière de page avec image à la une ou image par défaut
        $banner_image = has_post_thumbnail() ? get_the_post_thumbnail_url(get_the_ID(), 'full') : plugins_url('assets/img/backgrounds/default-banner.jpg', dirname(__FILE__));
        ?>
        
        <header class="page-banner" <?php echo $banner_image ? 'style="background-image: url(' . esc_url($banner_image) . ');"' : ''; ?>>
            <div class="container">
                <h1 class="page-title"><?php the_title(); ?></h1>
                <?php if (function_exists('get_field') && get_field('subtitle')) : ?>
                    <p class="page-description"><?php echo esc_html(get_field('subtitle')); ?></p>
                <?php endif; ?>
            </div>
        </header>

        <div class="entry-content container">
            <?php the_content(); ?>
            
            <?php
            // Vérifier si ACF est activé et si des galeries sont définies
            if (function_exists('get_field') && have_rows('galleries')) :
                while (have_rows('galleries')) : the_row();
                    $gallery_title = get_sub_field('title');
                    $gallery_description = get_sub_field('description');
                    $gallery_images = get_sub_field('images');
                    $columns = get_sub_field('columns') ?: 3;
                    
                    if ($gallery_images) :
                        // Convertir les images en IDs pour le shortcode
                        $image_ids = array();
                        foreach ($gallery_images as $image) {
                            $image_ids[] = $image['ID'];
                        }
                        
                        echo '<div class="life-travel-gallery-section">';
                        
                        if ($gallery_title) {
                            echo '<h2 class="gallery-title">' . esc_html($gallery_title) . '</h2>';
                        }
                        
                        if ($gallery_description) {
                            echo '<div class="gallery-description">' . wp_kses_post($gallery_description) . '</div>';
                        }
                        
                        // Utiliser le shortcode pour afficher la galerie
                        echo do_shortcode('[life_travel_gallery ids="' . implode(',', $image_ids) . '" columns="' . esc_attr($columns) . '" captions="true" animation="fade"]');
                        
                        echo '</div>';
                    endif;
                endwhile;
            else :
                // Exemple de galerie statique pour démonstration
                ?>
                <div class="life-travel-gallery-section">
                    <h2 class="gallery-title">Exemple de galerie d'images</h2>
                    <div class="gallery-description">
                        <p>Voici un exemple de galerie d'images qui peut être personnalisée selon vos besoins.</p>
                    </div>
                    
                    <?php
                    // Récupérer quelques images récentes de la médiathèque
                    $recent_images = get_posts(array(
                        'post_type' => 'attachment',
                        'post_mime_type' => 'image',
                        'post_status' => 'inherit',
                        'posts_per_page' => 6,
                        'orderby' => 'date',
                        'order' => 'DESC',
                    ));
                    
                    if ($recent_images) {
                        $image_ids = wp_list_pluck($recent_images, 'ID');
                        echo do_shortcode('[life_travel_gallery ids="' . implode(',', $image_ids) . '" columns="3" captions="true" animation="fade"]');
                    } else {
                        echo '<p>Aucune image n\'est disponible dans la médiathèque. Veuillez en téléverser quelques-unes pour voir la galerie en action.</p>';
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <div class="life-travel-video-section">
                <h2>Découvrez nos excursions en vidéo</h2>
                <p>Plongez au cœur de l'aventure avec notre sélection de vidéos exclusives.</p>
                
                <?php
                // Exemple d'utilisation du shortcode vidéo
                echo do_shortcode('[life_travel_video youtube="dQw4w9WgXcQ" width="100%" height="450" poster="' . plugins_url('assets/img/backgrounds/video-placeholder.jpg', dirname(__FILE__)) . '" class="featured-video"]');
                ?>
                
                <div class="video-info">
                    <h3>Notre philosophie de voyage</h3>
                    <p>Chez Life Travel, nous croyons que chaque voyage est une opportunité de découvrir non seulement de nouveaux paysages, mais aussi de nouvelles perspectives sur le monde et sur soi-même.</p>
                    
                    <div class="video-features">
                        <div class="feature">
                            <?php echo do_shortcode('[life_travel_icon name="culture" color="#0073B2" size="large"]'); ?>
                            <h4>Immersion culturelle</h4>
                            <p>Des rencontres authentiques avec les communautés locales.</p>
                        </div>
                        <div class="feature">
                            <?php echo do_shortcode('[life_travel_icon name="mountain" color="#0073B2" size="large"]'); ?>
                            <h4>Aventures naturelles</h4>
                            <p>Des paysages à couper le souffle et des activités en plein air.</p>
                        </div>
                        <div class="feature">
                            <?php echo do_shortcode('[life_travel_icon name="forest" color="#0073B2" size="large"]'); ?>
                            <h4>Écotourisme</h4>
                            <p>Des voyages responsables respectant l'environnement.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="brand-showcase">
                <h2>Notre marque</h2>
                <p>Le logo Life Travel représente notre engagement envers des voyages authentiques et significatifs.</p>
                
                <div class="logo-showcase">
                    <div class="logo-variant">
                        <h3>Version couleur</h3>
                        <?php echo do_shortcode('[life_travel_logo version="color" width="320" link="no"]'); ?>
                    </div>
                    <div class="logo-variant dark-bg">
                        <h3>Version blanche</h3>
                        <?php echo do_shortcode('[life_travel_logo version="white" width="320" link="no"]'); ?>
                    </div>
                </div>
            </div>
        </div>
    </article>
</main>

<?php
get_footer();
