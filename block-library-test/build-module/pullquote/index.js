/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { pullquote as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import deprecated from './deprecated';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/pullquote",
  title: "Pullquote",
  category: "text",
  description: "Give special visual emphasis to a quote from your text.",
  textdomain: "default",
  attributes: {
    value: {
      type: "rich-text",
      source: "rich-text",
      selector: "p",
      __experimentalRole: "content"
    },
    citation: {
      type: "rich-text",
      source: "rich-text",
      selector: "cite",
      __experimentalRole: "content"
    },
    textAlign: {
      type: "string"
    }
  },
  supports: {
    anchor: true,
    align: ["left", "right", "wide", "full"],
    color: {
      gradients: true,
      background: true,
      link: true,
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
      __experimentalFontWeight: true,
      __experimentalFontStyle: true,
      __experimentalTextTransform: true,
      __experimentalTextDecoration: true,
      __experimentalLetterSpacing: true,
      __experimentalDefaultControls: {
        fontSize: true
      }
    },
    __experimentalBorder: {
      color: true,
      radius: true,
      style: true,
      width: true,
      __experimentalDefaultControls: {
        color: true,
        radius: true,
        style: true,
        width: true
      }
    },
    __experimentalStyle: {
      typography: {
        fontSize: "1.5em",
        lineHeight: "1.6"
      }
    }
  },
  editorStyle: "wp-block-pullquote-editor",
  style: "wp-block-pullquote"
};
import save from './save';
import transforms from './transforms';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  example: {
    attributes: {
      value:
      // translators: Quote serving as example for the Pullquote block. Attributed to Matt Mullenweg.
      __('One of the hardest things to do in technology is disrupt yourself.'),
      citation: __('Matt Mullenweg')
    }
  },
  transforms,
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