/**
 * WordPress dependencies
 */
import { page, addSubmenu } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/navigation-submenu",
  title: "Submenu",
  category: "design",
  parent: ["core/navigation"],
  description: "Add a submenu to your navigation.",
  textdomain: "default",
  attributes: {
    label: {
      type: "string"
    },
    type: {
      type: "string"
    },
    description: {
      type: "string"
    },
    rel: {
      type: "string"
    },
    id: {
      type: "number"
    },
    opensInNewTab: {
      type: "boolean",
      "default": false
    },
    url: {
      type: "string"
    },
    title: {
      type: "string"
    },
    kind: {
      type: "string"
    },
    isTopLevelItem: {
      type: "boolean"
    }
  },
  usesContext: ["textColor", "customTextColor", "backgroundColor", "customBackgroundColor", "overlayTextColor", "customOverlayTextColor", "overlayBackgroundColor", "customOverlayBackgroundColor", "fontSize", "customFontSize", "showSubmenuIcon", "maxNestingLevel", "openSubmenusOnClick", "style"],
  supports: {
    reusable: false,
    html: false
  },
  editorStyle: "wp-block-navigation-submenu-editor",
  style: "wp-block-navigation-submenu"
};
import edit from './edit';
import save from './save';
import transforms from './transforms';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon: ({
    context
  }) => {
    if (context === 'list-view') {
      return page;
    }
    return addSubmenu;
  },
  __experimentalLabel(attributes, {
    context
  }) {
    const {
      label
    } = attributes;
    const customName = attributes?.metadata?.name;

    // In the list view, use the block's menu label as the label.
    // If the menu label is empty, fall back to the default label.
    if (context === 'list-view' && (customName || label)) {
      return attributes?.metadata?.name || label;
    }
    return label;
  },
  edit,
  save,
  transforms
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map