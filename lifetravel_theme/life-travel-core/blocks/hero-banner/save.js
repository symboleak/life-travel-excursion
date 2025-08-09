/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';

/**
 * Save component for the Hero Banner block
 *
 * @param {Object} props Block props
 * @returns {JSX.Element} Block save component
 */
export default function save({ attributes }) {
    const {
        title,
        subtitle,
        description,
        ctaText,
        ctaUrl,
        secondaryCtaText,
        secondaryCtaUrl,
        backgroundUrl,
        overlayOpacity,
        overlayColor,
        height,
        textAlign,
    } = attributes;

    const blockProps = useBlockProps.save({
        className: `hero-banner text-${textAlign}`,
        style: {
            height,
            backgroundImage: backgroundUrl ? `url(${backgroundUrl})` : undefined,
        },
        'data-overlay-opacity': overlayOpacity,
        'data-overlay-color': overlayColor,
    });

    return (
        <div {...blockProps}>
            <div 
                className="hero-overlay"
                style={{
                    backgroundColor: overlayColor,
                    opacity: overlayOpacity / 100,
                }}
            ></div>
            
            <div className="hero-content">
                <RichText.Content
                    tagName="h2"
                    className="hero-title"
                    value={title}
                />
                
                {subtitle && (
                    <RichText.Content
                        tagName="h3"
                        className="hero-subtitle"
                        value={subtitle}
                    />
                )}
                
                {description && (
                    <RichText.Content
                        tagName="p"
                        className="hero-description"
                        value={description}
                    />
                )}
                
                <div className="hero-buttons">
                    {ctaText && ctaUrl && (
                        <a href={ctaUrl} className="hero-cta-primary">
                            {ctaText}
                        </a>
                    )}
                    
                    {secondaryCtaText && secondaryCtaUrl && (
                        <a href={secondaryCtaUrl} className="hero-cta-secondary">
                            {secondaryCtaText}
                        </a>
                    )}
                </div>
            </div>
        </div>
    );
}
