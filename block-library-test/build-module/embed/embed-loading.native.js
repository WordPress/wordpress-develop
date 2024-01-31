import { createElement } from "react";
/**
 * External dependencies
 */
import { ActivityIndicator, View } from 'react-native';

/**
 * WordPress dependencies
 */
import { usePreferredColorSchemeStyle } from '@wordpress/compose';

/**
 * Internal dependencies
 */
import styles from './styles.scss';
const EmbedLoading = () => {
  const style = usePreferredColorSchemeStyle(styles['embed-preview__loading'], styles['embed-preview__loading--dark']);
  return createElement(View, {
    style: style
  }, createElement(ActivityIndicator, {
    animating: true
  }));
};
export default EmbedLoading;
//# sourceMappingURL=embed-loading.native.js.map