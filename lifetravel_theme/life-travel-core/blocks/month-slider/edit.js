/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
    useBlockProps,
    InspectorControls,
    RichText,
    ColorPalette,
} from '@wordpress/block-editor';
import {
    PanelBody,
    RangeControl,
    SelectControl,
    TextControl,
    Button,
    Placeholder,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { useState } from '@wordpress/element';

/**
 * Edit component for the Month Slider block
 *
 * @param {Object} props Block props
 * @returns {JSX.Element} Block edit component
 */
export default function Edit({ attributes, setAttributes }) {
    const {
        title,
        subtitle,
        excursionIds,
        slidesToShow,
        slidesToShowMobile,
        style,
        ctaText,
        ctaLink,
        backgroundColor,
        textColor,
    } = attributes;

    const [isSelectingProducts, setIsSelectingProducts] = useState(false);

    // Get excursion products from the store
    const excursions = useSelect((select) => {
        if (!excursionIds || !excursionIds.length) return [];

        const { getEntityRecord } = select(coreStore);
        
        return excursionIds.map((id) => {
            const product = getEntityRecord('postType', 'product', id);
            return product;
        }).filter(Boolean);
    }, [excursionIds]);

    // Get all products for selection
    const allProducts = useSelect((select) => {
        if (!isSelectingProducts) return [];

        const { getEntityRecords } = select(coreStore);
        const query = { per_page: 10 };
        
        return getEntityRecords('postType', 'product', query) || [];
    }, [isSelectingProducts]);

    const blockProps = useBlockProps({
        className: `month-slider month-slider-${style}`,
        style: {
            backgroundColor,
            color: textColor,
        },
    });

    // Toggle product selection
    const toggleProductSelection = (productId) => {
        const index = excursionIds.indexOf(productId);
        let newExcursionIds;

        if (index > -1) {
            // Remove product
            newExcursionIds = [...excursionIds];
            newExcursionIds.splice(index, 1);
        } else {
            // Add product
            newExcursionIds = [...excursionIds, productId];
        }

        setAttributes({ excursionIds: newExcursionIds });
    };

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Paramètres du slider', 'life-travel-core')}>
                    <RangeControl
                        label={__('Excursions visibles (desktop)', 'life-travel-core')}
                        value={slidesToShow}
                        onChange={(value) => setAttributes({ slidesToShow: value })}
                        min={1}
                        max={4}
                        step={0.5}
                    />
                    <RangeControl
                        label={__('Excursions visibles (mobile)', 'life-travel-core')}
                        value={slidesToShowMobile}
                        onChange={(value) => setAttributes({ slidesToShowMobile: value })}
                        min={1}
                        max={2}
                        step={0.5}
                    />
                    <SelectControl
                        label={__('Style du slider', 'life-travel-core')}
                        value={style}
                        options={[
                            { label: __('Standard', 'life-travel-core'), value: 'standard' },
                            { label: __('Compact', 'life-travel-core'), value: 'compact' },
                            { label: __('Hero (grand format)', 'life-travel-core'), value: 'hero' },
                        ]}
                        onChange={(value) => setAttributes({ style: value })}
                    />
                    <TextControl
                        label={__('Texte du bouton CTA', 'life-travel-core')}
                        value={ctaText}
                        onChange={(value) => setAttributes({ ctaText: value })}
                    />
                    <TextControl
                        label={__('Lien du bouton CTA', 'life-travel-core')}
                        value={ctaLink}
                        onChange={(value) => setAttributes({ ctaLink: value })}
                    />
                </PanelBody>
                <PanelBody title={__('Couleurs', 'life-travel-core')}>
                    <div className="components-base-control">
                        <label className="components-base-control__label">
                            {__('Couleur de fond', 'life-travel-core')}
                        </label>
                        <ColorPalette
                            value={backgroundColor}
                            onChange={(value) => setAttributes({ backgroundColor: value })}
                        />
                    </div>
                    <div className="components-base-control">
                        <label className="components-base-control__label">
                            {__('Couleur du texte', 'life-travel-core')}
                        </label>
                        <ColorPalette
                            value={textColor}
                            onChange={(value) => setAttributes({ textColor: value })}
                        />
                    </div>
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <div className="month-slider-header">
                    <RichText
                        tagName="h2"
                        className="month-slider-title"
                        value={title}
                        onChange={(value) => setAttributes({ title: value })}
                        placeholder={__('Titre du slider', 'life-travel-core')}
                    />
                    <RichText
                        tagName="p"
                        className="month-slider-subtitle"
                        value={subtitle}
                        onChange={(value) => setAttributes({ subtitle: value })}
                        placeholder={__('Sous-titre du slider', 'life-travel-core')}
                    />
                </div>

                {excursions.length > 0 ? (
                    <div className="month-slider-items">
                        {excursions.map((excursion) => (
                            <div key={excursion.id} className="month-slider-item">
                                <div className="excursion-image">
                                    {excursion.featured_media ? (
                                        <img
                                            src={excursion._embedded?.['wp:featuredmedia']?.[0]?.source_url || ''}
                                            alt={excursion.title?.rendered || ''}
                                        />
                                    ) : (
                                        <div className="placeholder-image"></div>
                                    )}
                                </div>
                                <div className="excursion-content">
                                    <h3 className="excursion-title">
                                        {excursion.title?.rendered || __('Titre de l\'excursion', 'life-travel-core')}
                                    </h3>
                                    <div className="excursion-meta">
                                        <span className="excursion-price">
                                            {excursion.price || __('Prix', 'life-travel-core')}
                                        </span>
                                        <span className="excursion-duration">
                                            {excursion.meta?.excursion_duration || __('Durée', 'life-travel-core')}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <Placeholder
                        icon="slides"
                        label={__('Slider des excursions du mois', 'life-travel-core')}
                        instructions={__('Sélectionnez les excursions à afficher dans le slider.', 'life-travel-core')}
                    >
                        <Button
                            isPrimary
                            onClick={() => setIsSelectingProducts(!isSelectingProducts)}
                        >
                            {__('Sélectionner des excursions', 'life-travel-core')}
                        </Button>
                        
                        {isSelectingProducts && (
                            <div className="product-selection">
                                {allProducts.length > 0 ? (
                                    <>
                                        <h3>{__('Sélectionnez des produits', 'life-travel-core')}</h3>
                                        <div className="product-list">
                                            {allProducts.map((product) => (
                                                <Button
                                                    key={product.id}
                                                    isSecondary={excursionIds.includes(product.id)}
                                                    onClick={() => toggleProductSelection(product.id)}
                                                >
                                                    {product.title?.rendered || product.id}
                                                </Button>
                                            ))}
                                        </div>
                                    </>
                                ) : (
                                    <p>{__('Chargement des produits...', 'life-travel-core')}</p>
                                )}
                            </div>
                        )}
                    </Placeholder>
                )}

                {excursions.length > 0 && ctaText && (
                    <div className="month-slider-cta">
                        <Button
                            isPrimary
                            href={ctaLink}
                        >
                            {ctaText}
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}
