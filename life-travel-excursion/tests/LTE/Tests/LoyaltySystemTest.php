<?php
/**
 * Tests pour le système de fidélité
 *
 * @package Life_Travel
 * @subpackage Tests
 * @since 2.5.0
 */

namespace LTE\Tests;

use WP_UnitTestCase;
use WC_Helper_Product;

/**
 * Classe de test pour le système de fidélité
 */
class LoyaltySystemTest extends WP_UnitTestCase {
    
    /**
     * ID du produit d'excursion pour les tests
     */
    private $product_id;
    
    /**
     * ID de l'utilisateur pour les tests
     */
    private $user_id;
    
    /**
     * Configuration initiale des tests
     */
    public function setUp(): void {
        parent::setUp();
        
        // Créer un utilisateur de test
        $this->user_id = $this->factory->user->create(array(
            'role' => 'customer'
        ));
        
        // Créer un produit d'excursion de test
        $product = new \WC_Product_Excursion();
        $product->set_name('Excursion Test');
        $product->set_regular_price(100);
        $product->set_status('publish');
        $product->save();
        
        $this->product_id = $product->get_id();
        
        // Configurer le système de points pour cette excursion
        update_post_meta($this->product_id, '_loyalty_points_type', 'fixed');
        update_post_meta($this->product_id, '_loyalty_points_value', 10);
        
        // Paramètres globaux de fidélité
        update_option('lte_points_value', 100); // 100 points = 1€
        update_option('lte_max_loyalty_points', 1000); // max 1000 points
        update_option('lte_max_points_discount_percent', 25); // max 25% de réduction
    }
    
    /**
     * Nettoyage après les tests
     */
    public function tearDown(): void {
        // Supprimer le produit
        wp_delete_post($this->product_id, true);
        
        // Supprimer l'utilisateur
        wp_delete_user($this->user_id);
        
        // Supprimer les options
        delete_option('lte_points_value');
        delete_option('lte_max_loyalty_points');
        delete_option('lte_max_points_discount_percent');
        
        parent::tearDown();
    }
    
    /**
     * Test d'attribution de points fixes pour une excursion
     */
    public function test_fixed_points_award() {
        // Simuler une commande terminée
        $order = wc_create_order(array(
            'customer_id' => $this->user_id,
            'status'      => 'processing',
        ));
        
        $order->add_product(wc_get_product($this->product_id), 1);
        $order->calculate_totals();
        $order->save();
        
        // Créer une instance du système de fidélité
        $loyalty = new \Life_Travel_Loyalty_Excursions();
        
        // Attribuer des points pour la commande
        $loyalty->award_points_for_order($order->get_id());
        
        // Vérifier que les points ont été attribués
        $points = get_user_meta($this->user_id, '_lte_loyalty_points', true);
        $this->assertEquals(10, $points, 'Les points fixes n\'ont pas été correctement attribués');
    }
    
    /**
     * Test d'attribution de points en pourcentage pour une excursion
     */
    public function test_percentage_points_award() {
        // Configurer des points en pourcentage
        update_post_meta($this->product_id, '_loyalty_points_type', 'percentage');
        update_post_meta($this->product_id, '_loyalty_points_value', 5); // 5% du montant
        
        // Simuler une commande terminée
        $order = wc_create_order(array(
            'customer_id' => $this->user_id,
            'status'      => 'processing',
        ));
        
        $order->add_product(wc_get_product($this->product_id), 1);
        $order->calculate_totals();
        $order->save();
        
        // Créer une instance du système de fidélité
        $loyalty = new \Life_Travel_Loyalty_Excursions();
        
        // Attribuer des points pour la commande
        $loyalty->award_points_for_order($order->get_id());
        
        // Vérifier que les points ont été attribués (5% de 100€ = 5 points)
        $points = get_user_meta($this->user_id, '_lte_loyalty_points', true);
        $this->assertEquals(5, $points, 'Les points en pourcentage n\'ont pas été correctement attribués');
    }
    
    /**
     * Test de plafonnement des points
     */
    public function test_points_capping() {
        // Configurer un plafond spécifique à l'excursion
        update_post_meta($this->product_id, '_loyalty_points_max', 3);
        
        // Simuler une commande terminée
        $order = wc_create_order(array(
            'customer_id' => $this->user_id,
            'status'      => 'processing',
        ));
        
        $order->add_product(wc_get_product($this->product_id), 1);
        $order->calculate_totals();
        $order->save();
        
        // Créer une instance du système de fidélité
        $loyalty = new \Life_Travel_Loyalty_Excursions();
        
        // Attribuer des points pour la commande
        $loyalty->award_points_for_order($order->get_id());
        
        // Vérifier que les points ont été plafonnés
        $points = get_user_meta($this->user_id, '_lte_loyalty_points', true);
        $this->assertEquals(3, $points, 'Les points n\'ont pas été correctement plafonnés');
    }
    
    /**
     * Test de l'application d'une réduction via des points
     */
    public function test_points_discount() {
        // Attribuer des points à l'utilisateur
        update_user_meta($this->user_id, '_lte_loyalty_points', 500);
        
        // Simuler une session WooCommerce
        WC()->session = new \WC_Mock_Session_Handler();
        WC()->session->set('lte_points_applied', 100);
        
        // Créer une instance du système de fidélité
        $loyalty = new \Life_Travel_Loyalty_Excursions();
        
        // Créer un panier
        $cart = WC()->cart;
        $cart->add_to_cart($this->product_id, 1);
        
        // Appliquer la réduction
        $original_total = $cart->get_total('edit');
        $new_total = $loyalty->apply_points_discount($original_total, $cart);
        
        // La réduction devrait être de 1€ (100 points = 1€)
        $this->assertEquals($original_total - 1, $new_total, 'La réduction de points n\'a pas été correctement appliquée');
    }
    
    /**
     * Test de la limitation de réduction maximale
     */
    public function test_max_discount_limit() {
        // Attribuer beaucoup de points à l'utilisateur
        update_user_meta($this->user_id, '_lte_loyalty_points', 5000);
        
        // Simuler une session WooCommerce
        WC()->session = new \WC_Mock_Session_Handler();
        
        // Tenter d'utiliser 3000 points (30€) alors que le max est de 25%
        WC()->session->set('lte_points_applied', 3000);
        
        // Créer une instance du système de fidélité
        $loyalty = new \Life_Travel_Loyalty_Excursions();
        
        // Créer un panier de 100€
        $cart = WC()->cart;
        $cart->add_to_cart($this->product_id, 1);
        
        // Le total original est de 100€
        $original_total = 100;
        
        // Appliquer la réduction (devrait être limitée à 25€)
        $new_total = $loyalty->apply_points_discount($original_total, $cart);
        
        // Vérifier que la réduction maximale a été appliquée correctement
        $this->assertEquals(75, $new_total, 'La limitation de réduction maximale n\'a pas été correctement appliquée');
    }
}
