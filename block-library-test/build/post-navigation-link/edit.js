"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = PostNavigationLinkEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _data = require("@wordpress/data");
var _coreData = require("@wordpress/core-data");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

function PostNavigationLinkEdit({
  context: {
    postType
  },
  attributes: {
    type,
    label,
    showTitle,
    textAlign,
    linkLabel,
    arrow,
    taxonomy
  },
  setAttributes
}) {
  const isNext = type === 'next';
  let placeholder = isNext ? (0, _i18n.__)('Next') : (0, _i18n.__)('Previous');
  const arrowMap = {
    none: '',
    arrow: isNext ? '→' : '←',
    chevron: isNext ? '»' : '«'
  };
  const displayArrow = arrowMap[arrow];
  if (showTitle) {
    /* translators: Label before for next and previous post. There is a space after the colon. */
    placeholder = isNext ? (0, _i18n.__)('Next: ') : (0, _i18n.__)('Previous: ');
  }
  const ariaLabel = isNext ? (0, _i18n.__)('Next post') : (0, _i18n.__)('Previous post');
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign
    })
  });
  const taxonomies = (0, _data.useSelect)(select => {
    const {
      getTaxonomies
    } = select(_coreData.store);
    const filteredTaxonomies = getTaxonomies({
      type: postType,
      per_page: -1
    });
    return filteredTaxonomies;
  }, [postType]);
  const getTaxonomyOptions = () => {
    const selectOption = {
      label: (0, _i18n.__)('Unfiltered'),
      value: ''
    };
    const taxonomyOptions = (taxonomies !== null && taxonomies !== void 0 ? taxonomies : []).filter(({
      visibility
    }) => !!visibility?.publicly_queryable).map(item => {
      return {
        value: item.slug,
        label: item.name
      };
    });
    return [selectOption, ...taxonomyOptions];
  };
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, null, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Display the title as a link'),
    help: (0, _i18n.__)('If you have entered a custom label, it will be prepended before the title.'),
    checked: !!showTitle,
    onChange: () => setAttributes({
      showTitle: !showTitle
    })
  }), showTitle && (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Include the label as part of the link'),
    checked: !!linkLabel,
    onChange: () => setAttributes({
      linkLabel: !linkLabel
    })
  }), (0, _react.createElement)(_components.__experimentalToggleGroupControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Arrow'),
    value: arrow,
    onChange: value => {
      setAttributes({
        arrow: value
      });
    },
    help: (0, _i18n.__)('A decorative arrow for the next and previous link.'),
    isBlock: true
  }, (0, _react.createElement)(_components.__experimentalToggleGroupControlOption, {
    value: "none",
    label: (0, _i18n._x)('None', 'Arrow option for Next/Previous link')
  }), (0, _react.createElement)(_components.__experimentalToggleGroupControlOption, {
    value: "arrow",
    label: (0, _i18n._x)('Arrow', 'Arrow option for Next/Previous link')
  }), (0, _react.createElement)(_components.__experimentalToggleGroupControlOption, {
    value: "chevron",
    label: (0, _i18n._x)('Chevron', 'Arrow option for Next/Previous link')
  })))), (0, _react.createElement)(_blockEditor.InspectorControls, {
    group: "advanced"
  }, (0, _react.createElement)(_components.SelectControl, {
    label: (0, _i18n.__)('Filter by taxonomy'),
    value: taxonomy,
    options: getTaxonomyOptions(),
    onChange: value => setAttributes({
      taxonomy: value
    }),
    help: (0, _i18n.__)('Only link to posts that have the same taxonomy terms as the current post. For example the same tags or categories.')
  })), (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_blockEditor.AlignmentToolbar, {
    value: textAlign,
    onChange: nextAlign => {
      setAttributes({
        textAlign: nextAlign
      });
    }
  })), (0, _react.createElement)("div", {
    ...blockProps
  }, !isNext && displayArrow && (0, _react.createElement)("span", {
    className: `wp-block-post-navigation-link__arrow-previous is-arrow-${arrow}`
  }, displayArrow), (0, _react.createElement)(_blockEditor.RichText, {
    tagName: "a",
    "aria-label": ariaLabel,
    placeholder: placeholder,
    value: label,
    allowedFormats: ['core/bold', 'core/italic'],
    onChange: newLabel => setAttributes({
      label: newLabel
    })
  }), showTitle && (0, _react.createElement)("a", {
    href: "#post-navigation-pseudo-link",
    onClick: event => event.preventDefault()
  }, (0, _i18n.__)('An example title')), isNext && displayArrow && (0, _react.createElement)("span", {
    className: `wp-block-post-navigation-link__arrow-next is-arrow-${arrow}`,
    "aria-hidden": true
  }, displayArrow)));
}
//# sourceMappingURL=edit.js.map