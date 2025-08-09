<?php
/**
 * Générateur de fichiers CSV pour les réservations
 * Solution alternative ne nécessitant pas PhpSpreadsheet
 *
 * @package Life_Travel_Excursion
 */

defined('ABSPATH') || exit;

/**
 * Classe de génération de fichiers CSV pour les réservations
 */
class Life_Travel_CSV_Generator {
    /**
     * Instance de la classe
     */
    private static $instance = null;
    
    /**
     * Dossier de stockage temporaire des fichiers
     */
    private $export_dir;
    
    /**
     * Délai d'expiration des fichiers CSV en heures
     */
    private $file_expiry = 24;
    
    /**
     * Constructeur
     */
    private function __construct() {
        // Définir le répertoire d'export
        $upload_dir = wp_upload_dir();
        $this->export_dir = $upload_dir['basedir'] . '/lte-exports/';
        
        // Créer le répertoire s'il n'existe pas
        if (!file_exists($this->export_dir)) {
            wp_mkdir_p($this->export_dir);
            
            // Créer un fichier .htaccess pour empêcher l'accès direct
            $htaccess = "deny from all\n";
            file_put_contents($this->export_dir . '.htaccess', $htaccess);
        }
        
        // Nettoyer les anciens fichiers
        add_action('init', [$this, 'cleanup_expired_files']);
        
        // Gérer les requêtes de téléchargement
        add_action('init', [$this, 'handle_download_request']);
    }
    
    /**
     * Récupère l'instance unique
     *
     * @return Life_Travel_CSV_Generator
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Génère un fichier CSV pour une réservation spécifique
     *
     * @param int $order_id ID de la commande
     * @return string|bool Chemin vers le fichier CSV ou false en cas d'échec
     */
    public function generate_order_csv($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return false;
        }
        
        $filename = 'reservation-' . $order_id . '-' . date('Ymd-His') . '.csv';
        $filepath = $this->export_dir . $filename;
        
        // Ouvrir le fichier en écriture
        $handle = fopen($filepath, 'w');
        
        if (!$handle) {
            return false;
        }
        
        // Ajouter la marque UTF-8 BOM pour Excel
        fputs($handle, "\xEF\xBB\xBF");
        
        // Entêtes du fichier CSV
        $headers = [
            __('ID Commande', 'life-travel-excursion'),
            __('Date', 'life-travel-excursion'),
            __('Statut', 'life-travel-excursion'),
            __('Client', 'life-travel-excursion'),
            __('Email', 'life-travel-excursion'),
            __('Téléphone', 'life-travel-excursion'),
            __('Excursion', 'life-travel-excursion'),
            __('Date d\'excursion', 'life-travel-excursion'),
            __('Nombre de participants', 'life-travel-excursion'),
            __('Total', 'life-travel-excursion'),
            __('Méthode de paiement', 'life-travel-excursion')
        ];
        
        // Écrire les entêtes
        fputcsv($handle, $headers, ';');
        
        // Récupérer les informations principales de la commande
        $date = $order->get_date_created() ? $order->get_date_created()->format('Y-m-d H:i:s') : '';
        $status = wc_get_order_status_name($order->get_status());
        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $customer_email = $order->get_billing_email();
        $customer_phone = $order->get_billing_phone();
        $payment_method = $order->get_payment_method_title();
        $total = $order->get_total();
        
        // Parcourir les articles de la commande
        foreach ($order->get_items() as $item) {
            $product_name = $item->get_name();
            $product_id = $item->get_product_id();
            
            // Récupérer la date de l'excursion et le nombre de participants
            $excursion_date = '';
            $participants = 0;
            
            // Récupérer les métadonnées de l'article
            if ($item->get_meta('_lte_excursion_date')) {
                $excursion_date = $item->get_meta('_lte_excursion_date');
            }
            
            if ($item->get_meta('_lte_participants')) {
                $participants = $item->get_meta('_lte_participants');
            }
            
            // Données pour cette ligne
            $line = [
                $order->get_id(),
                $date,
                $status,
                $customer_name,
                $customer_email,
                $customer_phone,
                $product_name,
                $excursion_date,
                $participants,
                $total,
                $payment_method
            ];
            
            // Écrire la ligne dans le CSV
            fputcsv($handle, $line, ';');
        }
        
        // Ajouter une section pour les données de facturation
        $billing_headers = [
            '',
            __('Informations de facturation', 'life-travel-excursion'),
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            ''
        ];
        fputcsv($handle, $billing_headers, ';');
        
        $billing_address = [
            __('Adresse', 'life-travel-excursion'),
            $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            ''
        ];
        fputcsv($handle, $billing_address, ';');
        
        $billing_city = [
            __('Ville', 'life-travel-excursion'),
            $order->get_billing_city(),
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            ''
        ];
        fputcsv($handle, $billing_city, ';');
        
        $billing_country = [
            __('Pays', 'life-travel-excursion'),
            $order->get_billing_country(),
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            ''
        ];
        fputcsv($handle, $billing_country, ';');
        
        // Ajouter les notes de commande
        $notes_headers = [
            '',
            __('Notes de commande', 'life-travel-excursion'),
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            ''
        ];
        fputcsv($handle, $notes_headers, ';');
        
        $customer_note = [
            __('Note client', 'life-travel-excursion'),
            $order->get_customer_note(),
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            ''
        ];
        fputcsv($handle, $customer_note, ';');
        
        // Fermer le fichier
        fclose($handle);
        
        // Vérifier que le fichier a bien été créé
        if (file_exists($filepath)) {
            return $filepath;
        }
        
        return false;
    }
    
    /**
     * Génère un fichier CSV contenant toutes les réservations
     *
     * @return string|bool Chemin vers le fichier CSV ou false en cas d'échec
     */
    public function generate_all_reservations_csv() {
        $filename = 'toutes-reservations-' . date('Ymd-His') . '.csv';
        $filepath = $this->export_dir . $filename;
        
        // Ouvrir le fichier en écriture
        $handle = fopen($filepath, 'w');
        
        if (!$handle) {
            return false;
        }
        
        // Ajouter la marque UTF-8 BOM pour Excel
        fputs($handle, "\xEF\xBB\xBF");
        
        // Entêtes du fichier CSV
        $headers = [
            __('ID Commande', 'life-travel-excursion'),
            __('Date', 'life-travel-excursion'),
            __('Statut', 'life-travel-excursion'),
            __('Client', 'life-travel-excursion'),
            __('Email', 'life-travel-excursion'),
            __('Téléphone', 'life-travel-excursion'),
            __('Excursion', 'life-travel-excursion'),
            __('Date d\'excursion', 'life-travel-excursion'),
            __('Nombre de participants', 'life-travel-excursion'),
            __('Total', 'life-travel-excursion'),
            __('Méthode de paiement', 'life-travel-excursion')
        ];
        
        // Écrire les entêtes
        fputcsv($handle, $headers, ';');
        
        // Récupérer les commandes récentes (maximum 500)
        $orders = wc_get_orders([
            'limit' => 500,
            'type' => 'shop_order',
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        
        foreach ($orders as $order) {
            $date = $order->get_date_created() ? $order->get_date_created()->format('Y-m-d H:i:s') : '';
            $status = wc_get_order_status_name($order->get_status());
            $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $customer_email = $order->get_billing_email();
            $customer_phone = $order->get_billing_phone();
            $payment_method = $order->get_payment_method_title();
            $total = $order->get_total();
            
            // Parcourir les articles de la commande
            foreach ($order->get_items() as $item) {
                $product_name = $item->get_name();
                $product_id = $item->get_product_id();
                
                // Récupérer la date de l'excursion et le nombre de participants
                $excursion_date = '';
                $participants = 0;
                
                // Récupérer les métadonnées de l'article
                if ($item->get_meta('_lte_excursion_date')) {
                    $excursion_date = $item->get_meta('_lte_excursion_date');
                }
                
                if ($item->get_meta('_lte_participants')) {
                    $participants = $item->get_meta('_lte_participants');
                }
                
                // Données pour cette ligne
                $line = [
                    $order->get_id(),
                    $date,
                    $status,
                    $customer_name,
                    $customer_email,
                    $customer_phone,
                    $product_name,
                    $excursion_date,
                    $participants,
                    $total,
                    $payment_method
                ];
                
                // Écrire la ligne dans le CSV
                fputcsv($handle, $line, ';');
            }
        }
        
        // Fermer le fichier
        fclose($handle);
        
        // Vérifier que le fichier a bien été créé
        if (file_exists($filepath)) {
            return $filepath;
        }
        
        return false;
    }
    
    /**
     * Récupère l'URL de téléchargement d'un fichier
     *
     * @param string $file Chemin vers le fichier
     * @return string URL de téléchargement
     */
    public function get_download_url($file) {
        if (!$file) {
            return '';
        }
        
        $filename = basename($file);
        $nonce = wp_create_nonce('lte_download_' . $filename);
        
        return add_query_arg([
            'action' => 'lte_download_csv',
            'file' => $filename,
            'nonce' => $nonce
        ], admin_url('admin-ajax.php'));
    }
    
    /**
     * Gère les requêtes de téléchargement
     */
    public function handle_download_request() {
        if (isset($_GET['action']) && $_GET['action'] === 'lte_download_csv' && 
            isset($_GET['file']) && isset($_GET['nonce'])) {
            
            $filename = sanitize_file_name($_GET['file']);
            $nonce = sanitize_text_field($_GET['nonce']);
            
            // Vérifier le nonce
            if (!wp_verify_nonce($nonce, 'lte_download_' . $filename)) {
                wp_die(__('Lien de téléchargement non valide ou expiré.', 'life-travel-excursion'));
            }
            
            $filepath = $this->export_dir . $filename;
            
            // Vérifier que le fichier existe
            if (!file_exists($filepath)) {
                wp_die(__('Le fichier demandé n\'existe plus.', 'life-travel-excursion'));
            }
            
            // Télécharger le fichier
            header('Content-Description: File Transfer');
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filepath));
            flush();
            readfile($filepath);
            exit;
        }
    }
    
    /**
     * Nettoie les fichiers expirés
     */
    public function cleanup_expired_files() {
        // Exécuter le nettoyage une fois par jour (en utilisant un transient)
        if (false === get_transient('lte_csv_cleanup')) {
            // Supprimer les fichiers plus anciens que la période d'expiration
            $files = glob($this->export_dir . '*.csv');
            $now = time();
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    $file_time = filemtime($file);
                    $expiry_seconds = $this->file_expiry * 3600; // Convertir en secondes
                    
                    if ($now - $file_time > $expiry_seconds) {
                        @unlink($file);
                    }
                }
            }
            
            // Définir le transient pour 24 heures
            set_transient('lte_csv_cleanup', true, 24 * HOUR_IN_SECONDS);
        }
    }
}

// Initialisation
add_action('init', function() {
    Life_Travel_CSV_Generator::get_instance();
});
