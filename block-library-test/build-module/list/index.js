/**
 * WordPress dependencies
 */
import { list as icon } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import deprecated from './deprecated';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/list",
  title: "List",
  category: "text",
  allowedBlocks: ["core/list-item"],
  description: "Create a bulleted or numbered list.",
  keywords: ["bullet list", "ordered list", "numbered list"],
  textdomain: "default",
  attributes: {
    ordered: {
      type: "boolean",
      "default": false,
      __experimentalRole: "content"
    },
    values: {
      type: "string",
      source: "html",
      selector: "ol,ul",
      multiline: "li",
      __unstableMultilineWrapperTags: ["ol", "ul"],
      "default": "",
      __experimentalRole: "content"
    },
    type: {
      type: "string"
    },
    start: {
      type: "number"
    },
    reversed: {
      type: "boolean"
    },
    placeholder: {
      type: "string"
    }
  },
  supports: {
    anchor: true,
    className: false,
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
      link: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
      }
    },
    spacing: {
      margin: true,
      padding: true,
      __experimentalDefaultControls: {
        margin: false,
        padding: false
      }
    },
    __unstablePasteTextInline: true,
    __experimentalSelector: "ol,ul",
    __experimentalOnMerge: true,
    __experimentalSlashInserter: true
  },
  editorStyle: "wp-block-list-editor",
  style: "wp-block-list"
};
import save from './save';
import transforms from './transforms';
const {
  name
} = metadata;
export { metadata, name };
const settings = {
  icon,
  example: {
    innerBlocks: [{
      name: 'core/list-item',
      attributes: {
        content: __('Alice.')
      }
    }, {
      name: 'core/list-item',
      attributes: {
        content: __('The White Rabbit.')
      }
    }, {
      name: 'core/list-item',
      attributes: {
        content: __('The Cheshire Cat.')
      }
    }, {
      name: 'core/list-item',
      attributes: {
        content: __('The Mad Hatter.')
      }
    }, {
      name: 'core/list-item',
      attributes: {
        content: __('The Queen of Hearts.')
      }
    }]
  },
  transforms,
  edit,
  save,
  deprecated
};
export { settings };
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map