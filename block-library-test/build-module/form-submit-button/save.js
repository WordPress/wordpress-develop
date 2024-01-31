import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
const Save = () => {
  const blockProps = useBlockProps.save();
  return createElement("div", {
    className: "wp-block-form-submit-wrapper",
    ...blockProps
  }, createElement(InnerBlocks.Content, null));
};
export default Save;
//# sourceMappingURL=save.js.map