/**
 * WordPress dependencies
 */
import { symbol as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/block",
  title: "Pattern",
  category: "reusable",
  description: "Reuse this design across your site.",
  keywords: ["reusable"],
  textdomain: "default",
  attributes: {
    ref: {
      type: "number"
    },
    overrides: {
      type: "object"
    }
  },
  supports: {
    customClassName: false,
    html: false,
    inserter: false,
    renaming: false
  }
};
import edit from './edit';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  edit,
  icon
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map