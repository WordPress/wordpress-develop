/**
 * WordPress dependencies
 */
import { heading as icon } from '@wordpress/icons';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import deprecated from './deprecated';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/heading",
  title: "Heading",
  category: "text",
  description: "Introduce new sections and organize content to help visitors (and search engines) understand the structure of your content.",
  keywords: ["title", "subtitle"],
  textdomain: "default",
  usesContext: ["pattern/overrides"],
  attributes: {
    textAlign: {
      type: "string"
    },
    content: {
      type: "rich-text",
      source: "rich-text",
      selector: "h1,h2,h3,h4,h5,h6",
      __experimentalRole: "content"
    },
    level: {
      type: "number",
      "default": 2
    },
    placeholder: {
      type: "string"
    }
  },
  supports: {
    align: ["wide", "full"],
    anchor: true,
    className: true,
    color: {
      gradients: true,
      link: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
      }
    },
    spacing: {
      margin: true,
      padding: true,
      __experimentalDefaultControls: {
        margin: false,
        padding: false
      }
    },
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontFamily: true,
      __experimentalFontStyle: true,
      __experimentalFontWeight: true,
      __experimentalLetterSpacing: true,
      __experimentalTextTransform: true,
      __experimentalTextDecoration: true,
      __experimentalWritingMode: true,
      __experimentalDefaultControls: {
        fontSize: true
      }
    },
    __unstablePasteTextInline: true,
    __experimentalSlashInserter: true
  },
  editorStyle: "wp-block-heading-editor",
  style: "wp-block-heading"
};
import save from './save';
import transforms from './transforms';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  example: {
    attributes: {
      content: __('Code is Poetry'),
      level: 2
    }
  },
  __experimentalLabel(attributes, {
    context
  }) {
    const {
      content,
      level
    } = attributes;
    const customName = attributes?.metadata?.name;

    // In the list view, use the block's content as the label.
    // If the content is empty, fall back to the default label.
    if (context === 'list-view' && (customName || content)) {
      return attributes?.metadata?.name || content;
    }
    if (context === 'accessibility') {
      return !content || content.length === 0 ? sprintf( /* translators: accessibility text. %s: heading level. */
      __('Level %s. Empty.'), level) : sprintf( /* translators: accessibility text. 1: heading level. 2: heading content. */
      __('Level %1$s. %2$s'), level, content);
    }
  },
  transforms,
  deprecated,
  merge(attributes, attributesToMerge) {
    return {
      content: (attributes.content || '') + (attributesToMerge.content || '')
    };
  },
  edit,
  save
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map