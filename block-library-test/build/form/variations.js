"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _i18n = require("@wordpress/i18n");
var _utils = require("./utils.js");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const variations = [{
  name: 'comment-form',
  title: (0, _i18n.__)('Experimental Comment form'),
  description: (0, _i18n.__)('A comment form for posts and pages.'),
  attributes: {
    submissionMethod: 'custom',
    action: '{SITE_URL}/wp-comments-post.php',
    method: 'post',
    anchor: 'comment-form'
  },
  isDefault: false,
  innerBlocks: [['core/form-input', {
    type: 'text',
    name: 'author',
    label: (0, _i18n.__)('Name'),
    required: true,
    visibilityPermissions: 'logged-out'
  }], ['core/form-input', {
    type: 'email',
    name: 'email',
    label: (0, _i18n.__)('Email'),
    required: true,
    visibilityPermissions: 'logged-out'
  }], ['core/form-input', {
    type: 'textarea',
    name: 'comment',
    label: (0, _i18n.__)('Comment'),
    required: true,
    visibilityPermissions: 'all'
  }], ['core/form-submit-button', {}]],
  scope: ['inserter', 'transform'],
  isActive: blockAttributes => !blockAttributes?.type || blockAttributes?.type === 'text'
}, {
  name: 'wp-privacy-form',
  title: (0, _i18n.__)('Experimental privacy request form'),
  keywords: ['GDPR'],
  description: (0, _i18n.__)('A form to request data exports and/or deletion.'),
  attributes: {
    submissionMethod: 'custom',
    action: '',
    method: 'post',
    anchor: 'gdpr-form'
  },
  isDefault: false,
  innerBlocks: [_utils.formSubmissionNotificationSuccess, _utils.formSubmissionNotificationError, ['core/paragraph', {
    content: (0, _i18n.__)('To request an export or deletion of your personal data on this site, please fill-in the form below. You can define the type of request you wish to perform, and your email address. Once the form is submitted, you will receive a confirmation email with instructions on the next steps.')
  }], ['core/form-input', {
    type: 'email',
    name: 'email',
    label: (0, _i18n.__)('Enter your email address.'),
    required: true,
    visibilityPermissions: 'all'
  }], ['core/form-input', {
    type: 'checkbox',
    name: 'export_personal_data',
    label: (0, _i18n.__)('Request data export'),
    required: false,
    visibilityPermissions: 'all'
  }], ['core/form-input', {
    type: 'checkbox',
    name: 'remove_personal_data',
    label: (0, _i18n.__)('Request data deletion'),
    required: false,
    visibilityPermissions: 'all'
  }], ['core/form-submit-button', {}], ['core/form-input', {
    type: 'hidden',
    name: 'wp-action',
    value: 'wp_privacy_send_request'
  }], ['core/form-input', {
    type: 'hidden',
    name: 'wp-privacy-request',
    value: '1'
  }]],
  scope: ['inserter', 'transform'],
  isActive: blockAttributes => !blockAttributes?.type || blockAttributes?.type === 'text'
}];
var _default = exports.default = variations;
//# sourceMappingURL=variations.js.map