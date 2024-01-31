/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  __experimental: true,
  name: "core/form-submit-button",
  title: "Form Submit Button",
  category: "common",
  icon: "button",
  ancestor: ["core/form"],
  allowedBlocks: ["core/buttons", "core/button"],
  description: "A submission button for forms.",
  keywords: ["submit", "button", "form"],
  textdomain: "default",
  style: ["wp-block-form-submit-button"]
};
import save from './save';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  edit,
  save
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map