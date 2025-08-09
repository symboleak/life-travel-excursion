<?php
/**
 * WooCommerce Integration for Life Travel
 *
 * @package Life_Travel_Core
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce Integration Class
 */
class Life_Travel_Core_WooCommerce {
    /**
     * Instance
     * 
     * @var Life_Travel_Core_WooCommerce|null
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        // Check if WooCommerce is active
        if (class_exists('WooCommerce')) {
            // Product modifications
            add_filter('product_type_selector', array($this, 'add_excursion_product_type'));
            add_filter('woocommerce_product_data_tabs', array($this, 'add_excursion_product_tab'));
            add_action('woocommerce_product_data_panels', array($this, 'add_excursion_product_tab_content'));
            
            // Cart & Checkout optimizations
            add_filter('woocommerce_cart_item_name', array($this, 'modify_cart_item_name'), 10, 3);
            add_filter('woocommerce_cart_item_thumbnail', array($this, 'modify_cart_item_thumbnail'), 10, 3);
            add_action('woocommerce_before_calculate_totals', array($this, 'add_reservation_fee'));
            
            // Schema.org integration
            add_action('woocommerce_single_product_summary', array($this, 'add_touristtrip_schema'), 60);
            
            // Integration with excursion-addon plugin
            add_action('plugins_loaded', array($this, 'integrate_with_excursion_addon'));
            
            // Product category and tag modifications
            add_filter('woocommerce_product_categories_widget_args', array($this, 'modify_product_categories_widget'));
            
            // Single Product Template modifications
            add_filter('woocommerce_product_tabs', array($this, 'modify_product_tabs'));
            add_action('woocommerce_single_product_summary', array($this, 'add_excursion_meta_data'), 25);
            
            // Product Metabox
            add_action('woocommerce_product_options_general_product_data', array($this, 'add_excursion_product_fields'));
            add_action('woocommerce_process_product_meta', array($this, 'save_excursion_product_fields'));
            
            // Add custom columns to products admin
            add_filter('manage_edit-product_columns', array($this, 'add_product_admin_columns'));
            add_action('manage_product_posts_custom_column', array($this, 'populate_product_admin_columns'));
        }
    }

    /**
     * Add excursion product type
     */
    public function add_excursion_product_type($types) {
        // Note: This is just a visual indicator, functionality comes from excursion-addon
        $types['excursion'] = __('Excursion', 'life-travel-core');
        return $types;
    }

    /**
     * Add excursion product tab
     */
    public function add_excursion_product_tab($tabs) {
        $tabs['excursion'] = array(
            'label'    => __('Excursion', 'life-travel-core'),
            'target'   => 'excursion_product_data',
            'class'    => array('show_if_simple'),
            'priority' => 21,
        );
        return $tabs;
    }

    /**
     * Add excursion product tab content
     */
    public function add_excursion_product_tab_content() {
        global $post;
        
        echo '<div id="excursion_product_data" class="panel woocommerce_options_panel">';
        
        echo '<div class="options_group">';
        
        woocommerce_wp_checkbox(array(
            'id'          => '_is_excursion',
            'label'       => __('Excursion programmée', 'life-travel-core'),
            'description' => __('Cochez cette case si ce produit est une excursion avec une date fixe', 'life-travel-core'),
        ));
        
        echo '<p class="form-field _excursion_addon_note"><strong>';
        echo __('Note importante:', 'life-travel-core');
        echo '</strong> ';
        echo __('Pour les fonctionnalités avancées de réservation comme les variations de dates et de nombre de participants, utilisez le plugin "excursion-addon" qui est déjà intégré avec ce thème.', 'life-travel-core');
        echo '</p>';
        
        echo '</div>';
        
        echo '</div>';
    }

    /**
     * Modify cart item name
     */
    public function modify_cart_item_name($name, $cart_item, $cart_item_key) {
        // Check if it's an excursion product
        if (isset($cart_item['data']) && $cart_item['data']->get_meta('_is_excursion') === 'yes') {
            // Get excursion date
            $excursion_date = get_post_meta($cart_item['product_id'], 'excursion_date', true);
            
            if ($excursion_date) {
                // Format date
                $date_obj = DateTime::createFromFormat('Ymd', $excursion_date);
                $formatted_date = $date_obj ? $date_obj->format('d/m/Y') : '';
                
                // Add date to product name
                if ($formatted_date) {
                    $name .= ' <span class="excursion-date">(' . esc_html__('Date: ', 'life-travel-core') . esc_html($formatted_date) . ')</span>';
                }
            }
        }
        
        return $name;
    }

    /**
     * Modify cart item thumbnail
     */
    public function modify_cart_item_thumbnail($thumbnail, $cart_item, $cart_item_key) {
        // Add badge for excursion products
        if (isset($cart_item['data']) && $cart_item['data']->get_meta('_is_excursion') === 'yes') {
            return '<div class="excursion-cart-thumb">' . $thumbnail . '<span class="excursion-badge">' . esc_html__('Excursion', 'life-travel-core') . '</span></div>';
        }
        
        return $thumbnail;
    }

    /**
     * Add reservation fee
     */
    public function add_reservation_fee($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        if (did_action('woocommerce_before_calculate_totals') >= 2) {
            return;
        }
        
        // Check if there's at least one excursion in cart
        $has_excursion = false;
        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['data']) && $cart_item['data']->get_meta('_is_excursion') === 'yes') {
                $has_excursion = true;
                break;
            }
        }
        
        // Apply reservation fee
        if ($has_excursion) {
            // This fee is handled by excursion-addon plugin
            // This is just a placeholder for integration
        }
    }

    /**
     * Add TouristTrip schema
     */
    public function add_touristtrip_schema() {
        global $product;
        
        // Only add schema for excursion products
        if (!$product || $product->get_meta('_is_excursion') !== 'yes') {
            return;
        }
        
        // Get product data
        $product_id = $product->get_id();
        $excursion_date = get_post_meta($product_id, 'excursion_date', true);
        $duration = get_post_meta($product_id, 'excursion_duration', true);
        $difficulty = get_post_meta($product_id, 'excursion_difficulty', true);
        
        // Format date
        $date_obj = $excursion_date ? DateTime::createFromFormat('Ymd', $excursion_date) : null;
        $formatted_date = $date_obj ? $date_obj->format('Y-m-d') : '';
        
        // Build schema
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'TouristTrip',
            'name' => $product->get_name(),
            'description' => $product->get_description(),
            'url' => get_permalink($product_id),
            'touristType' => array('Adventure Travel', 'Sightseeing'),
            'offers' => array(
                '@type' => 'Offer',
                'price' => $product->get_price(),
                'priceCurrency' => get_woocommerce_currency(),
                'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'validFrom' => $formatted_date,
                'url' => get_permalink($product_id),
            ),
        );
        
        // Add difficulty if available
        if ($difficulty) {
            $schema['additionalProperty'] = array(
                '@type' => 'PropertyValue',
                'name' => __('Difficulté', 'life-travel-core'),
                'value' => $difficulty,
            );
        }
        
        // Add duration if available
        if ($duration) {
            $schema['estimatedDuration'] = $duration;
        }
        
        // Add image if available
        if (has_post_thumbnail($product_id)) {
            $image_id = get_post_thumbnail_id($product_id);
            $image_url = wp_get_attachment_image_url($image_id, 'full');
            
            if ($image_url) {
                $schema['image'] = $image_url;
            }
        }
        
        // Output schema
        echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
    }

    /**
     * Integrate with excursion-addon plugin
     */
    public function integrate_with_excursion_addon() {
        if (class_exists('Excursion_Addon')) {
            // Add custom templates path
            add_filter('excursion_addon_template_path', function($path) {
                return 'woocommerce/excursion-addon/';
            });
            
            // Modify booking form
            add_filter('excursion_addon_booking_form_fields', function($fields) {
                // Add custom fields or modify existing ones
                $fields['phone'] = array(
                    'type'        => 'tel',
                    'label'       => __('Numéro de téléphone', 'life-travel-core'),
                    'placeholder' => __('Votre numéro de téléphone', 'life-travel-core'),
                    'required'    => true,
                    'priority'    => 15,
                );
                
                return $fields;
            });
            
            // Modify booking process
            add_action('excursion_addon_before_booking_process', function($booking_data) {
                // Custom validation or preprocessing
            });
            
            // Add custom confirmation message
            add_filter('excursion_addon_booking_confirmation_message', function($message, $booking_id) {
                $message .= '<p>' . sprintf(
                    __('Votre réservation a été confirmée. Numéro de réservation : %s', 'life-travel-core'),
                    '<strong>' . $booking_id . '</strong>'
                ) . '</p>';
                
                $message .= '<p>' . __('Un membre de notre équipe vous contactera prochainement pour confirmer les détails.', 'life-travel-core') . '</p>';
                
                return $message;
            }, 10, 2);
        }
    }

    /**
     * Modify product categories widget
     */
    public function modify_product_categories_widget($args) {
        // Rename "Categories" to "Types d'excursions"
        $args['title'] = __('Types d\'excursions', 'life-travel-core');
        
        return $args;
    }

    /**
     * Modify product tabs
     */
    public function modify_product_tabs($tabs) {
        global $product;
        
        // Only modify tabs for excursion products
        if (!$product || $product->get_meta('_is_excursion') !== 'yes') {
            return $tabs;
        }
        
        // Change "Description" tab title
        if (isset($tabs['description'])) {
            $tabs['description']['title'] = __('À propos de l\'excursion', 'life-travel-core');
        }
        
        // Change "Additional Information" tab title
        if (isset($tabs['additional_information'])) {
            $tabs['additional_information']['title'] = __('Informations pratiques', 'life-travel-core');
        }
        
        // Add "Itinerary" tab if data exists
        $itinerary = get_post_meta($product->get_id(), 'excursion_itinerary', true);
        if ($itinerary && !empty($itinerary)) {
            $tabs['itinerary'] = array(
                'title'    => __('Itinéraire', 'life-travel-core'),
                'priority' => 15,
                'callback' => array($this, 'itinerary_tab_content'),
            );
        }
        
        return $tabs;
    }

    /**
     * Itinerary tab content
     */
    public function itinerary_tab_content() {
        global $product;
        
        $itinerary = get_post_meta($product->get_id(), 'excursion_itinerary', true);
        
        if ($itinerary && !empty($itinerary)) {
            echo '<div class="excursion-itinerary">';
            
            foreach ($itinerary as $index => $step) {
                echo '<div class="itinerary-step">';
                
                // Display day/title
                if (!empty($step['day'])) {
                    echo '<h3 class="itinerary-day">' . esc_html($step['day']) . '</h3>';
                }
                
                // Display image if available
                if (!empty($step['image']) && isset($step['image']['url'])) {
                    echo '<div class="itinerary-image">';
                    echo '<img src="' . esc_url($step['image']['url']) . '" alt="' . esc_attr($step['day']) . '">';
                    echo '</div>';
                }
                
                // Display description
                if (!empty($step['description'])) {
                    echo '<div class="itinerary-description">' . wp_kses_post(wpautop($step['description'])) . '</div>';
                }
                
                echo '</div>';
            }
            
            echo '</div>';
        }
    }

    /**
     * Add excursion meta data to product page
     */
    public function add_excursion_meta_data() {
        global $product;
        
        // Only add meta data for excursion products
        if (!$product || $product->get_meta('_is_excursion') !== 'yes') {
            return;
        }
        
        // Get excursion data
        $product_id = $product->get_id();
        $excursion_date = get_post_meta($product_id, 'excursion_date', true);
        $duration = get_post_meta($product_id, 'excursion_duration', true);
        $difficulty = get_post_meta($product_id, 'excursion_difficulty', true);
        $departure_time = get_post_meta($product_id, 'excursion_departure_time', true);
        $meeting_point = get_post_meta($product_id, 'excursion_meeting_point', true);
        $spots = get_post_meta($product_id, 'excursion_spots', true);
        $reserved = get_post_meta($product_id, 'excursion_reserved', true);
        
        // Format date
        $date_obj = $excursion_date ? DateTime::createFromFormat('Ymd', $excursion_date) : null;
        $formatted_date = $date_obj ? $date_obj->format('d/m/Y') : '';
        
        // Calculate available spots
        $available = $spots ? ($spots - $reserved) : 0;
        $availability_class = $available <= 3 ? 'low-availability' : 'good-availability';
        
        // Output meta data
        echo '<div class="excursion-meta">';
        
        // Date
        if ($formatted_date) {
            echo '<div class="excursion-meta-item excursion-date">';
            echo '<span class="meta-label">' . esc_html__('Date:', 'life-travel-core') . '</span> ';
            echo '<span class="meta-value">' . esc_html($formatted_date) . '</span>';
            echo '</div>';
        }
        
        // Duration
        if ($duration) {
            echo '<div class="excursion-meta-item excursion-duration">';
            echo '<span class="meta-label">' . esc_html__('Durée:', 'life-travel-core') . '</span> ';
            echo '<span class="meta-value">' . esc_html($duration) . '</span>';
            echo '</div>';
        }
        
        // Difficulty
        if ($difficulty) {
            echo '<div class="excursion-meta-item excursion-difficulty">';
            echo '<span class="meta-label">' . esc_html__('Difficulté:', 'life-travel-core') . '</span> ';
            echo '<span class="meta-value">' . esc_html($difficulty) . '</span>';
            echo '</div>';
        }
        
        // Departure time
        if ($departure_time) {
            echo '<div class="excursion-meta-item excursion-departure">';
            echo '<span class="meta-label">' . esc_html__('Départ:', 'life-travel-core') . '</span> ';
            echo '<span class="meta-value">' . esc_html($departure_time) . '</span>';
            echo '</div>';
        }
        
        // Meeting point
        if ($meeting_point) {
            echo '<div class="excursion-meta-item excursion-meeting">';
            echo '<span class="meta-label">' . esc_html__('Point de rendez-vous:', 'life-travel-core') . '</span> ';
            echo '<span class="meta-value">' . esc_html($meeting_point) . '</span>';
            echo '</div>';
        }
        
        // Availability
        if ($spots) {
            echo '<div class="excursion-meta-item excursion-availability ' . esc_attr($availability_class) . '">';
            echo '<span class="meta-label">' . esc_html__('Disponibilité:', 'life-travel-core') . '</span> ';
            echo '<span class="meta-value">' . sprintf(
                esc_html__('%1$s places sur %2$s', 'life-travel-core'),
                '<strong>' . esc_html($available) . '</strong>',
                esc_html($spots)
            ) . '</span>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    /**
     * Add excursion product fields
     */
    public function add_excursion_product_fields() {
        global $post;
        
        echo '<div class="options_group show_if_simple">';
        
        woocommerce_wp_checkbox(array(
            'id'          => '_is_excursion',
            'label'       => __('Excursion programmée', 'life-travel-core'),
            'description' => __('Cochez cette case si ce produit est une excursion avec une date fixe', 'life-travel-core'),
        ));
        
        echo '</div>';
    }

    /**
     * Save excursion product fields
     */
    public function save_excursion_product_fields($post_id) {
        $is_excursion = isset($_POST['_is_excursion']) ? 'yes' : 'no';
        update_post_meta($post_id, '_is_excursion', $is_excursion);
    }

    /**
     * Add product admin columns
     */
    public function add_product_admin_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            
            // Add excursion column after title
            if ($key === 'name') {
                $new_columns['is_excursion'] = __('Excursion', 'life-travel-core');
                $new_columns['excursion_date'] = __('Date', 'life-travel-core');
            }
        }
        
        return $new_columns;
    }

    /**
     * Populate product admin columns
     */
    public function populate_product_admin_columns($column) {
        global $post;
        
        if ($column === 'is_excursion') {
            $is_excursion = get_post_meta($post->ID, '_is_excursion', true);
            
            if ($is_excursion === 'yes') {
                echo '<span class="dashicons dashicons-yes" style="color: #7ad03a;"></span>';
            } else {
                echo '<span class="dashicons dashicons-no" style="color: #a00;"></span>';
            }
        }
        
        if ($column === 'excursion_date') {
            $excursion_date = get_post_meta($post->ID, 'excursion_date', true);
            
            if ($excursion_date) {
                $date_obj = DateTime::createFromFormat('Ymd', $excursion_date);
                echo $date_obj ? $date_obj->format('d/m/Y') : '';
            } else {
                echo '—';
            }
        }
    }
}

// Initialize WooCommerce Integration class
Life_Travel_Core_WooCommerce::instance();
