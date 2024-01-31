"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = ResizableCoverPopover;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _element = require("@wordpress/element");
var _blockEditor = require("@wordpress/block-editor");
var _lockUnlock = require("../../lock-unlock");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const RESIZABLE_BOX_ENABLE_OPTION = {
  top: false,
  right: false,
  bottom: true,
  left: false,
  topRight: false,
  bottomRight: false,
  bottomLeft: false,
  topLeft: false
};
const {
  ResizableBoxPopover
} = (0, _lockUnlock.unlock)(_blockEditor.privateApis);
function ResizableCoverPopover({
  className,
  height,
  minHeight,
  onResize,
  onResizeStart,
  onResizeStop,
  showHandle,
  size,
  width,
  ...props
}) {
  const [isResizing, setIsResizing] = (0, _element.useState)(false);
  const dimensions = (0, _element.useMemo)(() => ({
    height,
    minHeight,
    width
  }), [minHeight, height, width]);
  const resizableBoxProps = {
    className: (0, _classnames.default)(className, {
      'is-resizing': isResizing
    }),
    enable: RESIZABLE_BOX_ENABLE_OPTION,
    onResizeStart: (_event, _direction, elt) => {
      onResizeStart(elt.clientHeight);
      onResize(elt.clientHeight);
    },
    onResize: (_event, _direction, elt) => {
      onResize(elt.clientHeight);
      if (!isResizing) {
        setIsResizing(true);
      }
    },
    onResizeStop: (_event, _direction, elt) => {
      onResizeStop(elt.clientHeight);
      setIsResizing(false);
    },
    showHandle,
    size,
    __experimentalShowTooltip: true,
    __experimentalTooltipProps: {
      axis: 'y',
      position: 'bottom',
      isVisible: isResizing
    }
  };
  return (0, _react.createElement)(ResizableBoxPopover, {
    className: "block-library-cover__resizable-box-popover",
    __unstableRefreshSize: dimensions,
    resizableBoxProps: resizableBoxProps,
    ...props
  });
}
//# sourceMappingURL=resizable-cover-popover.js.map