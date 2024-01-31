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
var _coreData = require("@wordpress/core-data");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

const minimumUsersForCombobox = 25;
const AUTHORS_QUERY = {
  who: 'authors',
  per_page: 100
};
function PostAuthorEdit({
  isSelected,
  context: {
    postType,
    postId,
    queryId
  },
  attributes,
  setAttributes
}) {
  const isDescendentOfQueryLoop = Number.isFinite(queryId);
  const {
    authorId,
    authorDetails,
    authors
  } = (0, _data.useSelect)(select => {
    const {
      getEditedEntityRecord,
      getUser,
      getUsers
    } = select(_coreData.store);
    const _authorId = getEditedEntityRecord('postType', postType, postId)?.author;
    return {
      authorId: _authorId,
      authorDetails: _authorId ? getUser(_authorId) : null,
      authors: getUsers(AUTHORS_QUERY)
    };
  }, [postType, postId]);
  const {
    editEntityRecord
  } = (0, _data.useDispatch)(_coreData.store);
  const {
    textAlign,
    showAvatar,
    showBio,
    byline,
    isLink,
    linkTarget
  } = attributes;
  const avatarSizes = [];
  const authorName = authorDetails?.name || (0, _i18n.__)('Post Author');
  if (authorDetails?.avatar_urls) {
    Object.keys(authorDetails.avatar_urls).forEach(size => {
      avatarSizes.push({
        value: size,
        label: `${size} x ${size}`
      });
    });
  }
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign
    })
  });
  const authorOptions = authors?.length ? authors.map(({
    id,
    name
  }) => {
    return {
      value: id,
      label: name
    };
  }) : [];
  const handleSelect = nextAuthorId => {
    editEntityRecord('postType', postType, postId, {
      author: nextAuthorId
    });
  };
  const showCombobox = authorOptions.length >= minimumUsersForCombobox;
  const showAuthorControl = !!postId && !isDescendentOfQueryLoop && authorOptions.length > 0;
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, showAuthorControl && (showCombobox && (0, _react.createElement)(_components.ComboboxControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Author'),
    options: authorOptions,
    value: authorId,
    onChange: handleSelect,
    allowReset: false
  }) || (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Author'),
    value: authorId,
    options: authorOptions,
    onChange: handleSelect
  })), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Show avatar'),
    checked: showAvatar,
    onChange: () => setAttributes({
      showAvatar: !showAvatar
    })
  }), showAvatar && (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Avatar size'),
    value: attributes.avatarSize,
    options: avatarSizes,
    onChange: size => {
      setAttributes({
        avatarSize: Number(size)
      });
    }
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Show bio'),
    checked: showBio,
    onChange: () => setAttributes({
      showBio: !showBio
    })
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Link author name to author page'),
    checked: isLink,
    onChange: () => setAttributes({
      isLink: !isLink
    })
  }), isLink && (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Open in new tab'),
    onChange: value => setAttributes({
      linkTarget: value ? '_blank' : '_self'
    }),
    checked: linkTarget === '_blank'
  }))), (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_blockEditor.AlignmentControl, {
    value: textAlign,
    onChange: nextAlign => {
      setAttributes({
        textAlign: nextAlign
      });
    }
  })), (0, _react.createElement)("div", {
    ...blockProps
  }, showAvatar && authorDetails?.avatar_urls && (0, _react.createElement)("div", {
    className: "wp-block-post-author__avatar"
  }, (0, _react.createElement)("img", {
    width: attributes.avatarSize,
    src: authorDetails.avatar_urls[attributes.avatarSize],
    alt: authorDetails.name
  })), (0, _react.createElement)("div", {
    className: "wp-block-post-author__content"
  }, (!_blockEditor.RichText.isEmpty(byline) || isSelected) && (0, _react.createElement)(_blockEditor.RichText, {
    className: "wp-block-post-author__byline",
    "aria-label": (0, _i18n.__)('Post author byline text'),
    placeholder: (0, _i18n.__)('Write byline…'),
    value: byline,
    onChange: value => setAttributes({
      byline: value
    })
  }), (0, _react.createElement)("p", {
    className: "wp-block-post-author__name"
  }, isLink ? (0, _react.createElement)("a", {
    href: "#post-author-pseudo-link",
    onClick: event => event.preventDefault()
  }, authorName) : authorName), showBio && (0, _react.createElement)("p", {
    className: "wp-block-post-author__bio",
    dangerouslySetInnerHTML: {
      __html: authorDetails?.description
    }
  }))));
}
var _default = exports.default = PostAuthorEdit;
//# sourceMappingURL=edit.js.map