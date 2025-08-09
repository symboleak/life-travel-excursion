<?php
/**
 * Performance Optimizations for Life Travel
 *
 * @package Life_Travel_Core
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Optimizations Class
 */
class Life_Travel_Core_Optimizations {
    /**
     * Instance
     * 
     * @var Life_Travel_Core_Optimizations|null
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
        // Image optimizations
        add_filter('wp_editor_set_quality', array($this, 'set_jpeg_quality'), 10, 2);
        add_filter('wp_calculate_image_srcset', array($this, 'adjust_image_srcset'), 10, 5);
        add_filter('wp_get_attachment_image_attributes', array($this, 'add_lazyload_attribute'), 10, 3);
        add_filter('the_content', array($this, 'add_lazyload_to_content_images'));
        add_filter('wp_generate_attachment_metadata', array($this, 'generate_webp_images'), 10, 2);
        
        // Script optimizations
        add_action('wp_enqueue_scripts', array($this, 'optimize_scripts'), 999);
        add_filter('script_loader_tag', array($this, 'add_defer_async_attributes'), 10, 3);
        
        // CSS optimizations
        add_action('wp_enqueue_scripts', array($this, 'optimize_styles'), 999);
        add_action('wp_head', array($this, 'add_critical_css'), 1);
        
        // HTML optimizations
        add_action('template_redirect', array($this, 'buffer_start'));
        add_action('shutdown', array($this, 'buffer_end'));
        
        // WordPress core optimizations
        add_action('init', array($this, 'disable_unnecessary_features'));
        add_action('wp_default_scripts', array($this, 'remove_jquery_migrate'));
        
        // WooCommerce specific optimizations
        add_action('wp_enqueue_scripts', array($this, 'optimize_woocommerce'), 100);
        add_filter('woocommerce_enqueue_styles', array($this, 'optimize_woocommerce_styles'));
        
        // Mobile detection
        add_action('init', array($this, 'setup_mobile_detection'));
    }

    /**
     * Set JPEG quality
     */
    public function set_jpeg_quality($quality, $context) {
        return 75; // Lower quality for smaller file size
    }

    /**
     * Adjust image srcset
     */
    public function adjust_image_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
        // Add WebP versions to srcset if available
        if (!empty($sources)) {
            foreach ($sources as $width => $source) {
                $webp_url = $this->get_webp_url($source['url']);
                
                if ($webp_url) {
                    $sources[$width]['url'] = $webp_url;
                }
            }
        }
        
        return $sources;
    }

    /**
     * Get WebP URL from original URL
     */
    private function get_webp_url($url) {
        // Check if webp version exists
        $file_path = str_replace(site_url('/'), ABSPATH, $url);
        $webp_path = $file_path . '.webp';
        
        if (file_exists($webp_path)) {
            return $url . '.webp';
        }
        
        return false;
    }

    /**
     * Add lazyload attribute to images
     */
    public function add_lazyload_attribute($attr, $attachment, $size) {
        if (!is_admin() && !wp_doing_ajax() && !is_feed()) {
            // Don't lazy load images in the admin
            $attr['loading'] = 'lazy';
        }
        
        return $attr;
    }

    /**
     * Add lazyload to content images
     */
    public function add_lazyload_to_content_images($content) {
        if (!is_admin() && !wp_doing_ajax() && !is_feed()) {
            // Don't lazy load images in the admin
            $content = preg_replace('/<img(.*?)>/', '<img$1 loading="lazy">', $content);
        }
        
        return $content;
    }

    /**
     * Generate WebP images
     */
    public function generate_webp_images($metadata, $attachment_id) {
        // Check if we have GD with WebP support
        if (!function_exists('imagewebp')) {
            return $metadata;
        }
        
        // Get attachment file path
        $file = get_attached_file($attachment_id);
        
        if (!file_exists($file)) {
            return $metadata;
        }
        
        // Check file type
        $mime_type = get_post_mime_type($attachment_id);
        
        if ($mime_type !== 'image/jpeg' && $mime_type !== 'image/png') {
            return $metadata;
        }
        
        // Generate WebP for original file
        $this->convert_to_webp($file);
        
        // Generate WebP for each size
        if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
            $upload_dir = wp_upload_dir();
            $base_dir = $upload_dir['basedir'] . '/' . dirname($metadata['file']) . '/';
            
            foreach ($metadata['sizes'] as $size => $size_data) {
                $size_file = $base_dir . $size_data['file'];
                $this->convert_to_webp($size_file);
            }
        }
        
        return $metadata;
    }

    /**
     * Convert image to WebP
     */
    private function convert_to_webp($file) {
        if (!file_exists($file)) {
            return false;
        }
        
        $webp_file = $file . '.webp';
        
        // Get image data
        $image_data = getimagesize($file);
        
        if (!$image_data) {
            return false;
        }
        
        // Create image resource
        switch ($image_data[2]) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($file);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($file);
                
                // Handle transparency
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
                break;
            default:
                return false;
        }
        
        // Save WebP image
        imagewebp($image, $webp_file, 75);
        
        // Free memory
        imagedestroy($image);
        
        return true;
    }

    /**
     * Optimize scripts
     */
    public function optimize_scripts() {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }
        
        // Move jQuery to footer
        global $wp_scripts;
        
        if (isset($wp_scripts->registered['jquery'])) {
            $wp_scripts->registered['jquery']->deps = array_diff($wp_scripts->registered['jquery']->deps, array('jquery-migrate'));
        }
        
        // Remove emoji script
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        
        // Remove unnecessary meta tags
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');
        
        // Disable embed script
        wp_dequeue_script('wp-embed');
        
        // Disable comment script if not needed
        if (!is_singular() || !comments_open()) {
            wp_dequeue_script('comment-reply');
        }
    }

    /**
     * Add defer/async attributes to scripts
     */
    public function add_defer_async_attributes($tag, $handle, $src) {
        // Check if the script should be deferred or async
        $defer_scripts = array(
            'life-travel-core',
            'woocommerce',
            'wc-cart-fragments',
            'jquery-blockui',
        );
        
        $async_scripts = array(
            'contact-form-7',
            'google-recaptcha',
        );
        
        if (in_array($handle, $defer_scripts)) {
            return str_replace(' src', ' defer src', $tag);
        }
        
        if (in_array($handle, $async_scripts)) {
            return str_replace(' src', ' async src', $tag);
        }
        
        return $tag;
    }

    /**
     * Optimize styles
     */
    public function optimize_styles() {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }
        
        // Remove unnecessary styles
        wp_dequeue_style('wp-block-library-theme');
        
        // Only load WooCommerce styles on WooCommerce pages
        if (!is_woocommerce() && !is_cart() && !is_checkout() && !is_account_page()) {
            wp_dequeue_style('woocommerce-general');
            wp_dequeue_style('woocommerce-layout');
            wp_dequeue_style('woocommerce-smallscreen');
        }
    }

    /**
     * Add critical CSS
     */
    public function add_critical_css() {
        // Output critical CSS inline
        echo '<style id="life-travel-critical-css">';
        
        // Base styles
        echo '
        /* Critical CSS for Life Travel */
        :root {
            --primary-color: #1F4D36;
            --secondary-color: #E19D4C;
            --light-color: #F7F3EB;
            --dark-color: #3D2B1F;
            --accent-color: #C53D13;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* Header */
        .site-header {
            background-color: var(--light-color);
            padding: 1rem;
            position: relative;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .site-branding {
            display: flex;
            align-items: center;
        }
        
        .site-title {
            font-size: 1.5rem;
            margin: 0;
        }
        
        /* Navigation */
        .main-navigation ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
        }
        
        .main-navigation li {
            margin-right: 1.5rem;
        }
        
        .main-navigation a {
            color: var(--dark-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        /* Mobile Menu Button */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
            
            .main-navigation ul {
                display: none;
            }
        }
        
        /* Hero Banner */
        .hero-banner {
            background-color: var(--primary-color);
            color: var(--light-color);
            padding: 2rem 1rem;
            text-align: center;
            position: relative;
        }
        
        .hero-banner h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        /* Buttons */
        .button, 
        .wp-block-button__link,
        .woocommerce a.button, 
        .woocommerce button.button {
            background-color: var(--secondary-color);
            color: var(--dark-color);
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        /* WhatsApp Button */
        .whatsapp-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #25D366;
            color: white;
            border-radius: 50%;
            width: 54px;
            height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 999;
        }
        
        /* Basic Layout */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        .content-area {
            padding: 2rem 0;
        }
        
        /* Footer */
        .site-footer {
            background-color: var(--primary-color);
            color: var(--light-color);
            padding: 2rem 1rem;
        }
        ';
        
        echo '</style>';
    }

    /**
     * Start output buffer
     */
    public function buffer_start() {
        if (!is_admin() && !wp_doing_ajax()) {
            ob_start(array($this, 'optimize_html'));
        }
    }

    /**
     * End output buffer
     */
    public function buffer_end() {
        if (ob_get_length() && !is_admin() && !wp_doing_ajax()) {
            ob_end_flush();
        }
    }

    /**
     * Optimize HTML
     */
    public function optimize_html($html) {
        if (empty($html)) {
            return $html;
        }
        
        // Remove HTML comments (except IE conditional comments)
        $html = preg_replace('/<!--[^\[><](.*?)-->/s', '', $html);
        
        // Remove whitespace
        $html = preg_replace('/\s+/', ' ', $html);
        $html = preg_replace('/>\s+</', '><', $html);
        
        return $html;
    }

    /**
     * Disable unnecessary features
     */
    public function disable_unnecessary_features() {
        // Disable RSS feeds if not needed
        // remove_action('wp_head', 'feed_links', 2);
        // remove_action('wp_head', 'feed_links_extra', 3);
        
        // Disable oEmbed
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
        
        // Disable XML-RPC
        add_filter('xmlrpc_enabled', '__return_false');
        
        // Remove REST API links
        remove_action('wp_head', 'rest_output_link_wp_head');
        remove_action('template_redirect', 'rest_output_link_header', 11);
    }

    /**
     * Remove jQuery migrate
     */
    public function remove_jquery_migrate($scripts) {
        if (!is_admin() && isset($scripts->registered['jquery'])) {
            $script = $scripts->registered['jquery'];
            
            if ($script->deps) {
                $script->deps = array_diff($script->deps, array('jquery-migrate'));
            }
        }
    }

    /**
     * Optimize WooCommerce
     */
    public function optimize_woocommerce() {
        if (!is_admin() && !wp_doing_ajax()) {
            // Remove WooCommerce scripts and styles from non-WooCommerce pages
            if (!is_woocommerce() && !is_cart() && !is_checkout() && !is_account_page()) {
                wp_dequeue_style('woocommerce-general');
                wp_dequeue_style('woocommerce-layout');
                wp_dequeue_style('woocommerce-smallscreen');
                
                wp_dequeue_script('woocommerce');
                wp_dequeue_script('wc-cart-fragments');
                wp_dequeue_script('wc-add-to-cart');
                
                // Don't dequeue on product pages that might be embedded elsewhere
                if (!is_singular('product')) {
                    wp_dequeue_script('wc-single-product');
                }
            }
        }
    }

    /**
     * Optimize WooCommerce styles
     */
    public function optimize_woocommerce_styles($styles) {
        // Remove unnecessary WooCommerce styles
        unset($styles['woocommerce-smallscreen']);
        
        return $styles;
    }

    /**
     * Setup mobile detection
     */
    public function setup_mobile_detection() {
        // Check user agent for mobile devices
        if (!isset($_SESSION['is_mobile'])) {
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            $is_mobile = preg_match('/(android|webos|iphone|ipad|ipod|blackberry|windows phone)/i', $user_agent);
            
            // Store in session to avoid checking on every page load
            $_SESSION['is_mobile'] = $is_mobile;
        }
        
        // Add body class for mobile
        if ($_SESSION['is_mobile']) {
            add_filter('body_class', function($classes) {
                $classes[] = 'is-mobile';
                return $classes;
            });
        }
    }
}

// Initialize Optimizations class
Life_Travel_Core_Optimizations::instance();
