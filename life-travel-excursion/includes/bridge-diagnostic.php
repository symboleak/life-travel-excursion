<?php
/**
 * Life Travel - Diagnostic des bridges
 *
 * Outil de diagnostic pour vérifier le bon fonctionnement des bridges
 * et détecter les problèmes de dépendances ou de chargement.
 *
 * @package Life Travel Excursion
 * @version 2.5.0
 */

defined('ABSPATH') || exit;

/**
 * Classe de diagnostic des bridges
 */
class Life_Travel_Bridge_Diagnostic {
    /**
     * Exécute les tests de diagnostic sur tous les bridges
     * 
     * @return array Résultats des tests
     */
    public static function run_diagnostics() {
        $results = array(
            'status' => 'ok',
            'messages' => array(),
            'bridges' => array(),
            'circular_dependencies' => array(),
            'loading_order' => array()
        );
        
        // Vérifier si l'initialiseur de bridges est disponible
        if (!class_exists('Life_Travel_Bridge_Initializer')) {
            $results['status'] = 'error';
            $results['messages'][] = 'L\'initialiseur de bridges n\'est pas disponible.';
            return $results;
        }
        
        // Récupérer le statut d'initialisation
        global $life_travel_bridge_initializer;
        $init_status = $life_travel_bridge_initializer->get_initialization_status();
        
        $results['bridges'] = array(
            'loaded' => $init_status['loaded'],
            'failed' => $init_status['failed']
        );
        
        if (!$init_status['success']) {
            $results['status'] = 'warning';
            $results['messages'][] = 'Certains bridges n\'ont pas été chargés correctement.';
        }
        
        // Vérifier les dépendances circulaires
        if (function_exists('life_travel_get_circular_dependencies')) {
            $circular = life_travel_get_circular_dependencies();
            if (!empty($circular)) {
                $results['status'] = 'error';
                $results['messages'][] = 'Des dépendances circulaires ont été détectées.';
                $results['circular_dependencies'] = $circular;
            }
        } else {
            $results['messages'][] = 'Impossible de vérifier les dépendances circulaires.';
        }
        
        // Vérifier l'ordre de chargement
        if (function_exists('life_travel_get_bridge_registry')) {
            $registry = life_travel_get_bridge_registry();
            $results['loading_order'] = array_keys($registry);
        }
        
        // Vérifier l'existence des fonctions importantes de chaque bridge
        $critical_functions = array(
            'life_travel_register_bridge',
            'life_travel_define_shared_function',
            'life_travel_call_shared_function',
            'life_travel_bridge_get_option',
            'life_travel_generate_offline_page',
            'life_travel_filter_image_attributes',
            'life_travel_register_service_worker'
        );
        
        $missing_functions = array();
        foreach ($critical_functions as $function) {
            if (!function_exists($function)) {
                $missing_functions[] = $function;
            }
        }
        
        if (!empty($missing_functions)) {
            $results['status'] = 'error';
            $results['messages'][] = 'Fonctions critiques manquantes : ' . implode(', ', $missing_functions);
            $results['missing_functions'] = $missing_functions;
        }
        
        return $results;
    }
    
    /**
     * Affiche les résultats de diagnostic sous forme de tableau HTML
     */
    public static function display_diagnostics() {
        $results = self::run_diagnostics();
        
        $status_label = array(
            'ok' => '<span style="color: green; font-weight: bold;">OK</span>',
            'warning' => '<span style="color: orange; font-weight: bold;">AVERTISSEMENT</span>',
            'error' => '<span style="color: red; font-weight: bold;">ERREUR</span>'
        );
        
        echo '<div class="wrap">';
        echo '<h1>Diagnostic des bridges Life Travel</h1>';
        
        echo '<h2>État général : ' . $status_label[$results['status']] . '</h2>';
        
        if (!empty($results['messages'])) {
            echo '<h3>Messages :</h3>';
            echo '<ul>';
            foreach ($results['messages'] as $message) {
                echo '<li>' . esc_html($message) . '</li>';
            }
            echo '</ul>';
        }
        
        echo '<h3>Bridges chargés :</h3>';
        echo '<ul>';
        foreach ($results['bridges']['loaded'] as $bridge) {
            echo '<li>' . esc_html($bridge) . '</li>';
        }
        echo '</ul>';
        
        if (!empty($results['bridges']['failed'])) {
            echo '<h3>Bridges non chargés :</h3>';
            echo '<ul>';
            foreach ($results['bridges']['failed'] as $bridge) {
                echo '<li>' . esc_html($bridge) . '</li>';
            }
            echo '</ul>';
        }
        
        if (!empty($results['circular_dependencies'])) {
            echo '<h3>Dépendances circulaires détectées :</h3>';
            echo '<ul>';
            foreach ($results['circular_dependencies'] as $path) {
                echo '<li>' . esc_html(implode(' → ', $path)) . '</li>';
            }
            echo '</ul>';
        }
        
        if (!empty($results['loading_order'])) {
            echo '<h3>Ordre de chargement :</h3>';
            echo '<ol>';
            foreach ($results['loading_order'] as $bridge) {
                echo '<li>' . esc_html($bridge) . '</li>';
            }
            echo '</ol>';
        }
        
        if (!empty($results['missing_functions'])) {
            echo '<h3>Fonctions critiques manquantes :</h3>';
            echo '<ul>';
            foreach ($results['missing_functions'] as $function) {
                echo '<li>' . esc_html($function) . '</li>';
            }
            echo '</ul>';
        }
        
        echo '</div>';
    }
    
    /**
     * Ajoute le menu d'administration pour le diagnostic
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'life-travel-admin', 
            'Diagnostic des bridges',
            'Diagnostic des bridges',
            'manage_options',
            'life-travel-bridge-diagnostic',
            array('Life_Travel_Bridge_Diagnostic', 'display_diagnostics')
        );
    }
}

// Ajouter le menu d'administration si nous sommes en contexte admin
if (is_admin()) {
    add_action('admin_menu', array('Life_Travel_Bridge_Diagnostic', 'add_admin_menu'), 99);
}

// Fonction d'accès rapide pour exécuter le diagnostic
function life_travel_run_bridge_diagnostic() {
    return Life_Travel_Bridge_Diagnostic::run_diagnostics();
}
