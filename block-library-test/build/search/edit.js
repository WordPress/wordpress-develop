"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = SearchEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _data = require("@wordpress/data");
var _element = require("@wordpress/element");
var _components = require("@wordpress/components");
var _compose = require("@wordpress/compose");
var _icons = require("@wordpress/icons");
var _i18n = require("@wordpress/i18n");
var _dom = require("@wordpress/dom");
var _icons2 = require("./icons");
var _utils = require("./utils.js");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

// Used to calculate border radius adjustment to avoid "fat" corners when
// button is placed inside wrapper.
const DEFAULT_INNER_PADDING = '4px';
function SearchEdit({
  className,
  attributes,
  setAttributes,
  toggleSelection,
  isSelected,
  clientId
}) {
  const {
    label,
    showLabel,
    placeholder,
    width,
    widthUnit,
    align,
    buttonText,
    buttonPosition,
    buttonUseIcon,
    isSearchFieldHidden,
    style
  } = attributes;
  const wasJustInsertedIntoNavigationBlock = (0, _data.useSelect)(select => {
    const {
      getBlockParentsByBlockName,
      wasBlockJustInserted
    } = select(_blockEditor.store);
    return !!getBlockParentsByBlockName(clientId, 'core/navigation')?.length && wasBlockJustInserted(clientId);
  }, [clientId]);
  const {
    __unstableMarkNextChangeAsNotPersistent
  } = (0, _data.useDispatch)(_blockEditor.store);
  (0, _element.useEffect)(() => {
    if (wasJustInsertedIntoNavigationBlock) {
      // This side-effect should not create an undo level.
      __unstableMarkNextChangeAsNotPersistent();
      setAttributes({
        showLabel: false,
        buttonUseIcon: true,
        buttonPosition: 'button-inside'
      });
    }
  }, [__unstableMarkNextChangeAsNotPersistent, wasJustInsertedIntoNavigationBlock, setAttributes]);
  const borderRadius = style?.border?.radius;
  const borderProps = (0, _blockEditor.__experimentalUseBorderProps)(attributes);

  // Check for old deprecated numerical border radius. Done as a separate
  // check so that a borderRadius style won't overwrite the longhand
  // per-corner styles.
  if (typeof borderRadius === 'number') {
    borderProps.style.borderRadius = `${borderRadius}px`;
  }
  const colorProps = (0, _blockEditor.__experimentalUseColorProps)(attributes);
  const [fluidTypographySettings, layout] = (0, _blockEditor.useSettings)('typography.fluid', 'layout');
  const typographyProps = (0, _blockEditor.getTypographyClassesAndStyles)(attributes, {
    typography: {
      fluid: fluidTypographySettings
    },
    layout: {
      wideSize: layout?.wideSize
    }
  });
  const unitControlInstanceId = (0, _compose.useInstanceId)(_components.__experimentalUnitControl);
  const unitControlInputId = `wp-block-search__width-${unitControlInstanceId}`;
  const isButtonPositionInside = 'button-inside' === buttonPosition;
  const isButtonPositionOutside = 'button-outside' === buttonPosition;
  const hasNoButton = 'no-button' === buttonPosition;
  const hasOnlyButton = 'button-only' === buttonPosition;
  const searchFieldRef = (0, _element.useRef)();
  const buttonRef = (0, _element.useRef)();
  const units = (0, _components.__experimentalUseCustomUnits)({
    availableUnits: ['%', 'px'],
    defaultValues: {
      '%': _utils.PC_WIDTH_DEFAULT,
      px: _utils.PX_WIDTH_DEFAULT
    }
  });
  (0, _element.useEffect)(() => {
    if (hasOnlyButton && !isSelected) {
      setAttributes({
        isSearchFieldHidden: true
      });
    }
  }, [hasOnlyButton, isSelected, setAttributes]);

  // Show the search field when width changes.
  (0, _element.useEffect)(() => {
    if (!hasOnlyButton || !isSelected) {
      return;
    }
    setAttributes({
      isSearchFieldHidden: false
    });
  }, [hasOnlyButton, isSelected, setAttributes, width]);
  const getBlockClassNames = () => {
    return (0, _classnames.default)(className, isButtonPositionInside ? 'wp-block-search__button-inside' : undefined, isButtonPositionOutside ? 'wp-block-search__button-outside' : undefined, hasNoButton ? 'wp-block-search__no-button' : undefined, hasOnlyButton ? 'wp-block-search__button-only' : undefined, !buttonUseIcon && !hasNoButton ? 'wp-block-search__text-button' : undefined, buttonUseIcon && !hasNoButton ? 'wp-block-search__icon-button' : undefined, hasOnlyButton && isSearchFieldHidden ? 'wp-block-search__searchfield-hidden' : undefined);
  };
  const buttonPositionControls = [{
    role: 'menuitemradio',
    title: (0, _i18n.__)('Button outside'),
    isActive: buttonPosition === 'button-outside',
    icon: _icons2.buttonOutside,
    onClick: () => {
      setAttributes({
        buttonPosition: 'button-outside',
        isSearchFieldHidden: false
      });
    }
  }, {
    role: 'menuitemradio',
    title: (0, _i18n.__)('Button inside'),
    isActive: buttonPosition === 'button-inside',
    icon: _icons2.buttonInside,
    onClick: () => {
      setAttributes({
        buttonPosition: 'button-inside',
        isSearchFieldHidden: false
      });
    }
  }, {
    role: 'menuitemradio',
    title: (0, _i18n.__)('No button'),
    isActive: buttonPosition === 'no-button',
    icon: _icons2.noButton,
    onClick: () => {
      setAttributes({
        buttonPosition: 'no-button',
        isSearchFieldHidden: false
      });
    }
  }, {
    role: 'menuitemradio',
    title: (0, _i18n.__)('Button only'),
    isActive: buttonPosition === 'button-only',
    icon: _icons2.buttonOnly,
    onClick: () => {
      setAttributes({
        buttonPosition: 'button-only',
        isSearchFieldHidden: true
      });
    }
  }];
  const getButtonPositionIcon = () => {
    switch (buttonPosition) {
      case 'button-inside':
        return _icons2.buttonInside;
      case 'button-outside':
        return _icons2.buttonOutside;
      case 'no-button':
        return _icons2.noButton;
      case 'button-only':
        return _icons2.buttonOnly;
    }
  };
  const getResizableSides = () => {
    if (hasOnlyButton) {
      return {};
    }
    return {
      right: align !== 'right',
      left: align === 'right'
    };
  };
  const renderTextField = () => {
    // If the input is inside the wrapper, the wrapper gets the border color styles/classes, not the input control.
    const textFieldClasses = (0, _classnames.default)('wp-block-search__input', isButtonPositionInside ? undefined : borderProps.className, typographyProps.className);
    const textFieldStyles = {
      ...(isButtonPositionInside ? {
        borderRadius
      } : borderProps.style),
      ...typographyProps.style,
      textDecoration: undefined
    };
    return (0, _react.createElement)("input", {
      type: "search",
      className: textFieldClasses,
      style: textFieldStyles,
      "aria-label": (0, _i18n.__)('Optional placeholder text')
      // We hide the placeholder field's placeholder when there is a value. This
      // stops screen readers from reading the placeholder field's placeholder
      // which is confusing.
      ,
      placeholder: placeholder ? undefined : (0, _i18n.__)('Optional placeholder…'),
      value: placeholder,
      onChange: event => setAttributes({
        placeholder: event.target.value
      }),
      ref: searchFieldRef
    });
  };
  const renderButton = () => {
    // If the button is inside the wrapper, the wrapper gets the border color styles/classes, not the button.
    const buttonClasses = (0, _classnames.default)('wp-block-search__button', colorProps.className, typographyProps.className, isButtonPositionInside ? undefined : borderProps.className, buttonUseIcon ? 'has-icon' : undefined, (0, _blockEditor.__experimentalGetElementClassName)('button'));
    const buttonStyles = {
      ...colorProps.style,
      ...typographyProps.style,
      ...(isButtonPositionInside ? {
        borderRadius
      } : borderProps.style)
    };
    const handleButtonClick = () => {
      if (hasOnlyButton) {
        setAttributes({
          isSearchFieldHidden: !isSearchFieldHidden
        });
      }
    };
    return (0, _react.createElement)(_react.Fragment, null, buttonUseIcon && (0, _react.createElement)("button", {
      type: "button",
      className: buttonClasses,
      style: buttonStyles,
      "aria-label": buttonText ? (0, _dom.__unstableStripHTML)(buttonText) : (0, _i18n.__)('Search'),
      onClick: handleButtonClick,
      ref: buttonRef
    }, (0, _react.createElement)(_icons.Icon, {
      icon: _icons.search
    })), !buttonUseIcon && (0, _react.createElement)(_blockEditor.RichText, {
      className: buttonClasses,
      style: buttonStyles,
      "aria-label": (0, _i18n.__)('Button text'),
      placeholder: (0, _i18n.__)('Add button text…'),
      withoutInteractiveFormatting: true,
      value: buttonText,
      onChange: html => setAttributes({
        buttonText: html
      }),
      onClick: handleButtonClick
    }));
  };
  const controls = (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_components.ToolbarGroup, null, (0, _react.createElement)(_components.ToolbarButton, {
    title: (0, _i18n.__)('Toggle search label'),
    icon: _icons2.toggleLabel,
    onClick: () => {
      setAttributes({
        showLabel: !showLabel
      });
    },
    className: showLabel ? 'is-pressed' : undefined
  }), (0, _react.createElement)(_components.ToolbarDropdownMenu, {
    icon: getButtonPositionIcon(),
    label: (0, _i18n.__)('Change button position'),
    controls: buttonPositionControls
  }), !hasNoButton && (0, _react.createElement)(_components.ToolbarButton, {
    title: (0, _i18n.__)('Use button with icon'),
    icon: _icons2.buttonWithIcon,
    onClick: () => {
      setAttributes({
        buttonUseIcon: !buttonUseIcon
      });
    },
    className: buttonUseIcon ? 'is-pressed' : undefined
  }))), (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Display Settings')
  }, (0, _react.createElement)(_components.BaseControl, {
    label: (0, _i18n.__)('Width'),
    id: unitControlInputId
  }, (0, _react.createElement)(_components.__experimentalUnitControl, {
    id: unitControlInputId,
    min: (0, _utils.isPercentageUnit)(widthUnit) ? 0 : _utils.MIN_WIDTH,
    max: (0, _utils.isPercentageUnit)(widthUnit) ? 100 : undefined,
    step: 1,
    onChange: newWidth => {
      const filteredWidth = widthUnit === '%' && parseInt(newWidth, 10) > 100 ? 100 : newWidth;
      setAttributes({
        width: parseInt(filteredWidth, 10)
      });
    },
    onUnitChange: newUnit => {
      setAttributes({
        width: '%' === newUnit ? _utils.PC_WIDTH_DEFAULT : _utils.PX_WIDTH_DEFAULT,
        widthUnit: newUnit
      });
    },
    __unstableInputWidth: '80px',
    value: `${width}${widthUnit}`,
    units: units
  }), (0, _react.createElement)(_components.ButtonGroup, {
    className: "wp-block-search__components-button-group",
    "aria-label": (0, _i18n.__)('Percentage Width')
  }, [25, 50, 75, 100].map(widthValue => {
    return (0, _react.createElement)(_components.Button, {
      key: widthValue,
      size: "small",
      variant: widthValue === width && widthUnit === '%' ? 'primary' : undefined,
      onClick: () => setAttributes({
        width: widthValue,
        widthUnit: '%'
      })
    }, widthValue, "%");
  }))))));
  const padBorderRadius = radius => radius ? `calc(${radius} + ${DEFAULT_INNER_PADDING})` : undefined;
  const getWrapperStyles = () => {
    const styles = isButtonPositionInside ? borderProps.style : {
      borderRadius: borderProps.style?.borderRadius,
      borderTopLeftRadius: borderProps.style?.borderTopLeftRadius,
      borderTopRightRadius: borderProps.style?.borderTopRightRadius,
      borderBottomLeftRadius: borderProps.style?.borderBottomLeftRadius,
      borderBottomRightRadius: borderProps.style?.borderBottomRightRadius
    };
    const isNonZeroBorderRadius = borderRadius !== undefined && parseInt(borderRadius, 10) !== 0;
    if (isButtonPositionInside && isNonZeroBorderRadius) {
      // We have button inside wrapper and a border radius value to apply.
      // Add default padding so we don't get "fat" corners.
      //
      // CSS calc() is used here to support non-pixel units. The inline
      // style using calc() will only apply if both values have units.

      if (typeof borderRadius === 'object') {
        // Individual corner border radii present.
        const {
          topLeft,
          topRight,
          bottomLeft,
          bottomRight
        } = borderRadius;
        return {
          ...styles,
          borderTopLeftRadius: padBorderRadius(topLeft),
          borderTopRightRadius: padBorderRadius(topRight),
          borderBottomLeftRadius: padBorderRadius(bottomLeft),
          borderBottomRightRadius: padBorderRadius(bottomRight)
        };
      }

      // The inline style using calc() will only apply if both values
      // supplied to calc() have units. Deprecated block's may have
      // unitless integer.
      const radius = Number.isInteger(borderRadius) ? `${borderRadius}px` : borderRadius;
      styles.borderRadius = `calc(${radius} + ${DEFAULT_INNER_PADDING})`;
    }
    return styles;
  };
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: getBlockClassNames(),
    style: {
      ...typographyProps.style,
      // Input opts out of text decoration.
      textDecoration: undefined
    }
  });
  const labelClassnames = (0, _classnames.default)('wp-block-search__label', typographyProps.className);
  return (0, _react.createElement)("div", {
    ...blockProps
  }, controls, showLabel && (0, _react.createElement)(_blockEditor.RichText, {
    className: labelClassnames,
    "aria-label": (0, _i18n.__)('Label text'),
    placeholder: (0, _i18n.__)('Add label…'),
    withoutInteractiveFormatting: true,
    value: label,
    onChange: html => setAttributes({
      label: html
    }),
    style: typographyProps.style
  }), (0, _react.createElement)(_components.ResizableBox, {
    size: {
      width: `${width}${widthUnit}`
    },
    className: (0, _classnames.default)('wp-block-search__inside-wrapper', isButtonPositionInside ? borderProps.className : undefined),
    style: getWrapperStyles(),
    minWidth: _utils.MIN_WIDTH,
    enable: getResizableSides(),
    onResizeStart: (event, direction, elt) => {
      setAttributes({
        width: parseInt(elt.offsetWidth, 10),
        widthUnit: 'px'
      });
      toggleSelection(false);
    },
    onResizeStop: (event, direction, elt, delta) => {
      setAttributes({
        width: parseInt(width + delta.width, 10)
      });
      toggleSelection(true);
    },
    showHandle: isSelected
  }, (isButtonPositionInside || isButtonPositionOutside || hasOnlyButton) && (0, _react.createElement)(_react.Fragment, null, renderTextField(), renderButton()), hasNoButton && renderTextField()));
}
//# sourceMappingURL=edit.js.map