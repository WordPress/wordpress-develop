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
  name: 'text',
  title: (0, _i18n.__)('Text Input'),
  icon: 'edit-page',
  description: (0, _i18n.__)('A generic text input.'),
  attributes: {
    type: 'text'
  },
  isDefault: true,
  scope: ['inserter', 'transform'],
  isActive: blockAttributes => !blockAttributes?.type || blockAttributes?.type === 'text'
}, {
  name: 'textarea',
  title: (0, _i18n.__)('Textarea Input'),
  icon: 'testimonial',
  description: (0, _i18n.__)('A textarea input to allow entering multiple lines of text.'),
  attributes: {
    type: 'textarea'
  },
  isDefault: true,
  scope: ['inserter', 'transform'],
  isActive: blockAttributes => blockAttributes?.type === 'textarea'
}, {
  name: 'checkbox',
  title: (0, _i18n.__)('Checkbox Input'),
  description: (0, _i18n.__)('A simple checkbox input.'),
  icon: 'forms',
  attributes: {
    type: 'checkbox',
    inlineLabel: true
  },
  isDefault: true,
  scope: ['inserter', 'transform'],
  isActive: blockAttributes => blockAttributes?.type === 'checkbox'
}, {
  name: 'email',
  title: (0, _i18n.__)('Email Input'),
  icon: 'email',
  description: (0, _i18n.__)('Used for email addresses.'),
  attributes: {
    type: 'email'
  },
  isDefault: true,
  scope: ['inserter', 'transform'],
  isActive: blockAttributes => blockAttributes?.type === 'email'
}, {
  name: 'url',
  title: (0, _i18n.__)('URL Input'),
  icon: 'admin-site',
  description: (0, _i18n.__)('Used for URLs.'),
  attributes: {
    type: 'url'
  },
  isDefault: true,
  scope: ['inserter', 'transform'],
  isActive: blockAttributes => blockAttributes?.type === 'url'
}, {
  name: 'tel',
  title: (0, _i18n.__)('Telephone Input'),
  icon: 'phone',
  description: (0, _i18n.__)('Used for phone numbers.'),
  attributes: {
    type: 'tel'
  },
  isDefault: true,
  scope: ['inserter', 'transform'],
  isActive: blockAttributes => blockAttributes?.type === 'tel'
}, {
  name: 'number',
  title: (0, _i18n.__)('Number Input'),
  icon: 'edit-page',
  description: (0, _i18n.__)('A numeric input.'),
  attributes: {
    type: 'number'
  },
  isDefault: true,
  scope: ['inserter', 'transform'],
  isActive: blockAttributes => blockAttributes?.type === 'number'
}];
var _default = exports.default = variations;
//# sourceMappingURL=variations.js.map