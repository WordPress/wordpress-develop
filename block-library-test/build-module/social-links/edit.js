import { createElement, Fragment } from "react";
/**
 * External dependencies
 */
import classNames from 'classnames';

/**
 * WordPress dependencies
 */
import { useEffect, useRef } from '@wordpress/element';
import { BlockControls, useInnerBlocksProps, useBlockProps, InspectorControls, ContrastChecker, withColors, __experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown, __experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients } from '@wordpress/block-editor';
import { MenuGroup, MenuItem, PanelBody, ToggleControl, ToolbarDropdownMenu } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { check } from '@wordpress/icons';
const sizeOptions = [{
  name: __('Small'),
  value: 'has-small-icon-size'
}, {
  name: __('Normal'),
  value: 'has-normal-icon-size'
}, {
  name: __('Large'),
  value: 'has-large-icon-size'
}, {
  name: __('Huge'),
  value: 'has-huge-icon-size'
}];
export function SocialLinksEdit(props) {
  var _attributes$layout$or;
  const {
    clientId,
    attributes,
    iconBackgroundColor,
    iconColor,
    isSelected,
    setAttributes,
    setIconBackgroundColor,
    setIconColor
  } = props;
  const {
    iconBackgroundColorValue,
    customIconBackgroundColor,
    iconColorValue,
    openInNewTab,
    showLabels,
    size
  } = attributes;
  const logosOnly = attributes.className?.includes('is-style-logos-only');

  // Remove icon background color when logos only style is selected or
  // restore it when any other style is selected.
  const backgroundBackup = useRef({});
  useEffect(() => {
    if (logosOnly) {
      backgroundBackup.current = {
        iconBackgroundColor,
        iconBackgroundColorValue,
        customIconBackgroundColor
      };
      setAttributes({
        iconBackgroundColor: undefined,
        customIconBackgroundColor: undefined,
        iconBackgroundColorValue: undefined
      });
    } else {
      setAttributes({
        ...backgroundBackup.current
      });
    }
  }, [logosOnly]);
  const SocialPlaceholder = createElement("li", {
    className: "wp-block-social-links__social-placeholder"
  }, createElement("div", {
    className: "wp-block-social-links__social-placeholder-icons"
  }, createElement("div", {
    className: "wp-social-link wp-social-link-twitter"
  }), createElement("div", {
    className: "wp-social-link wp-social-link-facebook"
  }), createElement("div", {
    className: "wp-social-link wp-social-link-instagram"
  })));
  const SelectedSocialPlaceholder = createElement("li", {
    className: "wp-block-social-links__social-prompt"
  }, __('Click plus to add'));

  // Fallback color values are used maintain selections in case switching
  // themes and named colors in palette do not match.
  const className = classNames(size, {
    'has-visible-labels': showLabels,
    'has-icon-color': iconColor.color || iconColorValue,
    'has-icon-background-color': iconBackgroundColor.color || iconBackgroundColorValue
  });
  const blockProps = useBlockProps({
    className
  });
  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    placeholder: isSelected ? SelectedSocialPlaceholder : SocialPlaceholder,
    templateLock: false,
    orientation: (_attributes$layout$or = attributes.layout?.orientation) !== null && _attributes$layout$or !== void 0 ? _attributes$layout$or : 'horizontal',
    __experimentalAppenderTagName: 'li'
  });
  const POPOVER_PROPS = {
    position: 'bottom right'
  };
  const colorSettings = [{
    // Use custom attribute as fallback to prevent loss of named color selection when
    // switching themes to a new theme that does not have a matching named color.
    value: iconColor.color || iconColorValue,
    onChange: colorValue => {
      setIconColor(colorValue);
      setAttributes({
        iconColorValue: colorValue
      });
    },
    label: __('Icon color'),
    resetAllFilter: () => {
      setIconColor(undefined);
      setAttributes({
        iconColorValue: undefined
      });
    }
  }];
  if (!logosOnly) {
    colorSettings.push({
      // Use custom attribute as fallback to prevent loss of named color selection when
      // switching themes to a new theme that does not have a matching named color.
      value: iconBackgroundColor.color || iconBackgroundColorValue,
      onChange: colorValue => {
        setIconBackgroundColor(colorValue);
        setAttributes({
          iconBackgroundColorValue: colorValue
        });
      },
      label: __('Icon background'),
      resetAllFilter: () => {
        setIconBackgroundColor(undefined);
        setAttributes({
          iconBackgroundColorValue: undefined
        });
      }
    });
  }
  const colorGradientSettings = useMultipleOriginColorsAndGradients();
  return createElement(Fragment, null, createElement(BlockControls, {
    group: "other"
  }, createElement(ToolbarDropdownMenu, {
    label: __('Size'),
    text: __('Size'),
    icon: null,
    popoverProps: POPOVER_PROPS
  }, ({
    onClose
  }) => createElement(MenuGroup, null, sizeOptions.map(entry => {
    return createElement(MenuItem, {
      icon: (size === entry.value || !size && entry.value === 'has-normal-icon-size') && check,
      isSelected: size === entry.value,
      key: entry.value,
      onClick: () => {
        setAttributes({
          size: entry.value
        });
      },
      onClose: onClose,
      role: "menuitemradio"
    }, entry.name);
  })))), createElement(InspectorControls, null, createElement(PanelBody, {
    title: __('Settings')
  }, createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Open links in new tab'),
    checked: openInNewTab,
    onChange: () => setAttributes({
      openInNewTab: !openInNewTab
    })
  }), createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Show labels'),
    checked: showLabels,
    onChange: () => setAttributes({
      showLabels: !showLabels
    })
  }))), colorGradientSettings.hasColorsOrGradients && createElement(InspectorControls, {
    group: "color"
  }, colorSettings.map(({
    onChange,
    label,
    value,
    resetAllFilter
  }) => createElement(ColorGradientSettingsDropdown, {
    key: `social-links-color-${label}`,
    __experimentalIsRenderedInSidebar: true,
    settings: [{
      colorValue: value,
      label,
      onColorChange: onChange,
      isShownByDefault: true,
      resetAllFilter,
      enableAlpha: true
    }],
    panelId: clientId,
    ...colorGradientSettings
  })), !logosOnly && createElement(ContrastChecker, {
    textColor: iconColorValue,
    backgroundColor: iconBackgroundColorValue,
    isLargeText: false
  })), createElement("ul", {
    ...innerBlocksProps
  }));
}
const iconColorAttributes = {
  iconColor: 'icon-color',
  iconBackgroundColor: 'icon-background-color'
};
export default withColors(iconColorAttributes)(SocialLinksEdit);
//# sourceMappingURL=edit.js.map