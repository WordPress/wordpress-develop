"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _element = require("@wordpress/element");
var _data = require("@wordpress/data");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _icons = require("@wordpress/icons");
var _editPost = require("@wordpress/edit-post");
var _richText = _interopRequireDefault(require("./rich-text.scss"));
var _editor = _interopRequireDefault(require("./editor.scss"));
var _colorBackground = _interopRequireDefault(require("./color-background"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

// eslint-disable-next-line no-restricted-imports

/**
 * Internal dependencies
 */

const MIN_BORDER_RADIUS_VALUE = 0;
const MAX_BORDER_RADIUS_VALUE = 50;
const INITIAL_MAX_WIDTH = 108;
const MIN_WIDTH = 40;
// Map of the percentage width to pixel subtraction that make the buttons fit nicely into columns.
const MIN_WIDTH_MARGINS = {
  100: 0,
  75: _editor.default.button75?.marginLeft,
  50: _editor.default.button50?.marginLeft,
  25: _editor.default.button25?.marginLeft
};
function WidthPanel({
  selectedWidth,
  setAttributes
}) {
  function handleChange(newWidth) {
    // Check if we are toggling the width off
    let width = selectedWidth === newWidth ? undefined : newWidth;
    if (newWidth === 'auto') {
      width = undefined;
    }
    // Update attributes.
    setAttributes({
      width
    });
  }
  const options = [{
    value: 'auto',
    label: (0, _i18n.__)('Auto')
  }, {
    value: 25,
    label: '25%'
  }, {
    value: 50,
    label: '50%'
  }, {
    value: 75,
    label: '75%'
  }, {
    value: 100,
    label: '100%'
  }];
  if (!selectedWidth) {
    selectedWidth = 'auto';
  }
  return (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Width Settings')
  }, (0, _react.createElement)(_components.BottomSheetSelectControl, {
    label: (0, _i18n.__)('Button width'),
    value: selectedWidth,
    onChange: handleChange,
    options: options
  }));
}
function ButtonEdit(props) {
  const {
    isSelected,
    parentWidth
  } = props;
  const initialBorderRadius = props?.attributes?.style?.border?.radius;
  const {
    valueUnit = 'px'
  } = (0, _components.getValueAndUnit)(initialBorderRadius) || {};
  const {
    editorSidebarOpened,
    numOfButtons
  } = (0, _data.useSelect)(select => {
    const {
      isEditorSidebarOpened
    } = select(_editPost.store);
    const {
      getBlockCount,
      getBlockRootClientId
    } = select(_blockEditor.store);
    const parentId = getBlockRootClientId(clientId);
    const blockCount = getBlockCount(parentId);
    const currentIsEditorSidebarOpened = isEditorSidebarOpened();
    return {
      editorSidebarOpened: isSelected && currentIsEditorSidebarOpened,
      numOfButtons: blockCount
    };
  }, [clientId, isSelected]);
  const {
    closeGeneralSidebar
  } = (0, _data.useDispatch)(_editPost.store);
  const [maxWidth, setMaxWidth] = (0, _element.useState)(INITIAL_MAX_WIDTH);
  const [isLinkSheetVisible, setIsLinkSheetVisible] = (0, _element.useState)(false);
  const [isButtonFocused, setIsButtonFocused] = (0, _element.useState)(true);
  const [placeholderTextWidth, setPlaceholderTextWidth] = (0, _element.useState)(0);
  const [borderRadiusUnit, setBorderRadiusUnit] = (0, _element.useState)(valueUnit);
  const richTextRef = (0, _element.useRef)();
  const colors = (0, _components.useMobileGlobalStylesColors)();
  const gradients = (0, _components.useMobileGlobalStylesColors)('gradients');
  (0, _element.useEffect)(() => {
    if (isSelected) {
      onToggleButtonFocus(true);
    }
  }, [isSelected]);
  (0, _element.useEffect)(() => {
    onSetMaxWidth(null, true);
  }, [parentWidth]);
  (0, _element.useEffect)(() => {
    // Blur `RichText` on Android when link settings sheet or button settings sheet is opened,
    // to avoid flashing caret after closing one of them
    const richText = richTextRef?.current;
    if (_reactNative.Platform.OS === 'android' && richText) {
      if (editorSidebarOpened || isLinkSheetVisible) {
        richText.blur();
        onToggleButtonFocus(false);
      } else {
        onToggleButtonFocus(true);
      }
    }
  }, [editorSidebarOpened, isLinkSheetVisible]);
  (0, _element.useEffect)(() => {
    if (richTextRef?.current) {
      if (!isSelected && isButtonFocused) {
        onToggleButtonFocus(false);
      }
      if (isSelected && !isButtonFocused) {
        _reactNative.AccessibilityInfo.isScreenReaderEnabled().then(enabled => {
          if (enabled) {
            onToggleButtonFocus(true);
            richTextRef?.current.focus();
          }
        });
      }
    }
  }, [isSelected, isButtonFocused]);
  const linkSettingsActions = [{
    label: (0, _i18n.__)('Remove link'),
    onPress: onClearSettings
  }];
  const linkSettingsOptions = {
    url: {
      label: (0, _i18n.__)('Button Link URL'),
      placeholder: (0, _i18n.__)('Add URL'),
      autoFocus: true,
      autoFill: false
    },
    openInNewTab: {
      label: (0, _i18n.__)('Open in new tab')
    },
    linkRel: {
      label: (0, _i18n.__)('Link Rel'),
      placeholder: (0, _i18n._x)('None', 'Link rel attribute value placeholder')
    }
  };
  const noFocusLinkSettingOptions = {
    ...linkSettingsOptions,
    url: {
      ...linkSettingsOptions.url,
      autoFocus: false
    }
  };
  function getBackgroundColor() {
    const {
      attributes,
      style
    } = props;
    const {
      backgroundColor,
      gradient
    } = attributes;

    // Return named gradient value if available.
    const gradientValue = (0, _blockEditor.getGradientValueBySlug)(gradients, gradient);
    if (gradientValue) {
      return gradientValue;
    }
    const colorProps = (0, _blockEditor.__experimentalGetColorClassesAndStyles)(attributes);

    // Retrieve named color object to force inline styles for themes that
    // do not load their color stylesheets in the editor.
    const colorObject = (0, _blockEditor.getColorObjectByAttributeValues)(colors, backgroundColor);
    return colorObject?.color || colorProps.style?.backgroundColor || colorProps.style?.background || style?.backgroundColor || _editor.default.defaultButton.backgroundColor;
  }
  function getTextColor() {
    const {
      attributes,
      style
    } = props;
    const colorProps = (0, _blockEditor.__experimentalGetColorClassesAndStyles)(attributes);

    // Retrieve named color object to force inline styles for themes that
    // do not load their color stylesheets in the editor.
    const colorObject = (0, _blockEditor.getColorObjectByAttributeValues)(colors, attributes.textColor);
    return colorObject?.color || colorProps.style?.color || style?.color || _editor.default.defaultButton.color;
  }
  function onChangeText(value) {
    const {
      setAttributes
    } = props;
    setAttributes({
      text: value
    });
  }
  function onChangeBorderRadius(newRadius) {
    const {
      setAttributes,
      attributes
    } = props;
    const {
      style
    } = attributes;
    const newStyle = getNewStyle(style, newRadius, borderRadiusUnit);
    setAttributes({
      style: newStyle
    });
  }
  function onChangeBorderRadiusUnit(newRadiusUnit) {
    const {
      setAttributes,
      attributes
    } = props;
    const {
      style
    } = attributes;
    const newBorderRadius = getBorderRadiusValue(attributes?.style?.border?.radius);
    const newStyle = getNewStyle(style, newBorderRadius, newRadiusUnit);
    setAttributes({
      style: newStyle
    });
    setBorderRadiusUnit(newRadiusUnit);
  }
  function getNewStyle(style, radius, radiusUnit) {
    return {
      ...style,
      border: {
        ...style?.border,
        radius: `${radius}${radiusUnit}` // Store the value with the unit so that it works as expected.
      }
    };
  }
  function onShowLinkSettings() {
    setIsLinkSheetVisible(true);
  }
  function onHideLinkSettings() {
    setIsLinkSheetVisible(false);
  }
  function onToggleButtonFocus(value) {
    if (value !== isButtonFocused) {
      setIsButtonFocused(value);
    }
  }
  function onClearSettings() {
    const {
      setAttributes
    } = props;
    setAttributes({
      url: '',
      rel: '',
      linkTarget: ''
    });
    onHideLinkSettings();
  }
  function onLayout({
    nativeEvent
  }) {
    const {
      width
    } = nativeEvent.layout;
    onSetMaxWidth(width);
  }
  const onSetMaxWidth = (0, _element.useCallback)((width, isParentWidthDidChange = false) => {
    const {
      marginRight: spacing
    } = _editor.default.defaultButton;
    const isParentWidthChanged = isParentWidthDidChange ? isParentWidthDidChange : maxWidth !== parentWidth;
    const isWidthChanged = maxWidth !== width;
    if (parentWidth && !width && isParentWidthChanged) {
      setMaxWidth(parentWidth - spacing);
    } else if (!parentWidth && width && isWidthChanged) {
      setMaxWidth(width - spacing);
    }
  }, [maxWidth, parentWidth]);
  function onRemove() {
    const {
      onDeleteBlock,
      onReplace
    } = props;
    if (numOfButtons === 1) {
      onDeleteBlock();
    } else {
      onReplace([]);
    }
  }
  function onPlaceholderTextWidth({
    nativeEvent
  }) {
    const textWidth = nativeEvent.lines[0] && nativeEvent.lines[0].width;
    if (textWidth && textWidth !== placeholderTextWidth) {
      setPlaceholderTextWidth(Math.min(textWidth, maxWidth));
    }
  }
  const onSetRef = (0, _element.useCallback)(ref => {
    richTextRef.current = ref;
  }, [richTextRef]);
  const onUnstableOnFocus = (0, _element.useCallback)(() => {
    onToggleButtonFocus(true);
  }, []);
  const onBlur = (0, _element.useCallback)(() => {
    onSetMaxWidth();
  }, []);
  function dismissSheet() {
    onHideLinkSettings();
    closeGeneralSidebar();
  }
  function getLinkSettings(isCompatibleWithSettings) {
    const {
      attributes,
      setAttributes
    } = props;
    return (0, _react.createElement)(_components.LinkSettingsNavigation, {
      isVisible: isLinkSheetVisible,
      url: attributes.url,
      rel: attributes.rel,
      linkTarget: attributes.linkTarget,
      onClose: dismissSheet,
      setAttributes: setAttributes,
      withBottomSheet: !isCompatibleWithSettings,
      hasPicker: true,
      actions: linkSettingsActions,
      options: isCompatibleWithSettings ? linkSettingsOptions : noFocusLinkSettingOptions,
      showIcon: !isCompatibleWithSettings
    });
  }

  // Render `Text` with `placeholderText` styled as a placeholder
  // to calculate its width which then is set as a `minWidth`
  function getPlaceholderWidth(placeholderText) {
    return (0, _react.createElement)(_reactNative.Text, {
      style: _editor.default.placeholder,
      onTextLayout: onPlaceholderTextWidth
    }, placeholderText);
  }
  function getBorderRadiusValue(currentBorderRadius, defaultBorderRadius) {
    const valueAndUnit = (0, _components.getValueAndUnit)(currentBorderRadius);
    if (Number.isInteger(parseInt(valueAndUnit?.valueToConvert))) {
      return parseFloat(valueAndUnit.valueToConvert);
    }
    return defaultBorderRadius;
  }
  const {
    attributes,
    clientId,
    onReplace,
    mergeBlocks,
    setAttributes,
    style
  } = props;
  const {
    placeholder,
    text,
    style: buttonStyle,
    url,
    align = 'center',
    width
  } = attributes;
  const {
    paddingTop: spacing,
    borderWidth
  } = _editor.default.defaultButton;
  if (parentWidth === 0) {
    return null;
  }
  const currentBorderRadius = buttonStyle?.border?.radius;
  const borderRadiusValue = getBorderRadiusValue(currentBorderRadius, _editor.default.defaultButton.borderRadius);
  const buttonBorderRadiusValue = borderRadiusUnit === 'px' || borderRadiusUnit === '%' ? borderRadiusValue : Math.floor(14 * borderRadiusValue); // Lets assume that the font size is set to 14px; TO get a nicer preview.
  const outlineBorderRadius = buttonBorderRadiusValue > 0 ? buttonBorderRadiusValue + spacing + borderWidth : 0;

  // To achieve proper expanding and shrinking `RichText` on iOS, there is a need to set a `minWidth`
  // value at least on 1 when `RichText` is focused or when is not focused, but `RichText` value is
  // different than empty string.
  let minWidth = isButtonFocused || !isButtonFocused && text && text !== '' ? MIN_WIDTH : placeholderTextWidth;
  if (width) {
    // Set the width of the button.
    minWidth = Math.floor(maxWidth * (width / 100) - MIN_WIDTH_MARGINS[width]);
  }
  // To achieve proper expanding and shrinking `RichText` on Android, there is a need to set
  // a `placeholder` as an empty string when `RichText` is focused,
  // because `AztecView` is calculating a `minWidth` based on placeholder text.
  const placeholderText = isButtonFocused || !isButtonFocused && text && text !== '' ? '' : placeholder || (0, _i18n.__)('Add text…');
  const backgroundColor = getBackgroundColor();
  const textColor = getTextColor();
  const isFixedWidth = !!width;
  const outLineStyles = [_editor.default.outline, {
    borderRadius: outlineBorderRadius,
    borderColor: backgroundColor
  }];
  const textStyles = {
    ..._richText.default.richText,
    paddingLeft: isFixedWidth ? 0 : _richText.default.richText.paddingLeft,
    paddingRight: isFixedWidth ? 0 : _richText.default.richText.paddingRight,
    color: textColor
  };
  return (0, _react.createElement)(_reactNative.View, {
    onLayout: onLayout
  }, getPlaceholderWidth(placeholderText), (0, _react.createElement)(_colorBackground.default, {
    borderRadiusValue: buttonBorderRadiusValue,
    backgroundColor: backgroundColor,
    isSelected: isSelected
  }, isSelected && (0, _react.createElement)(_reactNative.View, {
    pointerEvents: "none",
    style: outLineStyles
  }), (0, _react.createElement)(_blockEditor.RichText, {
    setRef: onSetRef,
    placeholder: placeholderText,
    value: text,
    onChange: onChangeText,
    style: textStyles,
    textAlign: align,
    placeholderTextColor: style?.color || _editor.default.placeholderTextColor.color,
    identifier: "text",
    tagName: "p",
    minWidth: minWidth // The minimum Button size.
    ,
    maxWidth: isFixedWidth ? minWidth : maxWidth // The width of the screen.
    ,
    id: clientId,
    isSelected: isButtonFocused,
    withoutInteractiveFormatting: true,
    unstableOnFocus: onUnstableOnFocus,
    __unstableMobileNoFocusOnMount: !isSelected,
    selectionColor: textColor,
    onBlur: onBlur,
    onReplace: onReplace,
    onRemove: onRemove,
    onMerge: mergeBlocks,
    fontSize: style?.fontSize
  })), isSelected && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_components.ToolbarGroup, null, (0, _react.createElement)(_components.ToolbarButton, {
    title: (0, _i18n.__)('Edit link'),
    icon: _icons.link,
    onClick: onShowLinkSettings,
    isActive: url
  }))), getLinkSettings(false), (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Border Settings')
  }, (0, _react.createElement)(_components.UnitControl, {
    label: (0, _i18n.__)('Border Radius'),
    min: MIN_BORDER_RADIUS_VALUE,
    max: MAX_BORDER_RADIUS_VALUE,
    value: borderRadiusValue,
    onChange: onChangeBorderRadius,
    onUnitChange: onChangeBorderRadiusUnit,
    unit: borderRadiusUnit,
    units: (0, _components.filterUnitsWithSettings)(['px', 'em', 'rem'], _components.CSS_UNITS)
  })), (0, _react.createElement)(WidthPanel, {
    selectedWidth: width,
    setAttributes: setAttributes
  }), (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Link Settings')
  }, getLinkSettings(true)))));
}
var _default = exports.default = ButtonEdit;
//# sourceMappingURL=edit.native.js.map