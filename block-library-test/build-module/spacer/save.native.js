import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { useBlockProps, getSpacingPresetCssVar } from '@wordpress/block-editor';
export default function save({
  attributes: {
    height,
    width
  }
}) {
  return createElement("div", {
    ...useBlockProps.save({
      style: {
        height: getSpacingPresetCssVar(height),
        width: getSpacingPresetCssVar(width)
      },
      'aria-hidden': true
    })
  });
}
//# sourceMappingURL=save.native.js.map