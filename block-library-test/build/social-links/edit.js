"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.SocialLinksEdit = SocialLinksEdit;
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _element = require("@wordpress/element");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

const sizeOptions = [{
  name: (0, _i18n.__)('Small'),
  value: 'has-small-icon-size'
}, {
  name: (0, _i18n.__)('Normal'),
  value: 'has-normal-icon-size'
}, {
  name: (0, _i18n.__)('Large'),
  value: 'has-large-icon-size'
}, {
  name: (0, _i18n.__)('Huge'),
  value: 'has-huge-icon-size'
}];
function SocialLinksEdit(props) {
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
  const backgroundBackup = (0, _element.useRef)({});
  (0, _element.useEffect)(() => {
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
  const SocialPlaceholder = (0, _react.createElement)("li", {
    className: "wp-block-social-links__social-placeholder"
  }, (0, _react.createElement)("div", {
    className: "wp-block-social-links__social-placeholder-icons"
  }, (0, _react.createElement)("div", {
    className: "wp-social-link wp-social-link-twitter"
  }), (0, _react.createElement)("div", {
    className: "wp-social-link wp-social-link-facebook"
  }), (0, _react.createElement)("div", {
    className: "wp-social-link wp-social-link-instagram"
  })));
  const SelectedSocialPlaceholder = (0, _react.createElement)("li", {
    className: "wp-block-social-links__social-prompt"
  }, (0, _i18n.__)('Click plus to add'));

  // Fallback color values are used maintain selections in case switching
  // themes and named colors in palette do not match.
  const className = (0, _classnames.default)(size, {
    'has-visible-labels': showLabels,
    'has-icon-color': iconColor.color || iconColorValue,
    'has-icon-background-color': iconBackgroundColor.color || iconBackgroundColorValue
  });
  const blockProps = (0, _blockEditor.useBlockProps)({
    className
  });
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(blockProps, {
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
    label: (0, _i18n.__)('Icon color'),
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
      label: (0, _i18n.__)('Icon background'),
      resetAllFilter: () => {
        setIconBackgroundColor(undefined);
        setAttributes({
          iconBackgroundColorValue: undefined
        });
      }
    });
  }
  const colorGradientSettings = (0, _blockEditor.__experimentalUseMultipleOriginColorsAndGradients)();
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "other"
  }, (0, _react.createElement)(_components.ToolbarDropdownMenu, {
    label: (0, _i18n.__)('Size'),
    text: (0, _i18n.__)('Size'),
    icon: null,
    popoverProps: POPOVER_PROPS
  }, ({
    onClose
  }) => (0, _react.createElement)(_components.MenuGroup, null, sizeOptions.map(entry => {
    return (0, _react.createElement)(_components.MenuItem, {
      icon: (size === entry.value || !size && entry.value === 'has-normal-icon-size') && _icons.check,
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
  })))), (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Open links in new tab'),
    checked: openInNewTab,
    onChange: () => setAttributes({
      openInNewTab: !openInNewTab
    })
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Show labels'),
    checked: showLabels,
    onChange: () => setAttributes({
      showLabels: !showLabels
    })
  }))), colorGradientSettings.hasColorsOrGradients && (0, _react.createElement)(_blockEditor.InspectorControls, {
    group: "color"
  }, colorSettings.map(({
    onChange,
    label,
    value,
    resetAllFilter
  }) => (0, _react.createElement)(_blockEditor.__experimentalColorGradientSettingsDropdown, {
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
  })), !logosOnly && (0, _react.createElement)(_blockEditor.ContrastChecker, {
    textColor: iconColorValue,
    backgroundColor: iconBackgroundColorValue,
    isLargeText: false
  })), (0, _react.createElement)("ul", {
    ...innerBlocksProps
  }));
}
const iconColorAttributes = {
  iconColor: 'icon-color',
  iconBackgroundColor: 'icon-background-color'
};
var _default = exports.default = (0, _blockEditor.withColors)(iconColorAttributes)(SocialLinksEdit);
//# sourceMappingURL=edit.js.map