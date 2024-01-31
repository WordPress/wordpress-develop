/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { navigation as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/navigation",
  title: "Navigation",
  category: "theme",
  allowedBlocks: ["core/navigation-link", "core/search", "core/social-links", "core/page-list", "core/spacer", "core/home-link", "core/site-title", "core/site-logo", "core/navigation-submenu", "core/loginout", "core/buttons"],
  description: "A collection of blocks that allow visitors to get around your site.",
  keywords: ["menu", "navigation", "links"],
  textdomain: "default",
  attributes: {
    ref: {
      type: "number"
    },
    textColor: {
      type: "string"
    },
    customTextColor: {
      type: "string"
    },
    rgbTextColor: {
      type: "string"
    },
    backgroundColor: {
      type: "string"
    },
    customBackgroundColor: {
      type: "string"
    },
    rgbBackgroundColor: {
      type: "string"
    },
    showSubmenuIcon: {
      type: "boolean",
      "default": true
    },
    openSubmenusOnClick: {
      type: "boolean",
      "default": false
    },
    overlayMenu: {
      type: "string",
      "default": "mobile"
    },
    icon: {
      type: "string",
      "default": "handle"
    },
    hasIcon: {
      type: "boolean",
      "default": true
    },
    __unstableLocation: {
      type: "string"
    },
    overlayBackgroundColor: {
      type: "string"
    },
    customOverlayBackgroundColor: {
      type: "string"
    },
    overlayTextColor: {
      type: "string"
    },
    customOverlayTextColor: {
      type: "string"
    },
    maxNestingLevel: {
      type: "number",
      "default": 5
    },
    templateLock: {
      type: ["string", "boolean"],
      "enum": ["all", "insert", "contentOnly", false]
    }
  },
  providesContext: {
    textColor: "textColor",
    customTextColor: "customTextColor",
    backgroundColor: "backgroundColor",
    customBackgroundColor: "customBackgroundColor",
    overlayTextColor: "overlayTextColor",
    customOverlayTextColor: "customOverlayTextColor",
    overlayBackgroundColor: "overlayBackgroundColor",
    customOverlayBackgroundColor: "customOverlayBackgroundColor",
    fontSize: "fontSize",
    customFontSize: "customFontSize",
    showSubmenuIcon: "showSubmenuIcon",
    openSubmenusOnClick: "openSubmenusOnClick",
    style: "style",
    maxNestingLevel: "maxNestingLevel"
  },
  supports: {
    align: ["wide", "full"],
    ariaLabel: true,
    html: false,
    inserter: true,
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontStyle: true,
      __experimentalFontWeight: true,
      __experimentalTextTransform: true,
      __experimentalFontFamily: true,
      __experimentalLetterSpacing: true,
      __experimentalTextDecoration: true,
      __experimentalSkipSerialization: ["textDecoration"],
      __experimentalDefaultControls: {
        fontSize: true
      }
    },
    spacing: {
      blockGap: true,
      units: ["px", "em", "rem", "vh", "vw"],
      __experimentalDefaultControls: {
        blockGap: true
      }
    },
    layout: {
      allowSwitching: false,
      allowInheriting: false,
      allowVerticalAlignment: false,
      allowSizingOnChildren: true,
      "default": {
        type: "flex"
      }
    },
    __experimentalStyle: {
      elements: {
        link: {
          color: {
            text: "inherit"
          }
        }
      }
    },
    interactivity: true,
    renaming: false
  },
  editorStyle: "wp-block-navigation-editor",
  style: "wp-block-navigation"
};
import edit from './edit';
import save from './save';
import deprecated from './deprecated';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  example: {
    attributes: {
      overlayMenu: 'never'
    },
    innerBlocks: [{
      name: 'core/navigation-link',
      attributes: {
        // translators: 'Home' as in a website's home page.
        label: __('Home'),
        url: 'https://make.wordpress.org/'
      }
    }, {
      name: 'core/navigation-link',
      attributes: {
        // translators: 'About' as in a website's about page.
        label: __('About'),
        url: 'https://make.wordpress.org/'
      }
    }, {
      name: 'core/navigation-link',
      attributes: {
        // translators: 'Contact' as in a website's contact page.
        label: __('Contact'),
        url: 'https://make.wordpress.org/'
      }
    }]
  },
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