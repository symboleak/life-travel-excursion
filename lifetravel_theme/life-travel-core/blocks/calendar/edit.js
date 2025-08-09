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
    ToggleControl,
    TextControl,
    SelectControl,
    Placeholder,
    Button,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Edit component for the Calendar block
 *
 * @param {Object} props Block props
 * @returns {JSX.Element} Block edit component
 */
export default function Edit({ attributes, setAttributes }) {
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

    const [currentMonth, setCurrentMonth] = useState(new Date().getMonth());
    const [currentYear, setCurrentYear] = useState(new Date().getFullYear());
    const [calendarData, setCalendarData] = useState({});
    const [isLoading, setIsLoading] = useState(true);

    // Get product categories for selection
    const categories = useSelect((select) => {
        const { getEntityRecords } = select(coreStore);
        const query = { per_page: -1 };
        
        return getEntityRecords('taxonomy', 'product_cat', query) || [];
    }, []);

    // Generate mock calendar data for editor preview
    useEffect(() => {
        // Simulate API call delay
        const timer = setTimeout(() => {
            const mockData = generateMockCalendarData();
            setCalendarData(mockData);
            setIsLoading(false);
        }, 1000);
        
        return () => clearTimeout(timer);
    }, [currentMonth, currentYear]);

    // Generate mock data for editor preview
    function generateMockCalendarData() {
        const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
        const mockData = {};
        
        // Add some excursions (will be replaced with real data in frontend)
        for (let i = 1; i <= daysInMonth; i++) {
            // Add excursions to random days (about 30% of days)
            if (Math.random() < 0.3) {
                const day = i < 10 ? `0${i}` : `${i}`;
                const month = currentMonth + 1 < 10 ? `0${currentMonth + 1}` : `${currentMonth + 1}`;
                const dateKey = `${currentYear}${month}${day}`;
                
                mockData[dateKey] = [
                    {
                        id: Math.floor(Math.random() * 1000),
                        title: `Excursion Example ${i}`,
                        image: `https://picsum.photos/id/${Math.floor(Math.random() * 100)}/320/213`,
                        price: `${Math.floor(Math.random() * 50000) + 10000} FCFA`,
                        available: Math.floor(Math.random() * 10) + 1,
                    }
                ];
            }
        }
        
        return mockData;
    }

    // Change month
    const navigateMonth = (direction) => {
        let newMonth = currentMonth;
        let newYear = currentYear;
        
        if (direction === 'next') {
            newMonth++;
            if (newMonth > 11) {
                newMonth = 0;
                newYear++;
            }
        } else {
            newMonth--;
            if (newMonth < 0) {
                newMonth = 11;
                newYear--;
            }
        }
        
        setCurrentMonth(newMonth);
        setCurrentYear(newYear);
        setIsLoading(true);
    };

    // Get month name
    const getMonthName = (month) => {
        const monthNames = [
            __('Janvier', 'life-travel-core'),
            __('Février', 'life-travel-core'),
            __('Mars', 'life-travel-core'),
            __('Avril', 'life-travel-core'),
            __('Mai', 'life-travel-core'),
            __('Juin', 'life-travel-core'),
            __('Juillet', 'life-travel-core'),
            __('Août', 'life-travel-core'),
            __('Septembre', 'life-travel-core'),
            __('Octobre', 'life-travel-core'),
            __('Novembre', 'life-travel-core'),
            __('Décembre', 'life-travel-core'),
        ];
        
        return monthNames[month];
    };

    // Generate calendar grid
    const generateCalendarGrid = () => {
        const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
        const firstDayOfMonth = new Date(currentYear, currentMonth, 1).getDay();
        const calendarGrid = [];
        
        // Month header
        calendarGrid.push(
            <div key="month-header" className="calendar-month-header">
                <button
                    className="month-nav prev"
                    onClick={() => navigateMonth('prev')}
                >
                    ←
                </button>
                <div className="current-month">
                    {`${getMonthName(currentMonth)} ${currentYear}`}
                </div>
                <button
                    className="month-nav next"
                    onClick={() => navigateMonth('next')}
                >
                    →
                </button>
            </div>
        );
        
        // Day names header (Monday first)
        const dayNames = [
            __('Lun', 'life-travel-core'),
            __('Mar', 'life-travel-core'),
            __('Mer', 'life-travel-core'),
            __('Jeu', 'life-travel-core'),
            __('Ven', 'life-travel-core'),
            __('Sam', 'life-travel-core'),
            __('Dim', 'life-travel-core'),
        ];
        
        calendarGrid.push(
            <div key="day-names" className="calendar-day-names">
                {dayNames.map((day, index) => (
                    <div key={`day-name-${index}`} className="day-name">
                        {day}
                    </div>
                ))}
            </div>
        );
        
        // Calendar days
        const days = [];
        
        // Adjust for Sunday as first day (0) to Monday as first day (1)
        let adjustedFirstDay = firstDayOfMonth - 1;
        if (adjustedFirstDay < 0) adjustedFirstDay = 6; // Sunday becomes last day
        
        // Add empty cells for days before the first day of the month
        for (let i = 0; i < adjustedFirstDay; i++) {
            days.push(
                <div key={`empty-${i}`} className="calendar-day empty"></div>
            );
        }
        
        // Add days of the month
        for (let i = 1; i <= daysInMonth; i++) {
            const day = i < 10 ? `0${i}` : `${i}`;
            const month = currentMonth + 1 < 10 ? `0${currentMonth + 1}` : `${currentMonth + 1}`;
            const dateKey = `${currentYear}${month}${day}`;
            const hasExcursions = calendarData[dateKey] && calendarData[dateKey].length > 0;
            
            days.push(
                <div
                    key={`day-${i}`}
                    className={`calendar-day ${hasExcursions ? 'has-excursions' : ''}`}
                >
                    <div className="day-number">{i}</div>
                    {hasExcursions && (
                        <div className="day-excursions">
                            {calendarData[dateKey].map((excursion, idx) => (
                                <div key={`excursion-${idx}`} className="excursion-preview">
                                    <div className="excursion-image">
                                        <img src={excursion.image} alt={excursion.title} />
                                    </div>
                                    <div className="excursion-info">
                                        <div className="excursion-title">{excursion.title}</div>
                                        <div className="excursion-price">{excursion.price}</div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                    {showVotingForEmptyDays && !hasExcursions && (
                        <div className="day-empty-message">
                            <span className="empty-day-text">
                                {__('Pas encore d\'excursion', 'life-travel-core')}
                            </span>
                        </div>
                    )}
                </div>
            );
        }
        
        calendarGrid.push(
            <div key="days-grid" className="calendar-days-grid">
                {days}
            </div>
        );
        
        // Add voting section for future months
        const currentDate = new Date();
        const isCurrentOrFutureMonth = 
            currentYear > currentDate.getFullYear() || 
            (currentYear === currentDate.getFullYear() && currentMonth >= currentDate.getMonth());
            
        if (showVotingForEmptyDays && isCurrentOrFutureMonth && Object.keys(calendarData).length < 5) {
            calendarGrid.push(
                <div key="voting-section" className="calendar-voting-section">
                    <h3>{votingTitle}</h3>
                    <div className="voting-options">
                        <div className="voting-option">
                            <div className="option-image">
                                <img src="https://picsum.photos/id/10/320/213" alt="Option 1" />
                            </div>
                            <div className="option-details">
                                <h4>{__('Randonnée Monts Mandara', 'life-travel-core')}</h4>
                                <div className="option-meta">
                                    <span>{__('2 jours / 1 nuit', 'life-travel-core')}</span>
                                </div>
                                <div className="option-votes">
                                    <div className="votes-bar" style={{width: '65%'}}></div>
                                    <span className="votes-count">65%</span>
                                </div>
                            </div>
                        </div>
                        <div className="voting-option">
                            <div className="option-image">
                                <img src="https://picsum.photos/id/15/320/213" alt="Option 2" />
                            </div>
                            <div className="option-details">
                                <h4>{__('Safari Waza', 'life-travel-core')}</h4>
                                <div className="option-meta">
                                    <span>{__('1 jour', 'life-travel-core')}</span>
                                </div>
                                <div className="option-votes">
                                    <div className="votes-bar" style={{width: '35%'}}></div>
                                    <span className="votes-count">35%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="voting-action">
                        <Button isPrimary disabled>
                            {__('Voter (édition uniquement)', 'life-travel-core')}
                        </Button>
                    </div>
                </div>
            );
        }
        
        return calendarGrid;
    };

    const blockProps = useBlockProps({
        className: 'excursion-calendar',
        style: {
            backgroundColor,
            color: textColor,
            '--accent-color': accentColor,
        },
    });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Paramètres du calendrier', 'life-travel-core')}>
                    <RangeControl
                        label={__('Mois à afficher vers l\'avant', 'life-travel-core')}
                        value={showMonthsAhead}
                        onChange={(value) => setAttributes({ showMonthsAhead: value })}
                        min={1}
                        max={12}
                    />
                    <RangeControl
                        label={__('Mois à afficher vers l\'arrière', 'life-travel-core')}
                        value={showMonthsBack}
                        onChange={(value) => setAttributes({ showMonthsBack: value })}
                        min={0}
                        max={3}
                    />
                    <ToggleControl
                        label={__('Afficher le système de vote', 'life-travel-core')}
                        checked={showVotingForEmptyDays}
                        onChange={(value) => setAttributes({ showVotingForEmptyDays: value })}
                    />
                    {showVotingForEmptyDays && (
                        <TextControl
                            label={__('Titre de la section de vote', 'life-travel-core')}
                            value={votingTitle}
                            onChange={(value) => setAttributes({ votingTitle: value })}
                        />
                    )}
                    <SelectControl
                        multiple
                        label={__('Catégories d\'excursions à inclure', 'life-travel-core')}
                        value={includeCategories}
                        options={categories.map((category) => ({
                            label: category.name,
                            value: category.id,
                        }))}
                        onChange={(values) => setAttributes({ includeCategories: values })}
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
                <div className="calendar-header">
                    <RichText
                        tagName="h2"
                        className="calendar-title"
                        value={title}
                        onChange={(value) => setAttributes({ title: value })}
                        placeholder={__('Titre du calendrier', 'life-travel-core')}
                    />
                    <RichText
                        tagName="p"
                        className="calendar-subtitle"
                        value={subtitle}
                        onChange={(value) => setAttributes({ subtitle: value })}
                        placeholder={__('Sous-titre du calendrier', 'life-travel-core')}
                    />
                </div>

                <div className="calendar-container">
                    {isLoading ? (
                        <div className="calendar-loading">
                            <span>{__('Chargement du calendrier...', 'life-travel-core')}</span>
                        </div>
                    ) : (
                        generateCalendarGrid()
                    )}
                </div>
            </div>
        </>
    );
}
