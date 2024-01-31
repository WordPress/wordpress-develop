/**
 * WordPress dependencies
 */
import { queryPagination as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/comments-pagination",
  title: "Comments Pagination",
  category: "theme",
  parent: ["core/comments"],
  allowedBlocks: ["core/comments-pagination-previous", "core/comments-pagination-numbers", "core/comments-pagination-next"],
  description: "Displays a paginated navigation to next/previous set of comments, when applicable.",
  textdomain: "default",
  attributes: {
    paginationArrow: {
      type: "string",
      "default": "none"
    }
  },
  providesContext: {
    "comments/paginationArrow": "paginationArrow"
  },
  supports: {
    align: true,
    reusable: false,
    html: false,
    color: {
      gradients: true,
      link: true,
      __experimentalDefaultControls: {
        background: true,
        text: true,
        link: true
      }
    },
    layout: {
      allowSwitching: false,
      allowInheriting: false,
      "default": {
        type: "flex"
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
  },
  editorStyle: "wp-block-comments-pagination-editor",
  style: "wp-block-comments-pagination"
};
import edit from './edit';
import save from './save';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  edit,
  save
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map