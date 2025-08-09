/**
 * Excursion Recap Gutenberg Block
 * 
 * @package Life_Travel
 */

const { registerBlockType } = wp.blocks;
const { InspectorControls, useBlockProps } = wp.blockEditor;
const { PanelBody, SelectControl, Placeholder, Spinner } = wp.components;
const { useSelect } = wp.data;
const { __ } = wp.i18n;

/**
 * Register Excursion Recap block
 */
registerBlockType('life-travel/excursion-recap', {
    title: __('Récapitulatif d\'excursion', 'life-travel'),
    icon: 'location-alt',
    category: 'life-travel',
    
    attributes: {
        relatedProductId: {
            type: 'number',
            default: 0,
        }
    },
    
    /**
     * Block edit function
     */
    edit: function(props) {
        const { attributes, setAttributes } = props;
        const { relatedProductId } = attributes;
        const blockProps = useBlockProps({
            className: 'excursion-recap-block',
        });
        
        // Fetch products for dropdown
        const products = useSelect((select) => {
            return select('core').getEntityRecords('postType', 'product', {
                per_page: -1,
                status: 'publish',
            });
        }, []);
        
        // Check if products are loading
        const isLoading = useSelect((select) => {
            return select('core/data').isResolving('core', 'getEntityRecords', [
                'postType', 'product', { per_page: -1, status: 'publish' }
            ]);
        }, []);
        
        // Prepare product options for dropdown
        const productOptions = [
            { value: 0, label: __('Sélectionner un produit...', 'life-travel') }
        ];
        
        if (products && products.length) {
            products.forEach((product) => {
                productOptions.push({
                    value: product.id,
                    label: product.title.rendered
                });
            });
        }
        
        return (
            <>
                <InspectorControls>
                    <PanelBody title={__('Paramètres du récapitulatif', 'life-travel')}>
                        {isLoading ? (
                            <Spinner />
                        ) : (
                            <SelectControl
                                label={__('Produit d\'excursion associé', 'life-travel')}
                                value={relatedProductId}
                                options={productOptions}
                                onChange={(value) => setAttributes({ relatedProductId: parseInt(value) })}
                                help={__('Sélectionnez le produit WooCommerce correspondant à cette excursion', 'life-travel')}
                            />
                        )}
                    </PanelBody>
                </InspectorControls>
                
                <div {...blockProps}>
                    {relatedProductId > 0 ? (
                        <div className="excursion-recap-content">
                            <h3>{__('Récapitulatif d\'excursion', 'life-travel')}</h3>
                            <p>
                                {__('Cette excursion est associée au produit: ', 'life-travel')}
                                <strong>
                                    {products && products.find(p => p.id === relatedProductId)?.title.rendered}
                                </strong>
                            </p>
                            <p className="description">
                                {__('Les participants à cette excursion pourront accéder au contenu exclusif et commenter cet article.', 'life-travel')}
                            </p>
                        </div>
                    ) : (
                        <Placeholder
                            icon="location-alt"
                            label={__('Récapitulatif d\'excursion', 'life-travel')}
                            instructions={__('Veuillez sélectionner le produit WooCommerce associé à cette excursion dans le panneau de droite. Cela permettra aux participants de l\'excursion d\'accéder au contenu exclusif.', 'life-travel')}
                        />
                    )}
                </div>
            </>
        );
    },
    
    /**
     * Block save function (handled on server-side)
     */
    save: function() {
        return null; // Using server-side rendering
    }
});
