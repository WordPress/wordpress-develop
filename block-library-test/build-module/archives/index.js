/**
 * WordPress dependencies
 */
import { archive as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/archives",
  title: "Archives",
  category: "widgets",
  description: "Display a date archive of your posts.",
  textdomain: "default",
  attributes: {
    displayAsDropdown: {
      type: "boolean",
      "default": false
    },
    showLabel: {
      type: "boolean",
      "default": true
    },
    showPostCounts: {
      type: "boolean",
      "default": false
    },
    type: {
      type: "string",
      "default": "monthly"
    }
  },
  supports: {
    align: true,
    html: false,
    spacing: {
      margin: true,
      padding: true,
      __experimentalDefaultControls: {
        margin: false,
        padding: false
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
    }
  },
  editorStyle: "wp-block-archives-editor"
};
import edit from './edit';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  example: {},
  edit
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map