"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = PostContentEdit;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _coreData = require("@wordpress/core-data");
var _data = require("@wordpress/data");
var _hooks = require("../utils/hooks");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function ReadOnlyContent({
  layoutClassNames,
  userCanEdit,
  postType,
  postId
}) {
  const [,, content] = (0, _coreData.useEntityProp)('postType', postType, 'content', postId);
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: layoutClassNames
  });
  return content?.protected && !userCanEdit ? (0, _react.createElement)("div", {
    ...blockProps
  }, (0, _react.createElement)(_blockEditor.Warning, null, (0, _i18n.__)('This content is password protected.'))) : (0, _react.createElement)("div", {
    ...blockProps,
    dangerouslySetInnerHTML: {
      __html: content?.rendered
    }
  });
}
function EditableContent({
  context = {}
}) {
  const {
    postType,
    postId
  } = context;
  const [blocks, onInput, onChange] = (0, _coreData.useEntityBlockEditor)('postType', postType, {
    id: postId
  });
  const entityRecord = (0, _data.useSelect)(select => {
    return select(_coreData.store).getEntityRecord('postType', postType, postId);
  }, [postType, postId]);
  const hasInnerBlocks = !!entityRecord?.content?.raw || blocks?.length;
  const initialInnerBlocks = [['core/paragraph']];
  const props = (0, _blockEditor.useInnerBlocksProps)((0, _blockEditor.useBlockProps)({
    className: 'entry-content'
  }), {
    value: blocks,
    onInput,
    onChange,
    template: !hasInnerBlocks ? initialInnerBlocks : undefined
  });
  return (0, _react.createElement)("div", {
    ...props
  });
}
function Content(props) {
  const {
    context: {
      queryId,
      postType,
      postId
    } = {},
    layoutClassNames
  } = props;
  const userCanEdit = (0, _hooks.useCanEditEntity)('postType', postType, postId);
  if (userCanEdit === undefined) {
    return null;
  }
  const isDescendentOfQueryLoop = Number.isFinite(queryId);
  const isEditable = userCanEdit && !isDescendentOfQueryLoop;
  return isEditable ? (0, _react.createElement)(EditableContent, {
    ...props
  }) : (0, _react.createElement)(ReadOnlyContent, {
    layoutClassNames: layoutClassNames,
    userCanEdit: userCanEdit,
    postType: postType,
    postId: postId
  });
}
function Placeholder({
  layoutClassNames
}) {
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: layoutClassNames
  });
  return (0, _react.createElement)("div", {
    ...blockProps
  }, (0, _react.createElement)("p", null, (0, _i18n.__)('This is the Content block, it will display all the blocks in any single post or page.')), (0, _react.createElement)("p", null, (0, _i18n.__)('That might be a simple arrangement like consecutive paragraphs in a blog post, or a more elaborate composition that includes image galleries, videos, tables, columns, and any other block types.')), (0, _react.createElement)("p", null, (0, _i18n.__)('If there are any Custom Post Types registered at your site, the Content block can display the contents of those entries as well.')));
}
function RecursionError() {
  const blockProps = (0, _blockEditor.useBlockProps)();
  return (0, _react.createElement)("div", {
    ...blockProps
  }, (0, _react.createElement)(_blockEditor.Warning, null, (0, _i18n.__)('Block cannot be rendered inside itself.')));
}
function PostContentEdit({
  context,
  __unstableLayoutClassNames: layoutClassNames
}) {
  const {
    postId: contextPostId,
    postType: contextPostType
  } = context;
  const hasAlreadyRendered = (0, _blockEditor.useHasRecursion)(contextPostId);
  if (contextPostId && contextPostType && hasAlreadyRendered) {
    return (0, _react.createElement)(RecursionError, null);
  }
  return (0, _react.createElement)(_blockEditor.RecursionProvider, {
    uniqueId: contextPostId
  }, contextPostId && contextPostType ? (0, _react.createElement)(Content, {
    context: context,
    layoutClassNames: layoutClassNames
  }) : (0, _react.createElement)(Placeholder, {
    layoutClassNames: layoutClassNames
  }));
}
//# sourceMappingURL=edit.js.map