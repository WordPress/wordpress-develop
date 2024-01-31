"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = ButtonsEdit;
var _react = require("react");
var _reactNative = require("react-native");
var _blockEditor = require("@wordpress/block-editor");
var _blocks = require("@wordpress/blocks");
var _compose = require("@wordpress/compose");
var _data = require("@wordpress/data");
var _element = require("@wordpress/element");
var _components = require("@wordpress/components");
var _editor = _interopRequireDefault(require("./editor.scss"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const layoutProp = {
  type: 'default',
  alignments: []
};
const POPOVER_PROPS = {
  placement: 'bottom-start'
};
function ButtonsEdit({
  attributes: {
    layout,
    align
  },
  clientId,
  isSelected,
  setAttributes,
  blockWidth,
  name
}) {
  const [resizeObserver, sizes] = (0, _compose.useResizeObserver)();
  const [maxWidth, setMaxWidth] = (0, _element.useState)(0);
  const {
    marginLeft: spacing
  } = _editor.default.spacing;

  // Extract attributes from block layout
  const layoutBlockSupport = (0, _blocks.getBlockSupport)(name, 'layout');
  const defaultBlockLayout = layoutBlockSupport?.default;
  const usedLayout = layout || defaultBlockLayout || {};
  const {
    justifyContent
  } = usedLayout;
  const {
    isInnerButtonSelected,
    shouldDelete
  } = (0, _data.useSelect)(select => {
    const {
      getBlockCount,
      getBlockParents,
      getSelectedBlockClientId
    } = select(_blockEditor.store);
    const selectedBlockClientId = getSelectedBlockClientId();
    const selectedBlockParents = getBlockParents(selectedBlockClientId, true);
    return {
      isInnerButtonSelected: selectedBlockParents[0] === clientId,
      // The purpose of `shouldDelete` check is giving the ability to
      // pass to mobile toolbar function called `onDelete` which removes
      // the whole `Buttons` container along with the last inner button
      // when there is exactly one button.
      shouldDelete: getBlockCount(clientId) === 1
    };
  }, [clientId]);
  const preferredStyle = (0, _data.useSelect)(select => {
    const preferredStyleVariations = select(_blockEditor.store).getSettings().__experimentalPreferredStyleVariations;
    return preferredStyleVariations?.value?.['core/button'];
  }, []);
  const {
    getBlockOrder
  } = (0, _data.useSelect)(_blockEditor.store);
  const {
    insertBlock,
    removeBlock,
    selectBlock
  } = (0, _data.useDispatch)(_blockEditor.store);
  (0, _element.useEffect)(() => {
    const {
      width
    } = sizes || {};
    const {
      isFullWidth
    } = _components.alignmentHelpers;
    if (width) {
      const isFullWidthBlock = isFullWidth(align);
      setMaxWidth(isFullWidthBlock ? blockWidth : width);
    }
  }, [sizes, align]);
  const onAddNextButton = (0, _element.useCallback)((0, _compose.debounce)(selectedId => {
    const order = getBlockOrder(clientId);
    const selectedButtonIndex = order.findIndex(i => i === selectedId);
    const index = selectedButtonIndex === -1 ? order.length + 1 : selectedButtonIndex;
    const insertedBlock = (0, _blocks.createBlock)('core/button');
    insertBlock(insertedBlock, index, clientId, false);
    selectBlock(insertedBlock.clientId);
  }, 200), []);
  const renderFooterAppender = (0, _element.useRef)(() => (0, _react.createElement)(_reactNative.View, {
    style: _editor.default.appenderContainer
  }, (0, _react.createElement)(_blockEditor.InnerBlocks.ButtonBlockAppender, {
    isFloating: true,
    onAddBlock: onAddNextButton
  })));
  const justifyControls = ['left', 'center', 'right'];
  const remove = (0, _element.useCallback)(() => removeBlock(clientId), [clientId]);
  const shouldRenderFooterAppender = isSelected || isInnerButtonSelected;
  return (0, _react.createElement)(_react.Fragment, null, isSelected && (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_blockEditor.JustifyContentControl, {
    allowedControls: justifyControls,
    value: justifyContent,
    onChange: value => setAttributes({
      layout: {
        ...usedLayout,
        justifyContent: value
      }
    }),
    popoverProps: POPOVER_PROPS
  })), resizeObserver, (0, _react.createElement)(_blockEditor.InnerBlocks, {
    template: [['core/button', {
      className: preferredStyle && `is-style-${preferredStyle}`
    }]],
    renderFooterAppender: shouldRenderFooterAppender && renderFooterAppender.current,
    orientation: "horizontal",
    horizontalAlignment: justifyContent,
    onDeleteBlock: shouldDelete ? remove : undefined,
    onAddBlock: onAddNextButton,
    parentWidth: maxWidth // This value controls the width of that the buttons are able to expand to.
    ,
    marginHorizontal: spacing,
    marginVertical: spacing,
    layout: layoutProp,
    templateInsertUpdatesSelection: true,
    blockWidth: blockWidth
  }));
}
//# sourceMappingURL=edit.native.js.map