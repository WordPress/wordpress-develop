/**
 * WordPress dependencies
 */
import { commentAuthorAvatar as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  __experimental: "fse",
  name: "core/comment-author-avatar",
  title: "Comment Author Avatar (deprecated)",
  category: "theme",
  ancestor: ["core/comment-template"],
  description: "This block is deprecated. Please use the Avatar block instead.",
  textdomain: "default",
  attributes: {
    width: {
      type: "number",
      "default": 96
    },
    height: {
      type: "number",
      "default": 96
    }
  },
  usesContext: ["commentId"],
  supports: {
    html: false,
    inserter: false,
    __experimentalBorder: {
      radius: true,
      width: true,
      color: true,
      style: true
    },
    color: {
      background: true,
      text: false,
      __experimentalDefaultControls: {
        background: true
      }
    },
    spacing: {
      __experimentalSkipSerialization: true,
      margin: true,
      padding: true
    }
  }
};
import edit from './edit';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  edit
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map