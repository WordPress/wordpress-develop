/**
 * WordPress dependencies
 */
import { shortcode as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import edit from './edit';
import save from './save';
import transforms from './transforms';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/shortcode",
  title: "Shortcode",
  category: "widgets",
  description: "Insert additional custom elements with a WordPress shortcode.",
  textdomain: "default",
  attributes: {
    text: {
      type: "string",
      source: "raw"
    }
  },
  supports: {
    className: false,
    customClassName: false,
    html: false
  },
  editorStyle: "wp-block-shortcode-editor"
};
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  transforms,
  edit,
  save
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map