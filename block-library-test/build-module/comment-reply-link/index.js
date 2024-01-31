/**
 * WordPress dependencies
 */
import { commentReplyLink as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/comment-reply-link",
  title: "Comment Reply Link",
  category: "theme",
  ancestor: ["core/comment-template"],
  description: "Displays a link to reply to a comment.",
  textdomain: "default",
  usesContext: ["commentId"],
  attributes: {
    textAlign: {
      type: "string"
    }
  },
  supports: {
    color: {
      gradients: true,
      link: true,
      text: false,
      __experimentalDefaultControls: {
        background: true,
        link: true
      }
    },
    spacing: {
      margin: true,
      padding: true
    },
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontFamily: true,
      __experimentalFontWeight: true,
      __experimentalFontStyle: true,
      __experimentalTextTransform: true,
      __experimentalTextDecoration: true,
      __experimentalLetterSpacing: true,
      __experimentalDefaultControls: {
        fontSize: true
      }
    },
    html: false
  }
};
import edit from './edit';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  edit,
  icon
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map