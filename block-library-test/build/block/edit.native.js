"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = ReusableBlockEdit;
var _react = require("react");
var _reactNative = require("react-native");
var _element = require("@wordpress/element");
var _coreData = require("@wordpress/core-data");
var _components = require("@wordpress/components");
var _data = require("@wordpress/data");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _compose = require("@wordpress/compose");
var _icons = require("@wordpress/icons");
var _reusableBlocks = require("@wordpress/reusable-blocks");
var _editor = require("@wordpress/editor");
var _notices = require("@wordpress/notices");
var _editor2 = _interopRequireDefault(require("./editor.scss"));
var _editTitle = _interopRequireDefault(require("./edit-title"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function ReusableBlockEdit({
  attributes: {
    ref
  },
  clientId,
  isSelected
}) {
  const hasAlreadyRendered = (0, _blockEditor.useHasRecursion)(ref);
  const [showHelp, setShowHelp] = (0, _element.useState)(false);
  const infoTextStyle = (0, _compose.usePreferredColorSchemeStyle)(_editor2.default.infoText, _editor2.default.infoTextDark);
  const infoTitleStyle = (0, _compose.usePreferredColorSchemeStyle)(_editor2.default.infoTitle, _editor2.default.infoTitleDark);
  const infoSheetIconStyle = (0, _compose.usePreferredColorSchemeStyle)(_editor2.default.infoSheetIcon, _editor2.default.infoSheetIconDark);
  const infoDescriptionStyle = (0, _compose.usePreferredColorSchemeStyle)(_editor2.default.infoDescription, _editor2.default.infoDescriptionDark);
  const actionButtonStyle = (0, _compose.usePreferredColorSchemeStyle)(_editor2.default.actionButton, _editor2.default.actionButtonDark);
  const spinnerStyle = (0, _compose.usePreferredColorSchemeStyle)(_editor2.default.spinner, _editor2.default.spinnerDark);
  const {
    hasResolved,
    isEditing,
    isMissing
  } = (0, _data.useSelect)(select => {
    const persistedBlock = select(_coreData.store).getEntityRecord('postType', 'wp_block', ref);
    const hasResolvedBlock = select(_coreData.store).hasFinishedResolution('getEntityRecord', ['postType', 'wp_block', ref]);
    const {
      getBlockCount
    } = select(_blockEditor.store);
    return {
      hasResolved: hasResolvedBlock,
      isEditing: select(_reusableBlocks.store).__experimentalIsEditingReusableBlock(clientId),
      isMissing: hasResolvedBlock && !persistedBlock,
      innerBlockCount: getBlockCount(clientId)
    };
  }, [ref, clientId]);
  const hostAppNamespace = (0, _data.useSelect)(select => select(_editor.store).getEditorSettings().hostAppNamespace, []);
  const {
    createSuccessNotice
  } = (0, _data.useDispatch)(_notices.store);
  const {
    __experimentalConvertBlockToStatic: convertBlockToStatic
  } = (0, _data.useDispatch)(_reusableBlocks.store);
  const {
    clearSelectedBlock
  } = (0, _data.useDispatch)(_blockEditor.store);
  const [blocks, onInput, onChange] = (0, _coreData.useEntityBlockEditor)('postType', 'wp_block', {
    id: ref
  });
  const [title] = (0, _coreData.useEntityProp)('postType', 'wp_block', 'title', ref);
  function openSheet() {
    setShowHelp(true);
  }
  function closeSheet() {
    setShowHelp(false);
  }
  const onConvertToRegularBlocks = (0, _element.useCallback)(() => {
    /* translators: %s: name of the synced block */
    const successNotice = (0, _i18n.__)('%s detached');
    createSuccessNotice((0, _i18n.sprintf)(successNotice, title));
    clearSelectedBlock();
    // Convert action is executed at the end of the current JavaScript execution block
    // to prevent issues related to undo/redo actions.
    setImmediate(() => convertBlockToStatic(clientId));
  }, [title, clientId]);
  function renderSheet() {
    const infoTitle = _reactNative.Platform.OS === 'android' ? (0, _i18n.sprintf)( /* translators: %s: name of the host app (e.g. WordPress) */
    (0, _i18n.__)('Editing synced patterns is not yet supported on %s for Android'), hostAppNamespace) : (0, _i18n.sprintf)( /* translators: %s: name of the host app (e.g. WordPress) */
    (0, _i18n.__)('Editing synced patterns is not yet supported on %s for iOS'), hostAppNamespace);
    return (0, _react.createElement)(_components.BottomSheet, {
      isVisible: showHelp,
      hideHeader: true,
      onClose: closeSheet
    }, (0, _react.createElement)(_reactNative.View, {
      style: _editor2.default.infoContainer
    }, (0, _react.createElement)(_components.Icon, {
      icon: _icons.help,
      color: infoSheetIconStyle.color,
      size: _editor2.default.infoSheetIcon.size
    }), (0, _react.createElement)(_reactNative.Text, {
      style: [infoTextStyle, infoTitleStyle]
    }, infoTitle), (0, _react.createElement)(_reactNative.Text, {
      style: [infoTextStyle, infoDescriptionStyle]
    }, (0, _i18n.__)('Alternatively, you can detach and edit this block separately by tapping “Detach”.')), (0, _react.createElement)(_components.TextControl, {
      label: (0, _i18n.__)('Detach'),
      separatorType: "topFullWidth",
      onPress: onConvertToRegularBlocks,
      labelStyle: actionButtonStyle
    })));
  }
  if (hasAlreadyRendered) {
    return (0, _react.createElement)(_blockEditor.Warning, {
      message: (0, _i18n.__)('Block cannot be rendered inside itself.')
    });
  }
  if (isMissing) {
    return (0, _react.createElement)(_blockEditor.Warning, {
      message: (0, _i18n.__)('Block has been deleted or is unavailable.')
    });
  }
  if (!hasResolved) {
    return (0, _react.createElement)(_reactNative.View, {
      style: spinnerStyle
    }, (0, _react.createElement)(_reactNative.ActivityIndicator, {
      animating: true
    }));
  }
  let element = (0, _react.createElement)(_blockEditor.InnerBlocks, {
    value: blocks,
    onChange: onChange,
    onInput: onInput
  });
  if (!isEditing) {
    element = (0, _react.createElement)(_components.Disabled, null, element);
  }
  return (0, _react.createElement)(_blockEditor.RecursionProvider, {
    uniqueId: ref
  }, (0, _react.createElement)(_reactNative.TouchableWithoutFeedback, {
    disabled: !isSelected,
    accessibilityLabel: (0, _i18n.__)('Help button'),
    accessibilityRole: 'button',
    accessibilityHint: (0, _i18n.__)('Tap here to show help'),
    onPress: openSheet
  }, (0, _react.createElement)(_reactNative.View, null, isSelected && (0, _react.createElement)(_editTitle.default, {
    title: title
  }), element, renderSheet())));
}
//# sourceMappingURL=edit.native.js.map