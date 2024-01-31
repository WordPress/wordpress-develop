/**
 * WordPress dependencies
 */
import { pageBreak as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/nextpage",
  title: "Page Break",
  category: "design",
  description: "Separate your content into a multi-page experience.",
  keywords: ["next page", "pagination"],
  parent: ["core/post-content"],
  textdomain: "default",
  supports: {
    customClassName: false,
    className: false,
    html: false
  },
  editorStyle: "wp-block-nextpage-editor"
};
import save from './save';
import transforms from './transforms';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  example: {},
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