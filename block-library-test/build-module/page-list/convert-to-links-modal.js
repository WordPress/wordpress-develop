import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { Button, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
export const convertDescription = __("This navigation menu displays your website's pages. Editing it will enable you to add, delete, or reorder pages. However, new pages will no longer be added automatically.");
export function ConvertToLinksModal({
  onClick,
  onClose,
  disabled
}) {
  return createElement(Modal, {
    onRequestClose: onClose,
    title: __('Edit Page List'),
    className: 'wp-block-page-list-modal',
    aria: {
      describedby: 'wp-block-page-list-modal__description'
    }
  }, createElement("p", {
    id: 'wp-block-page-list-modal__description'
  }, convertDescription), createElement("div", {
    className: "wp-block-page-list-modal-buttons"
  }, createElement(Button, {
    variant: "tertiary",
    onClick: onClose
  }, __('Cancel')), createElement(Button, {
    variant: "primary",
    disabled: disabled,
    onClick: onClick
  }, __('Edit'))));
}
//# sourceMappingURL=convert-to-links-modal.js.map