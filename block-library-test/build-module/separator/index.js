/**
 * WordPress dependencies
 */
import { separator as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/separator",
  title: "Separator",
  category: "design",
  description: "Create a break between ideas or sections with a horizontal separator.",
  keywords: ["horizontal-line", "hr", "divider"],
  textdomain: "default",
  attributes: {
    opacity: {
      type: "string",
      "default": "alpha-channel"
    }
  },
  supports: {
    anchor: true,
    align: ["center", "wide", "full"],
    color: {
      enableContrastChecker: false,
      __experimentalSkipSerialization: true,
      gradients: true,
      background: true,
      text: false,
      __experimentalDefaultControls: {
        background: true
      }
    },
    spacing: {
      margin: ["top", "bottom"]
    }
  },
  styles: [{
    name: "default",
    label: "Default",
    isDefault: true
  }, {
    name: "wide",
    label: "Wide Line"
  }, {
    name: "dots",
    label: "Dots"
  }],
  editorStyle: "wp-block-separator-editor",
  style: "wp-block-separator"
};
import save from './save';
import transforms from './transforms';
import deprecated from './deprecated';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  example: {
    attributes: {
      customColor: '#065174',
      className: 'is-style-wide'
    }
  },
  transforms,
  edit,
  save,
  deprecated
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map