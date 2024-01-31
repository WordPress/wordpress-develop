import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, useInnerBlocksProps, RecursionProvider, useHasRecursion, Warning } from '@wordpress/block-editor';
import { useEntityProp, useEntityBlockEditor, store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
/**
 * Internal dependencies
 */
import { useCanEditEntity } from '../utils/hooks';
function ReadOnlyContent({
  layoutClassNames,
  userCanEdit,
  postType,
  postId
}) {
  const [,, content] = useEntityProp('postType', postType, 'content', postId);
  const blockProps = useBlockProps({
    className: layoutClassNames
  });
  return content?.protected && !userCanEdit ? createElement("div", {
    ...blockProps
  }, createElement(Warning, null, __('This content is password protected.'))) : createElement("div", {
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
  const [blocks, onInput, onChange] = useEntityBlockEditor('postType', postType, {
    id: postId
  });
  const entityRecord = useSelect(select => {
    return select(coreStore).getEntityRecord('postType', postType, postId);
  }, [postType, postId]);
  const hasInnerBlocks = !!entityRecord?.content?.raw || blocks?.length;
  const initialInnerBlocks = [['core/paragraph']];
  const props = useInnerBlocksProps(useBlockProps({
    className: 'entry-content'
  }), {
    value: blocks,
    onInput,
    onChange,
    template: !hasInnerBlocks ? initialInnerBlocks : undefined
  });
  return createElement("div", {
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
  const userCanEdit = useCanEditEntity('postType', postType, postId);
  if (userCanEdit === undefined) {
    return null;
  }
  const isDescendentOfQueryLoop = Number.isFinite(queryId);
  const isEditable = userCanEdit && !isDescendentOfQueryLoop;
  return isEditable ? createElement(EditableContent, {
    ...props
  }) : createElement(ReadOnlyContent, {
    layoutClassNames: layoutClassNames,
    userCanEdit: userCanEdit,
    postType: postType,
    postId: postId
  });
}
function Placeholder({
  layoutClassNames
}) {
  const blockProps = useBlockProps({
    className: layoutClassNames
  });
  return createElement("div", {
    ...blockProps
  }, createElement("p", null, __('This is the Content block, it will display all the blocks in any single post or page.')), createElement("p", null, __('That might be a simple arrangement like consecutive paragraphs in a blog post, or a more elaborate composition that includes image galleries, videos, tables, columns, and any other block types.')), createElement("p", null, __('If there are any Custom Post Types registered at your site, the Content block can display the contents of those entries as well.')));
}
function RecursionError() {
  const blockProps = useBlockProps();
  return createElement("div", {
    ...blockProps
  }, createElement(Warning, null, __('Block cannot be rendered inside itself.')));
}
export default function PostContentEdit({
  context,
  __unstableLayoutClassNames: layoutClassNames
}) {
  const {
    postId: contextPostId,
    postType: contextPostType
  } = context;
  const hasAlreadyRendered = useHasRecursion(contextPostId);
  if (contextPostId && contextPostType && hasAlreadyRendered) {
    return createElement(RecursionError, null);
  }
  return createElement(RecursionProvider, {
    uniqueId: contextPostId
  }, contextPostId && contextPostType ? createElement(Content, {
    context: context,
    layoutClassNames: layoutClassNames
  }) : createElement(Placeholder, {
    layoutClassNames: layoutClassNames
  }));
}
//# sourceMappingURL=edit.js.map