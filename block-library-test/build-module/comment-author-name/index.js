/**
 * WordPress dependencies
 */
import { commentAuthorName as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/comment-author-name",
  title: "Comment Author Name",
  category: "theme",
  ancestor: ["core/comment-template"],
  description: "Displays the name of the author of the comment.",
  textdomain: "default",
  attributes: {
    isLink: {
      type: "boolean",
      "default": true
    },
    linkTarget: {
      type: "string",
      "default": "_self"
    },
    textAlign: {
      type: "string"
    }
  },
  usesContext: ["commentId"],
  supports: {
    html: false,
    spacing: {
      margin: true,
      padding: true
    },
    color: {
      gradients: true,
      link: true,
      __experimentalDefaultControls: {
        background: true,
        text: true,
        link: true
      }
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
import deprecated from './deprecated';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  edit,
  deprecated
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map