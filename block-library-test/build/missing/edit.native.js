"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = exports.UnsupportedBlockEdit = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _components = require("@wordpress/components");
var _compose = require("@wordpress/compose");
var _blockLibrary = require("@wordpress/block-library");
var _blocks = require("@wordpress/blocks");
var _element = require("@wordpress/element");
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _data = require("@wordpress/data");
var _hooks = require("@wordpress/hooks");
var _blockEditor = require("@wordpress/block-editor");
var _notices = require("@wordpress/notices");
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

// Blocks that can't be edited through the Unsupported block editor identified by their name.
const UBE_INCOMPATIBLE_BLOCKS = ['core/block'];
const I18N_BLOCK_SCHEMA_TITLE = 'block title';
const EMPTY_ARRAY = [];
class UnsupportedBlockEdit extends _element.Component {
  constructor(props) {
    super(props);
    this.state = {
      showHelp: false
    };
    this.toggleSheet = this.toggleSheet.bind(this);
    this.closeSheet = this.closeSheet.bind(this);
    this.requestFallback = this.requestFallback.bind(this);
    this.onHelpButtonPressed = this.onHelpButtonPressed.bind(this);
  }
  toggleSheet() {
    this.setState({
      showHelp: !this.state.showHelp
    });
  }
  closeSheet() {
    this.setState({
      showHelp: false
    });
  }
  componentWillUnmount() {
    if (this.timeout) {
      clearTimeout(this.timeout);
    }
  }
  getTitle() {
    const {
      originalName
    } = this.props.attributes;
    const blockType = _blockLibrary.coreBlocks[originalName];
    const title = blockType?.metadata.title;
    const textdomain = blockType?.metadata.textdomain;
    return title && textdomain ?
    // eslint-disable-next-line @wordpress/i18n-no-variables, @wordpress/i18n-text-domain
    (0, _i18n._x)(title, I18N_BLOCK_SCHEMA_TITLE, textdomain) : originalName;
  }
  renderHelpIcon() {
    const infoIconStyle = this.props.getStylesFromColorScheme(_style.default.infoIcon, _style.default.infoIconDark);
    return (0, _react.createElement)(_reactNative.TouchableOpacity, {
      onPress: this.onHelpButtonPressed,
      style: _style.default.helpIconContainer,
      accessibilityLabel: (0, _i18n.__)('Help button'),
      accessibilityRole: 'button',
      accessibilityHint: (0, _i18n.__)('Tap here to show help')
    }, (0, _react.createElement)(_components.Icon, {
      className: "unsupported-icon-help",
      label: (0, _i18n.__)('Help icon'),
      icon: _icons.help,
      fill: infoIconStyle.color
    }));
  }
  onHelpButtonPressed() {
    if (!this.props.isSelected) {
      this.props.selectBlock();
    }
    this.toggleSheet();
  }
  requestFallback() {
    if (this.props.canEnableUnsupportedBlockEditor && this.props.isUnsupportedBlockEditorSupported === false) {
      this.toggleSheet();
      this.setState({
        sendButtonPressMessage: true
      });
    } else {
      this.toggleSheet();
      this.setState({
        sendFallbackMessage: true
      });
    }
  }
  renderSheet(blockTitle, blockName) {
    const {
      block,
      clientId,
      createSuccessNotice,
      replaceBlocks
    } = this.props;
    const {
      showHelp
    } = this.state;

    /* translators: Missing block alert title. %s: The localized block name */
    const titleFormat = (0, _i18n.__)("'%s' is not fully-supported");
    const title = (0, _i18n.sprintf)(titleFormat, blockTitle);
    let description = (0, _hooks.applyFilters)('native.missing_block_detail', (0, _i18n.__)('We are working hard to add more blocks with each release.'), blockName);
    let customActions = EMPTY_ARRAY;

    // For Classic blocks, we offer the alternative to convert the content to blocks.
    if (blockName === 'core/freeform') {
      description += ' ' + (0, _i18n.__)('Alternatively, you can convert the content to blocks.');
      /* translators: displayed right after the classic block is converted to blocks. %s: The localized classic block name */
      const successNotice = (0, _i18n.__)("'%s' block converted to blocks");
      customActions = [{
        label: (0, _i18n.__)('Convert to blocks'),
        onPress: () => {
          createSuccessNotice((0, _i18n.sprintf)(successNotice, blockTitle));
          replaceBlocks(block);
        }
      }];
    }
    return (0, _react.createElement)(_blockEditor.UnsupportedBlockDetails, {
      clientId: clientId,
      showSheet: showHelp,
      onCloseSheet: this.closeSheet,
      customBlockTitle: blockTitle,
      title: title,
      description: description,
      customActions: customActions
    });
  }
  render() {
    const {
      originalName
    } = this.props.attributes;
    const {
      getStylesFromColorScheme,
      preferredColorScheme
    } = this.props;
    const blockType = _blockLibrary.coreBlocks[originalName];
    const title = this.getTitle();
    const titleStyle = getStylesFromColorScheme(_style.default.unsupportedBlockMessage, _style.default.unsupportedBlockMessageDark);
    const subTitleStyle = getStylesFromColorScheme(_style.default.unsupportedBlockSubtitle, _style.default.unsupportedBlockSubtitleDark);
    const subtitle = (0, _react.createElement)(_reactNative.Text, {
      style: subTitleStyle
    }, (0, _i18n.__)('Unsupported'));
    const icon = blockType ? (0, _blocks.normalizeIconObject)(blockType.settings.icon) : _icons.plugins;
    const iconStyle = getStylesFromColorScheme(_style.default.unsupportedBlockIcon, _style.default.unsupportedBlockIconDark);
    const iconClassName = 'unsupported-icon' + '-' + preferredColorScheme;
    return (0, _react.createElement)(_reactNative.TouchableWithoutFeedback, {
      disabled: !this.props.isSelected,
      accessibilityLabel: (0, _i18n.__)('Help button'),
      accessibilityRole: 'button',
      accessibilityHint: (0, _i18n.__)('Tap here to show help'),
      onPress: this.toggleSheet
    }, (0, _react.createElement)(_reactNative.View, {
      style: getStylesFromColorScheme(_style.default.unsupportedBlock, _style.default.unsupportedBlockDark)
    }, this.renderHelpIcon(), (0, _react.createElement)(_reactNative.View, {
      style: _style.default.unsupportedBlockHeader
    }, (0, _react.createElement)(_components.Icon, {
      className: iconClassName,
      icon: icon && icon.src ? icon.src : icon,
      fill: iconStyle.color
    }), (0, _react.createElement)(_reactNative.Text, {
      style: titleStyle
    }, title)), subtitle, this.renderSheet(title, originalName)));
  }
}
exports.UnsupportedBlockEdit = UnsupportedBlockEdit;
var _default = exports.default = (0, _compose.compose)([(0, _data.withSelect)((select, {
  attributes,
  clientId
}) => {
  const {
    getBlock,
    getSettings
  } = select(_blockEditor.store);
  const {
    capabilities
  } = getSettings();
  return {
    isUnsupportedBlockEditorSupported: capabilities?.unsupportedBlockEditor === true,
    canEnableUnsupportedBlockEditor: capabilities?.canEnableUnsupportedBlockEditor === true,
    isEditableInUnsupportedBlockEditor: !UBE_INCOMPATIBLE_BLOCKS.includes(attributes.originalName),
    block: getBlock(clientId)
  };
}), (0, _data.withDispatch)((dispatch, ownProps) => {
  const {
    selectBlock,
    replaceBlocks
  } = dispatch(_blockEditor.store);
  const {
    createSuccessNotice
  } = dispatch(_notices.store);
  return {
    selectBlock() {
      selectBlock(ownProps.clientId);
    },
    replaceBlocks(block) {
      replaceBlocks(ownProps.clientId, (0, _blocks.rawHandler)({
        HTML: (0, _blocks.serialize)(block)
      }));
    },
    createSuccessNotice
  };
}), _compose.withPreferredColorScheme])(UnsupportedBlockEdit);
//# sourceMappingURL=edit.native.js.map