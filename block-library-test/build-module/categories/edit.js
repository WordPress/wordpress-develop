import { createElement, Fragment } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { PanelBody, Placeholder, Spinner, ToggleControl, VisuallyHidden } from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';
import { pin } from '@wordpress/icons';
import { useEntityRecords } from '@wordpress/core-data';
export default function CategoriesEdit({
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
  const selectId = useInstanceId(CategoriesEdit, 'blocks-category-select');
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
  } = useEntityRecords('taxonomy', 'category', query);
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
  const renderCategoryName = name => !name ? __('(Untitled)') : decodeEntities(name).trim();
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
    return createElement("li", {
      key: id,
      className: `cat-item cat-item-${id}`
    }, createElement("a", {
      href: link,
      target: "_blank",
      rel: "noreferrer noopener"
    }, renderCategoryName(name)), showPostCounts && ` (${count})`, showHierarchy && !!childCategories.length && createElement("ul", {
      className: "children"
    }, childCategories.map(childCategory => renderCategoryListItem(childCategory))));
  };
  const renderCategoryDropdown = () => {
    const parentId = showHierarchy ? 0 : null;
    const categoriesList = getCategoriesList(parentId);
    return createElement(Fragment, null, createElement(VisuallyHidden, {
      as: "label",
      htmlFor: selectId
    }, __('Categories')), createElement("select", {
      id: selectId
    }, createElement("option", null, __('Select Category')), categoriesList.map(category => renderCategoryDropdownItem(category, 0))));
  };
  const renderCategoryDropdownItem = (category, level) => {
    const {
      id,
      count,
      name
    } = category;
    const childCategories = getCategoriesList(id);
    return [createElement("option", {
      key: id,
      className: `level-${level}`
    }, Array.from({
      length: level * 3
    }).map(() => '\xa0'), renderCategoryName(name), showPostCounts && ` (${count})`), showHierarchy && !!childCategories.length && childCategories.map(childCategory => renderCategoryDropdownItem(childCategory, level + 1))];
  };
  const TagName = !!categories?.length && !displayAsDropdown && !isResolving ? 'ul' : 'div';
  const classes = classnames(className, {
    'wp-block-categories-list': !!categories?.length && !displayAsDropdown && !isResolving,
    'wp-block-categories-dropdown': !!categories?.length && displayAsDropdown && !isResolving
  });
  const blockProps = useBlockProps({
    className: classes
  });
  return createElement(TagName, {
    ...blockProps
  }, createElement(InspectorControls, null, createElement(PanelBody, {
    title: __('Settings')
  }, createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Display as dropdown'),
    checked: displayAsDropdown,
    onChange: toggleAttribute('displayAsDropdown')
  }), createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Show post counts'),
    checked: showPostCounts,
    onChange: toggleAttribute('showPostCounts')
  }), createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Show only top level categories'),
    checked: showOnlyTopLevel,
    onChange: toggleAttribute('showOnlyTopLevel')
  }), createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Show empty categories'),
    checked: showEmpty,
    onChange: toggleAttribute('showEmpty')
  }), !showOnlyTopLevel && createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Show hierarchy'),
    checked: showHierarchy,
    onChange: toggleAttribute('showHierarchy')
  }))), isResolving && createElement(Placeholder, {
    icon: pin,
    label: __('Categories')
  }, createElement(Spinner, null)), !isResolving && categories?.length === 0 && createElement("p", null, __('Your site does not have any posts, so there is nothing to display here at the moment.')), !isResolving && categories?.length > 0 && (displayAsDropdown ? renderCategoryDropdown() : renderCategoryList()));
}
//# sourceMappingURL=edit.js.map