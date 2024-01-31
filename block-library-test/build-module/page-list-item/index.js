/**
 * WordPress dependencies
 */
import { page as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/page-list-item",
  title: "Page List Item",
  category: "widgets",
  parent: ["core/page-list"],
  description: "Displays a page inside a list of all pages.",
  keywords: ["page", "menu", "navigation"],
  textdomain: "default",
  attributes: {
    id: {
      type: "number"
    },
    label: {
      type: "string"
    },
    title: {
      type: "string"
    },
    link: {
      type: "string"
    },
    hasChildren: {
      type: "boolean"
    }
  },
  usesContext: ["textColor", "customTextColor", "backgroundColor", "customBackgroundColor", "overlayTextColor", "customOverlayTextColor", "overlayBackgroundColor", "customOverlayBackgroundColor", "fontSize", "customFontSize", "showSubmenuIcon", "style", "openSubmenusOnClick"],
  supports: {
    reusable: false,
    html: false,
    lock: false,
    inserter: false,
    __experimentalToolbar: false
  },
  editorStyle: "wp-block-page-list-editor",
  style: "wp-block-page-list"
};
import edit from './edit.js';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  __experimentalLabel: ({
    label
  }) => label,
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