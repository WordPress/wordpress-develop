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
var _element = require("@wordpress/element");
var _primitives = require("@wordpress/primitives");
var _data = require("@wordpress/data");
var _controls = _interopRequireDefault(require("./controls"));
var _constants = require("./constants");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const ResizableSpacer = ({
  orientation,
  onResizeStart,
  onResize,
  onResizeStop,
  isSelected,
  isResizing,
  setIsResizing,
  ...props
}) => {
  const getCurrentSize = elt => {
    return orientation === 'horizontal' ? elt.clientWidth : elt.clientHeight;
  };
  const getNextVal = elt => {
    return `${getCurrentSize(elt)}px`;
  };
  return (0, _react.createElement)(_components.ResizableBox, {
    className: (0, _classnames.default)('block-library-spacer__resize-container', {
      'resize-horizontal': orientation === 'horizontal',
      'is-resizing': isResizing,
      'is-selected': isSelected
    }),
    onResizeStart: (_event, _direction, elt) => {
      const nextVal = getNextVal(elt);
      onResizeStart(nextVal);
      onResize(nextVal);
    },
    onResize: (_event, _direction, elt) => {
      onResize(getNextVal(elt));
      if (!isResizing) {
        setIsResizing(true);
      }
    },
    onResizeStop: (_event, _direction, elt) => {
      const nextVal = getCurrentSize(elt);
      onResizeStop(`${nextVal}px`);
      setIsResizing(false);
    },
    __experimentalShowTooltip: true,
    __experimentalTooltipProps: {
      axis: orientation === 'horizontal' ? 'x' : 'y',
      position: 'corner',
      isVisible: isResizing
    },
    showHandle: isSelected,
    ...props
  });
};
const SpacerEdit = ({
  attributes,
  isSelected,
  setAttributes,
  toggleSelection,
  context,
  __unstableParentLayout: parentLayout,
  className
}) => {
  const disableCustomSpacingSizes = (0, _data.useSelect)(select => {
    const editorSettings = select(_blockEditor.store).getSettings();
    return editorSettings?.disableCustomSpacingSizes;
  });
  const {
    orientation
  } = context;
  const {
    orientation: parentOrientation,
    type
  } = parentLayout || {};
  // Check if the spacer is inside a flex container.
  const isFlexLayout = type === 'flex';
  // If the spacer is inside a flex container, it should either inherit the orientation
  // of the parent or use the flex default orientation.
  const inheritedOrientation = !parentOrientation && isFlexLayout ? 'horizontal' : parentOrientation || orientation;
  const {
    height,
    width,
    style: blockStyle = {}
  } = attributes;
  const {
    layout = {}
  } = blockStyle;
  const {
    selfStretch,
    flexSize
  } = layout;
  const [spacingSizes] = (0, _blockEditor.useSettings)('spacing.spacingSizes');
  const [isResizing, setIsResizing] = (0, _element.useState)(false);
  const [temporaryHeight, setTemporaryHeight] = (0, _element.useState)(null);
  const [temporaryWidth, setTemporaryWidth] = (0, _element.useState)(null);
  const onResizeStart = () => toggleSelection(false);
  const onResizeStop = () => toggleSelection(true);
  const handleOnVerticalResizeStop = newHeight => {
    onResizeStop();
    if (isFlexLayout) {
      setAttributes({
        style: {
          ...blockStyle,
          layout: {
            ...layout,
            flexSize: newHeight,
            selfStretch: 'fixed'
          }
        }
      });
    }
    setAttributes({
      height: newHeight
    });
    setTemporaryHeight(null);
  };
  const handleOnHorizontalResizeStop = newWidth => {
    onResizeStop();
    if (isFlexLayout) {
      setAttributes({
        style: {
          ...blockStyle,
          layout: {
            ...layout,
            flexSize: newWidth,
            selfStretch: 'fixed'
          }
        }
      });
    }
    setAttributes({
      width: newWidth
    });
    setTemporaryWidth(null);
  };
  const getHeightForVerticalBlocks = () => {
    if (isFlexLayout) {
      return undefined;
    }
    return temporaryHeight || (0, _blockEditor.getSpacingPresetCssVar)(height) || undefined;
  };
  const getWidthForHorizontalBlocks = () => {
    if (isFlexLayout) {
      return undefined;
    }
    return temporaryWidth || (0, _blockEditor.getSpacingPresetCssVar)(width) || undefined;
  };
  const sizeConditionalOnOrientation = inheritedOrientation === 'horizontal' ? temporaryWidth || flexSize : temporaryHeight || flexSize;
  const style = {
    height: inheritedOrientation === 'horizontal' ? 24 : getHeightForVerticalBlocks(),
    width: inheritedOrientation === 'horizontal' ? getWidthForHorizontalBlocks() : undefined,
    // In vertical flex containers, the spacer shrinks to nothing without a minimum width.
    minWidth: inheritedOrientation === 'vertical' && isFlexLayout ? 48 : undefined,
    // Add flex-basis so temporary sizes are respected.
    flexBasis: isFlexLayout ? sizeConditionalOnOrientation : undefined,
    // Remove flex-grow when resizing.
    flexGrow: isFlexLayout && isResizing ? 0 : undefined
  };
  const resizableBoxWithOrientation = blockOrientation => {
    if (blockOrientation === 'horizontal') {
      return (0, _react.createElement)(ResizableSpacer, {
        minWidth: _constants.MIN_SPACER_SIZE,
        enable: {
          top: false,
          right: true,
          bottom: false,
          left: false,
          topRight: false,
          bottomRight: false,
          bottomLeft: false,
          topLeft: false
        },
        orientation: blockOrientation,
        onResizeStart: onResizeStart,
        onResize: setTemporaryWidth,
        onResizeStop: handleOnHorizontalResizeStop,
        isSelected: isSelected,
        isResizing: isResizing,
        setIsResizing: setIsResizing
      });
    }
    return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(ResizableSpacer, {
      minHeight: _constants.MIN_SPACER_SIZE,
      enable: {
        top: false,
        right: false,
        bottom: true,
        left: false,
        topRight: false,
        bottomRight: false,
        bottomLeft: false,
        topLeft: false
      },
      orientation: blockOrientation,
      onResizeStart: onResizeStart,
      onResize: setTemporaryHeight,
      onResizeStop: handleOnVerticalResizeStop,
      isSelected: isSelected,
      isResizing: isResizing,
      setIsResizing: setIsResizing
    }));
  };
  (0, _element.useEffect)(() => {
    if (isFlexLayout && selfStretch !== 'fill' && selfStretch !== 'fit' && !flexSize) {
      if (inheritedOrientation === 'horizontal') {
        // If spacer is moving from a vertical container to a horizontal container,
        // it might not have width but have height instead.
        const newSize = (0, _blockEditor.getCustomValueFromPreset)(width, spacingSizes) || (0, _blockEditor.getCustomValueFromPreset)(height, spacingSizes) || '100px';
        setAttributes({
          width: '0px',
          style: {
            ...blockStyle,
            layout: {
              ...layout,
              flexSize: newSize,
              selfStretch: 'fixed'
            }
          }
        });
      } else {
        const newSize = (0, _blockEditor.getCustomValueFromPreset)(height, spacingSizes) || (0, _blockEditor.getCustomValueFromPreset)(width, spacingSizes) || '100px';
        setAttributes({
          height: '0px',
          style: {
            ...blockStyle,
            layout: {
              ...layout,
              flexSize: newSize,
              selfStretch: 'fixed'
            }
          }
        });
      }
    } else if (isFlexLayout && (selfStretch === 'fill' || selfStretch === 'fit')) {
      if (inheritedOrientation === 'horizontal') {
        setAttributes({
          width: undefined
        });
      } else {
        setAttributes({
          height: undefined
        });
      }
    } else if (!isFlexLayout && (selfStretch || flexSize)) {
      if (inheritedOrientation === 'horizontal') {
        setAttributes({
          width: flexSize
        });
      } else {
        setAttributes({
          height: flexSize
        });
      }
      setAttributes({
        style: {
          ...blockStyle,
          layout: {
            ...layout,
            flexSize: undefined,
            selfStretch: undefined
          }
        }
      });
    }
  }, [blockStyle, flexSize, height, inheritedOrientation, isFlexLayout, layout, selfStretch, setAttributes, spacingSizes, width]);
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_primitives.View, {
    ...(0, _blockEditor.useBlockProps)({
      style,
      className: (0, _classnames.default)(className, {
        'custom-sizes-disabled': disableCustomSpacingSizes
      })
    })
  }, resizableBoxWithOrientation(inheritedOrientation)), !isFlexLayout && (0, _react.createElement)(_controls.default, {
    setAttributes: setAttributes,
    height: temporaryHeight || height,
    width: temporaryWidth || width,
    orientation: inheritedOrientation,
    isResizing: isResizing
  }));
};
var _default = exports.default = SpacerEdit;
//# sourceMappingURL=edit.js.map