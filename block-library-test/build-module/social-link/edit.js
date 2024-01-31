import { createElement, Fragment } from "react";
/**
 * External dependencies
 */
import classNames from 'classnames';

/**
 * WordPress dependencies
 */
import { DELETE, BACKSPACE } from '@wordpress/keycodes';
import { useDispatch } from '@wordpress/data';
import { InspectorControls, URLPopover, URLInput, useBlockProps, store as blockEditorStore } from '@wordpress/block-editor';
import { useState } from '@wordpress/element';
import { Button, PanelBody, PanelRow, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { keyboardReturn } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { getIconBySite, getNameBySite } from './social-list';
const SocialLinkURLPopover = ({
  url,
  setAttributes,
  setPopover,
  popoverAnchor,
  clientId
}) => {
  const {
    removeBlock
  } = useDispatch(blockEditorStore);
  return createElement(URLPopover, {
    anchor: popoverAnchor,
    onClose: () => setPopover(false)
  }, createElement("form", {
    className: "block-editor-url-popover__link-editor",
    onSubmit: event => {
      event.preventDefault();
      setPopover(false);
    }
  }, createElement("div", {
    className: "block-editor-url-input"
  }, createElement(URLInput, {
    __nextHasNoMarginBottom: true,
    value: url,
    onChange: nextURL => setAttributes({
      url: nextURL
    }),
    placeholder: __('Enter address'),
    disableSuggestions: true,
    onKeyDown: event => {
      if (!!url || event.defaultPrevented || ![BACKSPACE, DELETE].includes(event.keyCode)) {
        return;
      }
      removeBlock(clientId);
    }
  })), createElement(Button, {
    icon: keyboardReturn,
    label: __('Apply'),
    type: "submit"
  })));
};
const SocialLinkEdit = ({
  attributes,
  context,
  isSelected,
  setAttributes,
  clientId
}) => {
  const {
    url,
    service,
    label,
    rel
  } = attributes;
  const {
    showLabels,
    iconColor,
    iconColorValue,
    iconBackgroundColor,
    iconBackgroundColorValue
  } = context;
  const [showURLPopover, setPopover] = useState(false);
  const classes = classNames('wp-social-link', 'wp-social-link-' + service, {
    'wp-social-link__is-incomplete': !url,
    [`has-${iconColor}-color`]: iconColor,
    [`has-${iconBackgroundColor}-background-color`]: iconBackgroundColor
  });

  // Use internal state instead of a ref to make sure that the component
  // re-renders when the popover's anchor updates.
  const [popoverAnchor, setPopoverAnchor] = useState(null);
  const IconComponent = getIconBySite(service);
  const socialLinkName = getNameBySite(service);
  const socialLinkLabel = label !== null && label !== void 0 ? label : socialLinkName;
  const blockProps = useBlockProps({
    className: classes,
    style: {
      color: iconColorValue,
      backgroundColor: iconBackgroundColorValue
    }
  });
  return createElement(Fragment, null, createElement(InspectorControls, null, createElement(PanelBody, {
    title: sprintf( /* translators: %s: name of the social service. */
    __('%s label'), socialLinkName),
    initialOpen: false
  }, createElement(PanelRow, null, createElement(TextControl, {
    __nextHasNoMarginBottom: true,
    label: __('Link label'),
    help: __('Briefly describe the link to help screen reader users.'),
    value: label || '',
    onChange: value => setAttributes({
      label: value
    })
  })))), createElement(InspectorControls, {
    group: "advanced"
  }, createElement(TextControl, {
    __nextHasNoMarginBottom: true,
    label: __('Link rel'),
    value: rel || '',
    onChange: value => setAttributes({
      rel: value
    })
  })), createElement("li", {
    ...blockProps
  }, createElement(Button, {
    className: "wp-block-social-link-anchor",
    ref: setPopoverAnchor,
    onClick: () => setPopover(true)
  }, createElement(IconComponent, null), createElement("span", {
    className: classNames('wp-block-social-link-label', {
      'screen-reader-text': !showLabels
    })
  }, socialLinkLabel), isSelected && showURLPopover && createElement(SocialLinkURLPopover, {
    url: url,
    setAttributes: setAttributes,
    setPopover: setPopover,
    popoverAnchor: popoverAnchor,
    clientId: clientId
  }))));
};
export default SocialLinkEdit;
//# sourceMappingURL=edit.js.map