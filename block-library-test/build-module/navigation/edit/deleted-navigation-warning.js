import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { Warning } from '@wordpress/block-editor';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
function DeletedNavigationWarning({
  onCreateNew
}) {
  return createElement(Warning, null, createInterpolateElement(__('Navigation menu has been deleted or is unavailable. <button>Create a new menu?</button>'), {
    button: createElement(Button, {
      onClick: onCreateNew,
      variant: "link"
    })
  }));
}
export default DeletedNavigationWarning;
//# sourceMappingURL=deleted-navigation-warning.js.map