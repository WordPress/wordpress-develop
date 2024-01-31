"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _i18n = require("@wordpress/i18n");
var _element = require("@wordpress/element");
var _data = require("@wordpress/data");
var _blocks = require("@wordpress/blocks");
var _blockEditor = require("@wordpress/block-editor");
var _autogenerateAnchors = require("./autogenerate-anchors");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function HeadingEdit({
  attributes,
  setAttributes,
  mergeBlocks,
  onReplace,
  style,
  clientId
}) {
  const {
    textAlign,
    content,
    level,
    placeholder,
    anchor
  } = attributes;
  const tagName = 'h' + level;
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign
    }),
    style
  });
  const blockEditingMode = (0, _blockEditor.useBlockEditingMode)();
  const {
    canGenerateAnchors
  } = (0, _data.useSelect)(select => {
    const {
      getGlobalBlockCount,
      getSettings
    } = select(_blockEditor.store);
    const settings = getSettings();
    return {
      canGenerateAnchors: !!settings.generateAnchors || getGlobalBlockCount('core/table-of-contents') > 0
    };
  }, []);
  const {
    __unstableMarkNextChangeAsNotPersistent
  } = (0, _data.useDispatch)(_blockEditor.store);

  // Initially set anchor for headings that have content but no anchor set.
  // This is used when transforming a block to heading, or for legacy anchors.
  (0, _element.useEffect)(() => {
    if (!canGenerateAnchors) {
      return;
    }
    if (!anchor && content) {
      // This side-effect should not create an undo level.
      __unstableMarkNextChangeAsNotPersistent();
      setAttributes({
        anchor: (0, _autogenerateAnchors.generateAnchor)(clientId, content)
      });
    }
    (0, _autogenerateAnchors.setAnchor)(clientId, anchor);

    // Remove anchor map when block unmounts.
    return () => (0, _autogenerateAnchors.setAnchor)(clientId, null);
  }, [anchor, content, clientId, canGenerateAnchors]);
  const onContentChange = value => {
    const newAttrs = {
      content: value
    };
    if (canGenerateAnchors && (!anchor || !value || (0, _autogenerateAnchors.generateAnchor)(clientId, content) === anchor)) {
      newAttrs.anchor = (0, _autogenerateAnchors.generateAnchor)(clientId, value);
    }
    setAttributes(newAttrs);
  };
  return (0, _react.createElement)(_react.Fragment, null, blockEditingMode === 'default' && (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_blockEditor.HeadingLevelDropdown, {
    value: level,
    onChange: newLevel => setAttributes({
      level: newLevel
    })
  }), (0, _react.createElement)(_blockEditor.AlignmentControl, {
    value: textAlign,
    onChange: nextAlign => {
      setAttributes({
        textAlign: nextAlign
      });
    }
  })), (0, _react.createElement)(_blockEditor.RichText, {
    identifier: "content",
    tagName: tagName,
    value: content,
    onChange: onContentChange,
    onMerge: mergeBlocks,
    onSplit: (value, isOriginal) => {
      let block;
      if (isOriginal || value) {
        block = (0, _blocks.createBlock)('core/heading', {
          ...attributes,
          content: value
        });
      } else {
        var _getDefaultBlockName;
        block = (0, _blocks.createBlock)((_getDefaultBlockName = (0, _blocks.getDefaultBlockName)()) !== null && _getDefaultBlockName !== void 0 ? _getDefaultBlockName : 'core/heading');
      }
      if (isOriginal) {
        block.clientId = clientId;
      }
      return block;
    },
    onReplace: onReplace,
    onRemove: () => onReplace([]),
    placeholder: placeholder || (0, _i18n.__)('Heading'),
    textAlign: textAlign,
    ...(_element.Platform.isNative && {
      deleteEnter: true
    }),
    ...blockProps
  }));
}
var _default = exports.default = HeadingEdit;
//# sourceMappingURL=edit.js.map