"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = CoverInspectorControls;
var _react = require("react");
var _element = require("@wordpress/element");
var _components = require("@wordpress/components");
var _compose = require("@wordpress/compose");
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _shared = require("../shared");
var _lockUnlock = require("../../lock-unlock");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const {
  cleanEmptyObject
} = (0, _lockUnlock.unlock)(_blockEditor.privateApis);
function CoverHeightInput({
  onChange,
  onUnitChange,
  unit = 'px',
  value = ''
}) {
  const instanceId = (0, _compose.useInstanceId)(_components.__experimentalUnitControl);
  const inputId = `block-cover-height-input-${instanceId}`;
  const isPx = unit === 'px';
  const [availableUnits] = (0, _blockEditor.useSettings)('spacing.units');
  const units = (0, _components.__experimentalUseCustomUnits)({
    availableUnits: availableUnits || ['px', 'em', 'rem', 'vw', 'vh'],
    defaultValues: {
      px: 430,
      '%': 20,
      em: 20,
      rem: 20,
      vw: 20,
      vh: 50
    }
  });
  const handleOnChange = unprocessedValue => {
    const inputValue = unprocessedValue !== '' ? parseFloat(unprocessedValue) : undefined;
    if (isNaN(inputValue) && inputValue !== undefined) {
      return;
    }
    onChange(inputValue);
  };
  const computedValue = (0, _element.useMemo)(() => {
    const [parsedQuantity] = (0, _components.__experimentalParseQuantityAndUnitFromRawValue)(value);
    return [parsedQuantity, unit].join('');
  }, [unit, value]);
  const min = isPx ? _shared.COVER_MIN_HEIGHT : 0;
  return (0, _react.createElement)(_components.__experimentalUnitControl, {
    label: (0, _i18n.__)('Minimum height of cover'),
    id: inputId,
    isResetValueOnUnitChange: true,
    min: min,
    onChange: handleOnChange,
    onUnitChange: onUnitChange,
    __unstableInputWidth: '80px',
    units: units,
    value: computedValue
  });
}
function CoverInspectorControls({
  attributes,
  setAttributes,
  clientId,
  setOverlayColor,
  coverRef,
  currentSettings,
  updateDimRatio,
  onClearMedia
}) {
  const {
    useFeaturedImage,
    dimRatio,
    focalPoint,
    hasParallax,
    isRepeated,
    minHeight,
    minHeightUnit,
    alt,
    tagName
  } = attributes;
  const {
    isVideoBackground,
    isImageBackground,
    mediaElement,
    url,
    overlayColor
  } = currentSettings;
  const {
    gradientValue,
    setGradient
  } = (0, _blockEditor.__experimentalUseGradient)();
  const toggleParallax = () => {
    setAttributes({
      hasParallax: !hasParallax,
      ...(!hasParallax ? {
        focalPoint: undefined
      } : {})
    });
  };
  const toggleIsRepeated = () => {
    setAttributes({
      isRepeated: !isRepeated
    });
  };
  const showFocalPointPicker = isVideoBackground || isImageBackground && (!hasParallax || isRepeated);
  const imperativeFocalPointPreview = value => {
    const [styleOfRef, property] = mediaElement.current ? [mediaElement.current.style, 'objectPosition'] : [coverRef.current.style, 'backgroundPosition'];
    styleOfRef[property] = (0, _shared.mediaPosition)(value);
  };
  const colorGradientSettings = (0, _blockEditor.__experimentalUseMultipleOriginColorsAndGradients)();
  const htmlElementMessages = {
    header: (0, _i18n.__)('The <header> element should represent introductory content, typically a group of introductory or navigational aids.'),
    main: (0, _i18n.__)('The <main> element should be used for the primary content of your document only.'),
    section: (0, _i18n.__)("The <section> element should represent a standalone portion of the document that can't be better represented by another element."),
    article: (0, _i18n.__)('The <article> element should represent a self-contained, syndicatable portion of the document.'),
    aside: (0, _i18n.__)("The <aside> element should represent a portion of a document whose content is only indirectly related to the document's main content."),
    footer: (0, _i18n.__)('The <footer> element should represent a footer for its nearest sectioning element (e.g.: <section>, <article>, <main> etc.).')
  };
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.InspectorControls, null, !!url && (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Media settings')
  }, isImageBackground && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Fixed background'),
    checked: hasParallax,
    onChange: toggleParallax
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Repeated background'),
    checked: isRepeated,
    onChange: toggleIsRepeated
  })), showFocalPointPicker && (0, _react.createElement)(_components.FocalPointPicker, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: (0, _i18n.__)('Focal point'),
    url: url,
    value: focalPoint,
    onDragStart: imperativeFocalPointPreview,
    onDrag: imperativeFocalPointPreview,
    onChange: newFocalPoint => setAttributes({
      focalPoint: newFocalPoint
    })
  }), !useFeaturedImage && url && !isVideoBackground && (0, _react.createElement)(_components.TextareaControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Alternative text'),
    value: alt,
    onChange: newAlt => setAttributes({
      alt: newAlt
    }),
    help: (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.ExternalLink, {
      href: "https://www.w3.org/WAI/tutorials/images/decision-tree"
    }, (0, _i18n.__)('Describe the purpose of the image.')), (0, _react.createElement)("br", null), (0, _i18n.__)('Leave empty if decorative.'))
  }), (0, _react.createElement)(_components.PanelRow, null, (0, _react.createElement)(_components.Button, {
    variant: "secondary",
    size: "small",
    className: "block-library-cover__reset-button",
    onClick: onClearMedia
  }, (0, _i18n.__)('Clear Media'))))), colorGradientSettings.hasColorsOrGradients && (0, _react.createElement)(_blockEditor.InspectorControls, {
    group: "color"
  }, (0, _react.createElement)(_blockEditor.__experimentalColorGradientSettingsDropdown, {
    __experimentalIsRenderedInSidebar: true,
    settings: [{
      colorValue: overlayColor.color,
      gradientValue,
      label: (0, _i18n.__)('Overlay'),
      onColorChange: setOverlayColor,
      onGradientChange: setGradient,
      isShownByDefault: true,
      resetAllFilter: () => ({
        overlayColor: undefined,
        customOverlayColor: undefined,
        gradient: undefined,
        customGradient: undefined
      })
    }],
    panelId: clientId,
    ...colorGradientSettings
  }), (0, _react.createElement)(_components.__experimentalToolsPanelItem, {
    hasValue: () => {
      // If there's a media background the dimRatio will be
      // defaulted to 50 whereas it will be 100 for colors.
      return dimRatio === undefined ? false : dimRatio !== (url ? 50 : 100);
    },
    label: (0, _i18n.__)('Overlay opacity'),
    onDeselect: () => updateDimRatio(url ? 50 : 100),
    resetAllFilter: () => ({
      dimRatio: url ? 50 : 100
    }),
    isShownByDefault: true,
    panelId: clientId
  }, (0, _react.createElement)(_components.RangeControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Overlay opacity'),
    value: dimRatio,
    onChange: newDimRatio => updateDimRatio(newDimRatio),
    min: 0,
    max: 100,
    step: 10,
    required: true,
    __next40pxDefaultSize: true
  }))), (0, _react.createElement)(_blockEditor.InspectorControls, {
    group: "dimensions"
  }, (0, _react.createElement)(_components.__experimentalToolsPanelItem, {
    hasValue: () => !!minHeight,
    label: (0, _i18n.__)('Minimum height'),
    onDeselect: () => setAttributes({
      minHeight: undefined,
      minHeightUnit: undefined
    }),
    resetAllFilter: () => ({
      minHeight: undefined,
      minHeightUnit: undefined
    }),
    isShownByDefault: true,
    panelId: clientId
  }, (0, _react.createElement)(CoverHeightInput, {
    value: minHeight,
    unit: minHeightUnit,
    onChange: newMinHeight => setAttributes({
      minHeight: newMinHeight,
      style: cleanEmptyObject({
        ...attributes?.style,
        dimensions: {
          ...attributes?.style?.dimensions,
          aspectRatio: undefined // Reset aspect ratio when minHeight is set.
        }
      })
    }),
    onUnitChange: nextUnit => setAttributes({
      minHeightUnit: nextUnit
    })
  }))), (0, _react.createElement)(_blockEditor.InspectorControls, {
    group: "advanced"
  }, (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: (0, _i18n.__)('HTML element'),
    options: [{
      label: (0, _i18n.__)('Default (<div>)'),
      value: 'div'
    }, {
      label: '<header>',
      value: 'header'
    }, {
      label: '<main>',
      value: 'main'
    }, {
      label: '<section>',
      value: 'section'
    }, {
      label: '<article>',
      value: 'article'
    }, {
      label: '<aside>',
      value: 'aside'
    }, {
      label: '<footer>',
      value: 'footer'
    }],
    value: tagName,
    onChange: value => setAttributes({
      tagName: value
    }),
    help: htmlElementMessages[tagName]
  })));
}
//# sourceMappingURL=inspector-controls.js.map