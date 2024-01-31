import { createElement, Fragment } from "react";
/**
 * Internal dependencies
 */
import EmbedLinkSettings from './embed-link-settings';
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { InspectorControls } from '@wordpress/block-editor';
import { useDispatch } from '@wordpress/data';
// eslint-disable-next-line no-restricted-imports
import { store as editPostStore } from '@wordpress/edit-post';
function getResponsiveHelp(checked) {
  return checked ? __('This embed will preserve its aspect ratio when the browser is resized.') : __('This embed may not preserve its aspect ratio when the browser is resized.');
}
const EmbedControls = ({
  blockSupportsResponsive,
  themeSupportsResponsive,
  allowResponsive,
  toggleResponsive,
  url,
  linkLabel,
  onEditURL
}) => {
  const {
    closeGeneralSidebar: closeSettingsBottomSheet
  } = useDispatch(editPostStore);
  return createElement(Fragment, null, createElement(InspectorControls, null, themeSupportsResponsive && blockSupportsResponsive && createElement(PanelBody, {
    title: __('Media settings')
  }, createElement(ToggleControl, {
    label: __('Resize for smaller devices'),
    checked: allowResponsive,
    help: getResponsiveHelp,
    onChange: toggleResponsive
  })), createElement(PanelBody, {
    title: __('Link settings')
  }, createElement(EmbedLinkSettings, {
    value: url,
    label: linkLabel,
    onSubmit: value => {
      closeSettingsBottomSheet();
      onEditURL(value);
    }
  }))));
};
export default EmbedControls;
//# sourceMappingURL=embed-controls.native.js.map