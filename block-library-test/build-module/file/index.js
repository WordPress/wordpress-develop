/**
 * WordPress dependencies
 */
import { _x } from '@wordpress/i18n';
import { file as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import deprecated from './deprecated';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/file",
  title: "File",
  category: "media",
  description: "Add a link to a downloadable file.",
  keywords: ["document", "pdf", "download"],
  textdomain: "default",
  attributes: {
    id: {
      type: "number"
    },
    href: {
      type: "string"
    },
    fileId: {
      type: "string",
      source: "attribute",
      selector: "a:not([download])",
      attribute: "id"
    },
    fileName: {
      type: "rich-text",
      source: "rich-text",
      selector: "a:not([download])"
    },
    textLinkHref: {
      type: "string",
      source: "attribute",
      selector: "a:not([download])",
      attribute: "href"
    },
    textLinkTarget: {
      type: "string",
      source: "attribute",
      selector: "a:not([download])",
      attribute: "target"
    },
    showDownloadButton: {
      type: "boolean",
      "default": true
    },
    downloadButtonText: {
      type: "rich-text",
      source: "rich-text",
      selector: "a[download]"
    },
    displayPreview: {
      type: "boolean"
    },
    previewHeight: {
      type: "number",
      "default": 600
    }
  },
  supports: {
    anchor: true,
    align: true,
    spacing: {
      margin: true,
      padding: true
    },
    color: {
      gradients: true,
      link: true,
      text: false,
      __experimentalDefaultControls: {
        background: true,
        link: true
      }
    },
    interactivity: true
  },
  editorStyle: "wp-block-file-editor",
  style: "wp-block-file"
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
      href: 'https://upload.wikimedia.org/wikipedia/commons/d/dd/Armstrong_Small_Step.ogg',
      fileName: _x('Armstrong_Small_Step', 'Name of the file')
    }
  },
  transforms,
  deprecated,
  edit,
  save
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map