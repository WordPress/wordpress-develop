"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = CommentTemplateEdit;
var _react = require("react");
var _element = require("@wordpress/element");
var _data = require("@wordpress/data");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _coreData = require("@wordpress/core-data");
var _hooks = require("./hooks");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const TEMPLATE = [['core/avatar'], ['core/comment-author-name'], ['core/comment-date'], ['core/comment-content'], ['core/comment-reply-link'], ['core/comment-edit-link']];

/**
 * Function that returns a comment structure that will be rendered with default placehoders.
 *
 * Each comment has a `commentId` property that is always a negative number in
 * case of the placeholders. This is to ensure that the comment does not
 * conflict with the actual (real) comments.
 *
 * @param {Object}  settings                       Discussion Settings.
 * @param {number}  [settings.perPage]             - Comments per page setting or block attribute.
 * @param {boolean} [settings.pageComments]        - Enable break comments into pages setting.
 * @param {boolean} [settings.threadComments]      - Enable threaded (nested) comments setting.
 * @param {number}  [settings.threadCommentsDepth] - Level deep of threaded comments.
 *
 * @typedef {{id: null, children: EmptyComment[]}} EmptyComment
 * @return {EmptyComment[]}                 		Inner blocks of the Comment Template
 */
const getCommentsPlaceholder = ({
  perPage,
  pageComments,
  threadComments,
  threadCommentsDepth
}) => {
  // Limit commentsDepth to 3
  const commentsDepth = !threadComments ? 1 : Math.min(threadCommentsDepth, 3);
  const buildChildrenComment = commentsLevel => {
    // Render children comments until commentsDepth is reached
    if (commentsLevel < commentsDepth) {
      const nextLevel = commentsLevel + 1;
      return [{
        commentId: -(commentsLevel + 3),
        children: buildChildrenComment(nextLevel)
      }];
    }
    return [];
  };

  // Add the first comment and its children
  const placeholderComments = [{
    commentId: -1,
    children: buildChildrenComment(1)
  }];

  // Add a second comment unless the break comments setting is active and set to less than 2, and there is one nested comment max
  if ((!pageComments || perPage >= 2) && commentsDepth < 3) {
    placeholderComments.push({
      commentId: -2,
      children: []
    });
  }

  // Add a third comment unless the break comments setting is active and set to less than 3, and there aren't nested comments
  if ((!pageComments || perPage >= 3) && commentsDepth < 2) {
    placeholderComments.push({
      commentId: -3,
      children: []
    });
  }

  // In case that the value is set but larger than 3 we truncate it to 3.
  return placeholderComments;
};

/**
 * Component which renders the inner blocks of the Comment Template.
 *
 * @param {Object} props                      Component props.
 * @param {Array}  [props.comment]            - A comment object.
 * @param {Array}  [props.activeCommentId]    - The ID of the comment that is currently active.
 * @param {Array}  [props.setActiveCommentId] - The setter for activeCommentId.
 * @param {Array}  [props.firstCommentId]     - ID of the first comment in the array.
 * @param {Array}  [props.blocks]             - Array of blocks returned from
 *                                            getBlocks() in parent .
 * @return {Element}                 		Inner blocks of the Comment Template
 */
function CommentTemplateInnerBlocks({
  comment,
  activeCommentId,
  setActiveCommentId,
  firstCommentId,
  blocks
}) {
  const {
    children,
    ...innerBlocksProps
  } = (0, _blockEditor.useInnerBlocksProps)({}, {
    template: TEMPLATE
  });
  return (0, _react.createElement)("li", {
    ...innerBlocksProps
  }, comment.commentId === (activeCommentId || firstCommentId) ? children : null, (0, _react.createElement)(MemoizedCommentTemplatePreview, {
    blocks: blocks,
    commentId: comment.commentId,
    setActiveCommentId: setActiveCommentId,
    isHidden: comment.commentId === (activeCommentId || firstCommentId)
  }), comment?.children?.length > 0 ? (0, _react.createElement)(CommentsList, {
    comments: comment.children,
    activeCommentId: activeCommentId,
    setActiveCommentId: setActiveCommentId,
    blocks: blocks,
    firstCommentId: firstCommentId
  }) : null);
}
const CommentTemplatePreview = ({
  blocks,
  commentId,
  setActiveCommentId,
  isHidden
}) => {
  const blockPreviewProps = (0, _blockEditor.__experimentalUseBlockPreview)({
    blocks
  });
  const handleOnClick = () => {
    setActiveCommentId(commentId);
  };

  // We have to hide the preview block if the `comment` props points to
  // the curently active block!

  // Or, to put it differently, every preview block is visible unless it is the
  // currently active block - in this case we render its inner blocks.
  const style = {
    display: isHidden ? 'none' : undefined
  };
  return (0, _react.createElement)("div", {
    ...blockPreviewProps,
    tabIndex: 0,
    role: "button",
    style: style
    // eslint-disable-next-line jsx-a11y/no-noninteractive-element-to-interactive-role
    ,
    onClick: handleOnClick,
    onKeyPress: handleOnClick
  });
};
const MemoizedCommentTemplatePreview = (0, _element.memo)(CommentTemplatePreview);

/**
 * Component that renders a list of (nested) comments. It is called recursively.
 *
 * @param {Object} props                      Component props.
 * @param {Array}  [props.comments]           - Array of comment objects.
 * @param {Array}  [props.blockProps]         - Props from parent's `useBlockProps()`.
 * @param {Array}  [props.activeCommentId]    - The ID of the comment that is currently active.
 * @param {Array}  [props.setActiveCommentId] - The setter for activeCommentId.
 * @param {Array}  [props.blocks]             - Array of blocks returned from getBlocks() in parent.
 * @param {Object} [props.firstCommentId]     - The ID of the first comment in the array of
 *                                            comment objects.
 * @return {Element}                 		List of comments.
 */
const CommentsList = ({
  comments,
  blockProps,
  activeCommentId,
  setActiveCommentId,
  blocks,
  firstCommentId
}) => (0, _react.createElement)("ol", {
  ...blockProps
}, comments && comments.map(({
  commentId,
  ...comment
}, index) => (0, _react.createElement)(_blockEditor.BlockContextProvider, {
  key: comment.commentId || index,
  value: {
    // If the commentId is negative it means that this comment is a
    // "placeholder" and that the block is most likely being used in the
    // site editor. In this case, we have to set the commentId to `null`
    // because otherwise the (non-existent) comment with a negative ID
    // would be reqested from the REST API.
    commentId: commentId < 0 ? null : commentId
  }
}, (0, _react.createElement)(CommentTemplateInnerBlocks, {
  comment: {
    commentId,
    ...comment
  },
  activeCommentId: activeCommentId,
  setActiveCommentId: setActiveCommentId,
  blocks: blocks,
  firstCommentId: firstCommentId
}))));
function CommentTemplateEdit({
  clientId,
  context: {
    postId
  }
}) {
  const blockProps = (0, _blockEditor.useBlockProps)();
  const [activeCommentId, setActiveCommentId] = (0, _element.useState)();
  const {
    commentOrder,
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
  const commentQuery = (0, _hooks.useCommentQueryArgs)({
    postId
  });
  const {
    topLevelComments,
    blocks
  } = (0, _data.useSelect)(select => {
    const {
      getEntityRecords
    } = select(_coreData.store);
    const {
      getBlocks
    } = select(_blockEditor.store);
    return {
      // Request only top-level comments. Replies are embedded.
      topLevelComments: commentQuery ? getEntityRecords('root', 'comment', commentQuery) : null,
      blocks: getBlocks(clientId)
    };
  }, [clientId, commentQuery]);

  // Generate a tree structure of comment IDs.
  let commentTree = (0, _hooks.useCommentTree)(
  // Reverse the order of top comments if needed.
  commentOrder === 'desc' && topLevelComments ? [...topLevelComments].reverse() : topLevelComments);
  if (!topLevelComments) {
    return (0, _react.createElement)("p", {
      ...blockProps
    }, (0, _react.createElement)(_components.Spinner, null));
  }
  if (!postId) {
    commentTree = getCommentsPlaceholder({
      perPage: commentsPerPage,
      pageComments,
      threadComments,
      threadCommentsDepth
    });
  }
  if (!commentTree.length) {
    return (0, _react.createElement)("p", {
      ...blockProps
    }, (0, _i18n.__)('No results found.'));
  }
  return (0, _react.createElement)(CommentsList, {
    comments: commentTree,
    blockProps: blockProps,
    blocks: blocks,
    activeCommentId: activeCommentId,
    setActiveCommentId: setActiveCommentId,
    firstCommentId: commentTree[0]?.commentId
  });
}
//# sourceMappingURL=edit.js.map