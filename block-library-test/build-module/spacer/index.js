/**
 * WordPress dependencies
 */
import { resizeCornerNE as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import deprecated from './deprecated';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/spacer",
  title: "Spacer",
  category: "design",
  description: "Add white space between blocks and customize its height.",
  textdomain: "default",
  attributes: {
    height: {
      type: "string",
      "default": "100px"
    },
    width: {
      type: "string"
    }
  },
  usesContext: ["orientation"],
  supports: {
    anchor: true,
    spacing: {
      margin: ["top", "bottom"],
      __experimentalDefaultControls: {
        margin: true
      }
    }
  },
  editorStyle: "wp-block-spacer-editor",
  style: "wp-block-spacer"
};
import save from './save';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
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