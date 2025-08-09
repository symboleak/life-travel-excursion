/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
    useBlockProps,
    InspectorControls,
    RichText,
    ColorPalette,
    MediaUpload,
    MediaUploadCheck,
} from '@wordpress/block-editor';
import {
    PanelBody,
    Button,
    TextControl,
    RangeControl,
    SelectControl,
    Placeholder,
} from '@wordpress/components';
import { useState } from '@wordpress/element';

/**
 * Edit component for the Hero Banner block
 *
 * @param {Object} props Block props
 * @returns {JSX.Element} Block edit component
 */
export default function Edit({ attributes, setAttributes }) {
    const {
        title,
        subtitle,
        description,
        ctaText,
        ctaUrl,
        secondaryCtaText,
        secondaryCtaUrl,
        backgroundId,
        backgroundUrl,
        overlayOpacity,
        overlayColor,
        height,
        textAlign,
    } = attributes;

    // Set a default background for editor preview if none is selected
    const defaultBackgroundUrl = 'https://picsum.photos/id/10/1280/720';
    const effectiveBackgroundUrl = backgroundUrl || defaultBackgroundUrl;

    const blockProps = useBlockProps({
        className: `hero-banner text-${textAlign}`,
        style: {
            height,
            backgroundImage: `url(${effectiveBackgroundUrl})`,
            backgroundSize: 'cover',
            backgroundPosition: 'center',
            position: 'relative',
        },
    });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Paramètres de la bannière', 'life-travel-core')}>
                    <MediaUploadCheck>
                        <MediaUpload
                            onSelect={(media) => {
                                setAttributes({
                                    backgroundId: media.id,
                                    backgroundUrl: media.url,
                                });
                            }}
                            allowedTypes={['image']}
                            value={backgroundId}
                            render={({ open }) => (
                                <Button
                                    onClick={open}
                                    isPrimary={!backgroundId}
                                    className="editor-post-featured-image__toggle"
                                >
                                    {!backgroundId
                                        ? __('Ajouter une image de fond', 'life-travel-core')
                                        : __('Changer l\'image de fond', 'life-travel-core')}
                                </Button>
                            )}
                        />
                    </MediaUploadCheck>
                    
                    {backgroundId > 0 && (
                        <div className="editor-post-featured-image">
                            <MediaUploadCheck>
                                <Button
                                    onClick={() => {
                                        setAttributes({
                                            backgroundId: 0,
                                            backgroundUrl: '',
                                        });
                                    }}
                                    isLink
                                    isDestructive
                                >
                                    {__('Supprimer l\'image de fond', 'life-travel-core')}
                                </Button>
                            </MediaUploadCheck>
                        </div>
                    )}
                    
                    <TextControl
                        label={__('Hauteur de la bannière', 'life-travel-core')}
                        value={height}
                        onChange={(value) => setAttributes({ height: value })}
                        help={__('Exemple: 75vh, 500px, auto', 'life-travel-core')}
                    />
                    
                    <SelectControl
                        label={__('Alignement du texte', 'life-travel-core')}
                        value={textAlign}
                        options={[
                            { label: __('Gauche', 'life-travel-core'), value: 'left' },
                            { label: __('Centre', 'life-travel-core'), value: 'center' },
                            { label: __('Droite', 'life-travel-core'), value: 'right' },
                        ]}
                        onChange={(value) => setAttributes({ textAlign: value })}
                    />
                    
                    <TextControl
                        label={__('URL du bouton principal', 'life-travel-core')}
                        value={ctaUrl}
                        onChange={(value) => setAttributes({ ctaUrl: value })}
                        placeholder="https://..."
                    />
                    
                    <TextControl
                        label={__('URL du bouton secondaire', 'life-travel-core')}
                        value={secondaryCtaUrl}
                        onChange={(value) => setAttributes({ secondaryCtaUrl: value })}
                        placeholder="https://..."
                    />
                </PanelBody>
                
                <PanelBody title={__('Superposition', 'life-travel-core')}>
                    <div className="components-base-control">
                        <label className="components-base-control__label">
                            {__('Couleur de la superposition', 'life-travel-core')}
                        </label>
                        <ColorPalette
                            value={overlayColor}
                            onChange={(value) => setAttributes({ overlayColor: value })}
                        />
                    </div>
                    
                    <RangeControl
                        label={__('Opacité de la superposition', 'life-travel-core')}
                        value={overlayOpacity}
                        onChange={(value) => setAttributes({ overlayOpacity: value })}
                        min={0}
                        max={100}
                        step={5}
                    />
                </PanelBody>
            </InspectorControls>
            
            <div {...blockProps}>
                {/* Overlay */}
                <div
                    className="hero-overlay"
                    style={{
                        backgroundColor: overlayColor,
                        opacity: overlayOpacity / 100,
                        position: 'absolute',
                        top: 0,
                        left: 0,
                        right: 0,
                        bottom: 0,
                        zIndex: 1,
                    }}
                ></div>
                
                {/* Content */}
                <div
                    className="hero-content"
                    style={{
                        position: 'relative',
                        zIndex: 2,
                        padding: '2rem',
                        height: '100%',
                        display: 'flex',
                        flexDirection: 'column',
                        justifyContent: 'center',
                        alignItems: textAlign === 'center' ? 'center' : textAlign === 'left' ? 'flex-start' : 'flex-end',
                        color: '#fff',
                    }}
                >
                    <RichText
                        tagName="h2"
                        className="hero-title"
                        value={title}
                        onChange={(value) => setAttributes({ title: value })}
                        placeholder={__('Titre de la bannière', 'life-travel-core')}
                    />
                    
                    <RichText
                        tagName="h3"
                        className="hero-subtitle"
                        value={subtitle}
                        onChange={(value) => setAttributes({ subtitle: value })}
                        placeholder={__('Sous-titre de la bannière', 'life-travel-core')}
                    />
                    
                    <RichText
                        tagName="p"
                        className="hero-description"
                        value={description}
                        onChange={(value) => setAttributes({ description: value })}
                        placeholder={__('Description de la bannière', 'life-travel-core')}
                    />
                    
                    <div className="hero-buttons">
                        <RichText
                            tagName="span"
                            className="hero-cta-primary"
                            value={ctaText}
                            onChange={(value) => setAttributes({ ctaText: value })}
                            placeholder={__('Texte du bouton principal', 'life-travel-core')}
                        />
                        
                        {secondaryCtaText && (
                            <RichText
                                tagName="span"
                                className="hero-cta-secondary"
                                value={secondaryCtaText}
                                onChange={(value) => setAttributes({ secondaryCtaText: value })}
                                placeholder={__('Texte du bouton secondaire', 'life-travel-core')}
                            />
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
