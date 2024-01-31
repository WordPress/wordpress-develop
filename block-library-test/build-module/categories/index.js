/**
 * WordPress dependencies
 */
import { category as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/categories",
  title: "Categories List",
  category: "widgets",
  description: "Display a list of all categories.",
  textdomain: "default",
  attributes: {
    displayAsDropdown: {
      type: "boolean",
      "default": false
    },
    showHierarchy: {
      type: "boolean",
      "default": false
    },
    showPostCounts: {
      type: "boolean",
      "default": false
    },
    showOnlyTopLevel: {
      type: "boolean",
      "default": false
    },
    showEmpty: {
      type: "boolean",
      "default": false
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
  editorStyle: "wp-block-categories-editor",
  style: "wp-block-categories"
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