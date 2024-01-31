/**
 * WordPress dependencies
 */
import { share as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import deprecated from './deprecated';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/social-links",
  title: "Social Icons",
  category: "widgets",
  allowedBlocks: ["core/social-link"],
  description: "Display icons linking to your social media profiles or sites.",
  keywords: ["links"],
  textdomain: "default",
  attributes: {
    iconColor: {
      type: "string"
    },
    customIconColor: {
      type: "string"
    },
    iconColorValue: {
      type: "string"
    },
    iconBackgroundColor: {
      type: "string"
    },
    customIconBackgroundColor: {
      type: "string"
    },
    iconBackgroundColorValue: {
      type: "string"
    },
    openInNewTab: {
      type: "boolean",
      "default": false
    },
    showLabels: {
      type: "boolean",
      "default": false
    },
    size: {
      type: "string"
    }
  },
  providesContext: {
    openInNewTab: "openInNewTab",
    showLabels: "showLabels",
    iconColor: "iconColor",
    iconColorValue: "iconColorValue",
    iconBackgroundColor: "iconBackgroundColor",
    iconBackgroundColorValue: "iconBackgroundColorValue"
  },
  supports: {
    align: ["left", "center", "right"],
    anchor: true,
    __experimentalExposeControlsToChildren: true,
    layout: {
      allowSwitching: false,
      allowInheriting: false,
      allowVerticalAlignment: false,
      "default": {
        type: "flex"
      }
    },
    color: {
      enableContrastChecker: false,
      background: true,
      gradients: true,
      text: false,
      __experimentalDefaultControls: {
        background: false
      }
    },
    spacing: {
      blockGap: ["horizontal", "vertical"],
      margin: true,
      padding: true,
      units: ["px", "em", "rem", "vh", "vw"],
      __experimentalDefaultControls: {
        blockGap: true,
        margin: true,
        padding: false
      }
    }
  },
  styles: [{
    name: "default",
    label: "Default",
    isDefault: true
  }, {
    name: "logos-only",
    label: "Logos Only"
  }, {
    name: "pill-shape",
    label: "Pill Shape"
  }],
  editorStyle: "wp-block-social-links-editor",
  style: "wp-block-social-links"
};
import save from './save';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  example: {
    innerBlocks: [{
      name: 'core/social-link',
      attributes: {
        service: 'wordpress',
        url: 'https://wordpress.org'
      }
    }, {
      name: 'core/social-link',
      attributes: {
        service: 'facebook',
        url: 'https://www.facebook.com/WordPress/'
      }
    }, {
      name: 'core/social-link',
      attributes: {
        service: 'twitter',
        url: 'https://twitter.com/WordPress'
      }
    }]
  },
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