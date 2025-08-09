<?php
/**
 * WooCommerce Hooks and Customizations
 * 
 * Ce fichier contient toutes les personnalisations spécifiques à WooCommerce
 * pour le thème Life Travel, optimisé pour les produits de type excursion/tour.
 *
 * @package Life_Travel
 * @since 1.0.0
 * @modified 1.1.0 - Ajout d'optimisations pour les connexions à faible bande passante
 * @modified 1.2.0 - Support multilingue avec TranslatePress
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Optimisations des performances WooCommerce
 */
function life_travel_wc_performance_optimizations() {
    // Désactiver les styles WooCommerce par défaut pour utiliser nos propres styles optimisés
    add_filter('woocommerce_enqueue_styles', '__return_empty_array');
    
    // Charger les styles du sélecteur de langue
    add_action('wp_enqueue_scripts', 'life_travel_load_language_switcher_styles');

    // Optimiser les requêtes WooCommerce sur les pages non-boutique
    if (!function_exists('is_woocommerce') || !is_woocommerce()) {
        remove_action('wp_footer', 'woocommerce_demo_store');
    }

    // Désactiver les scripts WooCommerce sur les pages où ils ne sont pas nécessaires
    add_action('wp_enqueue_scripts', 'life_travel_optimize_wc_scripts', 99);

    // Logger les optimisations WooCommerce pour débogage (mode développement uniquement)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        add_action('wp_footer', 'life_travel_log_wc_scripts', 9999);
    }
}
add_action('init', 'life_travel_wc_performance_optimizations');

/**
 * Désactive les scripts WooCommerce inutiles sur certaines pages
 *
 * @return void
 */
function life_travel_optimize_wc_scripts() {
    // Ne pas désactiver les scripts sur les pages WooCommerce principales
    if (is_woocommerce() || is_cart() || is_checkout() || is_account_page()) {
        return;
    }

    // Liste des pages où les scripts WooCommerce sont nécessaires (ID ou slug)
    $wc_needed_pages = array(
        'sur-mesure',          // Page pour les réservations sur mesure 
        'calendrier',          // Page calendrier des excursions
        'planning-excursions'  // Autre page potentielle liée aux excursions
    );

    // Vérifier si nous sommes sur une page qui nécessite les scripts
    global $post;
    if ($post && (in_array($post->post_name, $wc_needed_pages) || in_array($post->ID, $wc_needed_pages))) {
        return;
    }

    // Désactiver les scripts qui ne sont pas nécessaires sur cette page
    wp_dequeue_script('wc-add-to-cart');
    wp_dequeue_script('wc-cart-fragments');
    // Garder woocommerce.js car il pourrait être nécessaire pour afficher les prix, etc.

    // Nettoyer les CSS inutiles
    wp_dequeue_style('woocommerce-general');
    wp_dequeue_style('woocommerce-layout');
    wp_dequeue_style('woocommerce-smallscreen');
}

/**
 * Journalise les scripts WooCommerce chargés pour débogage
 *
 * @return void
 */
function life_travel_log_wc_scripts() {
    global $wp_scripts;
    
    if (!current_user_can('administrator') || !$wp_scripts) {
        return;
    }
    
    echo '<!-- Life Travel WC Script Debug -->';
    echo '<!-- Scripts chargés: -->';
    
    foreach ($wp_scripts->queue as $handle) {
        if (strpos($handle, 'wc-') === 0 || strpos($handle, 'woocommerce') !== false) {
            echo '<!-- ' . esc_html($handle) . ' -->';
        }
    }
}

/**
 * Personnalisation de l'affichage des produits (excursions)
 */
function life_travel_wc_shop_customizations() {
    // Utiliser des tailles d'image optimisées pour les excursions
    add_filter('woocommerce_get_image_size_thumbnail', 'life_travel_wc_thumbnail_size');
    add_filter('woocommerce_get_image_size_gallery_thumbnail', 'life_travel_wc_gallery_thumbnail_size');
    
    // Ajouter les méta-informations personnalisées pour les excursions
    add_action('woocommerce_after_shop_loop_item_title', 'life_travel_add_excursion_meta', 15);
    
    // Modifier la structure des pages produit pour les excursions
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
    add_action('woocommerce_single_product_summary', 'life_travel_excursion_meta', 40);
    
    // Adapter l'ajout au panier pour les réservations d'excursions
    add_filter('woocommerce_add_to_cart_validation', 'life_travel_validate_excursion_booking', 10, 3);
}
add_action('init', 'life_travel_wc_shop_customizations');

/**
 * Définit la taille des miniatures pour les excursions
 *
 * @param array $size La taille de l'image
 * @return array La taille modifiée
 */
function life_travel_wc_thumbnail_size($size) {
    return array(
        'width' => 450,
        'height' => 300,
        'crop' => true,
    );
}

/**
 * Définit la taille des miniatures de galerie
 *
 * @param array $size La taille de l'image
 * @return array La taille modifiée
 */
function life_travel_wc_gallery_thumbnail_size($size) {
    return array(
        'width' => 150,
        'height' => 100,
        'crop' => true,
    );
}

/**
 * Ajoute des métadonnées spécifiques aux excursions
 *
 * @return void
 */
function life_travel_add_excursion_meta() {
    global $product;
    
    // Vérifier si c'est une excursion (on pourrait utiliser une taxonomie ou un champ ACF)
    if (!$product || !has_term('excursion', 'product_cat', $product->get_id())) {
        return;
    }
    
    echo '<div class="excursion-meta">';
    
    // Durée de l'excursion (exemple avec un champ ACF)
    if (function_exists('get_field')) {
        $duration = get_field('duree_excursion', $product->get_id());
        if ($duration) {
            echo '<span class="excursion-duration"><i class="fas fa-clock"></i> ' . esc_html($duration) . '</span>';
        }
        
        // Lieu de l'excursion
        $location = get_field('lieu_excursion', $product->get_id());
        if ($location) {
            echo '<span class="excursion-location"><i class="fas fa-map-marker-alt"></i> ' . esc_html($location) . '</span>';
        }
    }
    
    echo '</div>';
}

/**
 * Affiche des métadonnées spécifiques sur la page produit des excursions
 *
 * @return void
 */
function life_travel_excursion_meta() {
    global $product;
    
    // Vérifier si c'est une excursion
    if (!$product || !has_term('excursion', 'product_cat', $product->get_id())) {
        // Si ce n'est pas une excursion, on affiche les métadonnées standard
        woocommerce_template_single_meta();
        return;
    }
    
    echo '<div class="excursion-details">';
    
    // Détails spécifiques aux excursions
    if (function_exists('get_field')) {
        // Points forts
        $highlights = get_field('points_forts', $product->get_id());
        if ($highlights) {
            echo '<div class="excursion-highlights">';
            echo '<h4>' . esc_html__('Points forts', 'life-travel') . '</h4>';
            echo '<ul>';
            foreach ($highlights as $highlight) {
                echo '<li>' . esc_html($highlight['texte']) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        
        // Inclusions
        $inclusions = get_field('inclusions', $product->get_id());
        if ($inclusions) {
            echo '<div class="excursion-inclusions">';
            echo '<h4>' . esc_html__('Ce qui est inclus', 'life-travel') . '</h4>';
            echo wp_kses_post($inclusions);
            echo '</div>';
        }
    }
    
    // Catégories et tags
    echo '<div class="product-meta">';
    echo wc_get_product_category_list($product->get_id(), ', ', '<span class="posted_in">' . _n('Catégorie:', 'Catégories:', count($product->get_category_ids()), 'life-travel') . ' ', '</span>');
    echo wc_get_product_tag_list($product->get_id(), ', ', '<span class="tagged_as">' . _n('Tag:', 'Tags:', count($product->get_tag_ids()), 'life-travel') . ' ', '</span>');
    echo '</div>';
    
    echo '</div>';
}

/**
 * Validation personnalisée pour l'ajout d'une excursion au panier
 *
 * @param bool $passed Si la validation a passé
 * @param int $product_id ID du produit
 * @param int $quantity Quantité
 * @return bool Si la validation passe
 */
function life_travel_validate_excursion_booking($passed, $product_id, $quantity) {
    // Vérifier si c'est une excursion
    if (!has_term('excursion', 'product_cat', $product_id)) {
        return $passed;
    }
    
    // Vérifier la disponibilité pour cette date (si la date est sélectionnée)
    if (isset($_POST['excursion_date']) && !empty($_POST['excursion_date'])) {
        $selected_date = sanitize_text_field($_POST['excursion_date']);
        
        // Code de vérification pour la disponibilité à cette date
        // ...
        
        // Journalisation pour débogage
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Réservation d\'excursion - Date: ' . $selected_date . ' - Produit: ' . $product_id);
        }
    }
    
    return $passed;
}

/**
 * Ajout/modification des champs checkout pour les excursions
 */
function life_travel_customize_checkout_fields() {
    // Vérifier si le panier contient une excursion
    $contains_excursion = false;
    
    if (function_exists('WC') && !empty(WC()->cart)) {
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (has_term('excursion', 'product_cat', $cart_item['product_id'])) {
                $contains_excursion = true;
                break;
            }
        }
    }
    
    if ($contains_excursion) {
        add_filter('woocommerce_checkout_fields', 'life_travel_add_excursion_checkout_fields');
    }
}
add_action('woocommerce_before_checkout_form', 'life_travel_customize_checkout_fields');

/**
 * Ajoute des champs spécifiques aux excursions sur le checkout
 *
 * @param array $fields Les champs de checkout
 * @return array Les champs modifiés
 */
function life_travel_add_excursion_checkout_fields($fields) {
    // Ajouter des champs pour les informations des participants
    $fields['order']['participants_info'] = array(
        'type'        => 'textarea',
        'label'       => __('Informations sur les participants', 'life-travel'),
        'placeholder' => __('Veuillez indiquer les noms, âges et nationalités des participants', 'life-travel'),
        'required'    => false,
        'class'       => array('form-row-wide'),
        'priority'    => 110,
    );
    
    // Ajouter un champ pour les besoins spécifiques
    $fields['order']['special_requirements'] = array(
        'type'        => 'textarea',
        'label'       => __('Besoins particuliers', 'life-travel'),
        'placeholder' => __('Allergies, restrictions alimentaires, limitations physiques...', 'life-travel'),
        'required'    => false,
        'class'       => array('form-row-wide'),
        'priority'    => 115,
    );
    
    return $fields;
}

/**
 * Sauvegarde des champs personnalisés des excursions lors de la commande
 *
 * @param int $order_id ID de la commande
 */
function life_travel_save_excursion_checkout_fields($order_id) {
    if (!empty($_POST['participants_info'])) {
        update_post_meta($order_id, '_participants_info', sanitize_textarea_field($_POST['participants_info']));
    }
    
    if (!empty($_POST['special_requirements'])) {
        update_post_meta($order_id, '_special_requirements', sanitize_textarea_field($_POST['special_requirements']));
    }
    
    // Journalisation pour débogage
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Commande excursion #' . $order_id . ' - Données participants sauvegardées');
    }
}
add_action('woocommerce_checkout_update_order_meta', 'life_travel_save_excursion_checkout_fields');

/**
 * Redéfinir le texte du bouton d'ajout au panier pour les excursions
 *
 * @param string $text Texte du bouton
 * @param WC_Product $product Le produit
 * @return string Texte modifié
 */
function life_travel_modify_add_to_cart_text($text, $product) {
    if (has_term('excursion', 'product_cat', $product->get_id())) {
        return __('Réserver maintenant', 'life-travel');
    }
    
    return $text;
}
add_filter('woocommerce_product_single_add_to_cart_text', 'life_travel_modify_add_to_cart_text', 10, 2);
add_filter('woocommerce_product_add_to_cart_text', 'life_travel_modify_add_to_cart_text', 10, 2);

/**
 * Charge les styles CSS du sélecteur de langue
 */
function life_travel_load_language_switcher_styles() {
    wp_enqueue_style(
        'life-travel-language-switcher',
        get_template_directory_uri() . '/assets/css/language-switcher.css',
        array(),
        LIFE_TRAVEL_VERSION
    );
}

/**
 * Intégration de TranslatePress pour les métadonnées d'excursion
 * Assure que les champs personnalisés sont correctement traduits
 */
function life_travel_translatepress_excursion_support() {
    // Ne pas exécuter si TranslatePress n'est pas actif
    if (!function_exists('trp_translate_gettext')) {
        return;
    }
    
    // Rendre traduisibles les métadonnées des excursions
    add_filter('trp_translatable_custom_post_meta', 'life_travel_translatable_excursion_meta', 10, 2);
    
    // Support pour les données dynamiques (variation d'excursions, prix)
    add_filter('trp_translatable_strings', 'life_travel_add_dynamic_strings_to_translation', 10, 1);
}
add_action('plugins_loaded', 'life_travel_translatepress_excursion_support', 20);

/**
 * Définir les métadonnées d'excursion qui doivent être traduisibles
 * 
 * @param array $translatable_meta Liste des métadonnées traduisibles
 * @param int $post_id ID du post
 * @return array Liste mise à jour
 */
function life_travel_translatable_excursion_meta($translatable_meta, $post_id) {
    // Métadonnées spécifiques aux excursions qui doivent être traduites
    $excursion_meta_fields = array(
        '_excursion_description',
        '_excursion_details',
        '_excursion_included',
        '_excursion_not_included',
        '_excursion_itinerary',
        '_excursion_location_description',
        '_participants_info',
        '_special_requirements',
        'participants_info',
        'special_requirements'
    );
    
    return array_merge($translatable_meta, $excursion_meta_fields);
}

/**
 * Ajoute les chaînes dynamiques à la traduction (ex: JS)
 * 
 * @param array $strings Liste des chaînes à traduire
 * @return array Liste mise à jour
 */
function life_travel_add_dynamic_strings_to_translation($strings) {
    // Messages dynamiques spécifiques aux excursions
    $dynamic_strings = array(
        // Messages de disponibilité
        'Disponible',
        'Complet',
        'Dernières places',
        'Sur demande',
        // Messages de validation
        'Veuillez sélectionner une date',
        'Veuillez indiquer le nombre de participants',
        'Nombre de participants invalide'
    );
    
    foreach ($dynamic_strings as $string) {
        $strings[] = array(
            'id' => md5($string),
            'original' => $string,
            'domain' => 'life-travel-js'
        );
    }
    
    return $strings;
}
