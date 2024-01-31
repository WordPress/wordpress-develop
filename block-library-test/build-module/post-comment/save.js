import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { useInnerBlocksProps, useBlockProps } from '@wordpress/block-editor';
export default function save() {
  const blockProps = useBlockProps.save();
  const innerBlocksProps = useInnerBlocksProps.save(blockProps);
  return createElement("div", {
    ...innerBlocksProps
  });
}
//# sourceMappingURL=save.js.map