import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { PlainText, useBlockProps } from '@wordpress/block-editor';
import { useInstanceId } from '@wordpress/compose';
import { Icon, shortcode } from '@wordpress/icons';
export default function ShortcodeEdit({
  attributes,
  setAttributes
}) {
  const instanceId = useInstanceId(ShortcodeEdit);
  const inputId = `blocks-shortcode-input-${instanceId}`;
  return createElement("div", {
    ...useBlockProps({
      className: 'components-placeholder'
    })
  }, createElement("label", {
    htmlFor: inputId,
    className: "components-placeholder__label"
  }, createElement(Icon, {
    icon: shortcode
  }), __('Shortcode')), createElement(PlainText, {
    className: "blocks-shortcode__textarea",
    id: inputId,
    value: attributes.text,
    "aria-label": __('Shortcode text'),
    placeholder: __('Write shortcode here…'),
    onChange: text => setAttributes({
      text
    })
  }));
}
//# sourceMappingURL=edit.js.map