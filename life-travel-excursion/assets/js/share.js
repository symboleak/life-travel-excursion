jQuery(function($){
    $('.lte-share-btn').on('click', function(){
        var network = $(this).data('network');
        var url = $(this).data('url');
        var post_id = $(this).data('post-id');
        var share_url = '';
        switch(network) {
            case 'facebook': share_url = 'https://www.facebook.com/sharer/sharer.php?u=' + url; break;
            case 'twitter': share_url = 'https://twitter.com/intent/tweet?url=' + url; break;
            case 'whatsapp': share_url = 'https://api.whatsapp.com/send?text=' + url; break;
            case 'instagram': share_url = url; break;
        }
        window.open(share_url, '_blank', 'width=600,height=400');
        // Award points via AJAX
        $.post(lte_share_params.ajax_url, {action:'lte_loyalty_award_share', post_id: post_id, nonce: lte_share_params.nonce}, function(resp) {
            if (resp.success) alert(resp.data);
        });
    });
});
