/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';

/**
 * Save component for the Month Slider block
 *
 * @param {Object} props Block props
 * @returns {JSX.Element} Block save component
 */
export default function save({ attributes }) {
    const {
        title,
        subtitle,
        excursionIds,
        style,
        ctaText,
        ctaLink,
        backgroundColor,
        textColor,
    } = attributes;

    const blockProps = useBlockProps.save({
        className: `month-slider month-slider-${style}`,
        style: {
            backgroundColor,
            color: textColor,
        },
        'data-excursion-ids': excursionIds.join(','),
    });

    return (
        <div {...blockProps}>
            <div className="month-slider-header">
                <RichText.Content
                    tagName="h2"
                    className="month-slider-title"
                    value={title}
                />
                <RichText.Content
                    tagName="p"
                    className="month-slider-subtitle"
                    value={subtitle}
                />
            </div>

            <div className="month-slider-items">
                {/* Items will be loaded dynamically by JavaScript */}
                <div className="month-slider-loading">
                    <span className="loading-text">{__('Chargement des excursions...', 'life-travel-core')}</span>
                </div>
            </div>

            {ctaText && ctaLink && (
                <div className="month-slider-cta">
                    <a href={ctaLink} className="wp-block-button__link">
                        {ctaText}
                    </a>
                </div>
            )}
        </div>
    );
}
