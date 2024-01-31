/**
 * WordPress dependencies
 */
import { queryPaginationNext as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/comments-pagination-next",
  title: "Comments Next Page",
  category: "theme",
  parent: ["core/comments-pagination"],
  description: "Displays the next comment's page link.",
  textdomain: "default",
  attributes: {
    label: {
      type: "string"
    }
  },
  usesContext: ["postId", "comments/paginationArrow"],
  supports: {
    reusable: false,
    html: false,
    color: {
      gradients: true,
      text: false,
      __experimentalDefaultControls: {
        background: true
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