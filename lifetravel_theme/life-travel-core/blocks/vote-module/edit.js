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
} from '@wordpress/block-editor';
import {
    PanelBody,
    Button,
    TextControl,
    ToggleControl,
    RangeControl,
    DatePicker,
    TextareaControl,
    Icon,
    Placeholder,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { trash, plus } from '@wordpress/icons';

/**
 * Edit component for the Vote Module block
 *
 * @param {Object} props Block props
 * @returns {JSX.Element} Block edit component
 */
export default function Edit({ attributes, setAttributes }) {
    const {
        title,
        description,
        options,
        maxOptions,
        endDate,
        showResults,
        backgroundColor,
        textColor,
        accentColor,
    } = attributes;

    const [editingOptionIndex, setEditingOptionIndex] = useState(null);

    // Add a new voting option
    const addOption = () => {
        if (options.length >= maxOptions) {
            return;
        }

        const newOptions = [
            ...options,
            {
                id: Date.now().toString(),
                title: '',
                description: '',
                image: 0,
                votes: Math.floor(Math.random() * 50), // Random votes for preview
            },
        ];

        setAttributes({ options: newOptions });
        setEditingOptionIndex(newOptions.length - 1);
    };

    // Remove a voting option
    const removeOption = (index) => {
        const newOptions = [...options];
        newOptions.splice(index, 1);
        setAttributes({ options: newOptions });
        setEditingOptionIndex(null);
    };

    // Update a voting option
    const updateOption = (index, field, value) => {
        const newOptions = [...options];
        newOptions[index] = {
            ...newOptions[index],
            [field]: value,
        };
        setAttributes({ options: newOptions });
    };

    // Get total votes
    const getTotalVotes = () => {
        return options.reduce((sum, option) => sum + option.votes, 0);
    };

    // Get vote percentage
    const getVotePercentage = (votes) => {
        const total = getTotalVotes();
        if (total === 0) return 0;
        return Math.round((votes / total) * 100);
    };

    const blockProps = useBlockProps({
        className: 'vote-module',
        style: {
            backgroundColor,
            color: textColor,
            '--accent-color': accentColor,
        },
    });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Paramètres du module de vote', 'life-travel-core')}>
                    <RangeControl
                        label={__('Nombre maximum d\'options', 'life-travel-core')}
                        value={maxOptions}
                        onChange={(value) => setAttributes({ maxOptions: value })}
                        min={2}
                        max={6}
                    />
                    <TextControl
                        label={__('Date de fin du vote', 'life-travel-core')}
                        help={__('Au format AAAA-MM-JJ', 'life-travel-core')}
                        value={endDate}
                        onChange={(value) => setAttributes({ endDate: value })}
                        type="date"
                    />
                    <ToggleControl
                        label={__('Afficher les résultats en temps réel', 'life-travel-core')}
                        checked={showResults}
                        onChange={(value) => setAttributes({ showResults: value })}
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
                    <div className="components-base-control">
                        <label className="components-base-control__label">
                            {__('Couleur d\'accent', 'life-travel-core')}
                        </label>
                        <ColorPalette
                            value={accentColor}
                            onChange={(value) => setAttributes({ accentColor: value })}
                        />
                    </div>
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <div className="vote-module-header">
                    <RichText
                        tagName="h2"
                        className="vote-title"
                        value={title}
                        onChange={(value) => setAttributes({ title: value })}
                        placeholder={__('Titre du module de vote', 'life-travel-core')}
                    />
                    <RichText
                        tagName="p"
                        className="vote-description"
                        value={description}
                        onChange={(value) => setAttributes({ description: value })}
                        placeholder={__('Description du module de vote', 'life-travel-core')}
                    />
                    {endDate && (
                        <div className="vote-end-date">
                            {__('Fin du vote : ', 'life-travel-core')}
                            {new Date(endDate).toLocaleDateString('fr-FR', {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric',
                            })}
                        </div>
                    )}
                </div>

                {options.length > 0 ? (
                    <div className="vote-options">
                        {options.map((option, index) => (
                            <div
                                key={option.id}
                                className={`vote-option ${
                                    editingOptionIndex === index ? 'is-editing' : ''
                                }`}
                            >
                                {editingOptionIndex === index ? (
                                    <div className="option-edit-form">
                                        <TextControl
                                            label={__('Titre', 'life-travel-core')}
                                            value={option.title}
                                            onChange={(value) => updateOption(index, 'title', value)}
                                        />
                                        <TextareaControl
                                            label={__('Description', 'life-travel-core')}
                                            value={option.description}
                                            onChange={(value) => updateOption(index, 'description', value)}
                                        />
                                        <MediaUpload
                                            onSelect={(media) => updateOption(index, 'image', media.id)}
                                            allowedTypes={['image']}
                                            value={option.image}
                                            render={({ open }) => (
                                                <Button
                                                    onClick={open}
                                                    isPrimary
                                                >
                                                    {option.image
                                                        ? __('Changer l\'image', 'life-travel-core')
                                                        : __('Ajouter une image', 'life-travel-core')}
                                                </Button>
                                            )}
                                        />
                                        <div className="edit-option-actions">
                                            <Button
                                                isSecondary
                                                onClick={() => setEditingOptionIndex(null)}
                                            >
                                                {__('Terminer', 'life-travel-core')}
                                            </Button>
                                            <Button
                                                isDestructive
                                                onClick={() => removeOption(index)}
                                            >
                                                {__('Supprimer', 'life-travel-core')}
                                            </Button>
                                        </div>
                                    </div>
                                ) : (
                                    <>
                                        <div className="option-content">
                                            <div className="option-image">
                                                {option.image ? (
                                                    <img
                                                        src={`https://picsum.photos/id/${option.image % 100}/320/213`}
                                                        alt={option.title}
                                                    />
                                                ) : (
                                                    <div className="placeholder-image"></div>
                                                )}
                                            </div>
                                            <div className="option-details">
                                                <h3 className="option-title">
                                                    {option.title || __('Option sans titre', 'life-travel-core')}
                                                </h3>
                                                {option.description && (
                                                    <p className="option-description">{option.description}</p>
                                                )}
                                            </div>
                                            <button
                                                className="edit-option-button"
                                                onClick={() => setEditingOptionIndex(index)}
                                            >
                                                {__('Modifier', 'life-travel-core')}
                                            </button>
                                        </div>
                                        {showResults && (
                                            <div className="option-votes">
                                                <div
                                                    className="votes-bar"
                                                    style={{
                                                        width: `${getVotePercentage(option.votes)}%`,
                                                    }}
                                                ></div>
                                                <span className="votes-percentage">
                                                    {getVotePercentage(option.votes)}%
                                                </span>
                                                <span className="votes-count">
                                                    {option.votes} {__('votes', 'life-travel-core')}
                                                </span>
                                            </div>
                                        )}
                                    </>
                                )}
                            </div>
                        ))}
                    </div>
                ) : (
                    <Placeholder
                        icon="thumbs-up"
                        label={__('Module de vote', 'life-travel-core')}
                        instructions={__('Ajoutez des options de vote pour permettre aux visiteurs de choisir leur excursion préférée.', 'life-travel-core')}
                    >
                        <Button
                            isPrimary
                            onClick={addOption}
                        >
                            {__('Ajouter une option de vote', 'life-travel-core')}
                        </Button>
                    </Placeholder>
                )}

                {options.length > 0 && options.length < maxOptions && (
                    <div className="add-option-button-container">
                        <Button
                            isPrimary
                            onClick={addOption}
                            icon={plus}
                        >
                            {__('Ajouter une option', 'life-travel-core')}
                        </Button>
                    </div>
                )}

                <div className="vote-action">
                    <Button
                        isPrimary
                        className="vote-button"
                        disabled
                    >
                        {__('Voter (édition uniquement)', 'life-travel-core')}
                    </Button>
                </div>
            </div>
        </>
    );
}
