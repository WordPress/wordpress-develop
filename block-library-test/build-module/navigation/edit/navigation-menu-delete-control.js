import { createElement, Fragment } from "react";
/**
 * WordPress dependencies
 */
import { Button, Modal, __experimentalHStack as HStack } from '@wordpress/components';
import { store as coreStore, useEntityId, useEntityProp } from '@wordpress/core-data';
import { useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
export default function NavigationMenuDeleteControl({
  onDelete
}) {
  const [isConfirmModalVisible, setIsConfirmModalVisible] = useState(false);
  const id = useEntityId('postType', 'wp_navigation');
  const [title] = useEntityProp('postType', 'wp_navigation', 'title');
  const {
    deleteEntityRecord
  } = useDispatch(coreStore);
  return createElement(Fragment, null, createElement(Button, {
    className: "wp-block-navigation-delete-menu-button",
    variant: "secondary",
    isDestructive: true,
    onClick: () => {
      setIsConfirmModalVisible(true);
    }
  }, __('Delete menu')), isConfirmModalVisible && createElement(Modal, {
    title: sprintf( /* translators: %s: the name of a menu to delete */
    __('Delete %s'), title),
    onRequestClose: () => setIsConfirmModalVisible(false)
  }, createElement("p", null, __('Are you sure you want to delete this navigation menu?')), createElement(HStack, {
    justify: "right"
  }, createElement(Button, {
    variant: "tertiary",
    onClick: () => {
      setIsConfirmModalVisible(false);
    }
  }, __('Cancel')), createElement(Button, {
    variant: "primary",
    onClick: () => {
      deleteEntityRecord('postType', 'wp_navigation', id, {
        force: true
      });
      onDelete(title);
    }
  }, __('Confirm')))));
}
//# sourceMappingURL=navigation-menu-delete-control.js.map