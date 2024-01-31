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
var _blocks = require("@wordpress/blocks");
var _icons = require("@wordpress/icons");
var _useEnter = require("./use-enter");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const name = 'core/paragraph';
function ParagraphRTLControl({
  direction,
  setDirection
}) {
  return (0, _i18n.isRTL)() && (0, _react.createElement)(_components.ToolbarButton, {
    icon: _icons.formatLtr,
    title: (0, _i18n._x)('Left to right', 'editor button'),
    isActive: direction === 'ltr',
    onClick: () => {
      setDirection(direction === 'ltr' ? undefined : 'ltr');
    }
  });
}
function hasDropCapDisabled(align) {
  return align === ((0, _i18n.isRTL)() ? 'left' : 'right') || align === 'center';
}
function DropCapControl({
  clientId,
  attributes,
  setAttributes
}) {
  // Please do not add a useSelect call to the paragraph block unconditionally.
  // Every useSelect added to a (frequently used) block will degrade load
  // and type performance. By moving it within InspectorControls, the subscription is
  // now only added for the selected block(s).
  const [isDropCapFeatureEnabled] = (0, _blockEditor.useSettings)('typography.dropCap');
  if (!isDropCapFeatureEnabled) {
    return null;
  }
  const {
    align,
    dropCap
  } = attributes;
  let helpText;
  if (hasDropCapDisabled(align)) {
    helpText = (0, _i18n.__)('Not available for aligned text.');
  } else if (dropCap) {
    helpText = (0, _i18n.__)('Showing large initial letter.');
  } else {
    helpText = (0, _i18n.__)('Toggle to show a large initial letter.');
  }
  return (0, _react.createElement)(_components.__experimentalToolsPanelItem, {
    hasValue: () => !!dropCap,
    label: (0, _i18n.__)('Drop cap'),
    onDeselect: () => setAttributes({
      dropCap: undefined
    }),
    resetAllFilter: () => ({
      dropCap: undefined
    }),
    panelId: clientId
  }, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Drop cap'),
    checked: !!dropCap,
    onChange: () => setAttributes({
      dropCap: !dropCap
    }),
    help: helpText,
    disabled: hasDropCapDisabled(align) ? true : false
  }));
}
function ParagraphBlock({
  attributes,
  mergeBlocks,
  onReplace,
  onRemove,
  setAttributes,
  clientId
}) {
  const {
    align,
    content,
    direction,
    dropCap,
    placeholder
  } = attributes;
  const blockProps = (0, _blockEditor.useBlockProps)({
    ref: (0, _useEnter.useOnEnter)({
      clientId,
      content
    }),
    className: (0, _classnames.default)({
      'has-drop-cap': hasDropCapDisabled(align) ? false : dropCap,
      [`has-text-align-${align}`]: align
    }),
    style: {
      direction
    }
  });
  const blockEditingMode = (0, _blockEditor.useBlockEditingMode)();
  return (0, _react.createElement)(_react.Fragment, null, blockEditingMode === 'default' && (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_blockEditor.AlignmentControl, {
    value: align,
    onChange: newAlign => setAttributes({
      align: newAlign,
      dropCap: hasDropCapDisabled(newAlign) ? false : dropCap
    })
  }), (0, _react.createElement)(ParagraphRTLControl, {
    direction: direction,
    setDirection: newDirection => setAttributes({
      direction: newDirection
    })
  })), (0, _react.createElement)(_blockEditor.InspectorControls, {
    group: "typography"
  }, (0, _react.createElement)(DropCapControl, {
    clientId: clientId,
    attributes: attributes,
    setAttributes: setAttributes
  })), (0, _react.createElement)(_blockEditor.RichText, {
    identifier: "content",
    tagName: "p",
    ...blockProps,
    value: content,
    onChange: newContent => setAttributes({
      content: newContent
    }),
    onSplit: (value, isOriginal) => {
      let newAttributes;
      if (isOriginal || value) {
        newAttributes = {
          ...attributes,
          content: value
        };
      }
      const block = (0, _blocks.createBlock)(name, newAttributes);
      if (isOriginal) {
        block.clientId = clientId;
      }
      return block;
    },
    onMerge: mergeBlocks,
    onReplace: onReplace,
    onRemove: onRemove,
    "aria-label": _blockEditor.RichText.isEmpty(content) ? (0, _i18n.__)('Empty block; start writing or type forward slash to choose a block') : (0, _i18n.__)('Block: Paragraph'),
    "data-empty": _blockEditor.RichText.isEmpty(content),
    placeholder: placeholder || (0, _i18n.__)('Type / to choose a block'),
    "data-custom-placeholder": placeholder ? true : undefined,
    __unstableEmbedURLOnPaste: true,
    __unstableAllowPrefixTransformations: true
  }));
}
var _default = exports.default = ParagraphBlock;
//# sourceMappingURL=edit.js.map