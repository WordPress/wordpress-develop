import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { addQueryArgs } from '@wordpress/url';
import { Button, MenuItem } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
const ManageMenusButton = ({
  className = '',
  disabled,
  isMenuItem = false
}) => {
  let ComponentName = Button;
  if (isMenuItem) {
    ComponentName = MenuItem;
  }
  return createElement(ComponentName, {
    variant: "link",
    disabled: disabled,
    className: className,
    href: addQueryArgs('edit.php', {
      post_type: 'wp_navigation'
    })
  }, __('Manage menus'));
};
export default ManageMenusButton;
//# sourceMappingURL=manage-menus-button.js.map