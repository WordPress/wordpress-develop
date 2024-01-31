"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.formSubmissionNotificationSuccess = exports.formSubmissionNotificationError = void 0;
var _i18n = require("@wordpress/i18n");
/**
 * WordPress dependencies
 */

const formSubmissionNotificationSuccess = exports.formSubmissionNotificationSuccess = ['core/form-submission-notification', {
  type: 'success'
}, [['core/paragraph', {
  content: '<mark style="background-color:rgba(0, 0, 0, 0);color:#345C00" class="has-inline-color">' + (0, _i18n.__)('Your form has been submitted successfully') + '</mark>'
}]]];
const formSubmissionNotificationError = exports.formSubmissionNotificationError = ['core/form-submission-notification', {
  type: 'error'
}, [['core/paragraph', {
  content: '<mark style="background-color:rgba(0, 0, 0, 0);color:#CF2E2E" class="has-inline-color">' + (0, _i18n.__)('There was an error submitting your form.') + '</mark>'
}]]];
//# sourceMappingURL=utils.js.map