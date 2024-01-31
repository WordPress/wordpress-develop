/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import edit from './edit';
import save from './save';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/embed",
  title: "Embed",
  category: "embed",
  description: "Add a block that displays content pulled from other sites, like Twitter or YouTube.",
  textdomain: "default",
  attributes: {
    url: {
      type: "string",
      __experimentalRole: "content"
    },
    caption: {
      type: "rich-text",
      source: "rich-text",
      selector: "figcaption",
      __experimentalRole: "content"
    },
    type: {
      type: "string",
      __experimentalRole: "content"
    },
    providerNameSlug: {
      type: "string",
      __experimentalRole: "content"
    },
    allowResponsive: {
      type: "boolean",
      "default": true
    },
    responsive: {
      type: "boolean",
      "default": false,
      __experimentalRole: "content"
    },
    previewable: {
      type: "boolean",
      "default": true,
      __experimentalRole: "content"
    }
  },
  supports: {
    align: true,
    spacing: {
      margin: true
    }
  },
  editorStyle: "wp-block-embed-editor",
  style: "wp-block-embed"
};
import transforms from './transforms';
import variations from './variations';
import deprecated from './deprecated';
import { embedContentIcon } from './icons';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon: embedContentIcon,
  edit,
  save,
  transforms,
  variations,
  deprecated
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map