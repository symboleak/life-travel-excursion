/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import './style.scss';
import './editor.scss';
import Edit from './edit';
import save from './save';

/**
 * Register block
 */
registerBlockType('life-travel-core/vote-module', {
    edit: Edit,
    save,
});
