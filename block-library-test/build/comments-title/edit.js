"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = Edit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _coreData = require("@wordpress/core-data");
var _components = require("@wordpress/components");
var _element = require("@wordpress/element");
var _data = require("@wordpress/data");
var _apiFetch = _interopRequireDefault(require("@wordpress/api-fetch"));
var _url = require("@wordpress/url");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

function Edit({
  attributes: {
    textAlign,
    showPostTitle,
    showCommentsCount,
    level
  },
  setAttributes,
  context: {
    postType,
    postId
  }
}) {
  const TagName = 'h' + level;
  const [commentsCount, setCommentsCount] = (0, _element.useState)();
  const [rawTitle] = (0, _coreData.useEntityProp)('postType', postType, 'title', postId);
  const isSiteEditor = typeof postId === 'undefined';
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign
    })
  });
  const {
    threadCommentsDepth,
    threadComments,
    commentsPerPage,
    pageComments
  } = (0, _data.useSelect)(select => {
    const {
      getSettings
    } = select(_blockEditor.store);
    return getSettings().__experimentalDiscussionSettings;
  });
  (0, _element.useEffect)(() => {
    if (isSiteEditor) {
      // Match the number of comments that will be shown in the comment-template/edit.js placeholder

      const nestedCommentsNumber = threadComments ? Math.min(threadCommentsDepth, 3) - 1 : 0;
      const topLevelCommentsNumber = pageComments ? commentsPerPage : 3;
      const commentsNumber = parseInt(nestedCommentsNumber) + parseInt(topLevelCommentsNumber);
      setCommentsCount(Math.min(commentsNumber, 3));
      return;
    }
    const currentPostId = postId;
    (0, _apiFetch.default)({
      path: (0, _url.addQueryArgs)('/wp/v2/comments', {
        post: postId,
        _fields: 'id'
      }),
      method: 'HEAD',
      parse: false
    }).then(res => {
      // Stale requests will have the `currentPostId` of an older closure.
      if (currentPostId === postId) {
        setCommentsCount(parseInt(res.headers.get('X-WP-Total')));
      }
    }).catch(() => {
      setCommentsCount(0);
    });
  }, [postId]);
  const blockControls = (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_blockEditor.AlignmentControl, {
    value: textAlign,
    onChange: newAlign => setAttributes({
      textAlign: newAlign
    })
  }), (0, _react.createElement)(_blockEditor.HeadingLevelDropdown, {
    value: level,
    onChange: newLevel => setAttributes({
      level: newLevel
    })
  }));
  const inspectorControls = (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Show post title'),
    checked: showPostTitle,
    onChange: value => setAttributes({
      showPostTitle: value
    })
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Show comments count'),
    checked: showCommentsCount,
    onChange: value => setAttributes({
      showCommentsCount: value
    })
  })));
  const postTitle = isSiteEditor ? (0, _i18n.__)('“Post Title”') : `"${rawTitle}"`;
  let placeholder;
  if (showCommentsCount && commentsCount !== undefined) {
    if (showPostTitle) {
      if (commentsCount === 1) {
        /* translators: %s: Post title. */
        placeholder = (0, _i18n.sprintf)((0, _i18n.__)('One response to %s'), postTitle);
      } else {
        placeholder = (0, _i18n.sprintf)( /* translators: 1: Number of comments, 2: Post title. */
        (0, _i18n._n)('%1$s response to %2$s', '%1$s responses to %2$s', commentsCount), commentsCount, postTitle);
      }
    } else if (commentsCount === 1) {
      placeholder = (0, _i18n.__)('One response');
    } else {
      placeholder = (0, _i18n.sprintf)( /* translators: %s: Number of comments. */
      (0, _i18n._n)('%s response', '%s responses', commentsCount), commentsCount);
    }
  } else if (showPostTitle) {
    if (commentsCount === 1) {
      /* translators: %s: Post title. */
      placeholder = (0, _i18n.sprintf)((0, _i18n.__)('Response to %s'), postTitle);
    } else {
      /* translators: %s: Post title. */
      placeholder = (0, _i18n.sprintf)((0, _i18n.__)('Responses to %s'), postTitle);
    }
  } else if (commentsCount === 1) {
    placeholder = (0, _i18n.__)('Response');
  } else {
    placeholder = (0, _i18n.__)('Responses');
  }
  return (0, _react.createElement)(_react.Fragment, null, blockControls, inspectorControls, (0, _react.createElement)(TagName, {
    ...blockProps
  }, placeholder));
}
//# sourceMappingURL=edit.js.map