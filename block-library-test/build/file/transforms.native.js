"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _transforms = _interopRequireDefault(require("./transforms.js"));
var _transformationCategories = _interopRequireDefault(require("../utils/transformation-categories"));
/**
 * Internal dependencies
 */

const transforms = {
  ..._transforms.default,
  supportedMobileTransforms: _transformationCategories.default.media
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.native.js.map