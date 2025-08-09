/**
 * JavaScript pour le tableau de bord des statistiques de fidélité
 * 
 * @package Life_Travel
 * @since 2.5.0
 */

(function($) {
    'use strict';
    
    // Attendre que le DOM soit chargé
    $(document).ready(function() {
        // Initialiser les graphiques si la bibliothèque Chart.js est chargée
        if (typeof Chart !== 'undefined') {
            initializeCharts();
        } else {
            // Charger Chart.js dynamiquement s'il n'est pas disponible
            loadChartJs().then(function() {
                initializeCharts();
            });
        }
        
        // Initialiser les gestionnaires d'événements
        initializeEventHandlers();
    });
    
    /**
     * Charge la bibliothèque Chart.js dynamiquement
     */
    function loadChartJs() {
        return new Promise(function(resolve, reject) {
            var script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }
    
    /**
     * Initialise tous les graphiques du tableau de bord
     */
    function initializeCharts() {
        // Graphique des sources de points
        initializeSourcesChart();
        
        // Graphique des points par excursion
        initializeExcursionsChart();
        
        // Graphique de l'évolution dans le temps
        initializeTimelineChart();
    }
    
    /**
     * Initialise le graphique circulaire des sources de points
     */
    function initializeSourcesChart() {
        if (!lteChartData || !lteChartData.sources) {
            return;
        }
        
        var ctx = document.getElementById('lte-chart-sources');
        if (!ctx) {
            return;
        }
        
        var data = {
            labels: [
                'Achats',
                'Partages sociaux',
                'Administration',
                'Autres'
            ],
            datasets: [{
                data: [
                    lteChartData.sources.purchase,
                    lteChartData.sources.social_share,
                    lteChartData.sources.admin,
                    lteChartData.sources.other
                ],
                backgroundColor: [
                    '#0073aa',
                    '#00a0d2',
                    '#826eb4',
                    '#d54e21'
                ],
                borderWidth: 1
            }]
        };
        
        new Chart(ctx, {
            type: 'doughnut',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var label = context.label || '';
                                var value = context.raw || 0;
                                var total = context.dataset.data.reduce(function(acc, val) {
                                    return acc + val;
                                }, 0);
                                var percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return label + ': ' + value + ' points (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
        
        // Créer la légende personnalisée
        createCustomLegend('lte-legend-sources', data.labels, data.datasets[0].backgroundColor);
    }
    
    /**
     * Initialise le graphique des excursions par points
     */
    function initializeExcursionsChart() {
        if (!lteChartData || !lteChartData.excursions) {
            return;
        }
        
        var ctx = document.getElementById('lte-chart-excursions');
        if (!ctx) {
            return;
        }
        
        var labels = [];
        var values = [];
        var colors = [];
        
        var i = 0;
        for (var excursion in lteChartData.excursions) {
            if (lteChartData.excursions.hasOwnProperty(excursion)) {
                labels.push(excursion);
                values.push(lteChartData.excursions[excursion]);
                
                // Utiliser une couleur définie ou générer une couleur
                var colorIndex = (i % 10) + 1;
                var color = getComputedStyle(document.documentElement).getPropertyValue('--lte-chart-color-' + colorIndex) || '#' + (Math.random().toString(16) + '000000').slice(2, 8);
                colors.push(color);
                
                i++;
            }
        }
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Points attribués',
                    data: values,
                    backgroundColor: colors,
                    borderColor: 'rgba(0, 0, 0, 0.1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Points attribués'
                        }
                    },
                    x: {
                        ticks: {
                            autoSkip: false,
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                }
            }
        });
    }
    
    /**
     * Initialise le graphique d'évolution dans le temps
     */
    function initializeTimelineChart() {
        if (!lteChartData || !lteChartData.timeline) {
            return;
        }
        
        var ctx = document.getElementById('lte-chart-timeline');
        if (!ctx) {
            return;
        }
        
        var labels = [];
        var earnedData = [];
        var redeemedData = [];
        
        // Transformer les données pour le graphique
        for (var month in lteChartData.timeline) {
            if (lteChartData.timeline.hasOwnProperty(month)) {
                // Formater le mois pour l'affichage (ex: "2025-05" -> "Mai 2025")
                var date = new Date(month + '-01');
                var formattedMonth = date.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
                
                labels.push(formattedMonth);
                earnedData.push(lteChartData.timeline[month].earned);
                redeemedData.push(lteChartData.timeline[month].redeemed);
            }
        }
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Points gagnés',
                        data: earnedData,
                        borderColor: '#46b450',
                        backgroundColor: 'rgba(70, 180, 80, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Points utilisés',
                        data: redeemedData,
                        borderColor: '#d54e21',
                        backgroundColor: 'rgba(213, 78, 33, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Points'
                        }
                    }
                }
            }
        });
        
        // Créer la légende personnalisée
        createCustomLegend('lte-legend-timeline', ['Points gagnés', 'Points utilisés'], ['#46b450', '#d54e21']);
    }
    
    /**
     * Crée une légende personnalisée pour un graphique
     */
    function createCustomLegend(elementId, labels, colors) {
        var legendContainer = document.getElementById(elementId);
        if (!legendContainer) {
            return;
        }
        
        // Vider le conteneur
        legendContainer.innerHTML = '';
        
        // Créer les éléments de légende
        for (var i = 0; i < labels.length; i++) {
            var item = document.createElement('div');
            item.className = 'lte-chart-legend-item';
            
            var colorBox = document.createElement('span');
            colorBox.className = 'lte-chart-legend-color';
            colorBox.style.backgroundColor = colors[i];
            
            var label = document.createElement('span');
            label.textContent = labels[i];
            
            item.appendChild(colorBox);
            item.appendChild(label);
            legendContainer.appendChild(item);
        }
    }
    
    /**
     * Initialise tous les gestionnaires d'événements
     */
    function initializeEventHandlers() {
        // Bouton d'exportation CSV
        $('#lte-export-stats').on('click', function() {
            exportStatsToCSV();
        });
        
        // Bouton de rafraîchissement des données
        $('#lte-refresh-stats').on('click', function() {
            refreshStats();
        });
        
        // Boutons de modification des points utilisateur
        $('.edit-user-points').on('click', function() {
            var userId = $(this).data('user-id');
            openEditPointsModal(userId);
        });
        
        // Fermeture du modal
        $('#lte-cancel-edit').on('click', function() {
            closeEditPointsModal();
        });
        
        // Soumission du formulaire d'édition
        $('#lte-edit-points-form').on('submit', function(e) {
            e.preventDefault();
            savePointsAdjustment();
        });
    }
    
    /**
     * Exporte les statistiques au format CSV
     */
    function exportStatsToCSV() {
        // Afficher un message de chargement
        var $button = $('#lte-export-stats');
        var originalText = $button.text();
        $button.text('Génération en cours...');
        $button.prop('disabled', true);
        
        // Requête AJAX pour générer le CSV
        $.ajax({
            url: loyaltyStatsObj.ajaxUrl,
            type: 'POST',
            data: {
                action: 'lte_export_loyalty_stats',
                nonce: loyaltyStatsObj.nonce
            },
            success: function(response) {
                if (response.success && response.data.url) {
                    // Créer un lien temporaire pour télécharger le fichier
                    var link = document.createElement('a');
                    link.href = response.data.url;
                    link.download = response.data.filename || 'loyalty-stats.csv';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                } else {
                    alert('Erreur lors de l\'exportation: ' + (response.data.message || 'Erreur inconnue'));
                }
            },
            error: function() {
                alert('Erreur de connexion lors de l\'exportation des données');
            },
            complete: function() {
                // Restaurer le bouton
                $button.text(originalText);
                $button.prop('disabled', false);
            }
        });
    }
    
    /**
     * Rafraîchit les statistiques en vidant le cache
     */
    function refreshStats() {
        // Afficher un message de chargement
        var $button = $('#lte-refresh-stats');
        var originalText = $button.text();
        $button.text('Actualisation...');
        $button.prop('disabled', true);
        
        // Requête AJAX pour rafraîchir les données
        $.ajax({
            url: loyaltyStatsObj.ajaxUrl,
            type: 'POST',
            data: {
                action: 'lte_refresh_loyalty_stats',
                nonce: loyaltyStatsObj.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Recharger la page pour afficher les nouvelles données
                    location.reload();
                } else {
                    alert('Erreur lors de l\'actualisation: ' + (response.data.message || 'Erreur inconnue'));
                    $button.text(originalText);
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                alert('Erreur de connexion lors de l\'actualisation des données');
                $button.text(originalText);
                $button.prop('disabled', false);
            }
        });
    }
    
    /**
     * Ouvre le modal d'édition des points
     */
    function openEditPointsModal(userId) {
        // Afficher un chargement
        $('#lte-edit-points-modal').show();
        $('#lte-edit-points-form').hide();
        $('#lte-edit-points-modal .lte-modal-content').append('<p class="loading">Chargement...</p>');
        
        // Récupérer les informations de l'utilisateur
        $.ajax({
            url: loyaltyStatsObj.ajaxUrl,
            type: 'POST',
            data: {
                action: 'lte_get_user_points',
                nonce: loyaltyStatsObj.nonce,
                user_id: userId
            },
            success: function(response) {
                $('#lte-edit-points-modal .loading').remove();
                
                if (response.success) {
                    // Remplir le formulaire
                    $('#lte-user-id').val(userId);
                    $('#lte-current-points').val(response.data.points);
                    $('#lte-points-amount').val('');
                    $('#lte-reason').val('');
                    
                    // Afficher le formulaire
                    $('#lte-edit-points-form').show();
                } else {
                    alert('Erreur: ' + (response.data.message || 'Impossible de récupérer les points de l\'utilisateur'));
                    closeEditPointsModal();
                }
            },
            error: function() {
                $('#lte-edit-points-modal .loading').remove();
                alert('Erreur de connexion');
                closeEditPointsModal();
            }
        });
    }
    
    /**
     * Ferme le modal d'édition des points
     */
    function closeEditPointsModal() {
        $('#lte-edit-points-modal').hide();
        $('#lte-edit-points-modal .loading').remove();
    }
    
    /**
     * Enregistre l'ajustement des points
     */
    function savePointsAdjustment() {
        var userId = $('#lte-user-id').val();
        var adjustmentType = $('#lte-adjustment-type').val();
        var pointsAmount = $('#lte-points-amount').val();
        var reason = $('#lte-reason').val();
        
        if (!userId || !pointsAmount || isNaN(pointsAmount) || !reason) {
            alert('Veuillez remplir tous les champs correctement');
            return;
        }
        
        // Désactiver le formulaire pendant l'enregistrement
        $('#lte-edit-points-form :input').prop('disabled', true);
        $('#lte-edit-points-form button[type="submit"]').text('Enregistrement...');
        
        // Envoyer la requête AJAX
        $.ajax({
            url: loyaltyStatsObj.ajaxUrl,
            type: 'POST',
            data: {
                action: 'lte_adjust_user_points',
                nonce: loyaltyStatsObj.nonce,
                user_id: userId,
                adjustment_type: adjustmentType,
                points_amount: pointsAmount,
                reason: reason
            },
            success: function(response) {
                if (response.success) {
                    closeEditPointsModal();
                    // Recharger la page pour afficher les données mises à jour
                    location.reload();
                } else {
                    alert('Erreur: ' + (response.data.message || 'Impossible d\'ajuster les points'));
                    $('#lte-edit-points-form :input').prop('disabled', false);
                    $('#lte-edit-points-form button[type="submit"]').text('Enregistrer');
                }
            },
            error: function() {
                alert('Erreur de connexion');
                $('#lte-edit-points-form :input').prop('disabled', false);
                $('#lte-edit-points-form button[type="submit"]').text('Enregistrer');
            }
        });
    }
    
})(jQuery);
