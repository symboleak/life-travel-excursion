/**
 * Script de gestion de la carte interactive
 * Compatible avec Leaflet et Google Maps
 */
(function($) {
    'use strict';

    // Vérifier si la configuration de la carte est disponible
    if (typeof lteMapConfig === 'undefined') {
        console.error('Configuration de la carte non disponible');
        return;
    }

    // Objet principal pour la carte
    var LTEMap = {
        maps: {},        // Stockage des instances de carte
        markers: {},     // Stockage des marqueurs par carte
        provider: lteMapConfig.provider || 'leaflet', // Fournisseur de carte
        
        /**
         * Initialisation des cartes sur la page
         */
        init: function() {
            // Rechercher toutes les cartes sur la page
            $('.lte-interactive-map').each(function() {
                var $mapElement = $(this);
                var mapId = $mapElement.attr('id');
                
                if (!mapId) {
                    mapId = 'lte-map-' + Math.floor(Math.random() * 10000);
                    $mapElement.attr('id', mapId);
                }
                
                // Récupérer les options de la carte
                var zoom = parseInt($mapElement.data('zoom')) || parseInt(lteMapConfig.default_zoom) || 8;
                var category = $mapElement.data('category') || '';
                
                // Initialiser la carte avec le fournisseur approprié
                if (LTEMap.provider === 'google' && typeof google !== 'undefined' && google.maps) {
                    LTEMap.initGoogleMap(mapId, zoom, category);
                } else {
                    LTEMap.initLeafletMap(mapId, zoom, category);
                }
            });
        },
        
        /**
         * Initialise une carte Google Maps
         */
        initGoogleMap: function(mapId, zoom, category) {
            var $mapElement = $('#' + mapId);
            var lat = parseFloat(lteMapConfig.default_lat) || 4.0511;
            var lng = parseFloat(lteMapConfig.default_lng) || 9.7679;
            
            // Créer une carte Google Maps
            var mapOptions = {
                center: { lat: lat, lng: lng },
                zoom: zoom,
                mapTypeControl: true,
                streetViewControl: false,
                fullscreenControl: true,
                zoomControl: true,
                styles: [
                    {
                        featureType: 'poi',
                        elementType: 'labels',
                        stylers: [{ visibility: 'off' }]
                    }
                ]
            };
            
            // Créer la carte
            var map = new google.maps.Map($mapElement[0], mapOptions);
            LTEMap.maps[mapId] = map;
            
            // Créer un groupe de marqueurs (MarkerClusterer)
            var markers = [];
            LTEMap.markers[mapId] = markers;
            
            // Charger les marqueurs
            LTEMap.loadMarkers(mapId, category, function(markersData) {
                if (markersData && markersData.length > 0) {
                    var bounds = new google.maps.LatLngBounds();
                    
                    // Créer les marqueurs
                    markersData.forEach(function(markerData) {
                        var marker = LTEMap.createGoogleMarker(map, markerData);
                        markers.push(marker);
                        bounds.extend(marker.getPosition());
                    });
                    
                    // Ajuster la vue
                    if (markers.length > 1) {
                        map.fitBounds(bounds);
                    }
                    
                    // Initialiser le MarkerClusterer si disponible
                    if (typeof MarkerClusterer !== 'undefined') {
                        new MarkerClusterer(map, markers, {
                            imagePath: 'https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/m'
                        });
                    }
                }
                
                // Cacher le loader
                $mapElement.find('.lte-map-loader').fadeOut();
            });
        },
        
        /**
         * Initialise une carte Leaflet
         */
        initLeafletMap: function(mapId, zoom, category) {
            var $mapElement = $('#' + mapId);
            var lat = parseFloat(lteMapConfig.default_lat) || 4.0511;
            var lng = parseFloat(lteMapConfig.default_lng) || 9.7679;
            
            // Créer une carte Leaflet
            var map = L.map(mapId, {
                center: [lat, lng],
                zoom: zoom,
                scrollWheelZoom: false
            });
            
            // Ajouter la couche de tuiles OpenStreetMap
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            
            LTEMap.maps[mapId] = map;
            
            // Créer un groupe de marqueurs
            var markerCluster = L.markerClusterGroup();
            LTEMap.markers[mapId] = markerCluster;
            map.addLayer(markerCluster);
            
            // Activer le zoom à la molette quand la souris est sur la carte
            map.on('mouseover', function() {
                map.scrollWheelZoom.enable();
            });
            
            map.on('mouseout', function() {
                map.scrollWheelZoom.disable();
            });
            
            // Charger les marqueurs
            LTEMap.loadMarkers(mapId, category, function(markersData) {
                if (markersData && markersData.length > 0) {
                    var latLngs = [];
                    
                    // Créer les marqueurs
                    markersData.forEach(function(markerData) {
                        var marker = LTEMap.createLeafletMarker(map, markerCluster, markerData);
                        latLngs.push([markerData.lat, markerData.lng]);
                    });
                    
                    // Ajuster la vue
                    if (latLngs.length > 1) {
                        map.fitBounds(latLngs);
                    }
                }
                
                // Mettre à jour la taille de la carte (nécessaire pour certains conteneurs)
                setTimeout(function() {
                    map.invalidateSize();
                }, 100);
                
                // Cacher le loader
                $mapElement.find('.lte-map-loader').fadeOut();
            });
        },
        
        /**
         * Charge les marqueurs depuis l'API
         */
        loadMarkers: function(mapId, category, callback) {
            $.ajax({
                url: lteMapConfig.ajax_url,
                data: {
                    action: 'get_map_markers',
                    nonce: lteMapConfig.nonce,
                    category: category
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.markers) {
                        callback(response.markers);
                    } else {
                        console.error('Erreur lors du chargement des marqueurs:', response);
                        $('#' + mapId).find('.lte-map-loader').html('<p>' + lteMapConfig.i18n.error_loading + '</p>');
                        callback([]);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erreur AJAX lors du chargement des marqueurs:', error);
                    $('#' + mapId).find('.lte-map-loader').html('<p>' + lteMapConfig.i18n.error_loading + '</p>');
                    callback([]);
                }
            });
        },
        
        /**
         * Crée un marqueur Google Maps
         */
        createGoogleMarker: function(map, markerData) {
            var latLng = new google.maps.LatLng(markerData.lat, markerData.lng);
            
            // Options du marqueur
            var markerOptions = {
                position: latLng,
                map: map,
                title: markerData.title,
                animation: google.maps.Animation.DROP
            };
            
            // Utiliser une icône personnalisée si disponible
            if (lteMapConfig.marker_icon) {
                markerOptions.icon = {
                    url: lteMapConfig.marker_icon,
                    scaledSize: new google.maps.Size(32, 32)
                };
            }
            
            // Créer le marqueur
            var marker = new google.maps.Marker(markerOptions);
            
            // Créer une infowindow
            var content = LTEMap.createMarkerContent(markerData);
            var infoWindow = new google.maps.InfoWindow({
                content: content
            });
            
            // Ajouter l'événement au clic
            marker.addListener('click', function() {
                // Fermer toutes les fenêtres d'information ouvertes
                google.maps.InfoWindow.prototype.close();
                
                // Ouvrir cette fenêtre d'information
                infoWindow.open(map, marker);
            });
            
            return marker;
        },
        
        /**
         * Crée un marqueur Leaflet
         */
        createLeafletMarker: function(map, cluster, markerData) {
            var latLng = [markerData.lat, markerData.lng];
            
            // Options du marqueur
            var markerOptions = {
                title: markerData.title
            };
            
            // Utiliser une icône personnalisée si disponible
            if (lteMapConfig.marker_icon) {
                markerOptions.icon = L.icon({
                    iconUrl: lteMapConfig.marker_icon,
                    iconSize: [32, 32],
                    iconAnchor: [16, 32],
                    popupAnchor: [0, -32]
                });
            }
            
            // Créer le marqueur
            var marker = L.marker(latLng, markerOptions);
            
            // Ajouter le popup
            var content = LTEMap.createMarkerContent(markerData);
            marker.bindPopup(content);
            
            // Ajouter au cluster
            cluster.addLayer(marker);
            
            return marker;
        },
        
        /**
         * Crée le contenu HTML pour un marqueur
         */
        createMarkerContent: function(markerData) {
            var content = '<div class="lte-map-popup">';
            
            // Ajouter l'image si disponible
            if (markerData.thumbnail) {
                content += '<div class="lte-popup-thumbnail">' +
                          '<a href="' + markerData.url + '">' +
                          '<img src="' + markerData.thumbnail + '" alt="' + markerData.title + '">' +
                          '</a>' +
                          '</div>';
            }
            
            // Ajouter les informations
            content += '<div class="lte-popup-content">' +
                      '<h4><a href="' + markerData.url + '">' + markerData.title + '</a></h4>';
            
            // Ajouter le résumé si disponible
            if (markerData.excerpt) {
                content += '<p class="lte-popup-excerpt">' + markerData.excerpt + '</p>';
            }
            
            // Ajouter le prix si disponible
            if (markerData.price) {
                content += '<div class="lte-popup-price">' +
                          '<span class="lte-price-label">' + lteMapConfig.i18n.starting_from + '</span> ' +
                          '<span class="lte-price-value">' + markerData.price + '</span>' +
                          '</div>';
            }
            
            // Ajouter le bouton de détails
            content += '<div class="lte-popup-action">' +
                      '<a href="' + markerData.url + '" class="lte-btn">' + 
                      lteMapConfig.i18n.view_details + 
                      '</a>' +
                      '</div>' +
                      '</div>' +
                      '</div>';
            
            return content;
        }
    };
    
    // Initialiser les cartes quand le DOM est prêt
    $(document).ready(function() {
        LTEMap.init();
    });
    
})(jQuery);
