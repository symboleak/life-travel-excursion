<?php
/**
 * Fonctions de sécurité pour Life Travel
 * 
 * Ce fichier contient toutes les fonctions et hooks liés à la sécurisation
 * du site Life Travel.
 *
 * @package Life_Travel
 * @since 1.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialisation des fonctionnalités de sécurité
 */
function life_travel_security_init() {
    // Protection contre les injections XSS
    add_filter('the_content', 'life_travel_xss_content_filter');
    add_filter('comment_text', 'life_travel_xss_content_filter');
    
    // Protection de l'API REST pour les utilisateurs non authentifiés
    add_filter('rest_authentication_errors', 'life_travel_restrict_rest_api');
    
    // Masquer les erreurs de connexion spécifiques
    add_filter('login_errors', 'life_travel_hide_login_errors');
    
    // Désactiver la découverte de version XML-RPC
    add_filter('xmlrpc_enabled', '__return_false');
    
    // Ajouter des en-têtes de sécurité
    add_action('send_headers', 'life_travel_security_headers');
    
    // Protection contre les attaques de force brute
    add_filter('authenticate', 'life_travel_limit_login_attempts', 30, 3);
    
    // Journalisation des connexions
    add_action('wp_login', 'life_travel_log_successful_login', 10, 2);
    add_action('wp_login_failed', 'life_travel_log_failed_login');
}
add_action('init', 'life_travel_security_init');

/**
 * Filter pour prévenir les attaques XSS dans le contenu
 *
 * @param string $content Le contenu à filtrer
 * @return string Contenu filtré
 */
function life_travel_xss_content_filter($content) {
    // Utiliser la fonction WordPress kses pour filtrer le contenu
    // et autoriser seulement certaines balises HTML sécurisées
    $allowed_html = wp_kses_allowed_html('post');
    
    // Supprimer les balises script et iframe pour plus de sécurité
    unset($allowed_html['script']);
    unset($allowed_html['iframe']);
    
    return wp_kses($content, $allowed_html);
}

/**
 * Restreint l'accès à l'API REST pour les utilisateurs non authentifiés
 *
 * @param WP_Error|null|bool $access Current access status
 * @return WP_Error|null|bool Modified access status
 */
function life_travel_restrict_rest_api($access) {
    // Si l'utilisateur n'est pas connecté
    if (!is_user_logged_in()) {
        // Liste des routes autorisées pour les visiteurs (nécessaire pour les blocs Gutenberg en frontend)
        $allowed_routes = array(
            '/wp/v2/pages',
            '/wp/v2/posts',
            '/wp/v2/media',
            '/wc/v3/products',  // Pour les produits WooCommerce (excursions)
        );
        
        // Récupérer l'URI demandée
        $current_route = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        
        // Autoriser l'accès aux routes sélectionnées
        foreach ($allowed_routes as $route) {
            if (strpos($current_route, $route) !== false) {
                return $access;
            }
        }
        
        // Journaliser les tentatives d'accès non autorisées
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
            error_log('Tentative d\'accès REST API non autorisée - IP: ' . $ip . ' - Route: ' . $current_route);
        }
        
        // Renvoyer une erreur d'authentification
        return new WP_Error(
            'rest_not_logged_in',
            __('Accès non autorisé.', 'life-travel'),
            array('status' => 401)
        );
    }
    
    return $access;
}

/**
 * Masquer les messages d'erreur de connexion spécifiques
 *
 * @param string $error Le message d'erreur
 * @return string Message d'erreur générique
 */
function life_travel_hide_login_errors($error) {
    // Remplacer les messages d'erreur spécifiques par un message générique
    return __('Identifiants incorrects. Veuillez réessayer.', 'life-travel');
}

/**
 * Ajouter des en-têtes de sécurité
 */
function life_travel_security_headers() {
    // Protection contre le clickjacking
    header('X-Frame-Options: SAMEORIGIN');
    
    // Protection XSS pour les navigateurs modernes
    header('X-XSS-Protection: 1; mode=block');
    
    // Empêcher le MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Politique de sécurité du contenu (CSP)
    $csp = "default-src 'self'; " .
           "script-src 'self' 'unsafe-inline' 'unsafe-eval' *.googleapis.com *.gstatic.com *.google.com *.google-analytics.com; " .
           "style-src 'self' 'unsafe-inline' *.googleapis.com; " .
           "img-src 'self' data: *.googleapis.com *.gstatic.com *.google-analytics.com; " .
           "font-src 'self' data: *.gstatic.com *.googleapis.com; " .
           "connect-src 'self' *.google-analytics.com;";
    
    // Activer la CSP seulement en production, pas en développement
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        // Utiliser Content-Security-Policy-Report-Only pour tester avant de l'activer complètement
        header("Content-Security-Policy-Report-Only: $csp");
    }
    
    // Référer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Feature Policy (maintenant Permissions Policy)
    header("Permissions-Policy: geolocation=(self), camera=(), microphone=()");
}

/**
 * Limiter les tentatives de connexion
 *
 * @param WP_User|WP_Error|null $user User to authenticate
 * @param string $username Username
 * @param string $password Password
 * @return WP_User|WP_Error|null Modified user
 */
function life_travel_limit_login_attempts($user, $username, $password) {
    if (empty($username)) {
        return $user;
    }
    
    // Récupérer l'adresse IP du visiteur
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    
    if (empty($ip)) {
        return $user;
    }
    
    // Récupérer les tentatives pour cette IP
    $attempts = get_transient('login_attempts_' . $ip);
    $attempts = false === $attempts ? 0 : $attempts;
    
    // Si l'authentification échoue, incrémenter le compteur
    if (is_wp_error($user)) {
        $attempts++;
        set_transient('login_attempts_' . $ip, $attempts, HOUR_IN_SECONDS);
        
        // Journaliser les tentatives multiples
        if ($attempts >= 3 && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Tentatives de connexion multiples (' . $attempts . ') - IP: ' . $ip . ' - Nom d\'utilisateur: ' . $username);
        }
        
        // Bloquer après 5 tentatives
        if ($attempts >= 5) {
            return new WP_Error(
                'too_many_attempts',
                sprintf(
                    __('Trop de tentatives de connexion. Veuillez réessayer dans %d minutes.', 'life-travel'),
                    ceil(HOUR_IN_SECONDS / 60)
                )
            );
        }
    } else {
        // Réinitialiser les tentatives en cas de succès
        delete_transient('login_attempts_' . $ip);
    }
    
    return $user;
}

/**
 * Journaliser les connexions réussies
 *
 * @param string $user_login Nom d'utilisateur
 * @param WP_User $user Objet utilisateur
 */
function life_travel_log_successful_login($user_login, $user) {
    if (!$user || !is_object($user)) {
        return;
    }
    
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : 'unknown';
    
    // Journaliser la connexion
    $log_message = sprintf(
        'Connexion réussie - Utilisateur: %s (ID: %d) - IP: %s - User-Agent: %s',
        $user_login,
        $user->ID,
        $ip,
        $user_agent
    );
    
    // Sauvegarder dans le journal WordPress
    error_log($log_message);
    
    // Notifier l'administrateur si c'est une connexion admin depuis une nouvelle IP
    if (in_array('administrator', (array) $user->roles, true)) {
        $known_ips = get_user_meta($user->ID, '_known_login_ips', true);
        $known_ips = $known_ips ? $known_ips : array();
        
        if (!in_array($ip, $known_ips, true)) {
            // Ajouter l'IP à la liste des IPs connues
            $known_ips[] = $ip;
            update_user_meta($user->ID, '_known_login_ips', $known_ips);
            
            // Envoyer une notification par email (uniquement en production)
            if (!defined('WP_DEBUG') || !WP_DEBUG) {
                $admin_email = get_option('admin_email');
                $subject = __('[Life Travel] Connexion administrateur depuis une nouvelle adresse IP', 'life-travel');
                $message = sprintf(
                    __('Un administrateur (%s) s\'est connecté depuis une nouvelle adresse IP: %s', 'life-travel'),
                    $user_login,
                    $ip
                );
                
                wp_mail($admin_email, $subject, $message);
            }
        }
    }
}

/**
 * Journaliser les échecs de connexion
 *
 * @param string $username Nom d'utilisateur tenté
 */
function life_travel_log_failed_login($username) {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : 'unknown';
    
    // Journaliser l'échec
    $log_message = sprintf(
        'Tentative de connexion échouée - Utilisateur: %s - IP: %s - User-Agent: %s',
        $username,
        $ip,
        $user_agent
    );
    
    // Sauvegarder dans le journal WordPress
    error_log($log_message);
}

/**
 * Ajoute les bonnes pratiques de sécurité dans wp-config.php
 * (Cette fonction doit être appelée manuellement ou lors de l'activation du thème)
 */
function life_travel_secure_wp_config() {
    // Ce code est commenté car il nécessite un accès direct au fichier wp-config.php
    // qui n'est pas accessible depuis le code du thème en fonctionnement normal
    
    // Exemple de modification recommandée pour wp-config.php:
    /*
    // Désactiver l'éditeur de fichiers
    define('DISALLOW_FILE_EDIT', true);
    
    // Forcer les connexions SSL pour l'administration
    define('FORCE_SSL_ADMIN', true);
    
    // Limiter les révisions
    define('WP_POST_REVISIONS', 5);
    
    // Activer la protection par salt
    define('AUTH_KEY',         'clé unique à générer');
    define('SECURE_AUTH_KEY',  'clé unique à générer');
    define('LOGGED_IN_KEY',    'clé unique à générer');
    define('NONCE_KEY',        'clé unique à générer');
    define('AUTH_SALT',        'clé unique à générer');
    define('SECURE_AUTH_SALT', 'clé unique à générer');
    define('LOGGED_IN_SALT',   'clé unique à générer');
    define('NONCE_SALT',       'clé unique à générer');
    */
}

/**
 * Vérification périodique de sécurité
 * Cette fonction peut être appelée via une tâche cron personnalisée
 */
function life_travel_security_check() {
    $issues = array();
    
    // Vérifier les plugins obsolètes
    if (function_exists('get_plugins')) {
        $plugins = get_plugins();
        foreach ($plugins as $plugin_file => $plugin_data) {
            if (isset($plugin_data['Version']) && isset($plugin_data['update']) && $plugin_data['update']) {
                $issues[] = sprintf(
                    __('Plugin obsolète: %s (version %s)', 'life-travel'),
                    $plugin_data['Name'],
                    $plugin_data['Version']
                );
            }
        }
    }
    
    // Vérifier les thèmes obsolètes
    if (function_exists('wp_get_themes')) {
        $themes = wp_get_themes();
        foreach ($themes as $theme_slug => $theme) {
            $update = get_theme_update_available($theme);
            if ($update) {
                $issues[] = sprintf(
                    __('Thème obsolète: %s (version %s)', 'life-travel'),
                    $theme->get('Name'),
                    $theme->get('Version')
                );
            }
        }
    }
    
    // Vérifier si WordPress est à jour
    include_once ABSPATH . WPINC . '/version.php';
    global $wp_version;
    $update_core = get_site_transient('update_core');
    if ($update_core && isset($update_core->updates) && !empty($update_core->updates)) {
        $latest_version = $update_core->updates[0]->current;
        if (version_compare($wp_version, $latest_version, '<')) {
            $issues[] = sprintf(
                __('WordPress obsolète: %s (version la plus récente: %s)', 'life-travel'),
                $wp_version,
                $latest_version
            );
        }
    }
    
    // Vérifier les paramètres de sécurité critiques
    if (!defined('DISALLOW_FILE_EDIT') || !DISALLOW_FILE_EDIT) {
        $issues[] = __('L\'édition de fichiers est activée dans wp-config.php', 'life-travel');
    }
    
    // Vérifier si le préfixe de table est le défaut
    global $wpdb;
    if ($wpdb->prefix === 'wp_') {
        $issues[] = __('Préfixe de table de base de données par défaut (wp_) utilisé', 'life-travel');
    }
    
    // Si des problèmes sont trouvés, envoyer un rapport à l'administrateur
    if (!empty($issues) && function_exists('wp_mail')) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        $subject = sprintf(__('[%s] Rapport de sécurité - Actions requises', 'life-travel'), $site_name);
        
        $message = __('Le rapport de sécurité de votre site a identifié les problèmes suivants:', 'life-travel') . "\n\n";
        foreach ($issues as $issue) {
            $message .= "- $issue\n";
        }
        
        $message .= "\n" . __('Veuillez prendre les mesures appropriées pour résoudre ces problèmes.', 'life-travel');
        
        wp_mail($admin_email, $subject, $message);
    }
    
    // Journaliser le résultat
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Vérification de sécurité Life Travel - Problèmes trouvés: ' . count($issues));
    }
    
    return $issues;
}
