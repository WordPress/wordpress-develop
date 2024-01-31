/**
 * WordPress dependencies
 */
import { share as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/social-link",
  title: "Social Icon",
  category: "widgets",
  parent: ["core/social-links"],
  description: "Display an icon linking to a social media profile or site.",
  textdomain: "default",
  attributes: {
    url: {
      type: "string"
    },
    service: {
      type: "string"
    },
    label: {
      type: "string"
    },
    rel: {
      type: "string"
    }
  },
  usesContext: ["openInNewTab", "showLabels", "iconColor", "iconColorValue", "iconBackgroundColor", "iconBackgroundColorValue"],
  supports: {
    reusable: false,
    html: false
  },
  editorStyle: "wp-block-social-link-editor"
};
import variations from './variations';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  edit,
  variations
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map