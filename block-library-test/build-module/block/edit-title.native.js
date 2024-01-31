import { createElement } from "react";
/**
 * External dependencies
 */
import { Text, View } from 'react-native';

/**
 * WordPress dependencies
 */
import { Icon, useGlobalStyles } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { withPreferredColorScheme } from '@wordpress/compose';
import { help, lock } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import styles from './editor.scss';
function EditTitle({
  getStylesFromColorScheme,
  title
}) {
  const globalStyles = useGlobalStyles();
  const baseColors = globalStyles?.baseColors?.color;
  const lockIconStyle = [getStylesFromColorScheme(styles.lockIcon, styles.lockIconDark), baseColors && {
    color: baseColors.text
  }];
  const titleStyle = [getStylesFromColorScheme(styles.title, styles.titleDark), baseColors && {
    color: baseColors.text
  }];
  const infoIconStyle = [getStylesFromColorScheme(styles.infoIcon, styles.infoIconDark), baseColors && {
    color: baseColors.text
  }];
  const separatorStyle = getStylesFromColorScheme(styles.separator, styles.separatorDark);
  return createElement(View, {
    style: styles.titleContainer
  }, createElement(View, {
    style: styles.lockIconContainer
  }, createElement(Icon, {
    label: __('Lock icon'),
    icon: lock,
    size: 16,
    style: lockIconStyle
  })), createElement(Text, {
    numberOfLines: 1,
    style: titleStyle
  }, title), createElement(View, {
    style: styles.helpIconContainer
  }, createElement(Icon, {
    label: __('Help icon'),
    icon: help,
    size: 20,
    style: infoIconStyle
  })), createElement(View, {
    style: separatorStyle
  }));
}
export default withPreferredColorScheme(EditTitle);
//# sourceMappingURL=edit-title.native.js.map