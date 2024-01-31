"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _data = require("@wordpress/data");
var _i18n = require("@wordpress/i18n");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

function ColumnEdit({
  attributes: {
    verticalAlignment,
    width,
    templateLock,
    allowedBlocks
  },
  setAttributes,
  clientId
}) {
  const classes = (0, _classnames.default)('block-core-columns', {
    [`is-vertically-aligned-${verticalAlignment}`]: verticalAlignment
  });
  const [availableUnits] = (0, _blockEditor.useSettings)('spacing.units');
  const units = (0, _components.__experimentalUseCustomUnits)({
    availableUnits: availableUnits || ['%', 'px', 'em', 'rem', 'vw']
  });
  const {
    columnsIds,
    hasChildBlocks,
    rootClientId
  } = (0, _data.useSelect)(select => {
    const {
      getBlockOrder,
      getBlockRootClientId
    } = select(_blockEditor.store);
    const rootId = getBlockRootClientId(clientId);
    return {
      hasChildBlocks: getBlockOrder(clientId).length > 0,
      rootClientId: rootId,
      columnsIds: getBlockOrder(rootId)
    };
  }, [clientId]);
  const {
    updateBlockAttributes
  } = (0, _data.useDispatch)(_blockEditor.store);
  const updateAlignment = value => {
    // Update own alignment.
    setAttributes({
      verticalAlignment: value
    });
    // Reset parent Columns block.
    updateBlockAttributes(rootClientId, {
      verticalAlignment: null
    });
  };
  const widthWithUnit = Number.isFinite(width) ? width + '%' : width;
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: classes,
    style: widthWithUnit ? {
      flexBasis: widthWithUnit
    } : undefined
  });
  const columnsCount = columnsIds.length;
  const currentColumnPosition = columnsIds.indexOf(clientId) + 1;
  const label = (0, _i18n.sprintf)( /* translators: 1: Block label (i.e. "Block: Column"), 2: Position of the selected block, 3: Total number of sibling blocks of the same type */
  (0, _i18n.__)('%1$s (%2$d of %3$d)'), blockProps['aria-label'], currentColumnPosition, columnsCount);
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)({
    ...blockProps,
    'aria-label': label
  }, {
    templateLock,
    allowedBlocks,
    renderAppender: hasChildBlocks ? undefined : _blockEditor.InnerBlocks.ButtonBlockAppender
  });
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_blockEditor.BlockVerticalAlignmentToolbar, {
    onChange: updateAlignment,
    value: verticalAlignment,
    controls: ['top', 'center', 'bottom', 'stretch']
  })), (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.__experimentalUnitControl, {
    label: (0, _i18n.__)('Width'),
    labelPosition: "edge",
    __unstableInputWidth: "80px",
    value: width || '',
    onChange: nextWidth => {
      nextWidth = 0 > parseFloat(nextWidth) ? '0' : nextWidth;
      setAttributes({
        width: nextWidth
      });
    },
    units: units
  }))), (0, _react.createElement)("div", {
    ...innerBlocksProps
  }));
}
var _default = exports.default = ColumnEdit;
//# sourceMappingURL=edit.js.map