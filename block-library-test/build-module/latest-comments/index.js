/**
 * WordPress dependencies
 */
import { comment as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/latest-comments",
  title: "Latest Comments",
  category: "widgets",
  description: "Display a list of your most recent comments.",
  keywords: ["recent comments"],
  textdomain: "default",
  attributes: {
    commentsToShow: {
      type: "number",
      "default": 5,
      minimum: 1,
      maximum: 100
    },
    displayAvatar: {
      type: "boolean",
      "default": true
    },
    displayDate: {
      type: "boolean",
      "default": true
    },
    displayExcerpt: {
      type: "boolean",
      "default": true
    }
  },
  supports: {
    align: true,
    html: false,
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
  },
  editorStyle: "wp-block-latest-comments-editor",
  style: "wp-block-latest-comments"
};
import edit from './edit';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  example: {},
  edit
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map