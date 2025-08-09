<?php
/**
 * Integration Bridge for Excursion Addon
 *
 * @package Life_Travel_Core
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Addon Bridge Class
 */
class Life_Travel_Core_Addon_Bridge {
    /**
     * Instance
     * 
     * @var Life_Travel_Core_Addon_Bridge|null
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
        // Check if Excursion Addon plugin is active
        if (class_exists('Excursion_Addon')) {
            // Template modifications
            add_filter('excursion_addon_template_path', array($this, 'custom_template_path'));
            
            // Booking form modifications
            add_filter('excursion_addon_booking_form_fields', array($this, 'custom_booking_fields'));
            
            // Variation handling
            add_filter('excursion_addon_variation_data', array($this, 'custom_variation_data'), 10, 2);
            
            // Checkout process
            add_action('excursion_addon_before_booking_process', array($this, 'before_booking_process'));
            add_action('excursion_addon_after_booking_process', array($this, 'after_booking_process'), 10, 2);
            
            // Email templates
            add_filter('excursion_addon_email_templates', array($this, 'custom_email_templates'));
            
            // Admin dashboard integration
            add_action('excursion_addon_admin_dashboard_before', array($this, 'custom_admin_dashboard'));
            
            // Calendar integration
            add_filter('excursion_addon_calendar_data', array($this, 'custom_calendar_data'));
            
            // User dashboard modifications
            add_filter('excursion_addon_user_dashboard_endpoints', array($this, 'custom_dashboard_endpoints'));
        }
    }

    /**
     * Custom template path for Excursion Addon
     */
    public function custom_template_path($path) {
        return 'woocommerce/excursion-addon/';
    }

    /**
     * Custom booking fields
     */
    public function custom_booking_fields($fields) {
        // Add WhatsApp field
        $fields['whatsapp'] = array(
            'type'        => 'tel',
            'label'       => __('WhatsApp (optionnel)', 'life-travel-core'),
            'placeholder' => __('Votre numéro WhatsApp', 'life-travel-core'),
            'required'    => false,
            'priority'    => 15,
        );
        
        // Add special requests field
        $fields['special_requests'] = array(
            'type'        => 'textarea',
            'label'       => __('Demandes spéciales', 'life-travel-core'),
            'placeholder' => __('Besoins particuliers, allergies, etc.', 'life-travel-core'),
            'required'    => false,
            'priority'    => 30,
        );
        
        // Modify existing fields
        if (isset($fields['phone'])) {
            $fields['phone']['priority'] = 10;
            $fields['phone']['required'] = true;
            $fields['phone']['label'] = __('Téléphone (pour confirmation)', 'life-travel-core');
        }
        
        return $fields;
    }

    /**
     * Custom variation data
     */
    public function custom_variation_data($data, $product_id) {
        // Get extras from ACF fields
        $extras = array();
        
        // Transportation options
        $extras['transportation'] = array(
            'label'   => __('Transport', 'life-travel-core'),
            'options' => array(
                'none'      => array(
                    'label'     => __('Pas de transport', 'life-travel-core'),
                    'price'     => 0,
                    'inventory' => -1, // Unlimited
                ),
                'shared_bus' => array(
                    'label'     => __('Bus partagé', 'life-travel-core'),
                    'price'     => 2000, // 2000 FCFA
                    'inventory' => 20,
                ),
                'private_car' => array(
                    'label'     => __('Voiture privée', 'life-travel-core'),
                    'price'     => 10000, // 10000 FCFA
                    'inventory' => 5,
                ),
            ),
        );
        
        // Accommodation options (for multi-day excursions)
        if (get_post_meta($product_id, 'excursion_duration', true) !== '1 jour') {
            $extras['accommodation'] = array(
                'label'   => __('Hébergement', 'life-travel-core'),
                'options' => array(
                    'standard' => array(
                        'label'     => __('Standard', 'life-travel-core'),
                        'price'     => 0,
                        'inventory' => 10,
                    ),
                    'comfort' => array(
                        'label'     => __('Confort', 'life-travel-core'),
                        'price'     => 15000, // 15000 FCFA
                        'inventory' => 5,
                    ),
                    'luxury' => array(
                        'label'     => __('Luxe', 'life-travel-core'),
                        'price'     => 30000, // 30000 FCFA
                        'inventory' => 3,
                    ),
                ),
            );
        }
        
        // Merge with existing data
        $data['extras'] = isset($data['extras']) ? array_merge($data['extras'], $extras) : $extras;
        
        return $data;
    }

    /**
     * Actions before booking process
     */
    public function before_booking_process($booking_data) {
        // Validate special requirements
        if (!empty($booking_data['special_requests']) && strlen($booking_data['special_requests']) > 500) {
            wc_add_notice(__('Les demandes spéciales ne peuvent pas dépasser 500 caractères.', 'life-travel-core'), 'error');
        }
        
        // Check if WhatsApp number is valid
        if (!empty($booking_data['whatsapp']) && !preg_match('/^\+?[0-9]{8,15}$/', $booking_data['whatsapp'])) {
            wc_add_notice(__('Le numéro WhatsApp n\'est pas valide.', 'life-travel-core'), 'error');
        }
    }

    /**
     * Actions after booking process
     */
    public function after_booking_process($booking_id, $booking_data) {
        // Send SMS notification if phone is provided
        if (!empty($booking_data['phone'])) {
            // This would integrate with an SMS API
            // do_action('life_travel_send_booking_sms', $booking_data['phone'], $booking_id);
        }
        
        // Add to calendar if available
        if (!empty($booking_data['product_id'])) {
            $excursion_date = get_post_meta($booking_data['product_id'], 'excursion_date', true);
            
            if ($excursion_date) {
                // Update calendar event
                $this->update_calendar_event($booking_id, $booking_data['product_id'], $excursion_date);
            }
        }
        
        // Update spots count
        $this->update_spots_count($booking_data['product_id'], $booking_data['quantity']);
    }

    /**
     * Update calendar event
     */
    private function update_calendar_event($booking_id, $product_id, $excursion_date) {
        // Get current bookings for this date
        $bookings = get_post_meta($product_id, '_excursion_bookings', true);
        
        if (!is_array($bookings)) {
            $bookings = array();
        }
        
        // Add new booking
        $bookings[$booking_id] = array(
            'date'     => $excursion_date,
            'status'   => 'confirmed',
            'modified' => current_time('mysql'),
        );
        
        // Update bookings meta
        update_post_meta($product_id, '_excursion_bookings', $bookings);
    }

    /**
     * Update spots count
     */
    private function update_spots_count($product_id, $quantity) {
        // Get current reserved spots
        $reserved = (int) get_post_meta($product_id, 'excursion_reserved', true);
        
        // Update reserved spots
        $reserved += $quantity;
        update_post_meta($product_id, 'excursion_reserved', $reserved);
        
        // Get total spots
        $spots = (int) get_post_meta($product_id, 'excursion_spots', true);
        
        // Check if sold out
        if ($reserved >= $spots) {
            // Mark product as out of stock
            update_post_meta($product_id, '_stock_status', 'outofstock');
        }
    }

    /**
     * Custom email templates
     */
    public function custom_email_templates($templates) {
        // Override standard template paths
        $templates['booking_confirmation'] = 'emails/excursion-booking-confirmation.php';
        $templates['booking_reminder'] = 'emails/excursion-booking-reminder.php';
        
        return $templates;
    }

    /**
     * Custom admin dashboard
     */
    public function custom_admin_dashboard() {
        // Add custom content to the admin dashboard
        echo '<div class="excursion-addon-dashboard-widget">';
        echo '<h3>' . esc_html__('Life Travel Dashboard', 'life-travel-core') . '</h3>';
        
        // Upcoming excursions
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => 5,
            'meta_query'     => array(
                array(
                    'key'     => '_is_excursion',
                    'value'   => 'yes',
                    'compare' => '=',
                ),
                array(
                    'key'     => 'excursion_date',
                    'value'   => date('Ymd'),
                    'compare' => '>=',
                ),
            ),
            'meta_key'       => 'excursion_date',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
        );
        
        $excursions = new WP_Query($args);
        
        if ($excursions->have_posts()) {
            echo '<div class="upcoming-excursions">';
            echo '<h4>' . esc_html__('Excursions à venir', 'life-travel-core') . '</h4>';
            echo '<ul>';
            
            while ($excursions->have_posts()) {
                $excursions->the_post();
                
                $excursion_date = get_post_meta(get_the_ID(), 'excursion_date', true);
                $date_obj = DateTime::createFromFormat('Ymd', $excursion_date);
                $formatted_date = $date_obj ? $date_obj->format('d/m/Y') : '';
                
                $spots = (int) get_post_meta(get_the_ID(), 'excursion_spots', true);
                $reserved = (int) get_post_meta(get_the_ID(), 'excursion_reserved', true);
                $available = $spots - $reserved;
                
                echo '<li>';
                echo '<a href="' . esc_url(admin_url('post.php?post=' . get_the_ID() . '&action=edit')) . '">' . get_the_title() . '</a>';
                echo ' - ' . esc_html($formatted_date);
                echo ' (' . sprintf(
                    esc_html__('%d places restantes', 'life-travel-core'),
                    $available
                ) . ')';
                echo '</li>';
            }
            
            echo '</ul>';
            echo '</div>';
        }
        
        wp_reset_postdata();
        
        echo '</div>';
    }

    /**
     * Custom calendar data
     */
    public function custom_calendar_data($data) {
        // Get all excursion products
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => '_is_excursion',
                    'value'   => 'yes',
                    'compare' => '=',
                ),
                array(
                    'key'     => 'excursion_date',
                    'value'   => '',
                    'compare' => '!=',
                ),
            ),
        );
        
        $excursions = new WP_Query($args);
        
        if ($excursions->have_posts()) {
            while ($excursions->have_posts()) {
                $excursions->the_post();
                
                $excursion_id = get_the_ID();
                $excursion_date = get_post_meta($excursion_id, 'excursion_date', true);
                
                if ($excursion_date) {
                    // Get excursion data
                    $spots = (int) get_post_meta($excursion_id, 'excursion_spots', true);
                    $reserved = (int) get_post_meta($excursion_id, 'excursion_reserved', true);
                    $available = $spots - $reserved;
                    $featured = (bool) get_post_meta($excursion_id, 'excursion_featured_calendar', true);
                    
                    // Add to calendar data
                    $data[$excursion_date][] = array(
                        'id'        => $excursion_id,
                        'title'     => get_the_title(),
                        'url'       => get_permalink(),
                        'image'     => get_the_post_thumbnail_url($excursion_id, 'life-travel-thumbnail'),
                        'price'     => get_post_meta($excursion_id, '_price', true),
                        'available' => $available,
                        'featured'  => $featured,
                    );
                }
            }
        }
        
        wp_reset_postdata();
        
        return $data;
    }

    /**
     * Custom dashboard endpoints
     */
    public function custom_dashboard_endpoints($endpoints) {
        // Add custom endpoint for user dashboard
        $endpoints['mes-excursions'] = array(
            'title'    => __('Mes excursions', 'life-travel-core'),
            'callback' => array($this, 'user_dashboard_excursions'),
            'icon'     => 'dashicons-location-alt',
        );
        
        return $endpoints;
    }

    /**
     * User dashboard excursions
     */
    public function user_dashboard_excursions() {
        // Get current user
        $user_id = get_current_user_id();
        
        // Get user bookings
        $bookings = excursion_addon_get_user_bookings($user_id);
        
        if (!empty($bookings)) {
            echo '<div class="user-excursions">';
            echo '<h2>' . esc_html__('Vos excursions réservées', 'life-travel-core') . '</h2>';
            
            foreach ($bookings as $booking) {
                // Get product
                $product = wc_get_product($booking['product_id']);
                
                if ($product) {
                    echo '<div class="user-excursion">';
                    
                    // Image
                    if (has_post_thumbnail($booking['product_id'])) {
                        echo '<div class="excursion-image">';
                        echo get_the_post_thumbnail($booking['product_id'], 'thumbnail');
                        echo '</div>';
                    }
                    
                    // Details
                    echo '<div class="excursion-details">';
                    echo '<h3>' . esc_html($product->get_name()) . '</h3>';
                    
                    // Date
                    $excursion_date = get_post_meta($booking['product_id'], 'excursion_date', true);
                    if ($excursion_date) {
                        $date_obj = DateTime::createFromFormat('Ymd', $excursion_date);
                        $formatted_date = $date_obj ? $date_obj->format('d/m/Y') : '';
                        
                        echo '<p class="excursion-date">';
                        echo '<strong>' . esc_html__('Date:', 'life-travel-core') . '</strong> ';
                        echo esc_html($formatted_date);
                        echo '</p>';
                    }
                    
                    // Booking details
                    echo '<p class="booking-number">';
                    echo '<strong>' . esc_html__('N° de réservation:', 'life-travel-core') . '</strong> ';
                    echo esc_html($booking['id']);
                    echo '</p>';
                    
                    echo '<p class="booking-status">';
                    echo '<strong>' . esc_html__('Statut:', 'life-travel-core') . '</strong> ';
                    echo esc_html($booking['status']);
                    echo '</p>';
                    
                    // Quantity
                    echo '<p class="booking-quantity">';
                    echo '<strong>' . esc_html__('Participants:', 'life-travel-core') . '</strong> ';
                    echo esc_html($booking['quantity']);
                    echo '</p>';
                    
                    // Actions
                    echo '<div class="booking-actions">';
                    echo '<a href="' . esc_url(get_permalink($booking['product_id'])) . '" class="button">';
                    echo esc_html__('Voir l\'excursion', 'life-travel-core');
                    echo '</a>';
                    
                    // Only show cancel button if not completed
                    if ($booking['status'] !== 'completed') {
                        echo '<a href="' . esc_url(excursion_addon_get_cancel_url($booking['id'])) . '" class="button cancel">';
                        echo esc_html__('Annuler', 'life-travel-core');
                        echo '</a>';
                    }
                    
                    echo '</div>';
                    
                    echo '</div>';
                    echo '</div>';
                }
            }
            
            echo '</div>';
        } else {
            echo '<div class="no-excursions">';
            echo '<p>' . esc_html__('Vous n\'avez pas encore réservé d\'excursion.', 'life-travel-core') . '</p>';
            echo '<a href="' . esc_url(get_permalink(wc_get_page_id('shop'))) . '" class="button">';
            echo esc_html__('Découvrir nos excursions', 'life-travel-core');
            echo '</a>';
            echo '</div>';
        }
    }
}

// Initialize Addon Bridge class
Life_Travel_Core_Addon_Bridge::instance();
