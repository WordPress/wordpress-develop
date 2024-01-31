/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { quote as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import deprecated from './deprecated';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/quote",
  title: "Quote",
  category: "text",
  description: "Give quoted text visual emphasis. \"In quoting others, we cite ourselves.\" \u2014 Julio Cort\xE1zar",
  keywords: ["blockquote", "cite"],
  textdomain: "default",
  attributes: {
    value: {
      type: "string",
      source: "html",
      selector: "blockquote",
      multiline: "p",
      "default": "",
      __experimentalRole: "content"
    },
    citation: {
      type: "rich-text",
      source: "rich-text",
      selector: "cite",
      __experimentalRole: "content"
    },
    align: {
      type: "string"
    }
  },
  supports: {
    anchor: true,
    html: false,
    __experimentalOnEnter: true,
    __experimentalOnMerge: true,
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
    color: {
      gradients: true,
      heading: true,
      link: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
      }
    },
    layout: {
      allowEditing: false
    },
    spacing: {
      blockGap: true
    }
  },
  styles: [{
    name: "default",
    label: "Default",
    isDefault: true
  }, {
    name: "plain",
    label: "Plain"
  }],
  editorStyle: "wp-block-quote-editor",
  style: "wp-block-quote"
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
      citation: 'Julio Cortázar'
    },
    innerBlocks: [{
      name: 'core/paragraph',
      attributes: {
        content: __('In quoting others, we cite ourselves.')
      }
    }]
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