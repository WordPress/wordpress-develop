"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = PostTitleEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _blocks = require("@wordpress/blocks");
var _coreData = require("@wordpress/core-data");
var _hooks = require("../utils/hooks");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function PostTitleEdit({
  attributes: {
    level,
    textAlign,
    isLink,
    rel,
    linkTarget
  },
  setAttributes,
  context: {
    postType,
    postId,
    queryId
  },
  insertBlocksAfter
}) {
  const TagName = 'h' + level;
  const isDescendentOfQueryLoop = Number.isFinite(queryId);
  /**
   * Hack: useCanEditEntity may trigger an OPTIONS request to the REST API via the canUser resolver.
   * However, when the Post Title is a descendant of a Query Loop block, the title cannot be edited.
   * In order to avoid these unnecessary requests, we call the hook without
   * the proper data, resulting in returning early without making them.
   */
  const userCanEdit = (0, _hooks.useCanEditEntity)('postType', !isDescendentOfQueryLoop && postType, postId);
  const [rawTitle = '', setTitle, fullTitle] = (0, _coreData.useEntityProp)('postType', postType, 'title', postId);
  const [link] = (0, _coreData.useEntityProp)('postType', postType, 'link', postId);
  const onSplitAtEnd = () => {
    insertBlocksAfter((0, _blocks.createBlock)((0, _blocks.getDefaultBlockName)()));
  };
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign
    })
  });
  const blockEditingMode = (0, _blockEditor.useBlockEditingMode)();
  let titleElement = (0, _react.createElement)(TagName, {
    ...blockProps
  }, (0, _i18n.__)('Title'));
  if (postType && postId) {
    titleElement = userCanEdit ? (0, _react.createElement)(_blockEditor.PlainText, {
      tagName: TagName,
      placeholder: (0, _i18n.__)('No Title'),
      value: rawTitle,
      onChange: setTitle,
      __experimentalVersion: 2,
      __unstableOnSplitAtEnd: onSplitAtEnd,
      ...blockProps
    }) : (0, _react.createElement)(TagName, {
      ...blockProps,
      dangerouslySetInnerHTML: {
        __html: fullTitle?.rendered
      }
    });
  }
  if (isLink && postType && postId) {
    titleElement = userCanEdit ? (0, _react.createElement)(TagName, {
      ...blockProps
    }, (0, _react.createElement)(_blockEditor.PlainText, {
      tagName: "a",
      href: link,
      target: linkTarget,
      rel: rel,
      placeholder: !rawTitle.length ? (0, _i18n.__)('No Title') : null,
      value: rawTitle,
      onChange: setTitle,
      __experimentalVersion: 2,
      __unstableOnSplitAtEnd: onSplitAtEnd
    })) : (0, _react.createElement)(TagName, {
      ...blockProps
    }, (0, _react.createElement)("a", {
      href: link,
      target: linkTarget,
      rel: rel,
      onClick: event => event.preventDefault(),
      dangerouslySetInnerHTML: {
        __html: fullTitle?.rendered
      }
    }));
  }
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
  })), (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Make title a link'),
    onChange: () => setAttributes({
      isLink: !isLink
    }),
    checked: isLink
  }), isLink && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Open in new tab'),
    onChange: value => setAttributes({
      linkTarget: value ? '_blank' : '_self'
    }),
    checked: linkTarget === '_blank'
  }), (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Link rel'),
    value: rel,
    onChange: newRel => setAttributes({
      rel: newRel
    })
  })))), titleElement);
}
//# sourceMappingURL=edit.js.map