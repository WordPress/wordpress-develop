"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = SearchEdit;
var _react = require("react");
var _reactNative = require("react-native");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _element = require("@wordpress/element");
var _compose = require("@wordpress/compose");
var _style = _interopRequireDefault(require("./style.scss"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

/**
 * Constants
 */
const MIN_BUTTON_WIDTH = 75;
const MARGINS = _style.default.widthMargin?.marginLeft + _style.default.widthMargin?.paddingLeft;
const BUTTON_OPTIONS = [{
  value: 'button-inside',
  label: (0, _i18n.__)('Button inside')
}, {
  value: 'button-outside',
  label: (0, _i18n.__)('Button outside')
}, {
  value: 'no-button',
  label: (0, _i18n.__)('No button')
}];
function useIsScreenReaderEnabled() {
  const [isScreenReaderEnabled, setIsScreenReaderEnabled] = (0, _element.useState)(false);
  (0, _element.useEffect)(() => {
    let mounted = true;
    const changeListener = _reactNative.AccessibilityInfo.addEventListener('screenReaderChanged', enabled => setIsScreenReaderEnabled(enabled));
    _reactNative.AccessibilityInfo.isScreenReaderEnabled().then(screenReaderEnabled => {
      if (mounted && screenReaderEnabled) {
        setIsScreenReaderEnabled(screenReaderEnabled);
      }
    });
    return () => {
      mounted = false;
      changeListener.remove();
    };
  }, []);
  return isScreenReaderEnabled;
}
function SearchEdit({
  onFocus,
  isSelected,
  attributes,
  setAttributes,
  className,
  blockWidth,
  style
}) {
  const [isButtonSelected, setIsButtonSelected] = (0, _element.useState)(false);
  const [isLabelSelected, setIsLabelSelected] = (0, _element.useState)(false);
  const [isPlaceholderSelected, setIsPlaceholderSelected] = (0, _element.useState)(false);
  const [isLongButton, setIsLongButton] = (0, _element.useState)(false);
  const [buttonWidth, setButtonWidth] = (0, _element.useState)(MIN_BUTTON_WIDTH);
  const isScreenReaderEnabled = useIsScreenReaderEnabled();
  const textInputRef = (0, _element.useRef)(null);
  const {
    label,
    showLabel,
    buttonPosition,
    buttonUseIcon,
    placeholder,
    buttonText
  } = attributes;

  /*
   * Called when the value of isSelected changes. Blurs the PlainText component
   * used by the placeholder when this block loses focus.
   */
  (0, _element.useEffect)(() => {
    if (hasTextInput() && isPlaceholderSelected && !isSelected) {
      textInputRef.current.blur();
    }
  }, [isSelected]);
  (0, _element.useEffect)(() => {
    const maxButtonWidth = Math.floor(blockWidth / 2 - MARGINS);
    const tempIsLongButton = buttonWidth > maxButtonWidth;

    // Update this value only if it has changed to avoid flickering.
    if (isLongButton !== tempIsLongButton) {
      setIsLongButton(tempIsLongButton);
    }
  }, [blockWidth, buttonWidth]);
  const hasTextInput = () => {
    return textInputRef && textInputRef.current;
  };
  const onLayoutButton = ({
    nativeEvent
  }) => {
    const {
      width
    } = nativeEvent?.layout;
    if (width) {
      setButtonWidth(width);
    }
  };
  const getBlockClassNames = () => {
    return (0, _classnames.default)(className, 'button-inside' === buttonPosition ? 'wp-block-search__button-inside' : undefined, 'button-outside' === buttonPosition ? 'wp-block-search__button-outside' : undefined, 'no-button' === buttonPosition ? 'wp-block-search__no-button' : undefined, 'button-only' === buttonPosition ? 'wp-block-search__button-only' : undefined, !buttonUseIcon && 'no-button' !== buttonPosition ? 'wp-block-search__text-button' : undefined, buttonUseIcon && 'no-button' !== buttonPosition ? 'wp-block-search__icon-button' : undefined);
  };
  const getSelectedButtonPositionLabel = option => {
    switch (option) {
      case 'button-inside':
        return (0, _i18n.__)('Inside');
      case 'button-outside':
        return (0, _i18n.__)('Outside');
      case 'no-button':
        return (0, _i18n.__)('No button');
    }
  };
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: getBlockClassNames()
  });
  const controls = (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Search settings')
  }, (0, _react.createElement)(_components.ToggleControl, {
    label: (0, _i18n.__)('Hide search heading'),
    checked: !showLabel,
    onChange: () => {
      setAttributes({
        showLabel: !showLabel
      });
    }
  }), (0, _react.createElement)(_components.SelectControl, {
    label: (0, _i18n.__)('Button position'),
    value: getSelectedButtonPositionLabel(buttonPosition),
    onChange: position => {
      setAttributes({
        buttonPosition: position
      });
    },
    options: BUTTON_OPTIONS,
    hideCancelButton: true
  }), buttonPosition !== 'no-button' && (0, _react.createElement)(_components.ToggleControl, {
    label: (0, _i18n.__)('Use icon button'),
    checked: buttonUseIcon,
    onChange: () => {
      setAttributes({
        buttonUseIcon: !buttonUseIcon
      });
    }
  })));
  const isButtonInside = buttonPosition === 'button-inside';
  const borderStyle = (0, _compose.usePreferredColorSchemeStyle)(_style.default.border, _style.default.borderDark);
  const inputStyle = [!isButtonInside && borderStyle, (0, _compose.usePreferredColorSchemeStyle)(_style.default.plainTextInput, _style.default.plainTextInputDark), style?.baseColors?.color && {
    color: style?.baseColors?.color?.text
  }];
  const placeholderStyle = {
    ...(0, _compose.usePreferredColorSchemeStyle)(_style.default.plainTextPlaceholder, _style.default.plainTextPlaceholderDark),
    ...(style?.baseColors?.color && {
      color: style?.baseColors?.color?.text
    })
  };
  const searchBarStyle = [_style.default.searchBarContainer, isButtonInside && borderStyle, isLongButton && {
    flexDirection: 'column'
  }];

  /**
   * If a screenreader is enabled, create a descriptive label for this field. If
   * not, return a label that is used during automated UI tests.
   *
   * @return {string} The accessibilityLabel for the Search Button
   */
  const getAccessibilityLabelForButton = () => {
    if (!isScreenReaderEnabled) {
      return 'search-block-button';
    }
    return `${(0, _i18n.__)('Search button. Current button text is')} ${buttonText}`;
  };

  /**
   * If a screenreader is enabled, create a descriptive label for this field. If
   * not, return a label that is used during automated UI tests.
   *
   * @return {string} The accessibilityLabel for the Search Input
   * 					 placeholder field.
   */
  const getAccessibilityLabelForPlaceholder = () => {
    if (!isScreenReaderEnabled) {
      return 'search-block-input';
    }
    const title = (0, _i18n.__)('Search input field.');
    const description = placeholder ? `${(0, _i18n.__)('Current placeholder text is')} ${placeholder}` : (0, _i18n.__)('No custom placeholder set');
    return `${title} ${description}`;
  };

  /**
   * If a screenreader is enabled, create a descriptive label for this field. If
   * not, return a label that is used during automated UI tests.
   *
   * @return {string} The accessibilityLabel for the Search Label field
   */
  const getAccessibilityLabelForLabel = () => {
    if (!isScreenReaderEnabled) {
      return 'search-block-label';
    }
    return `${(0, _i18n.__)('Search block label. Current text is')} ${label}`;
  };
  const renderTextField = () => {
    return (0, _react.createElement)(_reactNative.View, {
      style: _style.default.searchInputContainer,
      accessible: true,
      accessibilityRole: "none",
      accessibilityHint: isScreenReaderEnabled ? (0, _i18n.__)('Double tap to edit placeholder text') : undefined,
      accessibilityLabel: getAccessibilityLabelForPlaceholder()
    }, (0, _react.createElement)(_blockEditor.PlainText, {
      ref: textInputRef,
      isSelected: isPlaceholderSelected,
      className: "wp-block-search__input",
      style: inputStyle,
      numberOfLines: 1,
      ellipsizeMode: "tail" // Currently only works on ios.
      ,
      label: null,
      value: placeholder,
      placeholder: placeholder ? undefined : (0, _i18n.__)('Optional placeholder…'),
      onChange: newVal => setAttributes({
        placeholder: newVal
      }),
      onFocus: () => {
        setIsPlaceholderSelected(true);
        onFocus();
      },
      onBlur: () => setIsPlaceholderSelected(false),
      placeholderTextColor: placeholderStyle?.color
    }));
  };

  // To achieve proper expanding and shrinking `RichText` on Android, there is a need to set
  // a `placeholder` as an empty string when `RichText` is focused,
  // because `AztecView` is calculating a `minWidth` based on placeholder text.
  const buttonPlaceholderText = isButtonSelected || !isButtonSelected && buttonText && buttonText !== '' ? '' : (0, _i18n.__)('Add button text');
  const baseButtonStyles = {
    ...style?.baseColors?.blocks?.['core/button']?.color,
    ...attributes?.style?.color,
    ...(style?.color && {
      text: style.color
    })
  };
  const richTextButtonContainerStyle = [_style.default.buttonContainer, isLongButton && _style.default.buttonContainerWide, baseButtonStyles?.background && {
    backgroundColor: baseButtonStyles.background,
    borderWidth: 0
  }, style?.backgroundColor && {
    backgroundColor: style.backgroundColor,
    borderWidth: 0
  }];
  const richTextButtonStyle = {
    ..._style.default.richTextButton,
    ...(baseButtonStyles?.text && {
      color: baseButtonStyles.text,
      placeholderColor: baseButtonStyles.text
    })
  };
  const iconStyles = {
    ..._style.default.icon,
    ...(baseButtonStyles?.text && {
      fill: baseButtonStyles.text
    })
  };
  const renderButton = () => {
    return (0, _react.createElement)(_reactNative.View, {
      style: richTextButtonContainerStyle
    }, buttonUseIcon && (0, _react.createElement)(_components.Icon, {
      icon: _icons.search,
      ...iconStyles,
      onLayout: onLayoutButton
    }), !buttonUseIcon && (0, _react.createElement)(_reactNative.View, {
      accessible: true,
      accessibilityRole: "none",
      accessibilityHint: isScreenReaderEnabled ? (0, _i18n.__)('Double tap to edit button text') : undefined,
      accessibilityLabel: getAccessibilityLabelForButton(),
      onLayout: onLayoutButton
    }, (0, _react.createElement)(_blockEditor.RichText, {
      className: "wp-block-search__button",
      identifier: "text",
      tagName: "p",
      style: richTextButtonStyle,
      placeholder: buttonPlaceholderText,
      value: buttonText,
      withoutInteractiveFormatting: true,
      onChange: html => setAttributes({
        buttonText: html
      }),
      minWidth: MIN_BUTTON_WIDTH,
      maxWidth: blockWidth - MARGINS,
      textAlign: "center",
      isSelected: isButtonSelected,
      __unstableMobileNoFocusOnMount: !isSelected,
      unstableOnFocus: () => {
        setIsButtonSelected(true);
      },
      onBlur: () => {
        setIsButtonSelected(false);
      },
      selectionColor: _style.default.richTextButtonCursor?.color
    })));
  };
  return (0, _react.createElement)(_reactNative.View, {
    ...blockProps,
    style: _style.default.searchBlockContainer,
    importantForAccessibility: isSelected ? 'yes' : 'no-hide-descendants',
    accessibilityElementsHidden: isSelected ? false : true
  }, isSelected && controls, showLabel && (0, _react.createElement)(_reactNative.View, {
    accessible: true,
    accessibilityRole: "none",
    accessibilityHint: isScreenReaderEnabled ? (0, _i18n.__)('Double tap to edit label text') : undefined,
    accessibilityLabel: getAccessibilityLabelForLabel()
  }, (0, _react.createElement)(_blockEditor.RichText, {
    className: "wp-block-search__label",
    identifier: "text",
    tagName: "p",
    style: _style.default.richTextLabel,
    placeholder: (0, _i18n.__)('Add label…'),
    withoutInteractiveFormatting: true,
    value: label,
    onChange: html => setAttributes({
      label: html
    }),
    isSelected: isLabelSelected,
    __unstableMobileNoFocusOnMount: !isSelected,
    unstableOnFocus: () => {
      setIsLabelSelected(true);
    },
    onBlur: () => {
      setIsLabelSelected(false);
    },
    selectionColor: _style.default.richTextButtonCursor?.color
  })), ('button-inside' === buttonPosition || 'button-outside' === buttonPosition) && (0, _react.createElement)(_reactNative.View, {
    style: searchBarStyle
  }, renderTextField(), renderButton()), 'button-only' === buttonPosition && renderButton(), 'no-button' === buttonPosition && renderTextField());
}
//# sourceMappingURL=edit.native.js.map