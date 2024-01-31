/**
 * WordPress dependencies
 */
import { rss as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/rss",
  title: "RSS",
  category: "widgets",
  description: "Display entries from any RSS or Atom feed.",
  keywords: ["atom", "feed"],
  textdomain: "default",
  attributes: {
    columns: {
      type: "number",
      "default": 2
    },
    blockLayout: {
      type: "string",
      "default": "list"
    },
    feedURL: {
      type: "string",
      "default": ""
    },
    itemsToShow: {
      type: "number",
      "default": 5
    },
    displayExcerpt: {
      type: "boolean",
      "default": false
    },
    displayAuthor: {
      type: "boolean",
      "default": false
    },
    displayDate: {
      type: "boolean",
      "default": false
    },
    excerptLength: {
      type: "number",
      "default": 55
    }
  },
  supports: {
    align: true,
    html: false
  },
  editorStyle: "wp-block-rss-editor",
  style: "wp-block-rss"
};
import edit from './edit';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  example: {
    attributes: {
      feedURL: 'https://wordpress.org'
    }
  },
  edit
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map