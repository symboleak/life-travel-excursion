<?php
/**
 * Méthodes pour le tableau de bord unifié Life Travel
 * 
 * Ces méthodes complètent la classe principale d'administration
 * 
 * @package Life Travel Excursion
 * @version 2.3.7
 */

defined('ABSPATH') || exit;

/**
 * Méthodes de la classe d'administration
 */
trait Life_Travel_Admin_Methods {
    
    /**
     * Enregistre les menus d'administration dans WordPress
     */
    public function register_admin_menu() {
        foreach ($this->admin_pages as $id => $page) {
            // Pages de premier niveau
            if (empty($page['parent'])) {
                $hook = add_menu_page(
                    $page['title'],                // Titre de la page
                    $page['menu_title'],           // Titre dans le menu
                    $page['capability'],           // Capacité requise
                    'life-travel-' . $id,          // Slug de la page
                    array($this, 'render_admin_page'), // Callback d'affichage
                    $page['icon'],                 // Icône
                    $page['position']              // Position
                );
                
                // Stocker le hook pour les assets
                $this->admin_pages[$id]['hook'] = $hook;
            } 
            // Sous-pages
            else {
                $parent = isset($this->admin_pages[$page['parent']]) 
                    ? 'life-travel-' . $page['parent'] 
                    : $page['parent'];
                    
                $hook = add_submenu_page(
                    $parent,                      // Slug de la page parente
                    $page['title'],               // Titre de la page
                    $page['menu_title'],          // Titre dans le menu
                    $page['capability'],          // Capacité requise
                    'life-travel-' . $id,         // Slug de la page
                    array($this, 'render_admin_page') // Callback d'affichage
                );
                
                // Stocker le hook pour les assets
                $this->admin_pages[$id]['hook'] = $hook;
            }
        }
    }
    
    /**
     * Enregistre une option d'administration
     * 
     * @param string $page_id ID de la page
     * @param string $option_id ID de l'option
     * @param array $args Configuration
     * @return $this Pour chaînage
     */
    public function register_admin_option($page_id, $option_id, $args = []) {
        $defaults = [
            'label' => '',           // Libellé de l'option
            'description' => '',     // Description ou aide
            'type' => 'text',        // Type (text, select, checkbox, etc.)
            'default' => '',         // Valeur par défaut
            'options' => [],         // Options pour les select
            'section' => '',         // Section de la page
            'sanitize' => 'text',    // Méthode de sanitisation
            'show_in_rest' => false, // Exposer dans l'API REST
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Stocker l'option
        if (!isset($this->admin_options[$page_id])) {
            $this->admin_options[$page_id] = [];
        }
        
        $this->admin_options[$page_id][$option_id] = $args;
        
        return $this;
    }
    
    /**
     * Affiche une page d'administration
     */
    public function render_admin_page() {
        // Obtenir l'ID de la page depuis l'URL
        $screen = get_current_screen();
        $page_id = str_replace('life-travel-', '', $screen->id);
        
        if (!isset($this->admin_pages[$page_id])) {
            wp_die(__('Page non trouvée', 'life-travel-excursion'));
        }
        
        $page = $this->admin_pages[$page_id];
        
        // Afficher l'en-tête de la page
        echo '<div class="wrap life-travel-admin-wrap">';
        echo '<h1>' . esc_html($page['title']) . '</h1>';
        
        // Afficher un message d'aide si défini
        if (!empty($page['help'])) {
            echo '<div class="life-travel-admin-help">';
            echo '<p>' . wp_kses_post($page['help']) . '</p>';
            echo '</div>';
        }
        
        // Si la page a des sections, afficher les onglets
        if (!empty($page['sections'])) {
            $active_section = isset($_GET['section']) ? sanitize_key($_GET['section']) : key($page['sections']);
            
            echo '<div class="nav-tab-wrapper life-travel-nav-tab-wrapper">';
            
            foreach ($page['sections'] as $section_id => $section) {
                $active_class = ($active_section === $section_id) ? 'nav-tab-active' : '';
                $section_url = add_query_arg('section', $section_id);
                
                echo '<a href="' . esc_url($section_url) . '" class="nav-tab ' . esc_attr($active_class) . '">';
                if (!empty($section['icon'])) {
                    echo '<span class="dashicons ' . esc_attr($section['icon']) . '"></span> ';
                }
                echo esc_html($section['title']);
                echo '</a>';
            }
            
            echo '</div>';
            
            // Afficher le contenu de la section active
            if (isset($page['sections'][$active_section]['callback'])) {
                $callback = $page['sections'][$active_section]['callback'];
                
                echo '<div class="life-travel-admin-section life-travel-admin-section-' . esc_attr($active_section) . '">';
                
                if (is_callable($callback)) {
                    call_user_func($callback, $page_id, $active_section);
                }
                
                echo '</div>';
            }
        }
        // Sinon, afficher le contenu de la page principale
        else if (isset($page['callback']) && is_callable($page['callback'])) {
            call_user_func($page['callback'], $page_id);
        }
        
        echo '</div>'; // .wrap
    }
    
    /**
     * Charge les assets d'administration
     * 
     * @param string $hook Hook de la page courante
     */
    public function admin_assets($hook) {
        // Trouver la page correspondante au hook
        $current_page = null;
        foreach ($this->admin_pages as $id => $page) {
            if (isset($page['hook']) && $page['hook'] === $hook) {
                $current_page = $id;
                break;
            }
        }
        
        // Si ce n'est pas une de nos pages, sortir
        if (null === $current_page) {
            return;
        }
        
        // CSS commun à toutes les pages
        wp_enqueue_style(
            'life-travel-admin',
            LIFE_TRAVEL_EXCURSION_URL . 'assets/css/admin-dashboard.css',
            array(),
            LIFE_TRAVEL_EXCURSION_VERSION
        );
        
        // JavaScript commun
        wp_enqueue_script(
            'life-travel-admin',
            LIFE_TRAVEL_EXCURSION_URL . 'assets/js/admin-dashboard.js',
            array('jquery'),
            LIFE_TRAVEL_EXCURSION_VERSION,
            true
        );
        
        // Localisation des variables JS
        wp_localize_script('life-travel-admin', 'lifeTravelAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('life_travel_admin_nonce'),
            'i18n' => array(
                'saving' => __('Enregistrement...', 'life-travel-excursion'),
                'saved' => __('Enregistré !', 'life-travel-excursion'),
                'error' => __('Erreur lors de l\'enregistrement', 'life-travel-excursion'),
                'confirm' => __('Êtes-vous sûr ?', 'life-travel-excursion'),
            )
        ));
        
        // Media uploader
        wp_enqueue_media();
        
        // Assets spécifiques par page
        if ($current_page === 'dashboard') {
            wp_enqueue_script(
                'life-travel-dashboard',
                LIFE_TRAVEL_EXCURSION_URL . 'assets/js/admin-dashboard-main.js',
                array('jquery', 'life-travel-admin'),
                LIFE_TRAVEL_EXCURSION_VERSION,
                true
            );
        } else if ($current_page === 'media') {
            wp_enqueue_script(
                'life-travel-media',
                LIFE_TRAVEL_EXCURSION_URL . 'assets/js/admin-media.js',
                array('jquery', 'life-travel-admin'),
                LIFE_TRAVEL_EXCURSION_VERSION,
                true
            );
        } else if ($current_page === 'network') {
            wp_enqueue_script(
                'life-travel-network',
                LIFE_TRAVEL_EXCURSION_URL . 'assets/js/admin-network.js',
                array('jquery', 'life-travel-admin'),
                LIFE_TRAVEL_EXCURSION_VERSION,
                true
            );
        } else if ($current_page === 'payments') {
            wp_enqueue_script(
                'life-travel-payments',
                LIFE_TRAVEL_EXCURSION_URL . 'assets/js/admin-payments.js',
                array('jquery', 'life-travel-admin'),
                LIFE_TRAVEL_EXCURSION_VERSION,
                true
            );
        } else if ($current_page === 'abandoned_carts') {
            // Chargement spécifique pour paniers abandonnés avec statistiques
            wp_enqueue_script(
                'life-travel-charts',
                LIFE_TRAVEL_EXCURSION_URL . 'assets/js/admin-charts.js',
                array('jquery'),
                LIFE_TRAVEL_EXCURSION_VERSION,
                true
            );
            
            wp_enqueue_script(
                'life-travel-abandoned',
                LIFE_TRAVEL_EXCURSION_URL . 'assets/js/admin-abandoned.js',
                array('jquery', 'life-travel-admin', 'life-travel-charts'),
                LIFE_TRAVEL_EXCURSION_VERSION,
                true
            );
        }
    }
    
    /**
     * Ajoute des onglets d'aide contextuelle
     * 
     * @param WP_Screen $screen Écran courant
     */
    public function add_help_tabs($screen) {
        // Obtenir l'ID de la page depuis l'écran
        if (!$screen || !$screen->id) {
            return;
        }
        
        $page_id = str_replace('life-travel-', '', $screen->id);
        
        if (!isset($this->admin_pages[$page_id])) {
            return;
        }
        
        $page = $this->admin_pages[$page_id];
        
        // Si la page a des tooltips définis, les ajouter
        if (!empty($this->tooltips[$page_id])) {
            foreach ($this->tooltips[$page_id] as $tab_id => $tooltip) {
                $screen->add_help_tab(array(
                    'id'       => 'life-travel-help-' . $tab_id,
                    'title'    => $tooltip['title'],
                    'content'  => $tooltip['content'],
                ));
            }
            
            // Panneau latéral d'aide
            $screen->set_help_sidebar(
                '<p><strong>' . __('Pour plus d\'informations:', 'life-travel-excursion') . '</strong></p>' .
                '<p><a href="https://www.life-travel.org/aide" target="_blank">' .
                __('Documentation Life Travel', 'life-travel-excursion') .
                '</a></p>'
            );
        }
    }
    
    /**
     * Traitement AJAX pour enregistrer une option
     */
    public function ajax_save_option() {
        // Vérification de sécurité
        if (!check_ajax_referer('life_travel_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Erreur de sécurité', 'life-travel-excursion')
            ));
        }
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Permissions insuffisantes', 'life-travel-excursion')
            ));
        }
        
        // Obtenir les paramètres
        $page_id = isset($_POST['page_id']) ? sanitize_key($_POST['page_id']) : '';
        $option_id = isset($_POST['option_id']) ? sanitize_key($_POST['option_id']) : '';
        $value = isset($_POST['value']) ? $_POST['value'] : '';
        
        // Vérifier que l'option existe
        if (!isset($this->admin_options[$page_id][$option_id])) {
            wp_send_json_error(array(
                'message' => __('Option inconnue', 'life-travel-excursion')
            ));
        }
        
        $option = $this->admin_options[$page_id][$option_id];
        
        // Sanitiser la valeur selon le type
        $sanitized_value = $this->sanitize_option_value($value, $option['sanitize']);
        
        // Enregistrer l'option
        $option_name = 'life_travel_' . $page_id . '_' . $option_id;
        update_option($option_name, $sanitized_value);
        
        // Réponse de succès
        wp_send_json_success(array(
            'message' => __('Option enregistrée', 'life-travel-excursion'),
            'value' => $sanitized_value
        ));
    }
    
    /**
     * Sanitize une valeur d'option selon son type
     * 
     * @param mixed $value Valeur à sanitiser
     * @param string $type Type de sanitisation
     * @return mixed Valeur sanitisée
     */
    private function sanitize_option_value($value, $type) {
        switch ($type) {
            case 'text':
                return sanitize_text_field($value);
                
            case 'textarea':
                return sanitize_textarea_field($value);
                
            case 'email':
                return sanitize_email($value);
                
            case 'url':
                return esc_url_raw($value);
                
            case 'int':
                return intval($value);
                
            case 'float':
                return floatval($value);
                
            case 'bool':
                return (bool) $value;
                
            case 'array':
                if (is_array($value)) {
                    return array_map('sanitize_text_field', $value);
                }
                return array();
                
            case 'html':
                return wp_kses_post($value);
                
            default:
                if (is_callable($type)) {
                    return call_user_func($type, $value);
                }
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Enregistre un tooltip d'aide
     * 
     * @param string $page_id ID de la page
     * @param string $tooltip_id ID du tooltip
     * @param array $args Configuration
     * @return $this Pour chaînage
     */
    public function register_tooltip($page_id, $tooltip_id, $args = []) {
        $defaults = [
            'title' => '',    // Titre de l'onglet d'aide
            'content' => '',  // Contenu HTML
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        if (!isset($this->tooltips[$page_id])) {
            $this->tooltips[$page_id] = [];
        }
        
        $this->tooltips[$page_id][$tooltip_id] = $args;
        
        return $this;
    }
}
