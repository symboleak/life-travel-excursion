jQuery(document).ready(function($) {
    // Fonction utilitaire pour demander une confirmation avant suppression
    function confirmDeletion(message, element) {
        if (confirm(message)) {
            $(element).closest('.extra-item, .tier_row, .extra_row, .activity_row').remove();
        }
    }

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

    // Appel initial
    showHideExcursionFields();

    // Mise à jour lors du changement du type de produit
    $('#product-type').on('change', function() {
        showHideExcursionFields();
    });

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

    // Suppression de lignes de paliers, extras et activités avec confirmation
    $(document).on('click', '.remove_tier_row, .remove_extra_row, .remove_activity_row', function(e) {
        e.preventDefault();
        var message = '';
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