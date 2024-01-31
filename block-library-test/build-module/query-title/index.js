/**
 * WordPress dependencies
 */
import { title as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/query-title",
  title: "Query Title",
  category: "theme",
  description: "Display the query title.",
  textdomain: "default",
  attributes: {
    type: {
      type: "string"
    },
    textAlign: {
      type: "string"
    },
    level: {
      type: "number",
      "default": 1
    },
    showPrefix: {
      type: "boolean",
      "default": true
    },
    showSearchTerm: {
      type: "boolean",
      "default": true
    }
  },
  supports: {
    align: ["wide", "full"],
    html: false,
    color: {
      gradients: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
      }
    },
    spacing: {
      margin: true,
      padding: true
    },
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontFamily: true,
      __experimentalFontStyle: true,
      __experimentalFontWeight: true,
      __experimentalLetterSpacing: true,
      __experimentalTextTransform: true,
      __experimentalTextDecoration: true,
      __experimentalDefaultControls: {
        fontSize: true
      }
    }
  },
  style: "wp-block-query-title"
};
import edit from './edit';
import variations from './variations';
import deprecated from './deprecated';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  edit,
  variations,
  deprecated
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map