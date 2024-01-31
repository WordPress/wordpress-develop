"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _data = require("@wordpress/data");
var _coreData = require("@wordpress/core-data");
var _element = require("@wordpress/element");
/**
 * WordPress dependencies
 */

const AUTHORS_QUERY = {
  who: 'authors',
  per_page: -1,
  _fields: 'id,name',
  context: 'view'
};
function UserControl({
  value,
  onChange
}) {
  const [filteredAuthorsList, setFilteredAuthorsList] = (0, _element.useState)();
  const authorsList = (0, _data.useSelect)(select => {
    const {
      getUsers
    } = select(_coreData.store);
    return getUsers(AUTHORS_QUERY);
  }, []);
  if (!authorsList) {
    return null;
  }
  const options = authorsList.map(author => {
    return {
      label: author.name,
      value: author.id
    };
  });
  return (0, _react.createElement)(_components.ComboboxControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('User'),
    help: (0, _i18n.__)('Select the avatar user to display, if it is blank it will use the post/page author.'),
    value: value,
    onChange: onChange,
    options: filteredAuthorsList || options,
    onFilterValueChange: inputValue => setFilteredAuthorsList(options.filter(option => option.label.toLowerCase().startsWith(inputValue.toLowerCase())))
  });
}
var _default = exports.default = UserControl;
//# sourceMappingURL=user-control.js.map