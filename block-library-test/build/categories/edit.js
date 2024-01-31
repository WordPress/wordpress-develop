"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = CategoriesEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _components = require("@wordpress/components");
var _compose = require("@wordpress/compose");
var _blockEditor = require("@wordpress/block-editor");
var _htmlEntities = require("@wordpress/html-entities");
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _coreData = require("@wordpress/core-data");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

function CategoriesEdit({
  attributes: {
    displayAsDropdown,
    showHierarchy,
    showPostCounts,
    showOnlyTopLevel,
    showEmpty
  },
  setAttributes,
  className
}) {
  const selectId = (0, _compose.useInstanceId)(CategoriesEdit, 'blocks-category-select');
  const query = {
    per_page: -1,
    hide_empty: !showEmpty,
    context: 'view'
  };
  if (showOnlyTopLevel) {
    query.parent = 0;
  }
  const {
    records: categories,
    isResolving
  } = (0, _coreData.useEntityRecords)('taxonomy', 'category', query);
  const getCategoriesList = parentId => {
    if (!categories?.length) {
      return [];
    }
    if (parentId === null) {
      return categories;
    }
    return categories.filter(({
      parent
    }) => parent === parentId);
  };
  const toggleAttribute = attributeName => newValue => setAttributes({
    [attributeName]: newValue
  });
  const renderCategoryName = name => !name ? (0, _i18n.__)('(Untitled)') : (0, _htmlEntities.decodeEntities)(name).trim();
  const renderCategoryList = () => {
    const parentId = showHierarchy ? 0 : null;
    const categoriesList = getCategoriesList(parentId);
    return categoriesList.map(category => renderCategoryListItem(category));
  };
  const renderCategoryListItem = category => {
    const childCategories = getCategoriesList(category.id);
    const {
      id,
      link,
      count,
      name
    } = category;
    return (0, _react.createElement)("li", {
      key: id,
      className: `cat-item cat-item-${id}`
    }, (0, _react.createElement)("a", {
      href: link,
      target: "_blank",
      rel: "noreferrer noopener"
    }, renderCategoryName(name)), showPostCounts && ` (${count})`, showHierarchy && !!childCategories.length && (0, _react.createElement)("ul", {
      className: "children"
    }, childCategories.map(childCategory => renderCategoryListItem(childCategory))));
  };
  const renderCategoryDropdown = () => {
    const parentId = showHierarchy ? 0 : null;
    const categoriesList = getCategoriesList(parentId);
    return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.VisuallyHidden, {
      as: "label",
      htmlFor: selectId
    }, (0, _i18n.__)('Categories')), (0, _react.createElement)("select", {
      id: selectId
    }, (0, _react.createElement)("option", null, (0, _i18n.__)('Select Category')), categoriesList.map(category => renderCategoryDropdownItem(category, 0))));
  };
  const renderCategoryDropdownItem = (category, level) => {
    const {
      id,
      count,
      name
    } = category;
    const childCategories = getCategoriesList(id);
    return [(0, _react.createElement)("option", {
      key: id,
      className: `level-${level}`
    }, Array.from({
      length: level * 3
    }).map(() => '\xa0'), renderCategoryName(name), showPostCounts && ` (${count})`), showHierarchy && !!childCategories.length && childCategories.map(childCategory => renderCategoryDropdownItem(childCategory, level + 1))];
  };
  const TagName = !!categories?.length && !displayAsDropdown && !isResolving ? 'ul' : 'div';
  const classes = (0, _classnames.default)(className, {
    'wp-block-categories-list': !!categories?.length && !displayAsDropdown && !isResolving,
    'wp-block-categories-dropdown': !!categories?.length && displayAsDropdown && !isResolving
  });
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: classes
  });
  return (0, _react.createElement)(TagName, {
    ...blockProps
  }, (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Display as dropdown'),
    checked: displayAsDropdown,
    onChange: toggleAttribute('displayAsDropdown')
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Show post counts'),
    checked: showPostCounts,
    onChange: toggleAttribute('showPostCounts')
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Show only top level categories'),
    checked: showOnlyTopLevel,
    onChange: toggleAttribute('showOnlyTopLevel')
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Show empty categories'),
    checked: showEmpty,
    onChange: toggleAttribute('showEmpty')
  }), !showOnlyTopLevel && (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Show hierarchy'),
    checked: showHierarchy,
    onChange: toggleAttribute('showHierarchy')
  }))), isResolving && (0, _react.createElement)(_components.Placeholder, {
    icon: _icons.pin,
    label: (0, _i18n.__)('Categories')
  }, (0, _react.createElement)(_components.Spinner, null)), !isResolving && categories?.length === 0 && (0, _react.createElement)("p", null, (0, _i18n.__)('Your site does not have any posts, so there is nothing to display here at the moment.')), !isResolving && categories?.length > 0 && (displayAsDropdown ? renderCategoryDropdown() : renderCategoryList()));
}
//# sourceMappingURL=edit.js.map