import { createElement, Fragment } from "react";
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { ToolbarButton, PanelBody, ToggleControl, ToolbarGroup } from '@wordpress/components';
import { BlockControls, InspectorControls } from '@wordpress/block-editor';
import { edit } from '@wordpress/icons';
function getResponsiveHelp(checked) {
  return checked ? __('This embed will preserve its aspect ratio when the browser is resized.') : __('This embed may not preserve its aspect ratio when the browser is resized.');
}
const EmbedControls = ({
  blockSupportsResponsive,
  showEditButton,
  themeSupportsResponsive,
  allowResponsive,
  toggleResponsive,
  switchBackToURLInput
}) => createElement(Fragment, null, createElement(BlockControls, null, createElement(ToolbarGroup, null, showEditButton && createElement(ToolbarButton, {
  className: "components-toolbar__control",
  label: __('Edit URL'),
  icon: edit,
  onClick: switchBackToURLInput
}))), themeSupportsResponsive && blockSupportsResponsive && createElement(InspectorControls, null, createElement(PanelBody, {
  title: __('Media settings'),
  className: "blocks-responsive"
}, createElement(ToggleControl, {
  __nextHasNoMarginBottom: true,
  label: __('Resize for smaller devices'),
  checked: allowResponsive,
  help: getResponsiveHelp,
  onChange: toggleResponsive
}))));
export default EmbedControls;
//# sourceMappingURL=embed-controls.js.map