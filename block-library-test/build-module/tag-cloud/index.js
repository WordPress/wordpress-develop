/**
 * WordPress dependencies
 */
import { tag as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import transforms from './transforms';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/tag-cloud",
  title: "Tag Cloud",
  category: "widgets",
  description: "A cloud of your most used tags.",
  textdomain: "default",
  attributes: {
    numberOfTags: {
      type: "number",
      "default": 45,
      minimum: 1,
      maximum: 100
    },
    taxonomy: {
      type: "string",
      "default": "post_tag"
    },
    showTagCounts: {
      type: "boolean",
      "default": false
    },
    smallestFontSize: {
      type: "string",
      "default": "8pt"
    },
    largestFontSize: {
      type: "string",
      "default": "22pt"
    }
  },
  styles: [{
    name: "default",
    label: "Default",
    isDefault: true
  }, {
    name: "outline",
    label: "Outline"
  }],
  supports: {
    html: false,
    align: true,
    spacing: {
      margin: true,
      padding: true
    },
    typography: {
      lineHeight: true,
      __experimentalFontFamily: true,
      __experimentalFontWeight: true,
      __experimentalFontStyle: true,
      __experimentalTextTransform: true,
      __experimentalLetterSpacing: true
    }
  },
  editorStyle: "wp-block-tag-cloud-editor"
};
import edit from './edit';
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