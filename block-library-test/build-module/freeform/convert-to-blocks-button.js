import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { ToolbarButton } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { rawHandler, serialize } from '@wordpress/blocks';
import { store as blockEditorStore } from '@wordpress/block-editor';
const ConvertToBlocksButton = ({
  clientId
}) => {
  const {
    replaceBlocks
  } = useDispatch(blockEditorStore);
  const block = useSelect(select => {
    return select(blockEditorStore).getBlock(clientId);
  }, [clientId]);
  return createElement(ToolbarButton, {
    onClick: () => replaceBlocks(block.clientId, rawHandler({
      HTML: serialize(block)
    }))
  }, __('Convert to blocks'));
};
export default ConvertToBlocksButton;
//# sourceMappingURL=convert-to-blocks-button.js.map