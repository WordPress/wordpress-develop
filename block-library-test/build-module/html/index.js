/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { html as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/html",
  title: "Custom HTML",
  category: "widgets",
  description: "Add custom HTML code and preview it as you edit.",
  keywords: ["embed"],
  textdomain: "default",
  attributes: {
    content: {
      type: "string",
      source: "raw"
    }
  },
  supports: {
    customClassName: false,
    className: false,
    html: false
  },
  editorStyle: "wp-block-html-editor"
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
      content: '<marquee>' + __('Welcome to the wonderful world of blocks…') + '</marquee>'
    }
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