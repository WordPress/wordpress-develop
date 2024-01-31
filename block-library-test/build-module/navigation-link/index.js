import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { _x } from '@wordpress/i18n';
import { customLink as linkIcon } from '@wordpress/icons';
import { InnerBlocks } from '@wordpress/block-editor';
import { addFilter } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/navigation-link",
  title: "Custom Link",
  category: "design",
  parent: ["core/navigation"],
  allowedBlocks: ["core/navigation-link", "core/navigation-submenu", "core/page-list"],
  description: "Add a page, link, or another item to your navigation.",
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
    isTopLevelLink: {
      type: "boolean"
    }
  },
  usesContext: ["textColor", "customTextColor", "backgroundColor", "customBackgroundColor", "overlayTextColor", "customOverlayTextColor", "overlayBackgroundColor", "customOverlayBackgroundColor", "fontSize", "customFontSize", "showSubmenuIcon", "maxNestingLevel", "style"],
  supports: {
    reusable: false,
    html: false,
    __experimentalSlashInserter: true,
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
    renaming: false
  },
  editorStyle: "wp-block-navigation-link-editor",
  style: "wp-block-navigation-link"
};
import edit from './edit';
import save from './save';
import { enhanceNavigationLinkVariations } from './hooks';
import transforms from './transforms';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon: linkIcon,
  __experimentalLabel: ({
    label
  }) => label,
  merge(leftAttributes, {
    label: rightLabel = ''
  }) {
    return {
      ...leftAttributes,
      label: leftAttributes.label + rightLabel
    };
  },
  edit,
  save,
  example: {
    attributes: {
      label: _x('Example Link', 'navigation link preview example'),
      url: 'https://example.com'
    }
  },
  deprecated: [{
    isEligible(attributes) {
      return attributes.nofollow;
    },
    attributes: {
      label: {
        type: 'string'
      },
      type: {
        type: 'string'
      },
      nofollow: {
        type: 'boolean'
      },
      description: {
        type: 'string'
      },
      id: {
        type: 'number'
      },
      opensInNewTab: {
        type: 'boolean',
        default: false
      },
      url: {
        type: 'string'
      }
    },
    migrate({
      nofollow,
      ...rest
    }) {
      return {
        rel: nofollow ? 'nofollow' : '',
        ...rest
      };
    },
    save() {
      return createElement(InnerBlocks.Content, null);
    }
  }],
  transforms
};
export const init = () => {
  addFilter('blocks.registerBlockType', 'core/navigation-link', enhanceNavigationLinkVariations);
  return initBlock({
    name,
    metadata,
    settings
  });
};
//# sourceMappingURL=index.js.map