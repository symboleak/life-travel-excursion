/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';

/**
 * Save component for the Calendar block
 *
 * @param {Object} props Block props
 * @returns {JSX.Element} Block save component
 */
export default function save({ attributes }) {
    const {
        title,
        subtitle,
        showMonthsAhead,
        showMonthsBack,
        includeCategories,
        showVotingForEmptyDays,
        votingTitle,
        backgroundColor,
        textColor,
        accentColor,
    } = attributes;

    const blockProps = useBlockProps.save({
        className: 'excursion-calendar',
        style: {
            backgroundColor,
            color: textColor,
            '--accent-color': accentColor,
        },
        'data-months-ahead': showMonthsAhead,
        'data-months-back': showMonthsBack,
        'data-categories': includeCategories.join(','),
        'data-show-voting': showVotingForEmptyDays ? 'true' : 'false',
    });

    return (
        <div {...blockProps}>
            <div className="calendar-header">
                <RichText.Content
                    tagName="h2"
                    className="calendar-title"
                    value={title}
                />
                <RichText.Content
                    tagName="p"
                    className="calendar-subtitle"
                    value={subtitle}
                />
            </div>

            <div className="calendar-container">
                <div className="calendar-loading">
                    <span>{__('Chargement du calendrier...', 'life-travel-core')}</span>
                </div>
                
                {/* The calendar content will be loaded dynamically by JavaScript */}
                <div className="calendar-content" data-voting-title={votingTitle}></div>
            </div>
        </div>
    );
}
