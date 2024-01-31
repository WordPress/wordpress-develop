/**
 * WordPress dependencies
 */
import { commentEditLink as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/comment-edit-link",
  title: "Comment Edit Link",
  category: "theme",
  ancestor: ["core/comment-template"],
  description: "Displays a link to edit the comment in the WordPress Dashboard. This link is only visible to users with the edit comment capability.",
  textdomain: "default",
  usesContext: ["commentId"],
  attributes: {
    linkTarget: {
      type: "string",
      "default": "_self"
    },
    textAlign: {
      type: "string"
    }
  },
  supports: {
    html: false,
    color: {
      link: true,
      gradients: true,
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