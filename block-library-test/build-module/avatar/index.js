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
  name: "core/avatar",
  title: "Avatar",
  category: "theme",
  description: "Add a user\u2019s avatar.",
  textdomain: "default",
  attributes: {
    userId: {
      type: "number"
    },
    size: {
      type: "number",
      "default": 96
    },
    isLink: {
      type: "boolean",
      "default": false
    },
    linkTarget: {
      type: "string",
      "default": "_self"
    }
  },
  usesContext: ["postType", "postId", "commentId"],
  supports: {
    html: false,
    align: true,
    alignWide: false,
    spacing: {
      margin: true,
      padding: true,
      __experimentalDefaultControls: {
        margin: false,
        padding: false
      }
    },
    __experimentalBorder: {
      __experimentalSkipSerialization: true,
      radius: true,
      width: true,
      color: true,
      style: true,
      __experimentalDefaultControls: {
        radius: true
      }
    },
    color: {
      text: false,
      background: false,
      __experimentalDuotone: "img"
    }
  },
  selectors: {
    border: ".wp-block-avatar img"
  },
  editorStyle: "wp-block-avatar-editor",
  style: "wp-block-avatar"
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