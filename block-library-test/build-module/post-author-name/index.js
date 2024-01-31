/**
 * WordPress dependencies
 */
import { postAuthor as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/post-author-name",
  title: "Author Name",
  category: "theme",
  description: "The author name.",
  textdomain: "default",
  attributes: {
    textAlign: {
      type: "string"
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
  usesContext: ["postType", "postId"],
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
import transforms from './transforms';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  transforms,
  edit
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map