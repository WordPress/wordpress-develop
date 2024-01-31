import { createElement, Fragment } from "react";
/**
 * WordPress dependencies
 */
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
export default function LoginOutEdit({
  attributes,
  setAttributes
}) {
  const {
    displayLoginAsForm,
    redirectToCurrent
  } = attributes;
  return createElement(Fragment, null, createElement(InspectorControls, null, createElement(PanelBody, {
    title: __('Settings')
  }, createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Display login as form'),
    checked: displayLoginAsForm,
    onChange: () => setAttributes({
      displayLoginAsForm: !displayLoginAsForm
    })
  }), createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Redirect to current URL'),
    checked: redirectToCurrent,
    onChange: () => setAttributes({
      redirectToCurrent: !redirectToCurrent
    })
  }))), createElement("div", {
    ...useBlockProps({
      className: 'logged-in'
    })
  }, createElement("a", {
    href: "#login-pseudo-link"
  }, __('Log out'))));
}
//# sourceMappingURL=edit.js.map