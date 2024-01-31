import { createElement } from "react";
/**
 * External dependencies
 */
import { View } from 'react-native';
/**
 * WordPress dependencies
 */
import { Gradient, colorsUtils } from '@wordpress/components';
/**
 * Internal dependencies
 */
import styles from './editor.scss';
function ColorBackground({
  children,
  borderRadiusValue,
  backgroundColor
}) {
  const {
    isGradient
  } = colorsUtils;
  const wrapperStyles = [styles.richTextWrapper, {
    borderRadius: borderRadiusValue,
    backgroundColor
  }];
  return createElement(View, {
    style: wrapperStyles
  }, isGradient(backgroundColor) && createElement(Gradient, {
    gradientValue: backgroundColor,
    angleCenter: {
      x: 0.5,
      y: 0.5
    },
    style: [styles.linearGradient, {
      borderRadius: borderRadiusValue
    }]
  }), children);
}
export default ColorBackground;
//# sourceMappingURL=color-background.native.js.map