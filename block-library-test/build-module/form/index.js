/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  __experimental: true,
  name: "core/form",
  title: "Form",
  category: "common",
  allowedBlocks: ["core/paragraph", "core/heading", "core/form-input", "core/form-submit-button", "core/form-submission-notification", "core/group", "core/columns"],
  description: "A form.",
  keywords: ["container", "wrapper", "row", "section"],
  textdomain: "default",
  icon: "feedback",
  attributes: {
    submissionMethod: {
      type: "string",
      "default": "email"
    },
    method: {
      type: "string",
      "default": "post"
    },
    action: {
      type: "string"
    },
    email: {
      type: "string"
    }
  },
  supports: {
    anchor: true,
    className: false,
    color: {
      gradients: true,
      link: true,
      __experimentalDefaultControls: {
        background: true,
        text: true,
        link: true
      }
    },
    spacing: {
      margin: true,
      padding: true
    },
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontFamily: true,
      __experimentalTextDecoration: true,
      __experimentalFontStyle: true,
      __experimentalFontWeight: true,
      __experimentalLetterSpacing: true,
      __experimentalTextTransform: true,
      __experimentalDefaultControls: {
        fontSize: true
      }
    },
    __experimentalSelector: "form"
  },
  viewScript: "file:./view.min.js"
};
import save from './save';
import variations from './variations';

/**
 * WordPress dependencies
 */
import { addFilter } from '@wordpress/hooks';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  edit,
  save,
  variations
};
export const init = () => {
  // Prevent adding forms inside forms.
  const DISALLOWED_PARENTS = ['core/form'];
  addFilter('blockEditor.__unstableCanInsertBlockType', 'core/block-library/preventInsertingFormIntoAnotherForm', (canInsert, blockType, rootClientId, {
    getBlock,
    getBlockParentsByBlockName
  }) => {
    if (blockType.name !== 'core/form') {
      return canInsert;
    }
    for (const disallowedParentType of DISALLOWED_PARENTS) {
      const hasDisallowedParent = getBlock(rootClientId)?.name === disallowedParentType || getBlockParentsByBlockName(rootClientId, disallowedParentType).length;
      if (hasDisallowedParent) {
        return false;
      }
    }
    return true;
  });
  return initBlock({
    name,
    metadata,
    settings
  });
};
//# sourceMappingURL=index.js.map