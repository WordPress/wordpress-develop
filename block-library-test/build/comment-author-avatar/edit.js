"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = Edit;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _coreData = require("@wordpress/core-data");
var _data = require("@wordpress/data");
var _i18n = require("@wordpress/i18n");
/**
 * WordPress dependencies
 */

function Edit({
  attributes,
  context: {
    commentId
  },
  setAttributes,
  isSelected
}) {
  const {
    height,
    width
  } = attributes;
  const [avatars] = (0, _coreData.useEntityProp)('root', 'comment', 'author_avatar_urls', commentId);
  const [authorName] = (0, _coreData.useEntityProp)('root', 'comment', 'author_name', commentId);
  const avatarUrls = avatars ? Object.values(avatars) : null;
  const sizes = avatars ? Object.keys(avatars) : null;
  const minSize = sizes ? sizes[0] : 24;
  const maxSize = sizes ? sizes[sizes.length - 1] : 96;
  const blockProps = (0, _blockEditor.useBlockProps)();
  const spacingProps = (0, _blockEditor.__experimentalGetSpacingClassesAndStyles)(attributes);
  const maxSizeBuffer = Math.floor(maxSize * 2.5);
  const {
    avatarURL
  } = (0, _data.useSelect)(select => {
    const {
      getSettings
    } = select(_blockEditor.store);
    const {
      __experimentalDiscussionSettings
    } = getSettings();
    return __experimentalDiscussionSettings;
  });
  const inspectorControls = (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Avatar Settings')
  }, (0, _react.createElement)(_components.RangeControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: (0, _i18n.__)('Image size'),
    onChange: newWidth => setAttributes({
      width: newWidth,
      height: newWidth
    }),
    min: minSize,
    max: maxSizeBuffer,
    initialPosition: width,
    value: width
  })));
  const resizableAvatar = (0, _react.createElement)(_components.ResizableBox, {
    size: {
      width,
      height
    },
    showHandle: isSelected,
    onResizeStop: (event, direction, elt, delta) => {
      setAttributes({
        height: parseInt(height + delta.height, 10),
        width: parseInt(width + delta.width, 10)
      });
    },
    lockAspectRatio: true,
    enable: {
      top: false,
      right: !(0, _i18n.isRTL)(),
      bottom: true,
      left: (0, _i18n.isRTL)()
    },
    minWidth: minSize,
    maxWidth: maxSizeBuffer
  }, (0, _react.createElement)("img", {
    src: avatarUrls ? avatarUrls[avatarUrls.length - 1] : avatarURL,
    alt: `${authorName} ${(0, _i18n.__)('Avatar')}`,
    ...blockProps
  }));
  return (0, _react.createElement)(_react.Fragment, null, inspectorControls, (0, _react.createElement)("div", {
    ...spacingProps
  }, resizableAvatar));
}
//# sourceMappingURL=edit.js.map