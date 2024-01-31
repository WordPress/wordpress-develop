/**
 * WordPress dependencies
 */
import { queryPaginationNumbers as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/comments-pagination-numbers",
  title: "Comments Page Numbers",
  category: "theme",
  parent: ["core/comments-pagination"],
  description: "Displays a list of page numbers for comments pagination.",
  textdomain: "default",
  usesContext: ["postId"],
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