/**
 * WordPress dependencies
 */
import { group as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  __experimental: true,
  name: "core/form-submission-notification",
  title: "Form Submission Notification",
  category: "common",
  ancestor: ["core/form"],
  description: "Provide a notification message after the form has been submitted.",
  keywords: ["form", "feedback", "notification", "message"],
  textdomain: "default",
  icon: "feedback",
  attributes: {
    type: {
      type: "string",
      "default": "success"
    }
  }
};
import save from './save';
import variations from './variations';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  edit,
  save,
  variations
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map