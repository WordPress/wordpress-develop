"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _i18n = require("@wordpress/i18n");
/**
 * WordPress dependencies
 */

const variations = [{
  name: 'form-submission-success',
  title: (0, _i18n.__)('Form Submission Success'),
  description: (0, _i18n.__)('Success message for form submissions.'),
  attributes: {
    type: 'success'
  },
  isDefault: true,
  innerBlocks: [['core/paragraph', {
    content: (0, _i18n.__)('Your form has been submitted successfully.'),
    backgroundColor: '#00D084',
    textColor: '#000000',
    style: {
      elements: {
        link: {
          color: {
            text: '#000000'
          }
        }
      }
    }
  }]],
  scope: ['inserter', 'transform'],
  isActive: blockAttributes => !blockAttributes?.type || blockAttributes?.type === 'success'
}, {
  name: 'form-submission-error',
  title: (0, _i18n.__)('Form Submission Error'),
  description: (0, _i18n.__)('Error/failure message for form submissions.'),
  attributes: {
    type: 'error'
  },
  isDefault: false,
  innerBlocks: [['core/paragraph', {
    content: (0, _i18n.__)('There was an error submitting your form.'),
    backgroundColor: '#CF2E2E',
    textColor: '#FFFFFF',
    style: {
      elements: {
        link: {
          color: {
            text: '#FFFFFF'
          }
        }
      }
    }
  }]],
  scope: ['inserter', 'transform'],
  isActive: blockAttributes => !blockAttributes?.type || blockAttributes?.type === 'error'
}];
var _default = exports.default = variations;
//# sourceMappingURL=variations.js.map