/**
 * WordPress dependencies
 */
import { calendar as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/calendar",
  title: "Calendar",
  category: "widgets",
  description: "A calendar of your site\u2019s posts.",
  keywords: ["posts", "archive"],
  textdomain: "default",
  attributes: {
    month: {
      type: "integer"
    },
    year: {
      type: "integer"
    }
  },
  supports: {
    align: true,
    color: {
      link: true,
      __experimentalSkipSerialization: ["text", "background"],
      __experimentalDefaultControls: {
        background: true,
        text: true
      },
      __experimentalSelector: "table, th"
    },
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontFamily: true,
      __experimentalFontWeight: true,
      __experimentalFontStyle: true,
      __experimentalTextTransform: true,
      __experimentalLetterSpacing: true,
      __experimentalDefaultControls: {
        fontSize: true
      }
    }
  },
  style: "wp-block-calendar"
};
import edit from './edit';
import transforms from './transforms';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  example: {},
  edit,
  transforms
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map