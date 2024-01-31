"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _data = require("@wordpress/data");
var _utils = require("./utils.js");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const TEMPLATE = [_utils.formSubmissionNotificationSuccess, _utils.formSubmissionNotificationError, ['core/form-input', {
  type: 'text',
  label: (0, _i18n.__)('Name'),
  required: true
}], ['core/form-input', {
  type: 'email',
  label: (0, _i18n.__)('Email'),
  required: true
}], ['core/form-input', {
  type: 'textarea',
  label: (0, _i18n.__)('Comment'),
  required: true
}], ['core/form-submit-button', {}]];
const Edit = ({
  attributes,
  setAttributes,
  clientId
}) => {
  const {
    action,
    method,
    email,
    submissionMethod
  } = attributes;
  const blockProps = (0, _blockEditor.useBlockProps)();
  const {
    hasInnerBlocks
  } = (0, _data.useSelect)(select => {
    const {
      getBlock
    } = select(_blockEditor.store);
    const block = getBlock(clientId);
    return {
      hasInnerBlocks: !!(block && block.innerBlocks.length)
    };
  }, [clientId]);
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(blockProps, {
    template: TEMPLATE,
    renderAppender: hasInnerBlocks ? undefined : _blockEditor.InnerBlocks.ButtonBlockAppender
  });
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.SelectControl
  // __nextHasNoMarginBottom
  // size={ '__unstable-large' }
  , {
    label: (0, _i18n.__)('Submissions method'),
    options: [
    // TODO: Allow plugins to add their own submission methods.
    {
      label: (0, _i18n.__)('Send email'),
      value: 'email'
    }, {
      label: (0, _i18n.__)('- Custom -'),
      value: 'custom'
    }],
    value: submissionMethod,
    onChange: value => setAttributes({
      submissionMethod: value
    }),
    help: submissionMethod === 'custom' ? (0, _i18n.__)('Select the method to use for form submissions. Additional options for the "custom" mode can be found in the "Advanced" section.') : (0, _i18n.__)('Select the method to use for form submissions.')
  }), submissionMethod === 'email' && (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true,
    autoComplete: "off",
    label: (0, _i18n.__)('Email for form submissions'),
    value: email,
    required: true,
    onChange: value => {
      setAttributes({
        email: value
      });
      setAttributes({
        action: `mailto:${value}`
      });
      setAttributes({
        method: 'post'
      });
    },
    help: (0, _i18n.__)('The email address where form submissions will be sent. Separate multiple email addresses with a comma.')
  }))), submissionMethod !== 'email' && (0, _react.createElement)(_blockEditor.InspectorControls, {
    group: "advanced"
  }, (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Method'),
    options: [{
      label: 'Get',
      value: 'get'
    }, {
      label: 'Post',
      value: 'post'
    }],
    value: method,
    onChange: value => setAttributes({
      method: value
    }),
    help: (0, _i18n.__)('Select the method to use for form submissions.')
  }), (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true,
    autoComplete: "off",
    label: (0, _i18n.__)('Form action'),
    value: action,
    onChange: newVal => {
      setAttributes({
        action: newVal
      });
    },
    help: (0, _i18n.__)('The URL where the form should be submitted.')
  })), (0, _react.createElement)("form", {
    ...innerBlocksProps,
    className: "wp-block-form",
    encType: submissionMethod === 'email' ? 'text/plain' : null
  }));
};
var _default = exports.default = Edit;
//# sourceMappingURL=edit.js.map