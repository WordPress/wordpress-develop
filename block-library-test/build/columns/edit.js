"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _data = require("@wordpress/data");
var _blocks = require("@wordpress/blocks");
var _utils = require("./utils");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function ColumnsEditContainer({
  attributes,
  setAttributes,
  updateAlignment,
  updateColumns,
  clientId
}) {
  const {
    isStackedOnMobile,
    verticalAlignment,
    templateLock
  } = attributes;
  const {
    count,
    canInsertColumnBlock,
    minCount
  } = (0, _data.useSelect)(select => {
    const {
      canInsertBlockType,
      canRemoveBlock,
      getBlocks,
      getBlockCount
    } = select(_blockEditor.store);
    const innerBlocks = getBlocks(clientId);

    // Get the indexes of columns for which removal is prevented.
    // The highest index will be used to determine the minimum column count.
    const preventRemovalBlockIndexes = innerBlocks.reduce((acc, block, index) => {
      if (!canRemoveBlock(block.clientId)) {
        acc.push(index);
      }
      return acc;
    }, []);
    return {
      count: getBlockCount(clientId),
      canInsertColumnBlock: canInsertBlockType('core/column', clientId),
      minCount: Math.max(...preventRemovalBlockIndexes) + 1
    };
  }, [clientId]);
  const classes = (0, _classnames.default)({
    [`are-vertically-aligned-${verticalAlignment}`]: verticalAlignment,
    [`is-not-stacked-on-mobile`]: !isStackedOnMobile
  });
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: classes
  });
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(blockProps, {
    orientation: 'horizontal',
    renderAppender: false,
    templateLock
  });
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_blockEditor.BlockVerticalAlignmentToolbar, {
    onChange: updateAlignment,
    value: verticalAlignment
  })), (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, null, canInsertColumnBlock && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.RangeControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: (0, _i18n.__)('Columns'),
    value: count,
    onChange: value => updateColumns(count, Math.max(minCount, value)),
    min: Math.max(1, minCount),
    max: Math.max(6, count)
  }), count > 6 && (0, _react.createElement)(_components.Notice, {
    status: "warning",
    isDismissible: false
  }, (0, _i18n.__)('This column count exceeds the recommended amount and may cause visual breakage.'))), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Stack on mobile'),
    checked: isStackedOnMobile,
    onChange: () => setAttributes({
      isStackedOnMobile: !isStackedOnMobile
    })
  }))), (0, _react.createElement)("div", {
    ...innerBlocksProps
  }));
}
const ColumnsEditContainerWrapper = (0, _data.withDispatch)((dispatch, ownProps, registry) => ({
  /**
   * Update all child Column blocks with a new vertical alignment setting
   * based on whatever alignment is passed in. This allows change to parent
   * to overide anything set on a individual column basis.
   *
   * @param {string} verticalAlignment the vertical alignment setting
   */
  updateAlignment(verticalAlignment) {
    const {
      clientId,
      setAttributes
    } = ownProps;
    const {
      updateBlockAttributes
    } = dispatch(_blockEditor.store);
    const {
      getBlockOrder
    } = registry.select(_blockEditor.store);

    // Update own alignment.
    setAttributes({
      verticalAlignment
    });

    // Update all child Column Blocks to match.
    const innerBlockClientIds = getBlockOrder(clientId);
    innerBlockClientIds.forEach(innerBlockClientId => {
      updateBlockAttributes(innerBlockClientId, {
        verticalAlignment
      });
    });
  },
  /**
   * Updates the column count, including necessary revisions to child Column
   * blocks to grant required or redistribute available space.
   *
   * @param {number} previousColumns Previous column count.
   * @param {number} newColumns      New column count.
   */
  updateColumns(previousColumns, newColumns) {
    const {
      clientId
    } = ownProps;
    const {
      replaceInnerBlocks
    } = dispatch(_blockEditor.store);
    const {
      getBlocks
    } = registry.select(_blockEditor.store);
    let innerBlocks = getBlocks(clientId);
    const hasExplicitWidths = (0, _utils.hasExplicitPercentColumnWidths)(innerBlocks);

    // Redistribute available width for existing inner blocks.
    const isAddingColumn = newColumns > previousColumns;
    if (isAddingColumn && hasExplicitWidths) {
      // If adding a new column, assign width to the new column equal to
      // as if it were `1 / columns` of the total available space.
      const newColumnWidth = (0, _utils.toWidthPrecision)(100 / newColumns);

      // Redistribute in consideration of pending block insertion as
      // constraining the available working width.
      const widths = (0, _utils.getRedistributedColumnWidths)(innerBlocks, 100 - newColumnWidth);
      innerBlocks = [...(0, _utils.getMappedColumnWidths)(innerBlocks, widths), ...Array.from({
        length: newColumns - previousColumns
      }).map(() => {
        return (0, _blocks.createBlock)('core/column', {
          width: `${newColumnWidth}%`
        });
      })];
    } else if (isAddingColumn) {
      innerBlocks = [...innerBlocks, ...Array.from({
        length: newColumns - previousColumns
      }).map(() => {
        return (0, _blocks.createBlock)('core/column');
      })];
    } else if (newColumns < previousColumns) {
      // The removed column will be the last of the inner blocks.
      innerBlocks = innerBlocks.slice(0, -(previousColumns - newColumns));
      if (hasExplicitWidths) {
        // Redistribute as if block is already removed.
        const widths = (0, _utils.getRedistributedColumnWidths)(innerBlocks, 100);
        innerBlocks = (0, _utils.getMappedColumnWidths)(innerBlocks, widths);
      }
    }
    replaceInnerBlocks(clientId, innerBlocks);
  }
}))(ColumnsEditContainer);
function Placeholder({
  clientId,
  name,
  setAttributes
}) {
  const {
    blockType,
    defaultVariation,
    variations
  } = (0, _data.useSelect)(select => {
    const {
      getBlockVariations,
      getBlockType,
      getDefaultBlockVariation
    } = select(_blocks.store);
    return {
      blockType: getBlockType(name),
      defaultVariation: getDefaultBlockVariation(name, 'block'),
      variations: getBlockVariations(name, 'block')
    };
  }, [name]);
  const {
    replaceInnerBlocks
  } = (0, _data.useDispatch)(_blockEditor.store);
  const blockProps = (0, _blockEditor.useBlockProps)();
  return (0, _react.createElement)("div", {
    ...blockProps
  }, (0, _react.createElement)(_blockEditor.__experimentalBlockVariationPicker, {
    icon: blockType?.icon?.src,
    label: blockType?.title,
    variations: variations,
    onSelect: (nextVariation = defaultVariation) => {
      if (nextVariation.attributes) {
        setAttributes(nextVariation.attributes);
      }
      if (nextVariation.innerBlocks) {
        replaceInnerBlocks(clientId, (0, _blocks.createBlocksFromInnerBlocksTemplate)(nextVariation.innerBlocks), true);
      }
    },
    allowSkip: true
  }));
}
const ColumnsEdit = props => {
  const {
    clientId
  } = props;
  const hasInnerBlocks = (0, _data.useSelect)(select => select(_blockEditor.store).getBlocks(clientId).length > 0, [clientId]);
  const Component = hasInnerBlocks ? ColumnsEditContainerWrapper : Placeholder;
  return (0, _react.createElement)(Component, {
    ...props
  });
};
var _default = exports.default = ColumnsEdit;
//# sourceMappingURL=edit.js.map