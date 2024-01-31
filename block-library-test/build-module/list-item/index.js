/**
 * WordPress dependencies
 */
import { listItem as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/list-item",
  title: "List item",
  category: "text",
  parent: ["core/list"],
  allowedBlocks: ["core/list"],
  description: "Create a list item.",
  textdomain: "default",
  attributes: {
    placeholder: {
      type: "string"
    },
    content: {
      type: "rich-text",
      source: "rich-text",
      selector: "li",
      __experimentalRole: "content"
    }
  },
  supports: {
    className: false,
    __experimentalSelector: "li",
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
  }
};
import edit from './edit';
import save from './save';
import transforms from './transforms';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  edit,
  save,
  merge(attributes, attributesToMerge) {
    return {
      ...attributes,
      content: attributes.content + attributesToMerge.content
    };
  },
  transforms
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map