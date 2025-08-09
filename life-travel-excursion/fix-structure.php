<?php
/**
 * Script pour réparer la structure du fichier principal
 * 
 * Ce script va restructurer le fichier en préservant tout le code fonctionnel
 * mais en réorganisant la structure conditionnelle principale
 */

// Fichier à réparer
$file_to_fix = 'life-travel-excursion.php';
$output_file = 'life-travel-excursion-fixed.php';

if (!file_exists($file_to_fix)) {
    die("Le fichier $file_to_fix n'existe pas.\n");
}

echo "Réparation de la structure du fichier $file_to_fix...\n";

// Lire le contenu du fichier
$content = file_get_contents($file_to_fix);

// Extraire les sections principales du fichier
preg_match('/\<\?php.*?Plugin Name: Life Travel Excursion.*?Version: (.*?)\n/s', $content, $header_matches);
$plugin_header = $header_matches[0] ?? '';
$plugin_version = $header_matches[1] ?? '2.5.0';

// Extraire les définitions et initialisations
preg_match('/if \( ! defined\( \'ABSPATH\' \) \).*?require_once LIFE_TRAVEL_EXCURSION_DIR \. \'includes\/config.php\';/s', $content, $init_matches);
$init_section = $init_matches[0] ?? '';

// Extraire la fonction d'activation
preg_match('/register_activation_hook\(__FILE__, \'life_travel_excursion_activate\'\);.*?function life_travel_excursion_activate\(\) \{.*?\}/s', $content, $activation_matches);
$activation_section = $activation_matches[0] ?? '';

// Extraire la fonction de notification WooCommerce manquant
preg_match('/function life_travel_excursion_woocommerce_missing_notice\(\) \{.*?\}/s', $content, $notice_matches);
$notice_section = $notice_matches[0] ?? '';

// Extraire la vérification WooCommerce
preg_match('/\$woocommerce_active = in_array\(.*?\);/s', $content, $wc_check_matches);
$wc_check_section = $wc_check_matches[0] ?? '';

// Extraire le contenu principal quand WooCommerce est actif
preg_match('/\/\/ Inclure les fichiers d\'intégration.*?add_action\(\'woocommerce_before_my_account\'/s', $content, $main_content_matches);
$main_content = $main_content_matches[0] ?? '';

// Extraire les fonctions du plugin (celles qui sont entre les blocs principaux)
preg_match_all('/function (life_travel_excursion_[a-z_]+)\(\).*?\n\}/s', $content, $functions_matches);
$functions = $functions_matches[0] ?? [];

// Reconstruire le fichier correctement structuré
$fixed_content = <<<EOT
<?php
/**
 * Template Header (NOT A WP PLUGIN)
 * Name: Life Travel Excursion (template)
 * URI: https://www.life-travel.org/plugins/excursions
 * Details: Contenu modèle utilisé par l'outil de réparation; ce fichier généré NE DOIT PAS être traité comme une extension WordPress.
 * Version: {$plugin_version}
 * Author: Life Travel Team
 * Author URI: https://www.life-travel.org/
 * Text Domain: life-travel-excursion
 * Note: Ce bloc n'est pas un en-tête de plugin WordPress. Ne pas l'activer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Définition des constantes principales
define('LIFE_TRAVEL_EXCURSION_VERSION', '$plugin_version'); // Version du plugin pour comparaisons et mises à jour
define('LIFE_TRAVEL_EXCURSION_DIR', plugin_dir_path(__FILE__)); // Chemin absolu du plugin avec trailing slash
define('LIFE_TRAVEL_EXCURSION_URL', plugin_dir_url(__FILE__)); // URL du plugin avec trailing slash
define('LIFE_TRAVEL_EXCURSION_ASSETS', LIFE_TRAVEL_EXCURSION_URL . 'assets/'); // URL des assets

// Constantes pour le système offline/PWA (spécifique au contexte camerounais)
define('LIFE_TRAVEL_CACHE_NAME', 'life-travel-cache-v2'); // Nom du cache SW (must match avec JS)
define('LIFE_TRAVEL_SVG_PATH', LIFE_TRAVEL_EXCURSION_ASSETS . 'sprite.svg'); // Chemin du sprite SVG (unifié)
define('LIFE_TRAVEL_CAMEROON_NETWORK_TIMEOUT', 5000); // Timeout adapté aux réseaux camerounais

// Créer les dossiers requis s'ils n'existent pas
if (!file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes')) {
    mkdir(LIFE_TRAVEL_EXCURSION_DIR . 'includes', 0755, true);
}

if (!file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'assets/img')) {
    mkdir(LIFE_TRAVEL_EXCURSION_DIR . 'assets/img', 0755, true);
}

if (!file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'payment-gateways')) {
    mkdir(LIFE_TRAVEL_EXCURSION_DIR . 'payment-gateways', 0755, true);
}

// Fonction d'activation du plugin
register_activation_hook(__FILE__, 'life_travel_excursion_activate');
function life_travel_excursion_activate() {
    // Ajouter une version dans les options pour les mises à jour futures
    add_option('life_travel_excursion_version', LIFE_TRAVEL_EXCURSION_VERSION);
    
    // Activer par défaut les nouveaux systèmes pour les nouvelles installations
    add_option('life_travel_use_new_admin', true);
    add_option('life_travel_use_new_optimizer', true);
    add_option('life_travel_use_new_cart_system', true);
    add_option('life_travel_use_new_offline_system', true);
    
    // Créer les tables nécessaires via les ponts
    if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/abandoned-cart-bridge.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/abandoned-cart-bridge.php';
        // La fonction créera la table appropriée selon le système actif
        if (function_exists('life_travel_create_cart_tables')) {
            life_travel_create_cart_tables();
        }
    } else if (class_exists('Life_Travel_Abandoned_Cart')) {
        // Fallback vers l'ancien système
        Life_Travel_Abandoned_Cart::create_table();
    }
    
    // Générer la page hors ligne via le pont si disponible
    if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/offline-bridge.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/offline-bridge.php';
        if (function_exists('life_travel_generate_offline_page')) {
            life_travel_generate_offline_page();
        }
    } else if (!file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'offline.html')) {
        // Fallback: créer une page hors ligne basique si aucun pont n'est disponible
        \$offline_template = '<' . '!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mode Hors-Ligne - Life Travel Cameroun</title>
    <meta name="description" content="Vous êtes actuellement en mode hors-ligne. Certaines fonctionnalités de Life Travel ne sont pas disponibles.">
    <meta name="theme-color" content="#0073B2">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="shortcut icon" href="/assets/img/favicon.ico" type="image/x-icon">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        h1 {
            color: #333;
        }
        .btn {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Vous êtes hors ligne</h1>
        <p>Votre connexion internet semble interrompue. Vos données ont été sauvegardées.</p>
        <a href="javascript:window.location.reload()" class="btn">Réessayer</a>
    </div>
</body>
</html>';
        file_put_contents(LIFE_TRAVEL_EXCURSION_DIR . 'offline.html', \$offline_template);
    }
}

// Définir la fonction d'erreur en dehors des conditions pour qu'elle soit toujours disponible
function life_travel_excursion_woocommerce_missing_notice() {
    echo '<div class="error"><p>' . esc_html__( 'Le plugin Life Travel Excursion nécessite WooCommerce pour fonctionner.', 'life-travel-excursion' ) . '</p></div>';
}

// Structure principale du plugin 
// Vérifier si WooCommerce est actif
\$woocommerce_active = in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));

// Charger le plugin uniquement si WooCommerce est actif
if (\$woocommerce_active) {
    // Inclure les fichiers d'intégration
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/config.php'; // Configuration centralisée en premier
    
    // Ponts de migration pour éviter les doublons et assurer la transition
    if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/performance-optimizer-bridge.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/performance-optimizer-bridge.php';
    } else {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/performance-optimizer.php'; // Ancien optimisateur
    }
    
    // Pont pour les scripts JavaScript
    if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/js-bridge.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/js-bridge.php';
    }
    
    // Pont pour les styles CSS
    if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/css-bridge.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/css-bridge.php';
    }
    
    // Système centralisé d'initialisation des bridges pour éviter les dépendances circulaires
    if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/init-bridges.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/init-bridges.php';
        // L'initialisation des bridges sera gérée dans l'ordre optimal par l'initialiseur
    } else {
        // Fallback vers l'ancien système de chargement individuel des ponts
        if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/pwa-bridge.php')) {
            require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/pwa-bridge.php';
        }
        
        // Pont pour les ressources graphiques
        if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/images-bridge.php')) {
            require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/images-bridge.php';
        }
    }
    
    if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/abandoned-cart-bridge.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/abandoned-cart-bridge.php';
    } else {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/abandoned-cart.php';  // Ancien système
    }
    
    // Initialiser le connecteur d'admin si disponible
    if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin-connector.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin-connector.php';
    }
    
    // Système de validation centralisé pour vérifier la cohérence
    if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/bridge-validator.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/bridge-validator.php';
    }
    
    // Gestion du mode hors ligne (chargé par l'initialiseur, uniquement si fallback nécessaire)
    if (!file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/init-bridges.php') && 
        file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/offline-bridge.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/offline-bridge.php';
    }
    
    // Chargement du tableau de bord d'administration unifié ou de l'ancien système
    if (file_exists(LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin-bridge.php')) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin-bridge.php';
    } else {
        // Ancien système d'administration
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin/admin-menu.php';
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin/admin-settings.php';
    }
    
    // Inclusions principales du fonctionnement de base
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/frontend/checkout-enhancement.php'; // Amélioration du checkout
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/frontend/my-account-tabs.php'; // Onglets personnalisés My Account
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/frontend/authentication-manager.php'; // Authentification sécurisée
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/frontend/authentication-ajax.php'; // AJAX pour l'authentification
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/frontend/booking-form.php'; // Formulaire de réservation
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/frontend/booking-validation.php'; // Validation des réservations
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/frontend/product-sync.php'; // Synchronisation des produits
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/frontend/availability-calendar.php'; // Calendrier de disponibilité
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/frontend/excursion-display.php'; // Affichage des excursions
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/frontend/notifications.php'; // Système de notifications
    require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/helpers.php'; // Fonctions d'aide réutilisables

    // Inclure les fichiers d'administration
    if (is_admin()) {
        require_once LIFE_TRAVEL_EXCURSION_DIR . 'includes/admin/notification-settings.php';
    }
    
    // Inclure le gateway Orange Money
    require_once __DIR__ . '/includes/payment-gateway-orange-money.php';
    add_filter('woocommerce_payment_gateways', function(\$gateways) {
        \$gateways[] = 'Life_Travel_Gateway_Orange_Money';
        return \$gateways;
    });
    
    // Inclusions frontend intégrées dans la structure principale
    require_once __DIR__ . '/includes/frontend/myaccount.php';
    require_once __DIR__ . '/includes/frontend/auth.php';
    require_once __DIR__ . '/includes/frontend/loyalty.php';
    
    // Configuration de l'affichage du login dans My Account
    add_action('woocommerce_before_my_account', function() {
        if (!is_user_logged_in()) {
            echo do_shortcode('[lte_login]');
            return;
        }
    });
} 
else {
    // Message d'erreur si WooCommerce n'est pas actif
    add_action('admin_notices', 'life_travel_excursion_woocommerce_missing_notice');
}

EOT;

// Autres fonctions du plugin
foreach ($functions as $function) {
    $fixed_content .= "\n" . $function . "\n";
}

// Écrire le contenu fixé dans le nouveau fichier
file_put_contents($output_file, $fixed_content);

echo "Réparation terminée. Fichier sauvegardé sous $output_file\n";
echo "Vérification de la structure du fichier réparé...\n";

// Vérifier la structure du fichier réparé
$repaired_content = file_get_contents($output_file);
$repaired_lines = explode("\n", $repaired_content);

// Compteurs pour vérification
$open_braces = 0;
$brace_positions = [];
$line_number = 0;

// Analyser ligne par ligne
foreach ($repaired_lines as $line) {
    $line_number++;
    $chars = str_split($line);
    
    foreach ($chars as $position => $char) {
        // Compter les accolades
        if ($char === '{') {
            $open_braces++;
        } else if ($char === '}') {
            $open_braces--;
            
            // Détecter un déséquilibre immédiat
            if ($open_braces < 0) {
                echo "ERREUR dans le fichier réparé: Accolade fermante sans ouvrante correspondante à la ligne $line_number\n";
                exit(1);
            }
        }
    }
}

// Vérifier l'équilibre final
if ($open_braces === 0) {
    echo "Les accolades sont équilibrées dans le fichier réparé. Structure valide.\n";
} else {
    echo "ERREUR dans le fichier réparé: La structure des accolades n'est pas équilibrée.\n";
    echo "Nombre d'accolades ouvrantes sans fermeture: $open_braces\n";
    exit(1);
}

echo "Réparation réussie.\n";
