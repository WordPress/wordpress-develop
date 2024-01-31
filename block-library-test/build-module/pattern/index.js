/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/pattern",
  title: "Pattern placeholder",
  category: "theme",
  description: "Show a block pattern.",
  supports: {
    html: false,
    inserter: false,
    renaming: false
  },
  textdomain: "default",
  attributes: {
    slug: {
      type: "string"
    }
  }
};
import PatternEdit from './edit';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  edit: PatternEdit
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map