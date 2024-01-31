import { createElement, Fragment } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { RangeControl, __experimentalToolsPanelItem as ToolsPanelItem } from '@wordpress/components';
import { InspectorControls, withColors, __experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown, __experimentalUseGradient, __experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients, __experimentalUseBorderProps as useBorderProps } from '@wordpress/block-editor';
import { compose } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { dimRatioToClass } from './utils';
const Overlay = ({
  clientId,
  attributes,
  setAttributes,
  overlayColor,
  setOverlayColor
}) => {
  const {
    dimRatio
  } = attributes;
  const {
    gradientClass,
    gradientValue,
    setGradient
  } = __experimentalUseGradient();
  const colorGradientSettings = useMultipleOriginColorsAndGradients();
  const borderProps = useBorderProps(attributes);
  const overlayStyles = {
    backgroundColor: overlayColor.color,
    backgroundImage: gradientValue,
    ...borderProps.style
  };
  if (!colorGradientSettings.hasColorsOrGradients) {
    return null;
  }
  return createElement(Fragment, null, !!dimRatio && createElement("span", {
    "aria-hidden": "true",
    className: classnames('wp-block-post-featured-image__overlay', dimRatioToClass(dimRatio), {
      [overlayColor.class]: overlayColor.class,
      'has-background-dim': dimRatio !== undefined,
      'has-background-gradient': gradientValue,
      [gradientClass]: gradientClass
    }, borderProps.className),
    style: overlayStyles
  }), createElement(InspectorControls, {
    group: "color"
  }, createElement(ColorGradientSettingsDropdown, {
    __experimentalIsRenderedInSidebar: true,
    settings: [{
      colorValue: overlayColor.color,
      gradientValue,
      label: __('Overlay'),
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
  }), createElement(ToolsPanelItem, {
    hasValue: () => dimRatio !== undefined,
    label: __('Overlay opacity'),
    onDeselect: () => setAttributes({
      dimRatio: 0
    }),
    resetAllFilter: () => ({
      dimRatio: 0
    }),
    isShownByDefault: true,
    panelId: clientId
  }, createElement(RangeControl, {
    __nextHasNoMarginBottom: true,
    label: __('Overlay opacity'),
    value: dimRatio,
    onChange: newDimRatio => setAttributes({
      dimRatio: newDimRatio
    }),
    min: 0,
    max: 100,
    step: 10,
    required: true,
    __next40pxDefaultSize: true
  }))));
};
export default compose([withColors({
  overlayColor: 'background-color'
})])(Overlay);
//# sourceMappingURL=overlay.js.map