"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = HomeEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _data = require("@wordpress/data");
var _coreData = require("@wordpress/core-data");
var _element = require("@wordpress/element");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

const preventDefault = event => event.preventDefault();
function HomeEdit({
  attributes,
  setAttributes,
  context
}) {
  const {
    homeUrl
  } = (0, _data.useSelect)(select => {
    const {
      getUnstableBase // Site index.
    } = select(_coreData.store);
    return {
      homeUrl: getUnstableBase()?.home
    };
  }, []);
  const {
    __unstableMarkNextChangeAsNotPersistent
  } = (0, _data.useDispatch)(_blockEditor.store);
  const {
    textColor,
    backgroundColor,
    style
  } = context;
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)('wp-block-navigation-item', {
      'has-text-color': !!textColor || !!style?.color?.text,
      [`has-${textColor}-color`]: !!textColor,
      'has-background': !!backgroundColor || !!style?.color?.background,
      [`has-${backgroundColor}-background-color`]: !!backgroundColor
    }),
    style: {
      color: style?.color?.text,
      backgroundColor: style?.color?.background
    }
  });
  const {
    label
  } = attributes;
  (0, _element.useEffect)(() => {
    if (label === undefined) {
      __unstableMarkNextChangeAsNotPersistent();
      setAttributes({
        label: (0, _i18n.__)('Home')
      });
    }
  }, [label]);
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)("div", {
    ...blockProps
  }, (0, _react.createElement)("a", {
    className: "wp-block-home-link__content wp-block-navigation-item__content",
    href: homeUrl,
    onClick: preventDefault
  }, (0, _react.createElement)(_blockEditor.RichText, {
    identifier: "label",
    className: "wp-block-home-link__label",
    value: label,
    onChange: labelValue => {
      setAttributes({
        label: labelValue
      });
    },
    "aria-label": (0, _i18n.__)('Home link text'),
    placeholder: (0, _i18n.__)('Add home link'),
    withoutInteractiveFormatting: true,
    allowedFormats: ['core/bold', 'core/italic', 'core/image', 'core/strikethrough']
  }))));
}
//# sourceMappingURL=edit.js.map