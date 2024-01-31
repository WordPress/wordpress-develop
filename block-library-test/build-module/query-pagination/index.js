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
  name: "core/query-pagination",
  title: "Pagination",
  category: "theme",
  parent: ["core/query"],
  allowedBlocks: ["core/query-pagination-previous", "core/query-pagination-numbers", "core/query-pagination-next"],
  description: "Displays a paginated navigation to next/previous set of posts, when applicable.",
  textdomain: "default",
  attributes: {
    paginationArrow: {
      type: "string",
      "default": "none"
    },
    showLabel: {
      type: "boolean",
      "default": true
    }
  },
  usesContext: ["queryId", "query"],
  providesContext: {
    paginationArrow: "paginationArrow",
    showLabel: "showLabel"
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
  editorStyle: "wp-block-query-pagination-editor",
  style: "wp-block-query-pagination"
};
import edit from './edit';
import save from './save';
import deprecated from './deprecated';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  edit,
  save,
  deprecated
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map