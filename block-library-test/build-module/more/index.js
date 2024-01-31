/**
 * WordPress dependencies
 */
import { more as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/more",
  title: "More",
  category: "design",
  description: "Content before this block will be shown in the excerpt on your archives page.",
  keywords: ["read more"],
  textdomain: "default",
  attributes: {
    customText: {
      type: "string"
    },
    noTeaser: {
      type: "boolean",
      "default": false
    }
  },
  supports: {
    customClassName: false,
    className: false,
    html: false,
    multiple: false
  },
  editorStyle: "wp-block-more-editor"
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
  __experimentalLabel(attributes, {
    context
  }) {
    const customName = attributes?.metadata?.name;
    if (context === 'list-view' && customName) {
      return customName;
    }
    if (context === 'accessibility') {
      return attributes.customText;
    }
  },
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