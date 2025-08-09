/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';

/**
 * Save component for the Vote Module block
 *
 * @param {Object} props Block props
 * @returns {JSX.Element} Block save component
 */
export default function save({ attributes }) {
    const {
        title,
        description,
        options,
        endDate,
        showResults,
        backgroundColor,
        textColor,
        accentColor,
    } = attributes;

    const blockProps = useBlockProps.save({
        className: 'vote-module',
        style: {
            backgroundColor,
            color: textColor,
            '--accent-color': accentColor,
        },
        'data-end-date': endDate,
        'data-show-results': showResults ? 'true' : 'false',
    });

    return (
        <div {...blockProps}>
            <div className="vote-module-header">
                <RichText.Content
                    tagName="h2"
                    className="vote-title"
                    value={title}
                />
                <RichText.Content
                    tagName="p"
                    className="vote-description"
                    value={description}
                />
                {endDate && (
                    <div className="vote-end-date">
                        {__('Fin du vote : ', 'life-travel-core')}
                        <span className="end-date-value">{endDate}</span>
                    </div>
                )}
            </div>

            <div className="vote-options" data-options={JSON.stringify(options)}>
                {/* Options will be rendered by JavaScript */}
                <div className="vote-options-loading">
                    <span>{__('Chargement des options de vote...', 'life-travel-core')}</span>
                </div>
            </div>

            <div className="vote-action">
                <button type="button" className="vote-button">
                    {__('Voter', 'life-travel-core')}
                </button>
                <div className="vote-status"></div>
            </div>
        </div>
    );
}
