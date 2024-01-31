"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _icons = require("@wordpress/icons");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _edit = _interopRequireDefault(require("./edit"));
var _save = _interopRequireDefault(require("./save"));
var _variations = _interopRequireDefault(require("./variations"));
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  __experimental: true,
  name: "core/form-submission-notification",
  title: "Form Submission Notification",
  category: "common",
  ancestor: ["core/form"],
  description: "Provide a notification message after the form has been submitted.",
  keywords: ["form", "feedback", "notification", "message"],
  textdomain: "default",
  icon: "feedback",
  attributes: {
    type: {
      type: "string",
      "default": "success"
    }
  }
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.group,
  edit: _edit.default,
  save: _save.default,
  variations: _variations.default
};
const init = () => (0, _initBlock.default)({
  name,
  metadata,
  settings
});
exports.init = init;
//# sourceMappingURL=index.js.map