"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = Edit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _url = require("@wordpress/url");
var _hooks = require("./hooks");
var _userControl = _interopRequireDefault(require("./user-control"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const AvatarInspectorControls = ({
  setAttributes,
  avatar,
  attributes,
  selectUser
}) => (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
  title: (0, _i18n.__)('Settings')
}, (0, _react.createElement)(_components.RangeControl, {
  __nextHasNoMarginBottom: true,
  __next40pxDefaultSize: true,
  label: (0, _i18n.__)('Image size'),
  onChange: newSize => setAttributes({
    size: newSize
  }),
  min: avatar.minSize,
  max: avatar.maxSize,
  initialPosition: attributes?.size,
  value: attributes?.size
}), (0, _react.createElement)(_components.ToggleControl, {
  __nextHasNoMarginBottom: true,
  label: (0, _i18n.__)('Link to user profile'),
  onChange: () => setAttributes({
    isLink: !attributes.isLink
  }),
  checked: attributes.isLink
}), attributes.isLink && (0, _react.createElement)(_components.ToggleControl, {
  label: (0, _i18n.__)('Open in new tab'),
  onChange: value => setAttributes({
    linkTarget: value ? '_blank' : '_self'
  }),
  checked: attributes.linkTarget === '_blank'
}), selectUser && (0, _react.createElement)(_userControl.default, {
  value: attributes?.userId,
  onChange: value => {
    setAttributes({
      userId: value
    });
  }
})));
const ResizableAvatar = ({
  setAttributes,
  attributes,
  avatar,
  blockProps,
  isSelected
}) => {
  const borderProps = (0, _blockEditor.__experimentalUseBorderProps)(attributes);
  const doubledSizedSrc = (0, _url.addQueryArgs)((0, _url.removeQueryArgs)(avatar?.src, ['s']), {
    s: attributes?.size * 2
  });
  return (0, _react.createElement)("div", {
    ...blockProps
  }, (0, _react.createElement)(_components.ResizableBox, {
    size: {
      width: attributes.size,
      height: attributes.size
    },
    showHandle: isSelected,
    onResizeStop: (event, direction, elt, delta) => {
      setAttributes({
        size: parseInt(attributes.size + (delta.height || delta.width), 10)
      });
    },
    lockAspectRatio: true,
    enable: {
      top: false,
      right: !(0, _i18n.isRTL)(),
      bottom: true,
      left: (0, _i18n.isRTL)()
    },
    minWidth: avatar.minSize,
    maxWidth: avatar.maxSize
  }, (0, _react.createElement)("img", {
    src: doubledSizedSrc,
    alt: avatar.alt,
    className: (0, _classnames.default)('avatar', 'avatar-' + attributes.size, 'photo', 'wp-block-avatar__image', borderProps.className),
    style: borderProps.style
  })));
};
const CommentEdit = ({
  attributes,
  context,
  setAttributes,
  isSelected
}) => {
  const {
    commentId
  } = context;
  const blockProps = (0, _blockEditor.useBlockProps)();
  const avatar = (0, _hooks.useCommentAvatar)({
    commentId
  });
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(AvatarInspectorControls, {
    avatar: avatar,
    setAttributes: setAttributes,
    attributes: attributes,
    selectUser: false
  }), attributes.isLink ? (0, _react.createElement)("a", {
    href: "#avatar-pseudo-link",
    className: "wp-block-avatar__link",
    onClick: event => event.preventDefault()
  }, (0, _react.createElement)(ResizableAvatar, {
    attributes: attributes,
    avatar: avatar,
    blockProps: blockProps,
    isSelected: isSelected,
    setAttributes: setAttributes
  })) : (0, _react.createElement)(ResizableAvatar, {
    attributes: attributes,
    avatar: avatar,
    blockProps: blockProps,
    isSelected: isSelected,
    setAttributes: setAttributes
  }));
};
const UserEdit = ({
  attributes,
  context,
  setAttributes,
  isSelected
}) => {
  const {
    postId,
    postType
  } = context;
  const avatar = (0, _hooks.useUserAvatar)({
    userId: attributes?.userId,
    postId,
    postType
  });
  const blockProps = (0, _blockEditor.useBlockProps)();
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(AvatarInspectorControls, {
    selectUser: true,
    attributes: attributes,
    avatar: avatar,
    setAttributes: setAttributes
  }), attributes.isLink ? (0, _react.createElement)("a", {
    href: "#avatar-pseudo-link",
    className: "wp-block-avatar__link",
    onClick: event => event.preventDefault()
  }, (0, _react.createElement)(ResizableAvatar, {
    attributes: attributes,
    avatar: avatar,
    blockProps: blockProps,
    isSelected: isSelected,
    setAttributes: setAttributes
  })) : (0, _react.createElement)(ResizableAvatar, {
    attributes: attributes,
    avatar: avatar,
    blockProps: blockProps,
    isSelected: isSelected,
    setAttributes: setAttributes
  }));
};
function Edit(props) {
  // Don't show the Comment Edit controls if we have a comment ID set, or if we're in the Site Editor (where it is `null`).
  if (props?.context?.commentId || props?.context?.commentId === null) {
    return (0, _react.createElement)(CommentEdit, {
      ...props
    });
  }
  return (0, _react.createElement)(UserEdit, {
    ...props
  });
}
//# sourceMappingURL=edit.js.map