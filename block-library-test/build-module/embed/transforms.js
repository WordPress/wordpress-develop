/**
 * WordPress dependencies
 */
import { createBlock } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/embed",
  title: "Embed",
  category: "embed",
  description: "Add a block that displays content pulled from other sites, like Twitter or YouTube.",
  textdomain: "default",
  attributes: {
    url: {
      type: "string",
      __experimentalRole: "content"
    },
    caption: {
      type: "rich-text",
      source: "rich-text",
      selector: "figcaption",
      __experimentalRole: "content"
    },
    type: {
      type: "string",
      __experimentalRole: "content"
    },
    providerNameSlug: {
      type: "string",
      __experimentalRole: "content"
    },
    allowResponsive: {
      type: "boolean",
      "default": true
    },
    responsive: {
      type: "boolean",
      "default": false,
      __experimentalRole: "content"
    },
    previewable: {
      type: "boolean",
      "default": true,
      __experimentalRole: "content"
    }
  },
  supports: {
    align: true,
    spacing: {
      margin: true
    }
  },
  editorStyle: "wp-block-embed-editor",
  style: "wp-block-embed"
};
const {
  name: EMBED_BLOCK
} = metadata;

/**
 * Default transforms for generic embeds.
 */
const transforms = {
  from: [{
    type: 'raw',
    isMatch: node => node.nodeName === 'P' && /^\s*(https?:\/\/\S+)\s*$/i.test(node.textContent) && node.textContent?.match(/https/gi)?.length === 1,
    transform: node => {
      return createBlock(EMBED_BLOCK, {
        url: node.textContent.trim()
      });
    }
  }],
  to: [{
    type: 'block',
    blocks: ['core/paragraph'],
    isMatch: ({
      url
    }) => !!url,
    transform: ({
      url,
      caption
    }) => {
      let value = `<a href="${url}">${url}</a>`;
      if (caption?.trim()) {
        value += `<br />${caption}`;
      }
      return createBlock('core/paragraph', {
        content: value
      });
    }
  }]
};
export default transforms;
//# sourceMappingURL=transforms.js.map