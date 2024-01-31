/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { buttons as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import deprecated from './deprecated';
import transforms from './transforms';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/buttons",
  title: "Buttons",
  category: "design",
  allowedBlocks: ["core/button"],
  description: "Prompt visitors to take action with a group of button-style links.",
  keywords: ["link"],
  textdomain: "default",
  supports: {
    anchor: true,
    align: ["wide", "full"],
    html: false,
    __experimentalExposeControlsToChildren: true,
    spacing: {
      blockGap: true,
      margin: ["top", "bottom"],
      __experimentalDefaultControls: {
        blockGap: true
      }
    },
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontFamily: true,
      __experimentalFontWeight: true,
      __experimentalFontStyle: true,
      __experimentalTextTransform: true,
      __experimentalTextDecoration: true,
      __experimentalLetterSpacing: true,
      __experimentalDefaultControls: {
        fontSize: true
      }
    },
    layout: {
      allowSwitching: false,
      allowInheriting: false,
      "default": {
        type: "flex"
      }
    }
  },
  editorStyle: "wp-block-buttons-editor",
  style: "wp-block-buttons"
};
import save from './save';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  example: {
    innerBlocks: [{
      name: 'core/button',
      attributes: {
        text: __('Find out more')
      }
    }, {
      name: 'core/button',
      attributes: {
        text: __('Contact us')
      }
    }]
  },
  deprecated,
  transforms,
  edit,
  save
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map