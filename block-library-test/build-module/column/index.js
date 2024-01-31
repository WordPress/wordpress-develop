/**
 * WordPress dependencies
 */
import { column as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import deprecated from './deprecated';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/column",
  title: "Column",
  category: "design",
  parent: ["core/columns"],
  description: "A single column within a columns block.",
  textdomain: "default",
  attributes: {
    verticalAlignment: {
      type: "string"
    },
    width: {
      type: "string"
    },
    allowedBlocks: {
      type: "array"
    },
    templateLock: {
      type: ["string", "boolean"],
      "enum": ["all", "insert", "contentOnly", false]
    }
  },
  supports: {
    __experimentalOnEnter: true,
    anchor: true,
    reusable: false,
    html: false,
    color: {
      gradients: true,
      heading: true,
      button: true,
      link: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
      }
    },
    spacing: {
      blockGap: true,
      padding: true,
      __experimentalDefaultControls: {
        padding: true,
        blockGap: true
      }
    },
    __experimentalBorder: {
      color: true,
      style: true,
      width: true,
      __experimentalDefaultControls: {
        color: true,
        style: true,
        width: true
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
    layout: true
  }
};
import save from './save';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
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