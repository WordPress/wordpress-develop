"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _data = require("@wordpress/data");
var _element = require("@wordpress/element");
var _compose = require("@wordpress/compose");
var _blocks = require("@wordpress/blocks");
var _icons = require("@wordpress/icons");
var _variations = _interopRequireDefault(require("./variations"));
var _editor = _interopRequireDefault(require("./editor.scss"));
var _utils = require("./utils");
var _columnCalculations = require("./columnCalculations.native");
var _columnPreview = _interopRequireDefault(require("../column/column-preview"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

/**
 * Number of columns to assume for template in case the user opts to skip
 * template option selection.
 *
 * @type {number}
 */
const DEFAULT_COLUMNS_NUM = 2;

/**
 * Minimum number of columns in a row
 *
 * @type {number}
 */
const MIN_COLUMNS_NUM = 1;
const {
  isFullWidth
} = _components.alignmentHelpers;
function ColumnsEditContainer({
  attributes,
  updateAlignment,
  updateColumns,
  columnCount,
  isSelected,
  onDeleteBlock,
  innerWidths,
  updateInnerColumnWidth,
  editorSidebarOpened
}) {
  const [resizeListener, sizes] = (0, _compose.useResizeObserver)();
  const [columnsInRow, setColumnsInRow] = (0, _element.useState)(MIN_COLUMNS_NUM);
  const screenWidth = Math.floor(_reactNative.Dimensions.get('window').width);
  const globalStyles = (0, _element.useContext)(_components.GlobalStylesContext);
  const {
    verticalAlignment,
    align
  } = attributes;
  const {
    width
  } = sizes || {};
  const [availableUnits] = (0, _blockEditor.useSettings)('spacing.units');
  const units = (0, _components.__experimentalUseCustomUnits)({
    availableUnits: availableUnits || ['%', 'px', 'em', 'rem', 'vw']
  });
  (0, _element.useEffect)(() => {
    if (columnCount === 0) {
      const newColumnCount = columnCount || DEFAULT_COLUMNS_NUM;
      updateColumns(columnCount, newColumnCount);
    }
  }, []);
  (0, _element.useEffect)(() => {
    if (width) {
      if ((0, _columnCalculations.getColumnsInRow)(width, columnCount) !== columnsInRow) {
        setColumnsInRow((0, _columnCalculations.getColumnsInRow)(width, columnCount));
      }
    }
  }, [width, columnCount]);
  const renderAppender = () => {
    if (isSelected) {
      return (0, _react.createElement)(_reactNative.View, {
        style: isFullWidth(align) && _editor.default.columnAppender
      }, (0, _react.createElement)(_blockEditor.InnerBlocks.ButtonBlockAppender, {
        onAddBlock: onAddBlock
      }));
    }
    return null;
  };
  const contentWidths = (0, _element.useMemo)(() => (0, _columnCalculations.getContentWidths)(columnsInRow, width, columnCount, innerWidths, globalStyles), [width, columnsInRow, columnCount, innerWidths, globalStyles]);
  const onAddBlock = (0, _element.useCallback)(() => {
    updateColumns(columnCount, columnCount + 1);
  }, [columnCount]);
  const onChangeWidth = (nextWidth, valueUnit, columnId) => {
    const widthWithUnit = (0, _utils.getWidthWithUnit)(nextWidth, valueUnit);
    updateInnerColumnWidth(widthWithUnit, columnId);
  };
  const onChangeUnit = (nextUnit, index, columnId) => {
    const widthWithoutUnit = parseFloat((0, _utils.getWidths)(innerWidths)[index]);
    const widthWithUnit = (0, _utils.getWidthWithUnit)(widthWithoutUnit, nextUnit);
    updateInnerColumnWidth(widthWithUnit, columnId);
  };
  const onChange = (nextWidth, valueUnit, columnId) => {
    if ((0, _utils.isPercentageUnit)(valueUnit) || !valueUnit) {
      return;
    }
    onChangeWidth(nextWidth, valueUnit, columnId);
  };
  const getColumnsSliders = (0, _element.useMemo)(() => {
    if (!editorSidebarOpened || !isSelected) {
      return null;
    }
    return innerWidths.map((column, index) => {
      const {
        valueUnit = '%'
      } = (0, _components.getValueAndUnit)(column.attributes.width) || {};
      const label = (0, _i18n.sprintf)( /* translators: %d: column index. */
      (0, _i18n.__)('Column %d'), index + 1);
      return (0, _react.createElement)(_components.UnitControl, {
        label: label,
        settingLabel: (0, _i18n.__)('Width'),
        key: `${column.clientId}-${(0, _utils.getWidths)(innerWidths).length}`,
        min: 1,
        max: (0, _utils.isPercentageUnit)(valueUnit) || !valueUnit ? 100 : undefined,
        value: (0, _utils.getWidths)(innerWidths)[index],
        onChange: nextWidth => {
          onChange(nextWidth, valueUnit, column.clientId);
        },
        onUnitChange: nextUnit => onChangeUnit(nextUnit, index, column.clientId),
        onComplete: nextWidth => {
          onChangeWidth(nextWidth, valueUnit, column.clientId);
        },
        unit: valueUnit,
        units: units,
        preview: (0, _react.createElement)(_columnPreview.default, {
          columnWidths: (0, _utils.getWidths)(innerWidths, false),
          selectedColumnIndex: index
        })
      });
    });
  }, [editorSidebarOpened, isSelected, innerWidths]);
  const onChangeColumnsNum = (0, _element.useCallback)(value => {
    updateColumns(columnCount, value);
  }, [columnCount]);
  return (0, _react.createElement)(_react.Fragment, null, isSelected && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Columns Settings')
  }, (0, _react.createElement)(_components.RangeControl, {
    label: (0, _i18n.__)('Number of columns'),
    icon: _icons.columns,
    value: columnCount,
    onChange: onChangeColumnsNum,
    min: MIN_COLUMNS_NUM,
    max: columnCount + 1,
    type: "stepper"
  }), getColumnsSliders), (0, _react.createElement)(_components.PanelBody, null, (0, _react.createElement)(_components.FooterMessageControl, {
    label: (0, _i18n.__)('Note: Column layout may vary between themes and screen sizes')
  }))), (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_blockEditor.BlockVerticalAlignmentToolbar, {
    onChange: updateAlignment,
    value: verticalAlignment
  }))), (0, _react.createElement)(_reactNative.View, {
    style: isSelected && _editor.default.innerBlocksSelected
  }, resizeListener, width && (0, _react.createElement)(_blockEditor.InnerBlocks, {
    renderAppender: renderAppender,
    orientation: columnsInRow > 1 ? 'horizontal' : undefined,
    horizontal: columnsInRow > 1,
    contentResizeMode: "stretch",
    onAddBlock: onAddBlock,
    onDeleteBlock: columnCount === 1 ? onDeleteBlock : undefined,
    blockWidth: width,
    contentStyle: contentWidths,
    parentWidth: isFullWidth(align) && columnCount === 0 ? screenWidth : (0, _columnCalculations.calculateContainerWidth)(width, columnsInRow)
  })));
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
  updateInnerColumnWidth(value, columnId) {
    const {
      updateBlockAttributes
    } = dispatch(_blockEditor.store);
    updateBlockAttributes(columnId, {
      width: value
    });
  },
  /**
   * Updates the column columnCount, including necessary revisions to child Column
   * blocks to grant required or redistribute available space.
   *
   * @param {number} previousColumns Previous column columnCount.
   * @param {number} newColumns      New column columnCount.
   */
  updateColumns(previousColumns, newColumns) {
    const {
      clientId
    } = ownProps;
    const {
      replaceInnerBlocks
    } = dispatch(_blockEditor.store);
    const {
      getBlocks,
      getBlockAttributes
    } = registry.select(_blockEditor.store);
    let innerBlocks = getBlocks(clientId);
    const hasExplicitWidths = (0, _utils.hasExplicitPercentColumnWidths)(innerBlocks);

    // Redistribute available width for existing inner blocks.
    const isAddingColumn = newColumns > previousColumns;

    // Get verticalAlignment from Columns block to set the same to new Column.
    const {
      verticalAlignment
    } = getBlockAttributes(clientId) || {};
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
          width: `${newColumnWidth}%`,
          verticalAlignment
        });
      })];
    } else if (isAddingColumn) {
      innerBlocks = [...innerBlocks, ...Array.from({
        length: newColumns - previousColumns
      }).map(() => {
        return (0, _blocks.createBlock)('core/column', {
          verticalAlignment
        });
      })];
    } else {
      // The removed column will be the last of the inner blocks.
      innerBlocks = innerBlocks.slice(0, -(previousColumns - newColumns));
      if (hasExplicitWidths) {
        // Redistribute as if block is already removed.
        const widths = (0, _utils.getRedistributedColumnWidths)(innerBlocks, 100);
        innerBlocks = (0, _utils.getMappedColumnWidths)(innerBlocks, widths);
      }
    }
    replaceInnerBlocks(clientId, innerBlocks);
  },
  onAddNextColumn: () => {
    const {
      clientId
    } = ownProps;
    const {
      replaceInnerBlocks,
      selectBlock
    } = dispatch(_blockEditor.store);
    const {
      getBlocks,
      getBlockAttributes
    } = registry.select(_blockEditor.store);

    // Get verticalAlignment from Columns block to set the same to new Column.
    const {
      verticalAlignment
    } = getBlockAttributes(clientId);
    const innerBlocks = getBlocks(clientId);
    const insertedBlock = (0, _blocks.createBlock)('core/column', {
      verticalAlignment
    });
    replaceInnerBlocks(clientId, [...innerBlocks, insertedBlock], true);
    selectBlock(insertedBlock.clientId);
  },
  onDeleteBlock: () => {
    const {
      clientId
    } = ownProps;
    const {
      removeBlock
    } = dispatch(_blockEditor.store);
    removeBlock(clientId);
  }
}))((0, _element.memo)(ColumnsEditContainer));
const ColumnsEdit = props => {
  const {
    clientId,
    isSelected,
    style
  } = props;
  const {
    columnCount,
    isDefaultColumns,
    innerBlocks,
    hasParents,
    parentBlockAlignment,
    editorSidebarOpened
  } = (0, _data.useSelect)(select => {
    const {
      getBlockCount,
      getBlocks,
      getBlockParents,
      getBlockAttributes
    } = select(_blockEditor.store);
    const {
      isEditorSidebarOpened
    } = select('core/edit-post');
    const innerBlocksList = getBlocks(clientId);
    const isContentEmpty = innerBlocksList.every(innerBlock => innerBlock.innerBlocks.length === 0);
    const parents = getBlockParents(clientId, true);
    return {
      columnCount: getBlockCount(clientId),
      isDefaultColumns: isContentEmpty,
      innerBlocks: innerBlocksList,
      hasParents: parents.length > 0,
      parentBlockAlignment: getBlockAttributes(parents[0])?.align,
      editorSidebarOpened: isSelected && isEditorSidebarOpened()
    };
  }, [clientId, isSelected]);
  const innerWidths = (0, _element.useMemo)(() => innerBlocks.map(inn => ({
    clientId: inn.clientId,
    attributes: {
      width: inn.attributes.width
    }
  })), [innerBlocks]);
  const [isVisible, setIsVisible] = (0, _element.useState)(false);
  (0, _element.useEffect)(() => {
    if (isSelected && isDefaultColumns) {
      const revealTimeout = setTimeout(() => setIsVisible(true), 100);
      return () => clearTimeout(revealTimeout);
    }
  }, []);
  const onClose = (0, _element.useCallback)(() => {
    setIsVisible(false);
  }, []);
  return (0, _react.createElement)(_reactNative.View, {
    style: style
  }, (0, _react.createElement)(ColumnsEditContainerWrapper, {
    columnCount: columnCount,
    innerWidths: innerWidths,
    hasParents: hasParents,
    parentBlockAlignment: parentBlockAlignment,
    editorSidebarOpened: editorSidebarOpened,
    ...props
  }), (0, _react.createElement)(_blockEditor.BlockVariationPicker, {
    variations: _variations.default,
    onClose: onClose,
    clientId: clientId,
    isVisible: isVisible
  }));
};
var _default = exports.default = ColumnsEdit;
//# sourceMappingURL=edit.native.js.map