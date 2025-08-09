<?php
/**
 * Système de fidélité lié aux excursions
 *
 * Gère l'attribution de points pour les achats d'excursions
 * et permet la configuration par excursion du système de points.
 *
 * @package Life_Travel
 * @subpackage Frontend
 * @since 2.5.0
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe principale pour gérer les points de fidélité liés aux excursions
 */
class Life_Travel_Loyalty_Excursions {
    
    /**
     * Instance unique (pattern singleton)
     */
    private static $instance = null;
    
    /**
     * Limite maximale de points configurée
     */
    private $max_points_limit = 0;
    
    /**
     * Points par devise configurés
     */
    private $points_per_currency = 0;
    
    /**
     * Constructor
     */
    private function __construct() {
        // Chargement des configurations
        $this->max_points_limit = get_option('lte_max_loyalty_points', 1000);
        $this->points_per_currency = get_option('lte_points_per_currency', 5);
        
        // Hooks pour l'attribution des points après commande
        add_action('woocommerce_order_status_completed', array($this, 'award_points_for_order'), 10, 1);
        
        // Hooks pour l'affichage des notifications
        add_action('woocommerce_before_my_account', array($this, 'display_points_notifications'));
        add_action('wp_footer', array($this, 'display_floating_notifications'));
        
        // Hooks pour l'utilisation des points
        add_action('woocommerce_before_checkout_form', array($this, 'display_points_redemption_form'));
        add_filter('woocommerce_calculated_total', array($this, 'apply_points_discount'), 10, 2);
        
        // Filtre pour l'affichage du solde de points
        add_filter('lte_get_user_loyalty_points', array($this, 'get_user_loyalty_points'), 10, 1);
    }
    
    /**
     * Retourne l'instance unique de la classe
     *
     * @return Life_Travel_Loyalty_Excursions
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Attribue des points pour une commande complétée
     *
     * @param int $order_id ID de la commande
     * @return void
     */
    public function award_points_for_order($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        $user_id = $order->get_customer_id();
        
        if (!$user_id) {
            return; // Commande invité, pas de points
        }
        
        $total_points = 0;
        $points_breakdown = array();
        
        // Parcourir tous les produits de la commande
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);
            
            if (!$product || $product->get_type() !== 'excursion') {
                continue; // Ignorer les produits qui ne sont pas des excursions
            }
            
            // Récupérer la configuration des points pour cette excursion
            $points_type = get_post_meta($product_id, '_loyalty_points_type', true);
            $points_value = get_post_meta($product_id, '_loyalty_points_value', true);
            
            if (!$points_type || !$points_value) {
                // Configuration par défaut si non spécifiée
                $points_type = 'percentage';
                $points_value = $this->points_per_currency;
            }
            
            $item_total = $item->get_total();
            $points_earned = 0;
            
            if ($points_type === 'fixed') {
                // Points fixes par excursion
                $points_earned = intval($points_value);
            } else {
                // Points basés sur le montant dépensé
                $points_earned = floor($item_total * intval($points_value) / 100);
            }
            
            // Appliquer le plafond spécifique à l'excursion s'il existe
            $excursion_max = get_post_meta($product_id, '_loyalty_points_max', true);
            if ($excursion_max && $points_earned > intval($excursion_max)) {
                $points_earned = intval($excursion_max);
            }
            
            // Enregistrer les points pour cette excursion
            $points_breakdown[$product_id] = array(
                'name' => $product->get_name(),
                'points' => $points_earned
            );
            
            $total_points += $points_earned;
        }
        
        if ($total_points <= 0) {
            return; // Aucun point à attribuer
        }
        
        // Appliquer le plafond global
        if ($this->max_points_limit > 0 && $total_points > $this->max_points_limit) {
            $total_points = $this->max_points_limit;
        }
        
        // Récupérer les points actuels
        $current_points = get_user_meta($user_id, '_lte_loyalty_points', true);
        if (!is_numeric($current_points)) {
            $current_points = 0;
        }
        
        // Mettre à jour les points
        $new_points = $current_points + $total_points;
        update_user_meta($user_id, '_lte_loyalty_points', $new_points);
        
        // Stocker la notification de points
        $this->store_points_notification($user_id, $total_points, $points_breakdown, $order_id);
        
        // Log pour le débogage
        error_log(sprintf(
            'Points de fidélité attribués: %d points à l\'utilisateur #%d pour la commande #%d',
            $total_points,
            $user_id,
            $order_id
        ));
    }
    
    /**
     * Stocke une notification de points pour l'utilisateur
     *
     * @param int $user_id ID de l'utilisateur
     * @param int $points Nombre de points attribués
     * @param array $breakdown Détail des points par excursion
     * @param int $order_id ID de la commande
     * @return void
     */
    private function store_points_notification($user_id, $points, $breakdown, $order_id) {
        $notifications = get_user_meta($user_id, '_lte_loyalty_notifications', true);
        
        if (!is_array($notifications)) {
            $notifications = array();
        }
        
        // Limiter le nombre de notifications stockées
        if (count($notifications) > 10) {
            array_shift($notifications);
        }
        
        // Ajouter la nouvelle notification
        $notifications[] = array(
            'points' => $points,
            'breakdown' => $breakdown,
            'order_id' => $order_id,
            'timestamp' => time(),
            'read' => false
        );
        
        update_user_meta($user_id, '_lte_loyalty_notifications', $notifications);
    }
    
    /**
     * Affiche les notifications de points sur la page Mon compte
     *
     * @return void
     */
    public function display_points_notifications() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $notifications = get_user_meta($user_id, '_lte_loyalty_notifications', true);
        
        if (!is_array($notifications) || empty($notifications)) {
            return;
        }
        
        // Filtrer pour n'afficher que les notifications non lues
        $unread = array_filter($notifications, function($notification) {
            return !$notification['read'];
        });
        
        if (empty($unread)) {
            return;
        }
        
        // Marquer comme lues après affichage
        foreach ($notifications as $key => $notification) {
            $notifications[$key]['read'] = true;
        }
        update_user_meta($user_id, '_lte_loyalty_notifications', $notifications);
        
        // Afficher les notifications
        echo '<div class="woocommerce-message lte-loyalty-notification">';
        
        if (count($unread) == 1) {
            $notification = reset($unread);
            echo sprintf(
                __('Félicitations ! Vous avez gagné %d points de fidélité avec votre dernière commande !', 'life-travel-excursion'),
                $notification['points']
            );
        } else {
            echo sprintf(
                __('Félicitations ! Vous avez gagné un total de %d points de fidélité avec vos dernières commandes !', 'life-travel-excursion'),
                array_sum(array_column($unread, 'points'))
            );
        }
        
        echo ' <a href="' . esc_url(wc_get_endpoint_url('loyalty', '', wc_get_page_permalink('myaccount'))) . '">';
        echo __('Voir mon solde de points', 'life-travel-excursion');
        echo '</a>';
        echo '</div>';
    }
    
    /**
     * Affiche une notification flottante pour les nouveaux points
     *
     * @return void
     */
    public function display_floating_notifications() {
        if (!is_user_logged_in() || is_admin()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $notifications = get_user_meta($user_id, '_lte_loyalty_notifications', true);
        
        if (!is_array($notifications) || empty($notifications)) {
            return;
        }
        
        // Filtrer pour n'afficher que les notifications récentes (moins de 24h)
        $recent = array_filter($notifications, function($notification) {
            return !$notification['read'] && $notification['timestamp'] > (time() - 86400);
        });
        
        if (empty($recent)) {
            return;
        }
        
        // Afficher la notification flottante
        ?>
        <div class="lte-floating-notification">
            <div class="lte-notification-content">
                <span class="lte-notification-close">×</span>
                <h4><?php _e('Points de fidélité', 'life-travel-excursion'); ?></h4>
                <?php
                foreach ($recent as $notification) {
                    echo '<p>' . sprintf(
                        __('Vous avez gagné %d points avec votre commande #%d', 'life-travel-excursion'),
                        $notification['points'],
                        $notification['order_id']
                    ) . '</p>';
                }
                ?>
                <a href="<?php echo esc_url(wc_get_endpoint_url('loyalty', '', wc_get_page_permalink('myaccount'))); ?>" class="button">
                    <?php _e('Voir mon solde', 'life-travel-excursion'); ?>
                </a>
            </div>
        </div>
        <style>
            .lte-floating-notification {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #fff;
                box-shadow: 0 0 10px rgba(0,0,0,0.2);
                border-radius: 5px;
                width: 300px;
                z-index: 9999;
                animation: lte-slide-in 0.5s forwards;
            }
            .lte-notification-content {
                padding: 15px;
                position: relative;
            }
            .lte-notification-close {
                position: absolute;
                top: 5px;
                right: 10px;
                cursor: pointer;
                font-size: 18px;
            }
            @keyframes lte-slide-in {
                from { transform: translateX(100%); }
                to { transform: translateX(0); }
            }
        </style>
        <script>
            jQuery(document).ready(function($) {
                $('.lte-notification-close').on('click', function() {
                    $(this).closest('.lte-floating-notification').fadeOut();
                });
                
                // Masquer automatiquement après 10 secondes
                setTimeout(function() {
                    $('.lte-floating-notification').fadeOut();
                }, 10000);
            });
        </script>
        <?php
    }
    
    /**
     * Récupère le solde de points d'un utilisateur
     * 
     * @param int $user_id ID de l'utilisateur (si 0, utilise l'utilisateur courant)
     * @return int Nombre de points
     */
    public function get_user_loyalty_points($user_id = 0) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return 0;
        }
        
        $points = get_user_meta($user_id, '_lte_loyalty_points', true);
        
        return is_numeric($points) ? intval($points) : 0;
    }
    
    /**
     * Affiche le formulaire d'utilisation des points sur la page de paiement
     * 
     * @return void
     */
    public function display_points_redemption_form() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $points = $this->get_user_loyalty_points($user_id);
        
        if ($points <= 0) {
            return;
        }
        
        // Récupérer les points déjà appliqués (si existant)
        $applied_points = WC()->session->get('lte_points_applied', 0);
        
        // Calculer la valeur en euros des points
        $points_value = get_option('lte_points_value', 100);
        $max_discount_percent = get_option('lte_max_points_discount_percent', 25);
        
        if ($points_value <= 0) {
            return;
        }
        
        $points_worth = number_format($points / $points_value, 2, ',', ' ');
        
        // Valider la session au besoin
        if ($applied_points > $points) {
            WC()->session->set('lte_points_applied', 0);
            $applied_points = 0;
        }
        
        // Afficher le formulaire
        ?>
        <div class="lte-loyalty-redemption-form">
            <h3><?php _e('Mes points de fidélité', 'life-travel-excursion'); ?></h3>
            
            <p>
                <?php 
                printf(
                    __('Vous avez <strong>%d points</strong> disponibles (valeur : %s€).', 'life-travel-excursion'),
                    $points,
                    $points_worth
                ); 
                ?>
            </p>
            
            <form method="post" class="lte-apply-points-form">
                <div class="lte-points-slider-container">
                    <label for="lte-points-slider">
                        <?php _e('Utiliser mes points :', 'life-travel-excursion'); ?>
                        <span class="lte-points-to-use"><?php echo $applied_points; ?></span> / <?php echo $points; ?>
                    </label>
                    <input type="range" id="lte-points-slider" name="lte_points_to_use" 
                           min="0" max="<?php echo $points; ?>" value="<?php echo $applied_points; ?>" 
                           step="1" style="width: 100%">
                    <span class="lte-points-value">
                        <?php echo __('Réduction :', 'life-travel-excursion'); ?> 
                        <span class="lte-discount-amount">
                            <?php echo number_format($applied_points / $points_value, 2, ',', ' '); ?>
                        </span> €
                    </span>
                </div>
                
                <div class="lte-points-actions">
                    <?php if ($applied_points > 0) : ?>
                        <button type="submit" name="lte_remove_points" class="button alt">
                            <?php _e('Retirer mes points', 'life-travel-excursion'); ?>
                        </button>
                    <?php endif; ?>
                    <button type="submit" name="lte_apply_points" class="button alt">
                        <?php _e('Appliquer', 'life-travel-excursion'); ?>
                    </button>
                </div>
                
                <?php wp_nonce_field('lte_apply_points', 'lte_points_nonce'); ?>
            </form>
            
            <p class="lte-points-info">
                <?php 
                printf(
                    __('Note : La réduction maximale est de %d%% du montant total de la commande.', 'life-travel-excursion'),
                    $max_discount_percent
                ); 
                ?>
            </p>
            
            <script>
                jQuery(document).ready(function($) {
                    var pointsValue = <?php echo $points_value; ?>;
                    
                    $('#lte-points-slider').on('input change', function() {
                        var pointsToUse = $(this).val();
                        $('.lte-points-to-use').text(pointsToUse);
                        
                        var discountAmount = (pointsToUse / pointsValue).toFixed(2).replace('.', ',');
                        $('.lte-discount-amount').text(discountAmount);
                    });
                });
            </script>
            <style>
                .lte-loyalty-redemption-form {
                    margin-bottom: 30px;
                    padding: 15px;
                    background: #f9f9f9;
                    border-radius: 4px;
                }
                .lte-points-slider-container {
                    margin-bottom: 15px;
                }
                .lte-points-actions {
                    display: flex;
                    gap: 10px;
                    margin-bottom: 15px;
                }
                .lte-points-info {
                    font-size: 0.9em;
                    color: #666;
                    margin-top: 10px;
                }
            </style>
        </div>
        <?php
        
        // Traiter le formulaire si soumis
        if (isset($_POST['lte_apply_points']) && isset($_POST['lte_points_nonce']) && 
            wp_verify_nonce($_POST['lte_points_nonce'], 'lte_apply_points')) {
            
            $points_to_use = isset($_POST['lte_points_to_use']) ? intval($_POST['lte_points_to_use']) : 0;
            
            // Valider les points
            if ($points_to_use > $points) {
                $points_to_use = $points;
            }
            
            // Sauvegarder en session
            WC()->session->set('lte_points_applied', $points_to_use);
            
            // Rediriger pour refléter le changement
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }
        
        // Traiter le retrait des points
        if (isset($_POST['lte_remove_points']) && isset($_POST['lte_points_nonce']) && 
            wp_verify_nonce($_POST['lte_points_nonce'], 'lte_apply_points')) {
            
            // Retirer les points
            WC()->session->set('lte_points_applied', 0);
            
            // Rediriger pour refléter le changement
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }
    }
    
    /**
     * Applique une réduction basée sur les points utilisés
     * 
     * @param float $total Total de la commande
     * @param WC_Cart $cart Objet panier WooCommerce
     * @return float Total réduit
     */
    public function apply_points_discount($total, $cart) {
        if (!is_user_logged_in() || !isset(WC()->session)) {
            return $total;
        }
        
        $points_applied = WC()->session->get('lte_points_applied', 0);
        
        if ($points_applied <= 0) {
            return $total;
        }
        
        $user_id = get_current_user_id();
        $user_points = $this->get_user_loyalty_points($user_id);
        
        // Vérifier que l'utilisateur a suffisamment de points
        if ($points_applied > $user_points) {
            WC()->session->set('lte_points_applied', 0);
            return $total;
        }
        
        // Calculer la valeur en euros des points
        $points_value = get_option('lte_points_value', 100);
        if ($points_value <= 0) {
            return $total;
        }
        
        $discount_amount = $points_applied / $points_value;
        
        // Limiter la réduction à un pourcentage maximal du total
        $max_discount_percent = get_option('lte_max_points_discount_percent', 25);
        $max_discount = $total * ($max_discount_percent / 100);
        
        if ($discount_amount > $max_discount) {
            $discount_amount = $max_discount;
            
            // Recalculer les points utilisés en fonction de la réduction maximale
            $adjusted_points = floor($discount_amount * $points_value);
            WC()->session->set('lte_points_applied', $adjusted_points);
        }
        
        // Appliquer la réduction
        $new_total = $total - $discount_amount;
        
        // Ajouter une note au panier indiquant la réduction
        if (!WC()->cart->has_discount('lte_points')) {
            WC()->cart->add_fee(
                sprintf(__('Réduction points fidélité (%d points)', 'life-travel-excursion'), $points_applied),
                -$discount_amount,
                false
            );
        }
        
        // Hook pour déduire les points lors de la finalisation de la commande
        add_action('woocommerce_checkout_order_processed', array($this, 'deduct_points_on_checkout'), 10, 3);
        
        return $new_total;
    }
    
    /**
     * Déduit les points utilisés lors de la validation de la commande
     * 
     * @param int $order_id ID de la commande
     * @param array $posted_data Données du formulaire
     * @param WC_Order $order Objet commande
     * @return void
     */
    public function deduct_points_on_checkout($order_id, $posted_data, $order) {
        $points_applied = WC()->session->get('lte_points_applied', 0);
        
        if ($points_applied <= 0) {
            return;
        }
        
        $user_id = $order->get_customer_id();
        
        if (!$user_id) {
            return;
        }
        
        // Récupérer les points actuels
        $current_points = $this->get_user_loyalty_points($user_id);
        
        // Vérifier que l'utilisateur a suffisamment de points
        if ($points_applied > $current_points) {
            $points_applied = $current_points;
        }
        
        // Déduire les points
        $new_points = $current_points - $points_applied;
        update_user_meta($user_id, '_lte_loyalty_points', $new_points);
        
        // Stocker les points utilisés dans la commande
        $order->update_meta_data('_lte_loyalty_points_used', $points_applied);
        
        // Stocker la valeur des points utilisés
        $points_value = get_option('lte_points_value', 100);
        $discount_amount = $points_applied / $points_value;
        $order->update_meta_data('_lte_loyalty_discount_amount', $discount_amount);
        
        $order->save();
        
        // Réinitialiser les points appliqués
        WC()->session->set('lte_points_applied', 0);
        
        // Log pour le débogage
        error_log(sprintf(
            'Points de fidélité déduits: %d points de l\'utilisateur #%d pour la commande #%d',
            $points_applied,
            $user_id,
            $order_id
        ));
        
        // Déclencher une action pour les extensions
        do_action('lte_loyalty_points_deducted', $user_id, $points_applied, $order_id);
    }
    
    /**
     * Enregistre les points hors-ligne pour synchronisation ultérieure
     * 
     * @param int $user_id ID de l'utilisateur
     * @param int $points Nombre de points à ajouter/déduire
     * @param string $action Type d'action ('add' ou 'deduct')
     * @param array $details Détails complémentaires
     * @return bool Succès de l'opération
     */
    public function store_offline_points($user_id, $points, $action = 'add', $details = []) {
        if (!$user_id || $points <= 0) {
            return false;
        }
        
        // Obtenir les transactions en attente existantes
        $pending_transactions = get_user_meta($user_id, '_lte_pending_loyalty_transactions', true);
        if (!is_array($pending_transactions)) {
            $pending_transactions = [];
        }
        
        // Générer un ID unique pour cette transaction
        $transaction_id = 'lte_' . uniqid() . '_' . time();
        
        // Créer la transaction
        $transaction = [
            'id' => $transaction_id,
            'user_id' => $user_id,
            'points' => $points,
            'action' => $action,
            'source' => isset($details['source']) ? $details['source'] : 'manual',
            'details' => $details,
            'timestamp' => time(),
            'status' => 'pending'
        ];
        
        // Ajouter la transaction à la liste
        $pending_transactions[$transaction_id] = $transaction;
        
        // Enregistrer les transactions en attente
        update_user_meta($user_id, '_lte_pending_loyalty_transactions', $pending_transactions);
        
        // Log pour le débogage
        error_log(sprintf(
            'Transaction de points de fidélité en attente: %s %d points pour l\'utilisateur #%d',
            $action === 'add' ? 'Ajout de' : 'Retrait de',
            $points,
            $user_id
        ));
        
        return true;
    }
    
    /**
     * Synchronise les transactions de points hors-ligne
     * 
     * @param int $user_id ID de l'utilisateur
     * @return array Résultat de la synchronisation
     */
    public function sync_offline_points($user_id) {
        if (!$user_id) {
            return [
                'success' => false,
                'message' => 'ID utilisateur invalide',
                'synced' => 0,
                'failed' => 0
            ];
        }
        
        // Obtenir les transactions en attente
        $pending_transactions = get_user_meta($user_id, '_lte_pending_loyalty_transactions', true);
        if (!is_array($pending_transactions) || empty($pending_transactions)) {
            return [
                'success' => true,
                'message' => 'Aucune transaction en attente',
                'synced' => 0,
                'failed' => 0
            ];
        }
        
        // Statistiques de synchronisation
        $stats = [
            'synced' => 0,
            'failed' => 0,
            'total' => count($pending_transactions)
        ];
        
        // Points actuels de l'utilisateur
        $current_points = $this->get_user_loyalty_points($user_id);
        
        // Liste des transactions traitées avec succès
        $processed_transactions = [];
        
        // Traiter chaque transaction
        foreach ($pending_transactions as $transaction_id => $transaction) {
            $points = $transaction['points'];
            $action = $transaction['action'];
            
            try {
                if ($action === 'add') {
                    // Ajout de points
                    $new_points = $current_points + $points;
                    
                    // Plafonner si nécessaire
                    if ($this->max_points_limit > 0 && $new_points > $this->max_points_limit) {
                        $new_points = $this->max_points_limit;
                    }
                    
                    update_user_meta($user_id, '_lte_loyalty_points', $new_points);
                    $current_points = $new_points; // Mise à jour pour les transactions suivantes
                    
                    // Enregistrer dans l'historique
                    $this->log_points_transaction($user_id, $points, $transaction['source'], $transaction['details']);
                    
                    $stats['synced']++;
                    $processed_transactions[] = $transaction_id;
                    
                } elseif ($action === 'deduct') {
                    // Vérifier s'il y a assez de points
                    if ($current_points >= $points) {
                        $new_points = $current_points - $points;
                        update_user_meta($user_id, '_lte_loyalty_points', $new_points);
                        $current_points = $new_points; // Mise à jour pour les transactions suivantes
                        
                        // Enregistrer dans l'historique
                        $this->log_points_transaction($user_id, -$points, $transaction['source'], $transaction['details']);
                        
                        $stats['synced']++;
                        $processed_transactions[] = $transaction_id;
                    } else {
                        // Pas assez de points, marquer comme échouée
                        $stats['failed']++;
                    }
                } else {
                    // Action inconnue, marquer comme échouée
                    $stats['failed']++;
                }
            } catch (\Exception $e) {
                // Erreur de traitement, journaliser et continuer
                error_log(sprintf(
                    'Erreur lors de la synchronisation des points de fidélité pour l\'utilisateur #%d: %s',
                    $user_id,
                    $e->getMessage()
                ));
                $stats['failed']++;
            }
        }
        
        // Supprimer les transactions traitées
        if (!empty($processed_transactions)) {
            foreach ($processed_transactions as $transaction_id) {
                unset($pending_transactions[$transaction_id]);
            }
            
            // Mettre à jour les transactions restantes
            update_user_meta($user_id, '_lte_pending_loyalty_transactions', $pending_transactions);
        }
        
        return [
            'success' => ($stats['synced'] > 0),
            'message' => sprintf(
                '%d transaction(s) synchronisée(s), %d échec(s)',
                $stats['synced'],
                $stats['failed']
            ),
            'synced' => $stats['synced'],
            'failed' => $stats['failed'],
            'remaining' => count($pending_transactions)
        ];
    }
    
    /**
     * Journalise une transaction de points dans l'historique
     * 
     * @param int $user_id ID de l'utilisateur
     * @param int $points Nombre de points (positif pour ajout, négatif pour retrait)
     * @param string $source Source des points
     * @param array $details Détails de la transaction
     * @return int|false ID de la transaction ou false si échec
     */
    private function log_points_transaction($user_id, $points, $source = 'manual', $details = []) {
        global $wpdb;
        
        // Vérifier si la table existe
        $table_name = $wpdb->prefix . 'lte_points_history';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            // Créer la table si elle n'existe pas
            $this->create_points_history_table();
        }
        
        // Préparer les données pour insertion
        $data = [
            'user_id' => $user_id,
            'points' => $points,
            'source' => $source,
            'date_created' => current_time('mysql')
        ];
        
        // Ajouter des détails spécifiques si présents
        if (!empty($details['product_id'])) {
            $data['product_id'] = $details['product_id'];
        }
        
        if (!empty($details['order_id'])) {
            $data['order_id'] = $details['order_id'];
        }
        
        // Insérer dans la base de données
        $result = $wpdb->insert(
            $table_name,
            $data,
            ['%d', '%d', '%s', '%s', '%d', '%d']
        );
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Crée la table d'historique des points si nécessaire
     */
    private function create_points_history_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'lte_points_history';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            points int(11) NOT NULL,
            source varchar(50) NOT NULL,
            product_id bigint(20) unsigned DEFAULT NULL,
            order_id bigint(20) unsigned DEFAULT NULL,
            date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY product_id (product_id),
            KEY order_id (order_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}