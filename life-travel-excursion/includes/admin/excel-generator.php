<?php
/**
 * Générateur de fichiers Excel pour les réservations
 *
 * @package Life_Travel_Excursion
 */

defined('ABSPATH') || exit;

/**
 * Classe de génération de fichiers Excel pour les réservations
 */
class Life_Travel_Excel_Generator {
    /**
     * Instance de la classe
     */
    private static $instance = null;
    
    /**
     * Répertoire pour les fichiers Excel temporaires
     */
    private $excel_dir;
    
    /**
     * URL pour les fichiers Excel temporaires
     */
    private $excel_url;
    
    /**
     * Constructeur
     */
    private function __construct() {
        // Définir le répertoire de stockage
        $upload_dir = wp_upload_dir();
        $this->excel_dir = $upload_dir['basedir'] . '/life-travel-exports/';
        $this->excel_url = $upload_dir['baseurl'] . '/life-travel-exports/';
        
        // S'assurer que le répertoire existe
        if (!file_exists($this->excel_dir)) {
            wp_mkdir_p($this->excel_dir);
            
            // Créer un fichier .htaccess pour protéger le répertoire
            $htaccess_content = "Order deny,allow\nDeny from all\n\n# Allow export download via WordPress\n<Files \"*.php\">\nAllow from all\n</Files>";
            file_put_contents($this->excel_dir . '.htaccess', $htaccess_content);
        }
        
        // Gérer le téléchargement des fichiers Excel
        add_action('init', [$this, 'handle_download_request']);
        
        // Nettoyage périodique des fichiers temporaires
        add_action('life_travel_cleanup_exports', [$this, 'cleanup_old_exports']);
        
        // Planifier le nettoyage s'il n'est pas déjà planifié
        if (!wp_next_scheduled('life_travel_cleanup_exports')) {
            wp_schedule_event(time(), 'daily', 'life_travel_cleanup_exports');
        }
    }
    
    /**
     * Récupère l'instance unique
     *
     * @return Life_Travel_Excel_Generator
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Vérifie si la bibliothèque PhpSpreadsheet est disponible
     *
     * @return bool
     */
    public function check_excel_library() {
        return class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet');
    }
    
    /**
     * Génère un fichier Excel pour une commande spécifique
     *
     * @param int $order_id ID de la commande
     * @return string|bool Chemin vers le fichier générée ou false en cas d'échec
     */
    public function generate_order_excel($order_id) {
        // Vérifier si la bibliothèque est disponible
        if (!$this->check_excel_library()) {
            $this->maybe_load_excel_library();
            
            // Vérifier à nouveau
            if (!$this->check_excel_library()) {
                return false;
            }
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        // Créer un nouveau document Excel
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Configurer l'en-tête
        $sheet->setCellValue('A1', 'Détails de la réservation #' . $order->get_order_number());
        $sheet->mergeCells('A1:G1');
        
        // Style pour l'en-tête
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        // Information client
        $sheet->setCellValue('A3', 'Informations client');
        $sheet->mergeCells('A3:G3');
        $sheet->getStyle('A3')->getFont()->setBold(true);
        
        $sheet->setCellValue('A4', 'Nom');
        $sheet->setCellValue('B4', $order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        
        $sheet->setCellValue('A5', 'Email');
        $sheet->setCellValue('B5', $order->get_billing_email());
        
        $sheet->setCellValue('A6', 'Téléphone');
        $sheet->setCellValue('B6', $order->get_billing_phone());
        
        $sheet->setCellValue('A7', 'Date de commande');
        $sheet->setCellValue('B7', $order->get_date_created()->date_i18n(get_option('date_format') . ' ' . get_option('time_format')));
        
        // Détails des excursions
        $sheet->setCellValue('A9', 'Détails des excursions');
        $sheet->mergeCells('A9:G9');
        $sheet->getStyle('A9')->getFont()->setBold(true);
        
        $sheet->setCellValue('A10', 'Excursion');
        $sheet->setCellValue('B10', 'Quantité');
        $sheet->setCellValue('C10', 'Prix unitaire');
        $sheet->setCellValue('D10', 'Date de l\'excursion');
        $sheet->setCellValue('E10', 'Nombre de participants');
        $sheet->setCellValue('F10', 'Options');
        $sheet->setCellValue('G10', 'Total');
        
        $sheet->getStyle('A10:G10')->getFont()->setBold(true);
        
        $row = 11;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            
            // Vérifier si c'est une excursion
            if ($product && has_term('excursion', 'product_cat', $product->get_id())) {
                $sheet->setCellValue('A' . $row, $item->get_name());
                $sheet->setCellValue('B' . $row, $item->get_quantity());
                $sheet->setCellValue('C' . $row, $order->get_formatted_line_subtotal($item));
                
                // Récupérer les méta-données d'excursion
                $excursion_date = wc_get_order_item_meta($item->get_id(), '_excursion_date', true);
                $participants = wc_get_order_item_meta($item->get_id(), '_participants', true);
                $options = wc_get_order_item_meta($item->get_id(), '_excursion_options', true);
                
                $sheet->setCellValue('D' . $row, $excursion_date);
                $sheet->setCellValue('E' . $row, is_array($participants) ? count($participants) : $item->get_quantity());
                
                // Formatter les options
                $options_text = '';
                if (is_array($options)) {
                    foreach ($options as $option_name => $option_value) {
                        $options_text .= $option_name . ': ' . $option_value . "\n";
                    }
                }
                $sheet->setCellValue('F' . $row, $options_text);
                
                $sheet->setCellValue('G' . $row, $item->get_total());
                
                // Si nous avons des participants, ajouter une liste détaillée
                if (is_array($participants) && !empty($participants)) {
                    $row++;
                    $sheet->setCellValue('A' . $row, 'Liste des participants:');
                    $sheet->mergeCells('A' . $row . ':G' . $row);
                    $sheet->getStyle('A' . $row)->getFont()->setItalic(true);
                    
                    $row++;
                    $sheet->setCellValue('A' . $row, 'Nom');
                    $sheet->setCellValue('B' . $row, 'Email');
                    $sheet->setCellValue('C' . $row, 'Téléphone');
                    $sheet->setCellValue('D' . $row, 'Âge');
                    $sheet->setCellValue('E' . $row, 'Besoins spéciaux');
                    $sheet->mergeCells('E' . $row . ':G' . $row);
                    $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
                    
                    foreach ($participants as $participant) {
                        $row++;
                        $sheet->setCellValue('A' . $row, isset($participant['name']) ? $participant['name'] : '');
                        $sheet->setCellValue('B' . $row, isset($participant['email']) ? $participant['email'] : '');
                        $sheet->setCellValue('C' . $row, isset($participant['phone']) ? $participant['phone'] : '');
                        $sheet->setCellValue('D' . $row, isset($participant['age']) ? $participant['age'] : '');
                        $sheet->setCellValue('E' . $row, isset($participant['special_needs']) ? $participant['special_needs'] : '');
                        $sheet->mergeCells('E' . $row . ':G' . $row);
                    }
                }
                
                $row++;
            }
        }
        
        // Totaux
        $row++;
        $sheet->setCellValue('F' . $row, 'Sous-total:');
        $sheet->setCellValue('G' . $row, $order->get_subtotal());
        
        $row++;
        $sheet->setCellValue('F' . $row, 'Réduction:');
        $sheet->setCellValue('G' . $row, $order->get_discount_total());
        
        $row++;
        $sheet->setCellValue('F' . $row, 'Taxe:');
        $sheet->setCellValue('G' . $row, $order->get_total_tax());
        
        $row++;
        $sheet->setCellValue('F' . $row, 'Total:');
        $sheet->setCellValue('G' . $row, $order->get_total());
        $sheet->getStyle('F' . $row . ':G' . $row)->getFont()->setBold(true);
        
        // Informations de statut
        $row += 2;
        $sheet->setCellValue('A' . $row, 'Informations de statut');
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Statut actuel');
        $sheet->setCellValue('B' . $row, wc_get_order_status_name($order->get_status()));
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Mode de paiement');
        $sheet->setCellValue('B' . $row, $order->get_payment_method_title());
        
        // Ajuster les largeurs des colonnes
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(20);
        $sheet->getColumnDimension('G')->setWidth(15);
        
        // Enregistrer le fichier
        $filename = 'reservation-' . $order_id . '-' . date('YmdHis') . '.xlsx';
        $filepath = $this->excel_dir . $filename;
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filepath);
        
        // Retourner le chemin du fichier
        return $filepath;
    }
    
    /**
     * Génère un fichier Excel récapitulatif de toutes les réservations en cours
     *
     * @return string|bool Chemin vers le fichier généré ou false en cas d'échec
     */
    public function generate_all_reservations_excel() {
        // Vérifier si la bibliothèque est disponible
        if (!$this->check_excel_library()) {
            $this->maybe_load_excel_library();
            
            // Vérifier à nouveau
            if (!$this->check_excel_library()) {
                return false;
            }
        }
        
        // Créer un nouveau document Excel
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Configurer l'en-tête
        $sheet->setCellValue('A1', 'Récapitulatif des réservations - ' . date_i18n(get_option('date_format')));
        $sheet->mergeCells('A1:I1');
        
        // Style pour l'en-tête
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        // En-têtes de colonnes
        $sheet->setCellValue('A3', 'ID');
        $sheet->setCellValue('B3', 'Date');
        $sheet->setCellValue('C3', 'Client');
        $sheet->setCellValue('D3', 'Email');
        $sheet->setCellValue('E3', 'Téléphone');
        $sheet->setCellValue('F3', 'Excursions');
        $sheet->setCellValue('G3', 'Statut');
        $sheet->setCellValue('H3', 'Paiement');
        $sheet->setCellValue('I3', 'Total');
        
        $sheet->getStyle('A3:I3')->getFont()->setBold(true);
        
        // Récupérer toutes les commandes d'excursion
        $orders = wc_get_orders([
            'status' => ['processing', 'on-hold', 'completed'],
            'limit' => -1,
            'return' => 'objects',
        ]);
        
        $row = 4;
        foreach ($orders as $order) {
            $has_excursion = false;
            $excursions_list = [];
            
            // Vérifier si la commande contient une excursion
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if ($product && has_term('excursion', 'product_cat', $product->get_id())) {
                    $has_excursion = true;
                    $excursion_date = wc_get_order_item_meta($item->get_id(), '_excursion_date', true);
                    $date_info = $excursion_date ? ' (' . $excursion_date . ')' : '';
                    $excursions_list[] = $item->get_name() . ' x' . $item->get_quantity() . $date_info;
                }
            }
            
            // Si cette commande contient une excursion, l'ajouter au tableau
            if ($has_excursion) {
                $sheet->setCellValue('A' . $row, $order->get_order_number());
                $sheet->setCellValue('B' . $row, $order->get_date_created()->date_i18n(get_option('date_format')));
                $sheet->setCellValue('C' . $row, $order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
                $sheet->setCellValue('D' . $row, $order->get_billing_email());
                $sheet->setCellValue('E' . $row, $order->get_billing_phone());
                $sheet->setCellValue('F' . $row, implode("\n", $excursions_list));
                $sheet->setCellValue('G' . $row, wc_get_order_status_name($order->get_status()));
                $sheet->setCellValue('H' . $row, $order->get_payment_method_title());
                $sheet->setCellValue('I' . $row, $order->get_total());
                
                $row++;
            }
        }
        
        // Ajuster les largeurs des colonnes
        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(25);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(40);
        $sheet->getColumnDimension('G')->setWidth(15);
        $sheet->getColumnDimension('H')->setWidth(15);
        $sheet->getColumnDimension('I')->setWidth(15);
        
        // Calculer les statistiques
        $row++;
        $sheet->setCellValue('A' . $row, 'Statistiques');
        $sheet->mergeCells('A' . $row . ':I' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Nombre total de réservations:');
        $sheet->setCellValue('B' . $row, $row - 5); // Moins les lignes d'en-tête
        $sheet->mergeCells('A' . $row . ':B' . $row);
        
        // Enregistrer le fichier
        $filename = 'toutes-reservations-' . date('YmdHis') . '.xlsx';
        $filepath = $this->excel_dir . $filename;
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filepath);
        
        // Retourner le chemin du fichier
        return $filepath;
    }
    
    /**
     * Génère une URL de téléchargement pour un fichier
     *
     * @param string $filepath Chemin du fichier
     * @return string URL de téléchargement sécurisée
     */
    public function get_download_url($filepath) {
        if (!$filepath || !file_exists($filepath)) {
            return '';
        }
        
        $filename = basename($filepath);
        $token = wp_create_nonce('download_excel_' . $filename);
        
        return add_query_arg([
            'action' => 'lte_download_excel',
            'file' => $filename,
            'token' => $token
        ], admin_url('admin-ajax.php'));
    }
    
    /**
     * Gère les requêtes de téléchargement de fichiers Excel
     */
    public function handle_download_request() {
        if (isset($_GET['action']) && $_GET['action'] === 'lte_download_excel' && isset($_GET['file']) && isset($_GET['token'])) {
            $filename = sanitize_file_name($_GET['file']);
            $token = sanitize_text_field($_GET['token']);
            
            // Vérifier le nonce
            if (!wp_verify_nonce($token, 'download_excel_' . $filename)) {
                wp_die(__('Lien de téléchargement invalide ou expiré.', 'life-travel-excursion'));
            }
            
            $filepath = $this->excel_dir . $filename;
            
            // Vérifier que le fichier existe
            if (!file_exists($filepath)) {
                wp_die(__('Le fichier demandé n\'existe pas.', 'life-travel-excursion'));
            }
            
            // Forcer le téléchargement
            header('Content-Description: File Transfer');
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));
            header('Pragma: no-cache');
            header('Expires: 0');
            
            readfile($filepath);
            exit;
        }
    }
    
    /**
     * Nettoie les anciens fichiers d'export
     */
    public function cleanup_old_exports() {
        if (!is_dir($this->excel_dir)) {
            return;
        }
        
        $files = glob($this->excel_dir . '*.xlsx');
        $now = time();
        
        foreach ($files as $file) {
            // Supprimer les fichiers de plus de 24 heures
            if ($now - filemtime($file) > 24 * 3600) {
                @unlink($file);
            }
        }
    }
    
    /**
     * Tente de charger la bibliothèque PhpSpreadsheet
     */
    private function maybe_load_excel_library() {
        // Essayer de charger via Composer si disponible
        $composer_autoload = LIFE_TRAVEL_EXCURSION_DIR . 'vendor/autoload.php';
        if (file_exists($composer_autoload)) {
            require_once $composer_autoload;
        }
        
        // Si la classe n'est toujours pas disponible, tenter de la charger manuellement
        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            // Essayer de charger depuis des emplacements communs
            $common_locations = [
                ABSPATH . 'wp-content/plugins/woocommerce-excel-export/vendor/autoload.php',
                ABSPATH . 'wp-content/plugins/wp-spreadsheet/vendor/autoload.php',
                WP_PLUGIN_DIR . '/woocommerce-excel-export/vendor/autoload.php',
                WP_PLUGIN_DIR . '/wp-spreadsheet/vendor/autoload.php'
            ];
            
            foreach ($common_locations as $location) {
                if (file_exists($location)) {
                    require_once $location;
                    if (class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
                        break;
                    }
                }
            }
        }
    }
}

// Initialisation
add_action('init', function() {
    Life_Travel_Excel_Generator::get_instance();
});
