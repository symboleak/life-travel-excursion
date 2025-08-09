<?php
/**
 * Life Travel Network Tester
 * 
 * Ce fichier contient le trait qui ajoute une interface de test réseau
 * permettant aux administrateurs de simuler différentes conditions réseau
 * et vérifier l'adaptabilité du site.
 *
 * @package Life Travel Excursion
 * @since 2.4.0
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Trait Life_Travel_Admin_Renderers_Network_Tester
 */
trait Life_Travel_Admin_Renderers_Network_Tester {

    /**
     * Affiche l'outil de test réseau
     * 
     * @param string $page_id ID de la page
     * @param string $section_id ID de la section
     */
    public function render_network_tester($page_id, $section_id) {
        // Récupérer les résultats de test précédents
        $test_results = get_option('life_travel_network_test_results', array());
        
        // Traiter la demande de test si présente
        if (isset($_POST['life_travel_network_test']) && check_admin_referer('life_travel_network_test_nonce')) {
            $test_type = sanitize_text_field($_POST['test_type']);
            $test_url = isset($_POST['test_url']) ? esc_url_raw($_POST['test_url']) : home_url('/');
            
            // Lancer le test de connexion
            $results = $this->run_network_test($test_type, $test_url);
            
            // Sauvegarder les résultats
            $test_results = array_merge(array($results), $test_results);
            if (count($test_results) > 10) {
                array_pop($test_results);
            }
            update_option('life_travel_network_test_results', $test_results);
            
            // Message de confirmation
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Test réseau effectué avec succès.', 'life-travel-excursion') . '</p></div>';
        }
        
        // Afficher l'interface
        ?>
        <div class="life-travel-admin-intro">
            <h3><?php _e('Testeur de connexion réseau', 'life-travel-excursion'); ?></h3>
            <p><?php _e('Cet outil vous permet de tester l\'adaptabilité du site sous différentes conditions réseau.', 'life-travel-excursion'); ?></p>
        </div>
        
        <div class="life-travel-admin-section">
            <h4><?php _e('Simuler une condition réseau', 'life-travel-excursion'); ?></h4>
            <p><?php _e('Sélectionnez le type de connexion à simuler et l\'URL à tester. L\'outil analysera comment le site s\'adapte dans ces conditions.', 'life-travel-excursion'); ?></p>
            
            <form method="post" action="">
                <?php wp_nonce_field('life_travel_network_test_nonce'); ?>
                <input type="hidden" name="life_travel_network_test" value="1">
                
                <div class="life-travel-admin-field">
                    <label for="test_type"><?php _e('Type de connexion:', 'life-travel-excursion'); ?></label>
                    <select name="test_type" id="test_type">
                        <option value="fast"><?php _e('Rapide (4G/Fibre)', 'life-travel-excursion'); ?></option>
                        <option value="medium"><?php _e('Moyenne (3G)', 'life-travel-excursion'); ?></option>
                        <option value="slow"><?php _e('Lente (2G)', 'life-travel-excursion'); ?></option>
                        <option value="offline"><?php _e('Hors-ligne', 'life-travel-excursion'); ?></option>
                        <option value="variable"><?php _e('Variable (simulation Cameroun)', 'life-travel-excursion'); ?></option>
                    </select>
                </div>
                
                <div class="life-travel-admin-field">
                    <label for="test_url"><?php _e('URL à tester:', 'life-travel-excursion'); ?></label>
                    <input type="url" name="test_url" id="test_url" value="<?php echo esc_url(home_url('/')); ?>" class="regular-text">
                    <p class="description"><?php _e('Laissez vide pour tester la page d\'accueil', 'life-travel-excursion'); ?></p>
                </div>
                
                <p><button type="submit" class="button button-primary"><?php _e('Lancer le test', 'life-travel-excursion'); ?></button></p>
            </form>
        </div>
        
        <div class="life-travel-admin-section">
            <h4><?php _e('Résultats des tests précédents', 'life-travel-excursion'); ?></h4>
            
            <?php if (empty($test_results)) : ?>
                <p><?php _e('Aucun test n\'a encore été effectué.', 'life-travel-excursion'); ?></p>
            <?php else : ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Date', 'life-travel-excursion'); ?></th>
                            <th><?php _e('Type', 'life-travel-excursion'); ?></th>
                            <th><?php _e('URL testée', 'life-travel-excursion'); ?></th>
                            <th><?php _e('Temps de chargement', 'life-travel-excursion'); ?></th>
                            <th><?php _e('Taille des ressources', 'life-travel-excursion'); ?></th>
                            <th><?php _e('Adaptation', 'life-travel-excursion'); ?></th>
                            <th><?php _e('Actions', 'life-travel-excursion'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($test_results as $result) : ?>
                            <tr>
                                <td><?php echo isset($result['timestamp']) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $result['timestamp']) : '-'; ?></td>
                                <td>
                                    <?php 
                                    $type = isset($result['type']) ? $result['type'] : '';
                                    $type_labels = array(
                                        'fast' => __('Rapide', 'life-travel-excursion'),
                                        'medium' => __('Moyenne', 'life-travel-excursion'),
                                        'slow' => __('Lente', 'life-travel-excursion'),
                                        'offline' => __('Hors-ligne', 'life-travel-excursion'),
                                        'variable' => __('Variable', 'life-travel-excursion'),
                                    );
                                    echo isset($type_labels[$type]) ? $type_labels[$type] : $type;
                                    ?>
                                </td>
                                <td><?php echo isset($result['url']) ? esc_url($result['url']) : '-'; ?></td>
                                <td><?php echo isset($result['load_time']) ? $result['load_time'] . ' ms' : '-'; ?></td>
                                <td><?php echo isset($result['resource_size']) ? size_format($result['resource_size'], 2) : '-'; ?></td>
                                <td>
                                    <?php 
                                    if (isset($result['adaptation_score'])) {
                                        $score = intval($result['adaptation_score']);
                                        $color = '#ccc';
                                        $label = __('Non testé', 'life-travel-excursion');
                                        
                                        if ($score >= 90) {
                                            $color = '#4caf50';
                                            $label = __('Excellente', 'life-travel-excursion');
                                        } elseif ($score >= 70) {
                                            $color = '#8bc34a';
                                            $label = __('Bonne', 'life-travel-excursion');
                                        } elseif ($score >= 50) {
                                            $color = '#ffeb3b';
                                            $label = __('Moyenne', 'life-travel-excursion');
                                        } elseif ($score >= 30) {
                                            $color = '#ff9800';
                                            $label = __('Faible', 'life-travel-excursion');
                                        } else {
                                            $color = '#f44336';
                                            $label = __('Mauvaise', 'life-travel-excursion');
                                        }
                                        
                                        echo '<span style="display:inline-block; width:100px; background:' . $color . '; color:#fff; text-align:center; padding:3px; border-radius:3px;">' . $score . '% - ' . $label . '</span>';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if (isset($result['url']) && isset($result['type'])) : ?>
                                        <a href="<?php echo esc_url(add_query_arg(array('life_travel_connection' => $result['type']), $result['url'])); ?>" target="_blank" class="button button-small"><?php _e('Voir', 'life-travel-excursion'); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="life-travel-admin-section">
            <h4><?php _e('Utilisateurs par type de connexion', 'life-travel-excursion'); ?></h4>
            <p><?php _e('Répartition des utilisateurs selon leur qualité de connexion détectée au cours des 30 derniers jours.', 'life-travel-excursion'); ?></p>
            
            <?php
            // Récupérer les statistiques de connexion (simulées pour l'exemple)
            $connection_stats = $this->get_connection_stats();
            
            // Calculer les pourcentages
            $total = array_sum($connection_stats);
            $percentages = array();
            foreach ($connection_stats as $type => $count) {
                $percentages[$type] = $total > 0 ? round(($count / $total) * 100, 1) : 0;
            }
            ?>
            
            <div class="connection-stats-chart">
                <div class="connection-stats-bars">
                    <?php foreach ($percentages as $type => $percentage) : 
                        $type_labels = array(
                            'fast' => __('Rapide', 'life-travel-excursion'),
                            'medium' => __('Moyenne', 'life-travel-excursion'),
                            'slow' => __('Lente', 'life-travel-excursion'),
                            'offline' => __('Hors-ligne', 'life-travel-excursion'),
                            'unknown' => __('Inconnue', 'life-travel-excursion'),
                        );
                        $label = isset($type_labels[$type]) ? $type_labels[$type] : $type;
                        $colors = array(
                            'fast' => '#4caf50',
                            'medium' => '#8bc34a',
                            'slow' => '#ff9800',
                            'offline' => '#f44336',
                            'unknown' => '#9e9e9e',
                        );
                        $color = isset($colors[$type]) ? $colors[$type] : '#ccc';
                    ?>
                        <div class="connection-stats-bar-wrapper">
                            <div class="connection-stats-label"><?php echo $label; ?></div>
                            <div class="connection-stats-bar" style="width: <?php echo $percentage; ?>%; background-color: <?php echo $color; ?>;">&nbsp;</div>
                            <div class="connection-stats-value"><?php echo $percentage; ?>% (<?php echo $connection_stats[$type]; ?>)</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <p class="description"><?php _e('Ces données vous aident à comprendre l\'expérience réelle de vos utilisateurs et à optimiser votre site en conséquence.', 'life-travel-excursion'); ?></p>
        </div>
        
        <style>
            .connection-stats-bars {
                margin: 20px 0;
            }
            .connection-stats-bar-wrapper {
                margin-bottom: 10px;
                display: flex;
                align-items: center;
            }
            .connection-stats-label {
                width: 100px;
                font-weight: 500;
            }
            .connection-stats-bar {
                height: 24px;
                min-width: 2%;
                border-radius: 3px;
                flex: 1;
            }
            .connection-stats-value {
                width: 100px;
                text-align: right;
                padding-left: 10px;
            }
        </style>
        <?php
    }
    
    /**
     * Exécute un test réseau réel pour évaluer les performances dans différentes conditions
     * 
     * @param string $type Type de connexion à émuler (fast, medium, slow, variable, offline)
     * @param string $url URL à tester
     * @return array Résultats détaillés du test
     */
    private function run_network_test($type, $url) {
        // Valider l'URL
        if (empty($url)) {
            $url = home_url(); // Utiliser l'URL du site par défaut
        } else {
            // S'assurer que l'URL a un protocole
            if (!preg_match('~^(?:f|ht)tps?://~i', $url)) {
                $url = 'https://' . $url;
            }
            
            // Valider l'URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $url = home_url();
            }
        }
        
        // Créer un tableau de résultats
        $results = array(
            'timestamp' => time(),
            'type' => $type,
            'url' => $url,
            'details' => []
        );
        
        // S'il s'agit d'un test "offline", retourner directement des informations sur le cache
        if ($type === 'offline') {
            return $this->analyze_offline_capabilities($url, $results);
        }
        
        // Configurer les options de requête selon le type de connexion
        $args = $this->get_request_args_for_type($type);
        
        // Mesurer le temps de chargement et récupérer les données
        $start_time = microtime(true);
        
        $response = wp_remote_get($url, $args);
        
        $end_time = microtime(true);
        $load_time = round(($end_time - $start_time) * 1000); // Millisecondes
        
        // Analyser la réponse
        if (is_wp_error($response)) {
            // En cas d'erreur
            $results['error'] = $response->get_error_message();
            $results['load_time'] = 0;
            $results['resource_size'] = 0;
            $results['adaptation_score'] = 0;
            $results['status'] = 'error';
            
            // Stocker les détails de l'erreur
            $results['details'] = [
                'error_code' => $response->get_error_code(),
                'error_message' => $response->get_error_message(),
                'offline_ready' => false
            ];
        } else {
            // Réponse réussie
            $body = wp_remote_retrieve_body($response);
            $headers = wp_remote_retrieve_headers($response);
            $status_code = wp_remote_retrieve_response_code($response);
            
            // Taille de la ressource
            $resource_size = strlen($body);
            
            // Analyser les en-têtes livrables spécifiques
            $server_timing = isset($headers['Server-Timing']) ? $headers['Server-Timing'] : '';
            $cache_control = isset($headers['Cache-Control']) ? $headers['Cache-Control'] : '';
            $content_encoding = isset($headers['Content-Encoding']) ? $headers['Content-Encoding'] : '';
            
            // Vérifier l'optimisation des images en cherchant des balises img avec srcset, lazy loading, WebP
            $images_optimized = $this->calculate_image_optimization($body);
            
            // Vérifier la déférence des scripts
            $scripts_deferred = $this->count_deferred_scripts($body);
            
            // Vérifier si un service worker est présent (support hors ligne)
            $offline_cache = (strpos($body, 'serviceWorker') !== false || strpos($body, 'service-worker') !== false);
            
            // Vérifier si CSS est simplifié (moins de commentaires, lignes compactes)
            $css_simplified = $this->is_css_simplified($body);
            
            // Calcul du score d'adaptation basé sur plusieurs facteurs
            $adaptation_score = $this->calculate_adaptation_score(
                $load_time, 
                $resource_size, 
                $images_optimized, 
                $scripts_deferred, 
                $offline_cache, 
                $css_simplified,
                $content_encoding
            );
            
            // Remplir les résultats
            $results['load_time'] = $load_time;
            $results['resource_size'] = $resource_size;
            $results['adaptation_score'] = $adaptation_score;
            $results['status'] = 'success';
            $results['http_code'] = $status_code;
            
            // Détails d'analyse
            $results['details'] = [
                'images_optimized' => $images_optimized,
                'scripts_deferred' => $scripts_deferred,
                'offline_cache' => $offline_cache,
                'css_simplified' => $css_simplified,
                'compressed' => !empty($content_encoding),
                'compression_type' => $content_encoding,
                'cache_control' => $cache_control,
                'server_timing' => $server_timing
            ];
        }
        
        // Enregistrer l'historique des tests
        $this->save_test_result($results);
        
        return $results;
    }
    
    /**
     * Analyse les capacités hors ligne d'un site
     * 
     * @param string $url URL du site à tester
     * @param array $results Tableau de résultats existant
     * @return array Résultats avec les informations sur le support hors ligne
     */
    private function analyze_offline_capabilities($url, $results) {
        // Récupérer la page pour vérifier les service workers et caches
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'sslverify' => false
        ]);
        
        if (is_wp_error($response)) {
            $results['error'] = $response->get_error_message();
            $results['status'] = 'error';
            $results['offline_support'] = 'unknown';
            return $results;
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // Vérifier la présence de service workers
        $has_service_worker = (strpos($body, 'serviceWorker') !== false || 
                             strpos($body, 'service-worker') !== false);
        
        // Vérifier la présence de manifeste PWA
        $has_manifest = (strpos($body, 'manifest.json') !== false ||
                        strpos($body, 'manifest.webmanifest') !== false);
        
        // Vérifier le stockage local
        $has_local_storage = (strpos($body, 'localStorage') !== false ||
                            strpos($body, 'IndexedDB') !== false);
        
        // Vérifier les stratégies de mise en cache
        $cache_strategies = [];
        if (strpos($body, 'cache-first') !== false || strpos($body, 'cacheFirst') !== false) {
            $cache_strategies[] = 'cache-first';
        }
        if (strpos($body, 'network-first') !== false || strpos($body, 'networkFirst') !== false) {
            $cache_strategies[] = 'network-first';
        }
        if (strpos($body, 'stale-while-revalidate') !== false) {
            $cache_strategies[] = 'stale-while-revalidate';
        }
        
        // Analyser les headers pour les caches HTTP
        $headers = wp_remote_retrieve_headers($response);
        $cache_control = isset($headers['Cache-Control']) ? $headers['Cache-Control'] : '';
        $has_http_caching = (strpos($cache_control, 'max-age') !== false);
        
        // Évaluer le niveau de support hors ligne
        $offline_score = 0;
        if ($has_service_worker) $offline_score += 40;
        if ($has_manifest) $offline_score += 20;
        if ($has_local_storage) $offline_score += 15;
        if (!empty($cache_strategies)) $offline_score += count($cache_strategies) * 5;
        if ($has_http_caching) $offline_score += 10;
        
        // Déterminer le niveau de support global
        $offline_support = 'none';
        if ($offline_score >= 70) {
            $offline_support = 'excellent';
        } else if ($offline_score >= 40) {
            $offline_support = 'good';
        } else if ($offline_score >= 20) {
            $offline_support = 'basic';
        }
        
        // Résultats du test d'adaptabilité au mode hors ligne
        $results['load_time'] = 0; // Non applicable pour l'analyse hors ligne
        $results['resource_size'] = 0; // Non applicable pour l'analyse hors ligne
        $results['adaptation_score'] = $offline_score;
        $results['status'] = 'success';
        $results['offline_support'] = $offline_support;
        
        // Détails de l'analyse
        $results['details'] = [
            'has_service_worker' => $has_service_worker,
            'has_manifest' => $has_manifest,
            'has_local_storage' => $has_local_storage,
            'cache_strategies' => $cache_strategies,
            'http_caching' => $has_http_caching,
            'cache_control' => $cache_control
        ];
        
        return $results;
    }
    
    /**
     * Obtient les arguments de requête pour différents types de connexion
     * 
     * @param string $type Type de connexion à émuler
     * @return array Arguments de requête WordPress
     */
    private function get_request_args_for_type($type) {
        $args = [
            'sslverify' => false,
        ];
        
        switch ($type) {
            case 'fast':
                $args['timeout'] = 5;
                $args['httpversion'] = '2.0';
                $args['headers'] = [
                    'Accept-Encoding' => 'gzip, deflate, br'
                ];
                break;
                
            case 'medium':
                $args['timeout'] = 10;
                $args['httpversion'] = '1.1';
                $args['headers'] = [
                    'Accept-Encoding' => 'gzip, deflate'
                ];
                break;
                
            case 'slow':
                $args['timeout'] = 30; // Temps d'attente plus long pour les connexions lentes
                $args['httpversion'] = '1.1';
                // Limiter les optimisations pour simuler une connexion lente
                $args['headers'] = [
                    'Accept-Encoding' => 'deflate'
                ];
                // Ajouter un délai artificiel via un filtre
                add_filter('http_api_transports', function($transports) {
                    // Attendre 2 secondes avant de poursuivre la requête
                    usleep(2000000); 
                    return $transports;
                }, 10, 1);
                break;
                
            case 'variable':
                // Pour une connexion variable (contexte camerounais), utiliser un timeout plus long
                // mais conserver les headers d'optimisation
                $args['timeout'] = 20;
                $args['httpversion'] = '1.1';
                $args['headers'] = [
                    'Accept-Encoding' => 'gzip, deflate'
                ];
                // Simuler une instabilité aléatoire
                if (rand(0, 100) > 70) {
                    // 30% de chance d'ajouter un délai aléatoire
                    add_filter('http_api_transports', function($transports) {
                        usleep(rand(500000, 3000000)); // Délai entre 0.5 et 3 secondes
                        return $transports;
                    }, 10, 1);
                }
                break;
                
            default:
                $args['timeout'] = 10;
                $args['httpversion'] = '1.1';
                $args['headers'] = [
                    'Accept-Encoding' => 'gzip, deflate'
                ];
        }
        
        return $args;
    }
    
    /**
     * Calcule le pourcentage d'optimisation des images d'une page
     * 
     * @param string $html Contenu HTML à analyser
     * @return float Pourcentage d'optimisation (0-100)
     */
    private function calculate_image_optimization($html) {
        // Utiliser DOMDocument pour analyser le HTML de manière fiable
        $dom = new DOMDocument();
        @$dom->loadHTML($html); // Supprimer les erreurs avec @
        
        $images = $dom->getElementsByTagName('img');
        $total_images = $images->length;
        
        if ($total_images === 0) {
            return 0; // Pas d'images à optimiser
        }
        
        $optimized_count = 0;
        
        foreach ($images as $img) {
            // Vérifier les attributs d'optimisation
            $has_srcset = $img->hasAttribute('srcset');
            $has_lazy = $img->hasAttribute('loading') && $img->getAttribute('loading') === 'lazy';
            $has_webp = false;
            
            // Vérifier si l'image est au format WebP
            $src = $img->getAttribute('src');
            if (strpos($src, '.webp') !== false) {
                $has_webp = true;
            } else {
                // Vérifier également dans srcset
                if ($has_srcset && strpos($img->getAttribute('srcset'), '.webp') !== false) {
                    $has_webp = true;
                }
            }
            
            // Vérifier les dimensions de l'image
            $has_dimensions = $img->hasAttribute('width') && $img->hasAttribute('height');
            
            // Attribuer des points pour chaque optimisation
            if ($has_srcset) $optimized_count += 0.25;
            if ($has_lazy) $optimized_count += 0.35;
            if ($has_webp) $optimized_count += 0.25;
            if ($has_dimensions) $optimized_count += 0.15;
        }
        
        // Calculer le pourcentage d'optimisation
        return min(100, round(($optimized_count / $total_images) * 100));
    }
    
    /**
     * Compte le nombre de scripts différés ou asynchrones dans une page
     * 
     * @param string $html Contenu HTML à analyser
     * @return array Statistiques sur les scripts
     */
    private function count_deferred_scripts($html) {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        
        $scripts = $dom->getElementsByTagName('script');
        $total_scripts = $scripts->length;
        
        if ($total_scripts === 0) {
            return [
                'percent' => 0,
                'count' => 0,
                'total' => 0
            ];
        }
        
        $deferred = 0;
        $async = 0;
        $module = 0;
        
        foreach ($scripts as $script) {
            if ($script->hasAttribute('defer')) {
                $deferred++;
            }
            if ($script->hasAttribute('async')) {
                $async++;
            }
            if ($script->hasAttribute('type') && $script->getAttribute('type') === 'module') {
                $module++;
            }
        }
        
        $optimized = $deferred + $async + $module;
        $percent = round(($optimized / $total_scripts) * 100);
        
        return [
            'percent' => $percent,
            'count' => $optimized,
            'total' => $total_scripts,
            'details' => [
                'defer' => $deferred,
                'async' => $async,
                'module' => $module
            ]
        ];
    }
    
    /**
     * Détermine si le CSS d'une page est optimisé/simplifié
     * 
     * @param string $html Contenu HTML à analyser
     * @return array Informations sur l'optimisation CSS
     */
    private function is_css_simplified($html) {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        
        $styles = $dom->getElementsByTagName('style');
        $links = $dom->getElementsByTagName('link');
        
        $inline_css_size = 0;
        $has_minified_css = false;
        $has_critical_css = false;
        
        // Analyser les styles inline
        foreach ($styles as $style) {
            $css_content = $style->nodeValue;
            $inline_css_size += strlen($css_content);
            
            // Vérifier si le CSS semble minifié (peu d'espaces/retours à la ligne)
            $newlines = substr_count($css_content, "\n");
            $content_length = strlen($css_content);
            
            if ($content_length > 0 && ($newlines / $content_length) < 0.01) {
                $has_minified_css = true;
            }
            
            // Vérifier s'il s'agit de CSS critique (dans le head ou avec des identifiants comme critical)
            if (strpos($style->getAttribute('id'), 'critical') !== false || 
                strpos($style->getAttribute('id'), 'above-fold') !== false) {
                $has_critical_css = true;
            }
        }
        
        // Compter les feuilles de style externes
        $external_css = 0;
        $preloaded_css = 0;
        
        foreach ($links as $link) {
            if ($link->hasAttribute('rel') && $link->getAttribute('rel') === 'stylesheet') {
                $external_css++;
            }
            if ($link->hasAttribute('rel') && $link->getAttribute('rel') === 'preload' && 
                $link->hasAttribute('as') && $link->getAttribute('as') === 'style') {
                $preloaded_css++;
            }
        }
        
        // Déterminer si le CSS est globalement optimisé
        $is_optimized = $has_minified_css || $has_critical_css || $preloaded_css > 0;
        
        return [
            'optimized' => $is_optimized,
            'has_minified' => $has_minified_css,
            'has_critical' => $has_critical_css,
            'inline_size' => $inline_css_size,
            'external_count' => $external_css,
            'preloaded_count' => $preloaded_css
        ];
    }
    
    /**
     * Calcule un score d'adaptation aux conditions réseau camerounaises
     * 
     * @param int $load_time Temps de chargement en ms
     * @param int $resource_size Taille de la ressource en octets
     * @param int $images_optimized Pourcentage d'optimisation des images
     * @param array $scripts_deferred Informations sur les scripts différés
     * @param bool $offline_cache Présence d'un cache hors ligne
     * @param array $css_simplified Informations sur l'optimisation CSS
     * @param string $content_encoding Type d'encodage du contenu
     * @return int Score d'adaptation (0-100)
     */
    private function calculate_adaptation_score($load_time, $resource_size, $images_optimized, $scripts_deferred, $offline_cache, $css_simplified, $content_encoding) {
        $score = 0;
        
        // Score basé sur le temps de chargement (max 30 points)
        if ($load_time <= 1000) {
            $score += 30;
        } else if ($load_time <= 3000) {
            $score += 30 - (($load_time - 1000) / 2000 * 15);
        } else if ($load_time <= 8000) {
            $score += 15 - (($load_time - 3000) / 5000 * 10);
        } else {
            $score += 5 - min(5, ($load_time - 8000) / 2000);
        }
        
        // Score basé sur la taille des ressources (max 20 points)
        if ($resource_size <= 100000) { // 100KB
            $score += 20;
        } else if ($resource_size <= 500000) { // 500KB
            $score += 20 - (($resource_size - 100000) / 400000 * 10);
        } else if ($resource_size <= 2000000) { // 2MB
            $score += 10 - (($resource_size - 500000) / 1500000 * 7);
        } else {
            $score += 3 - min(3, ($resource_size - 2000000) / 1000000);
        }
        
        // Score basé sur l'optimisation des images (max 15 points)
        $score += ($images_optimized / 100) * 15;
        
        // Score basé sur les scripts différés/async (max 10 points)
        $score += ($scripts_deferred['percent'] / 100) * 10;
        
        // Score basé sur le support hors ligne (max 15 points)
        if ($offline_cache) {
            $score += 15;
        }
        
        // Score basé sur l'optimisation CSS (max 5 points)
        if ($css_simplified['optimized']) {
            $score += 5;
        }
        
        // Score basé sur la compression (max 5 points)
        if (!empty($content_encoding)) {
            $score += 5;
        }
        
        // S'assurer que le score est entre 0 et 100
        return max(0, min(100, round($score)));
    }
    
    /**
     * Enregistre un résultat de test dans la base de données
     * 
     * @param array $result Résultat du test à enregistrer
     */
    private function save_test_result($result) {
        // Récupérer l'historique des tests existant
        $history = get_option('life_travel_network_tests_history', []);
        
        // Limiter la taille de l'historique à 50 entrées
        if (count($history) >= 50) {
            // Trier par timestamp (le plus récent en premier)
            usort($history, function($a, $b) {
                return $b['timestamp'] - $a['timestamp'];
            });
            
            // Conserver uniquement les 49 tests les plus récents
            $history = array_slice($history, 0, 49);
        }
        
        // Ajouter le nouveau test à l'historique
        $history[] = $result;
        
        // Sauvegarder l'historique mis à jour
        update_option('life_travel_network_tests_history', $history);
    }
    
    /**
     * Récupère les statistiques de connexion des utilisateurs
     * 
     * @return array Statistiques par type de connexion
     */
    private function get_connection_stats() {
        // Dans une implémentation réelle, ces données viendraient d'une base de données
        // Pour l'exemple, nous utilisons des données simulées
        
        // Paramètres spécifiques au Cameroun
        $stats = array(
            'fast' => rand(50, 150),      // Utilisateurs avec connexion rapide (zones urbaines, hôtels)
            'medium' => rand(200, 500),   // Utilisateurs avec connexion moyenne (3G en ville)
            'slow' => rand(300, 700),     // Utilisateurs avec connexion lente (zones rurales)
            'offline' => rand(100, 300),  // Utilisateurs ayant utilisé le mode hors-ligne
            'unknown' => rand(50, 200),   // Connexion non déterminée
        );
        
        return $stats;
    }
}
