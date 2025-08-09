/**
 * Script d'administration unifié pour Life Travel
 * 
 * Ce fichier centralise toutes les fonctionnalités JavaScript pour l'administration
 * des excursions, remplaçant les fichiers admin-excursion.js et admin-script.js.
 * 
 * @package Life Travel Excursion
 * @version 2.4.0
 */
jQuery(document).ready(function($) {
    // ======== UTILITAIRES COMMUNS ========
    
    /**
     * Fonction utilitaire pour demander une confirmation avant suppression
     * 
     * @param {string} message - Message de confirmation à afficher
     * @param {Element} element - Élément déclencheur à cibler
     * @param {string} selector - Sélecteur CSS du conteneur parent à supprimer
     * @return {boolean} - True si confirmé, false sinon
     */
    function confirmDeletion(message, element, selector) {
        if (confirm(message)) {
            $(element).closest(selector || '.extra-item, .tier_row, .extra_row, .activity_row').remove();
            return true;
        }
        return false;
    }
    
    /**
     * Fonction pour afficher ou masquer les champs spécifiques à l'excursion
     * selon le type de produit sélectionné
     */
    function showHideExcursionFields() {
        var productType = $('#product-type').val();
        if (productType === 'excursion') {
            $('.show_if_excursion').show();
            $('.hide_if_excursion').hide();
        } else {
            $('.show_if_excursion').hide();
            $('.hide_if_excursion').show();
        }
    }
    
    // Appel initial et configuration du changement
    showHideExcursionFields();
    $('#product-type').on('change', showHideExcursionFields);
    
    // ======== GESTION DES PALIERS DE PRIX ========
    
    // Ajout d'une ligne de palier de prix
    $('#add_tier_row').on('click', function(e) {
        e.preventDefault();
        var newRow = '<div class="tier_row" role="group" aria-label="Palier de prix">';
        newRow += '<input type="number" name="pricing_tiers_min[]" placeholder="' + life_travel_excursion_admin.min_participants + '" />';
        newRow += '<input type="number" name="pricing_tiers_max[]" placeholder="' + life_travel_excursion_admin.max_participants + '" />';
        newRow += '<input type="number" name="pricing_tiers_price[]" placeholder="' + life_travel_excursion_admin.price_per_person + '" step="0.01" />';
        newRow += '<button type="button" class="remove_tier_row button" aria-label="Supprimer ce palier">-</button>';
        newRow += '</div>';
        $('#pricing_tiers_container').append(newRow);
    });
    
    // ======== GESTION DES EXTRAS ========
    
    // Ajout d'une ligne d'extra
    $('#add_extra_row').on('click', function(e) {
        e.preventDefault();
        var newRow = '<div class="extra_row" role="group" aria-label="Extra">';
        newRow += '<input type="text" name="extras_name[]" placeholder="' + life_travel_excursion_admin.extra_name + '" />';
        newRow += '<input type="number" name="extras_price[]" placeholder="' + life_travel_excursion_admin.price + '" step="0.01" />';
        newRow += '<select name="extras_type[]">';
        newRow += '<option value="quantite">' + life_travel_excursion_admin.quantity + '</option>';
        newRow += '<option value="selection">' + life_travel_excursion_admin.selection + '</option>';
        newRow += '</select>';
        newRow += '<select name="extras_multiplier[]">';
        newRow += '<option value="unique">' + life_travel_excursion_admin.unique + '</option>';
        newRow += '<option value="jours">' + life_travel_excursion_admin.days + '</option>';
        newRow += '<option value="participants">' + life_travel_excursion_admin.participants + '</option>';
        newRow += '<option value="jours_participants">' + life_travel_excursion_admin.days_participants + '</option>';
        newRow += '</select>';
        newRow += '<button type="button" class="remove_extra_row button" aria-label="Supprimer cet extra">-</button>';
        newRow += '</div>';
        $('#extras_container').append(newRow);
    });
    
    // ======== GESTION DES ACTIVITÉS ========
    
    // Ajout d'une ligne d'activité
    $('#add_activity_row').on('click', function(e) {
        e.preventDefault();
        var newRow = '<div class="activity_row" role="group" aria-label="Activité">';
        newRow += '<input type="text" name="activities_name[]" placeholder="' + life_travel_excursion_admin.activity_name + '" />';
        newRow += '<input type="number" name="activities_price[]" placeholder="' + life_travel_excursion_admin.activity_price + '" step="0.01" />';
        newRow += '<input type="number" name="activities_max_duration[]" placeholder="' + life_travel_excursion_admin.activity_max_duration + '" />';
        newRow += '<button type="button" class="remove_activity_row button" aria-label="Supprimer cette activité">-</button>';
        newRow += '</div>';
        $('#activities_container').append(newRow);
    });
    
    // ======== GESTION DES ÉLÉMENTS EXISTANTS ========
    
    // Ajout d'un bouton de suppression personnalisé aux extras existants
    function addDeleteButton(element) {
        var deleteButton = $('<button type="button" class="delete-extra button" aria-label="Supprimer cet élément">Supprimer</button>');
        deleteButton.on('click', function(e) {
            e.preventDefault();
            confirmDeletion('Voulez-vous vraiment supprimer cet élément ?', this);
        });
        $(element).append(deleteButton);
    }
    
    // Appliquer le bouton de suppression aux extras existants
    $('.extra-item').each(function() {
        addDeleteButton(this);
    });
    
    // ======== DÉLÉGATION D'ÉVÉNEMENTS POUR LA SUPPRESSION ========
    
    // Gestion unifiée des suppressions de lignes avec confirmation
    $(document).on('click', '.remove_tier_row, .remove_extra_row, .remove_activity_row', function(e) {
        e.preventDefault();
        
        var message = 'Voulez-vous vraiment supprimer cet élément ?';
        
        // Messages spécifiques selon le type d'élément
        if ($(this).hasClass('remove_tier_row')) {
            message = 'Voulez-vous vraiment supprimer ce palier de prix ?';
        } else if ($(this).hasClass('remove_extra_row')) {
            message = 'Voulez-vous vraiment supprimer cet extra ?';
        } else if ($(this).hasClass('remove_activity_row')) {
            message = 'Voulez-vous vraiment supprimer cette activité ?';
        }
        
        confirmDeletion(message, this);
    });
});
