jQuery(document).ready(function($) {
    // Ajout d'une ligne pour les paliers de prix
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

    // Suppression d'une ligne de palier de prix avec confirmation
    $(document).on('click', '.remove_tier_row', function() {
        if (confirm('Voulez-vous vraiment supprimer ce palier de prix ?')) {
            $(this).closest('.tier_row').remove();
        }
    });

    // Ajout d'une ligne pour les extras
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

    // Suppression d'une ligne pour les extras avec confirmation
    $(document).on('click', '.remove_extra_row', function() {
        if (confirm('Voulez-vous vraiment supprimer cet extra ?')) {
            $(this).closest('.extra_row').remove();
        }
    });

    // Ajout d'une ligne pour les activités
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

    // Suppression d'une ligne pour les activités avec confirmation
    $(document).on('click', '.remove_activity_row', function() {
        if (confirm('Voulez-vous vraiment supprimer cette activité ?')) {
            $(this).closest('.activity_row').remove();
        }
    });

    // Fonction pour afficher ou masquer les champs spécifiques à l'excursion selon le type de produit
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

    // Appel initial de la fonction
    showHideExcursionFields();

    // Mise à jour lors du changement du type de produit
    $('#product-type').on('change', function() {
        showHideExcursionFields();
    });
});