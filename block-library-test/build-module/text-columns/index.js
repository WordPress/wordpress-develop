/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/text-columns",
  title: "Text Columns (deprecated)",
  icon: "columns",
  category: "design",
  description: "This block is deprecated. Please use the Columns block instead.",
  textdomain: "default",
  attributes: {
    content: {
      type: "array",
      source: "query",
      selector: "p",
      query: {
        children: {
          type: "string",
          source: "html"
        }
      },
      "default": [{}, {}]
    },
    columns: {
      type: "number",
      "default": 2
    },
    width: {
      type: "string"
    }
  },
  supports: {
    inserter: false
  },
  editorStyle: "wp-block-text-columns-editor",
  style: "wp-block-text-columns"
};
import save from './save';
import transforms from './transforms';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  transforms,
  getEditWrapperProps(attributes) {
    const {
      width
    } = attributes;
    if ('wide' === width || 'full' === width) {
      return {
        'data-align': width
      };
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