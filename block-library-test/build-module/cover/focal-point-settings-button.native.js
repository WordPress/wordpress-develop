import { createElement } from "react";
/**
 * External dependencies
 */
import { useNavigation } from '@react-navigation/native';
import { View } from 'react-native';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Icon, BottomSheet } from '@wordpress/components';
import { blockSettingsScreens } from '@wordpress/block-editor';
import { chevronRight } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import styles from './style.scss';
function FocalPointSettingsButton({
  disabled,
  focalPoint,
  onFocalPointChange,
  url
}) {
  const navigation = useNavigation();
  return createElement(BottomSheet.Cell, {
    customActionButton: true,
    disabled: disabled,
    labelStyle: disabled && styles.dimmedActionButton,
    leftAlign: true,
    label: __('Edit focal point'),
    onPress: () => {
      navigation.navigate(blockSettingsScreens.focalPoint, {
        focalPoint,
        onFocalPointChange,
        url
      });
    }
  }, createElement(View, {
    style: disabled && styles.dimmedActionButton
  }, createElement(Icon, {
    icon: chevronRight
  })));
}
export default FocalPointSettingsButton;
//# sourceMappingURL=focal-point-settings-button.native.js.map