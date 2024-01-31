/**
 * WordPress dependencies
 */
import { classic as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/freeform",
  title: "Classic",
  category: "text",
  description: "Use the classic WordPress editor.",
  textdomain: "default",
  attributes: {
    content: {
      type: "string",
      source: "raw"
    }
  },
  supports: {
    className: false,
    customClassName: false,
    reusable: false
  },
  editorStyle: "wp-block-freeform-editor"
};
import save from './save';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  edit,
  save
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map