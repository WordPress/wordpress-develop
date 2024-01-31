import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
const Save = ({
  attributes
}) => {
  const blockProps = useBlockProps.save();
  const {
    submissionMethod
  } = attributes;
  return createElement("form", {
    ...blockProps,
    className: "wp-block-form",
    encType: submissionMethod === 'email' ? 'text/plain' : null
  }, createElement(InnerBlocks.Content, null));
};
export default Save;
//# sourceMappingURL=save.js.map