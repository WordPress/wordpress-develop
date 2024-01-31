"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = exports.MoreEdit = void 0;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _element = require("@wordpress/element");
var _compose = require("@wordpress/compose");
var _components = require("@wordpress/components");
var _editor = _interopRequireDefault(require("./editor.scss"));
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

class MoreEdit extends _element.Component {
  constructor() {
    super(...arguments);
    this.state = {
      defaultText: (0, _i18n.__)('Read more')
    };
  }
  render() {
    const {
      attributes,
      getStylesFromColorScheme
    } = this.props;
    const {
      customText
    } = attributes;
    const {
      defaultText
    } = this.state;
    const content = customText || defaultText;
    const textStyle = getStylesFromColorScheme(_editor.default.moreText, _editor.default.moreTextDark);
    const lineStyle = getStylesFromColorScheme(_editor.default.moreLine, _editor.default.moreLineDark);
    return (0, _react.createElement)(_components.HorizontalRule, {
      text: content,
      marginLeft: 0,
      marginRight: 0,
      textStyle: textStyle,
      lineStyle: lineStyle
    });
  }
}
exports.MoreEdit = MoreEdit;
var _default = exports.default = (0, _compose.withPreferredColorScheme)(MoreEdit);
//# sourceMappingURL=edit.native.js.map