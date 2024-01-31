import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
export default function CommentsInspectorControls({
  attributes: {
    tagName
  },
  setAttributes
}) {
  const htmlElementMessages = {
    section: __("The <section> element should represent a standalone portion of the document that can't be better represented by another element."),
    aside: __("The <aside> element should represent a portion of a document whose content is only indirectly related to the document's main content.")
  };
  return createElement(InspectorControls, null, createElement(InspectorControls, {
    group: "advanced"
  }, createElement(SelectControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: __('HTML element'),
    options: [{
      label: __('Default (<div>)'),
      value: 'div'
    }, {
      label: '<section>',
      value: 'section'
    }, {
      label: '<aside>',
      value: 'aside'
    }],
    value: tagName,
    onChange: value => setAttributes({
      tagName: value
    }),
    help: htmlElementMessages[tagName]
  })));
}
//# sourceMappingURL=comments-inspector-controls.js.map