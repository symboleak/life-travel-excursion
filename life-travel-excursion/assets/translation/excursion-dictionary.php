<?php
/**
 * Dictionnaire personnalisé pour TranslatePress
 * Termes spécifiques aux excursions Life Travel
 * 
 * Ce fichier définit les traductions spécifiques pour les termes techniques
 * et expressions propres au domaine des excursions touristiques au Cameroun.
 * 
 * @package Life Travel Excursion
 * @version 1.0.0
 */

// Sécurité : sortir si accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fonction qui enregistre un dictionnaire personnalisé pour TranslatePress
 * À charger via le hook 'trp_register_advanced_settings'
 */
function life_travel_register_custom_dictionary($settings_array) {
    // Dictionnaire français -> anglais pour les termes d'excursion
    $excursion_dictionary = array(
        // Termes généraux
        'Excursion' => 'Tour',
        'Excursions' => 'Tours',
        'Circuit' => 'Circuit',
        'Visite guidée' => 'Guided tour',
        'Réservation' => 'Booking',
        'Réserver maintenant' => 'Book now',
        'Voir les excursions' => 'View tours',
        'Prochains départs' => 'Upcoming departures',
        'Calendrier des excursions' => 'Tour calendar',
        'Sur mesure' => 'Custom tour',
        
        // Difficultés
        'Difficulté' => 'Difficulty',
        'Facile' => 'Easy',
        'Modéré' => 'Moderate',
        'Difficile' => 'Difficult',
        'Extrême' => 'Extreme',
        
        // Durées
        'Durée' => 'Duration',
        'Journée complète' => 'Full day',
        'Demi-journée' => 'Half day',
        'Week-end' => 'Weekend',
        'Séjour' => 'Multi-day tour',
        
        // Statuts de disponibilité
        'Disponible' => 'Available',
        'Complet' => 'Fully booked',
        'Dernières places' => 'Last spots',
        'Sur demande' => 'On request',
        
        // Inclusions/exclusions
        'Ce qui est inclus' => 'What\'s included',
        'Non inclus' => 'Not included',
        'Transport' => 'Transportation',
        'Hébergement' => 'Accommodation',
        'Repas' => 'Meals',
        'Guide francophone' => 'French-speaking guide',
        'Guide anglophone' => 'English-speaking guide',
        'Entrées aux sites' => 'Entrance fees',
        'Assurance' => 'Insurance',
        
        // Participants
        'Participants' => 'Participants',
        'Nombre de participants' => 'Number of participants',
        'Taille du groupe' => 'Group size',
        'Âge minimum' => 'Minimum age',
        'Adultes' => 'Adults',
        'Enfants' => 'Children',
        
        // Lieux spécifiques au Cameroun
        'Mont Cameroun' => 'Mount Cameroon',
        'Réserve du Dja' => 'Dja Reserve',
        'Chutes de la Lobé' => 'Lobé Falls',
        'Parc national de Waza' => 'Waza National Park',
        'Kribi' => 'Kribi',
        'Limbe' => 'Limbe',
        'Yaoundé' => 'Yaoundé',
        'Douala' => 'Douala',
        
        // Messages techniques
        'Veuillez sélectionner une date' => 'Please select a date',
        'Veuillez indiquer le nombre de participants' => 'Please specify the number of participants',
        'Nombre de participants invalide' => 'Invalid number of participants',
        'Date de début' => 'Start date',
        'Date de fin' => 'End date',
        'Point de rendez-vous' => 'Meeting point',
        'Informations pratiques' => 'Practical information',
        'Conditions d\'annulation' => 'Cancellation policy',
        
        // Termes de paiement
        'Paiement' => 'Payment',
        'Acompte' => 'Deposit',
        'Solde' => 'Balance',
        'Paiement intégral' => 'Full payment',
        'Paiement à l\'arrivée' => 'Payment on arrival',
        'Mobile Money' => 'Mobile Money',
        'Carte bancaire' => 'Credit card',
        'Virement bancaire' => 'Bank transfer',
        
        // Équipement
        'Équipement nécessaire' => 'Required equipment',
        'Équipement fourni' => 'Provided equipment',
        'Chaussures de randonnée' => 'Hiking shoes',
        'Vêtements adaptés' => 'Suitable clothing',
        'Protection solaire' => 'Sun protection',
        'Chapeau' => 'Hat',
        'Bouteille d\'eau' => 'Water bottle',
        
        // Météo
        'Prévisions météo' => 'Weather forecast',
        'Saison sèche' => 'Dry season',
        'Saison des pluies' => 'Rainy season',
        'Température' => 'Temperature',
        'Climat' => 'Climate'
    );
    
    // Si le paramètre spécifique existe déjà, le compléter
    if (isset($settings_array['trp_advanced_settings']['custom_dictionary'])) {
        $settings_array['trp_advanced_settings']['custom_dictionary'] = array_merge(
            $settings_array['trp_advanced_settings']['custom_dictionary'], 
            $excursion_dictionary
        );
    } else {
        // Sinon, créer le paramètre
        $settings_array['trp_advanced_settings']['custom_dictionary'] = $excursion_dictionary;
    }
    
    return $settings_array;
}
add_filter('trp_register_advanced_settings', 'life_travel_register_custom_dictionary', 10, 1);

/**
 * Ajouter notre dictionnaire personnalisé au processus de traduction automatique
 */
function life_travel_apply_dictionary_translations($translation, $original_string, $context) {
    // Récupérer le dictionnaire
    $settings = get_option('trp_advanced_settings', array());
    $dictionary = isset($settings['custom_dictionary']) ? $settings['custom_dictionary'] : array();
    
    // Si le terme existe dans notre dictionnaire, utiliser cette traduction
    if (isset($dictionary[$original_string])) {
        return $dictionary[$original_string];
    }
    
    // Sinon, renvoyer la traduction originale
    return $translation;
}
add_filter('trp_before_automatic_translation', 'life_travel_apply_dictionary_translations', 10, 3);
