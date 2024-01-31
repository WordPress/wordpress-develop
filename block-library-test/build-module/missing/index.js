/**
 * WordPress dependencies
 */
import { getBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/missing",
  title: "Unsupported",
  category: "text",
  description: "Your site doesn\u2019t include support for this block.",
  textdomain: "default",
  attributes: {
    originalName: {
      type: "string"
    },
    originalUndelimitedContent: {
      type: "string"
    },
    originalContent: {
      type: "string",
      source: "raw"
    }
  },
  supports: {
    className: false,
    customClassName: false,
    inserter: false,
    html: false,
    reusable: false
  }
};
import save from './save';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  name,
  __experimentalLabel(attributes, {
    context
  }) {
    if (context === 'accessibility') {
      const {
        originalName
      } = attributes;
      const originalBlockType = originalName ? getBlockType(originalName) : undefined;
      if (originalBlockType) {
        return originalBlockType.settings.title || originalName;
      }
      return '';
    }
  },
  edit,
  save
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map