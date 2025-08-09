<?php
/**
 * Intégration TranslatePress avec gestion des paniers abandonnés
 *
 * Ce fichier assure que les fonctionnalités multilingues 
 * fonctionnent correctement avec le système de paniers abandonnés.
 *
 * @package Life Travel Excursion
 * @version 1.0.0
 */

// Sortir si accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe qui gère l'intégration entre TranslatePress et le système de paniers abandonnés
 */
class Life_Travel_TP_Cart_Integration {
    
    /**
     * Constructeur
     */
    public function __construct() {
        // Modifier les données du panier abandonné pour inclure la langue
        add_filter('life_travel_abandoned_cart_data', array($this, 'add_language_to_cart_data'), 10, 1);
        
        // Assurer que les emails de récupération de panier sont dans la bonne langue
        add_filter('life_travel_abandoned_cart_email_content', array($this, 'translate_cart_email_content'), 10, 2);
        
        // Modifier l'URL de récupération pour inclure le paramètre de langue
        add_filter('life_travel_cart_recovery_url', array($this, 'add_language_to_recovery_url'), 10, 2);
        
        // Assurer que la synchronisation du panier fonctionne avec le paramètre de langue
        add_action('wp_ajax_life_travel_sync_abandoned_cart', array($this, 'prepare_cart_sync'), 5);
        add_action('wp_ajax_nopriv_life_travel_sync_abandoned_cart', array($this, 'prepare_cart_sync'), 5);
    }
    
    /**
     * Ajoute la langue actuelle aux données du panier abandonné
     *
     * @param array $cart_data Les données du panier
     * @return array Les données du panier modifiées
     */
    public function add_language_to_cart_data($cart_data) {
        if (function_exists('trp_get_current_language')) {
            $cart_data['language'] = trp_get_current_language();
        }
        
        return $cart_data;
    }
    
    /**
     * Traduit le contenu de l'email de récupération de panier dans la langue appropriée
     *
     * @param string $content Le contenu de l'email
     * @param array $cart_data Les données du panier abandonné
     * @return string Le contenu traduit
     */
    public function translate_cart_email_content($content, $cart_data) {
        // Si TranslatePress n'est pas actif ou si la langue n'est pas définie, retourner le contenu original
        if (!function_exists('trp_translate_gettext') || empty($cart_data['language'])) {
            return $content;
        }
        
        // Stocker la langue actuelle
        $current_language = null;
        if (function_exists('trp_get_current_language')) {
            $current_language = trp_get_current_language();
        }
        
        // Forcer la langue du panier pour la traduction
        if (function_exists('trp_set_translation_language')) {
            trp_set_translation_language($cart_data['language']);
        }
        
        // Appliquer les traductions
        $translated_content = $content;
        
        // Traduire les chaînes spécifiques au panier abandonné
        $strings_to_translate = array(
            'Votre panier vous attend' => true,
            'Bonjour,' => true,
            'Nous avons remarqué que vous avez laissé des articles dans votre panier.' => true,
            'Cliquez ici pour finaliser votre réservation' => true,
            'Les excursions se remplissent rapidement, ne tardez pas trop !' => true,
            'À bientôt,' => true,
            'L\'équipe Life Travel' => true
        );
        
        foreach ($strings_to_translate as $string => $translate) {
            if (strpos($translated_content, $string) !== false) {
                $translated_string = trp_translate_gettext($string, $string, 'life-travel');
                $translated_content = str_replace($string, $translated_string, $translated_content);
            }
        }
        
        // Restaurer la langue originale si nécessaire
        if ($current_language && function_exists('trp_set_translation_language')) {
            trp_set_translation_language($current_language);
        }
        
        return $translated_content;
    }
    
    /**
     * Ajoute le paramètre de langue à l'URL de récupération du panier
     *
     * @param string $url L'URL de récupération
     * @param array $cart_data Les données du panier
     * @return string L'URL modifiée
     */
    public function add_language_to_recovery_url($url, $cart_data) {
        if (!empty($cart_data['language'])) {
            $url = add_query_arg('lang', $cart_data['language'], $url);
        }
        
        return $url;
    }
    
    /**
     * Prépare la synchronisation du panier avec le support multilingue
     */
    public function prepare_cart_sync() {
        // Ajouter la langue actuelle aux données à synchroniser
        if (function_exists('trp_get_current_language')) {
            $language = trp_get_current_language();
            $_POST['language'] = $language;
            
            // Ajouter le paramètre de langue à tous les liens dans les données du panier
            if (!empty($_POST['cart_data']) && is_string($_POST['cart_data'])) {
                $cart_data = json_decode(wp_unslash($_POST['cart_data']), true);
                if (is_array($cart_data)) {
                    // Ajouter la langue à chaque URL dans les données du panier
                    $this->add_language_to_cart_urls($cart_data, $language);
                    $_POST['cart_data'] = wp_slash(json_encode($cart_data));
                }
            }
        }
        
        // Laisser la fonction sync_abandoned_cart() s'exécuter normalement
    }
    
    /**
     * Ajoute récursivement le paramètre de langue à toutes les URLs dans un tableau
     *
     * @param array &$data Tableau de données à modifier
     * @param string $language Code de langue à ajouter
     */
    private function add_language_to_cart_urls(&$data, $language) {
        if (!is_array($data)) {
            return;
        }
        
        foreach ($data as $key => &$value) {
            if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                // Ajouter le paramètre de langue à l'URL
                $value = add_query_arg('lang', $language, $value);
            } elseif (is_array($value)) {
                // Appliquer récursivement aux tableaux imbriqués
                $this->add_language_to_cart_urls($value, $language);
            }
        }
    }
}

// Initialiser l'intégration seulement si TranslatePress est actif
function life_travel_init_tp_cart_integration() {
    if (function_exists('trp_translate_gettext')) {
        new Life_Travel_TP_Cart_Integration();
    }
}
add_action('plugins_loaded', 'life_travel_init_tp_cart_integration', 30);
