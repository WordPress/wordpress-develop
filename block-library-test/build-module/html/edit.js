import { createElement, Fragment } from "react";
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useContext, useState } from '@wordpress/element';
import { BlockControls, PlainText, useBlockProps } from '@wordpress/block-editor';
import { ToolbarButton, Disabled, ToolbarGroup, VisuallyHidden } from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';

/**
 * Internal dependencies
 */
import Preview from './preview';
export default function HTMLEdit({
  attributes,
  setAttributes,
  isSelected
}) {
  const [isPreview, setIsPreview] = useState();
  const isDisabled = useContext(Disabled.Context);
  const instanceId = useInstanceId(HTMLEdit, 'html-edit-desc');
  function switchToPreview() {
    setIsPreview(true);
  }
  function switchToHTML() {
    setIsPreview(false);
  }
  const blockProps = useBlockProps({
    className: 'block-library-html__edit',
    'aria-describedby': isPreview ? instanceId : undefined
  });
  return createElement("div", {
    ...blockProps
  }, createElement(BlockControls, null, createElement(ToolbarGroup, null, createElement(ToolbarButton, {
    className: "components-tab-button",
    isPressed: !isPreview,
    onClick: switchToHTML
  }, "HTML"), createElement(ToolbarButton, {
    className: "components-tab-button",
    isPressed: isPreview,
    onClick: switchToPreview
  }, __('Preview')))), isPreview || isDisabled ? createElement(Fragment, null, createElement(Preview, {
    content: attributes.content,
    isSelected: isSelected
  }), createElement(VisuallyHidden, {
    id: instanceId
  }, __('HTML preview is not yet fully accessible. Please switch screen reader to virtualized mode to navigate the below iFrame.'))) : createElement(PlainText, {
    value: attributes.content,
    onChange: content => setAttributes({
      content
    }),
    placeholder: __('Write HTML…'),
    "aria-label": __('HTML')
  }));
}
//# sourceMappingURL=edit.js.map