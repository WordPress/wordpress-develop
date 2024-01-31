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
var _utils = require("../../utils");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const AUTHORS_QUERY = {
  who: 'authors',
  per_page: -1,
  _fields: 'id,name',
  context: 'view'
};
function AuthorControl({
  value,
  onChange
}) {
  const authorsList = (0, _data.useSelect)(select => {
    const {
      getUsers
    } = select(_coreData.store);
    return getUsers(AUTHORS_QUERY);
  }, []);
  if (!authorsList) {
    return null;
  }
  const authorsInfo = (0, _utils.getEntitiesInfo)(authorsList);
  /**
   * We need to normalize the value because the block operates on a
   * comma(`,`) separated string value and `FormTokenFiels` needs an
   * array.
   */
  const normalizedValue = !value ? [] : value.toString().split(',');
  // Returns only the existing authors ids. This prevents the component
  // from crashing in the editor, when non existing ids are provided.
  const sanitizedValue = normalizedValue.reduce((accumulator, authorId) => {
    const author = authorsInfo.mapById[authorId];
    if (author) {
      accumulator.push({
        id: authorId,
        value: author.name
      });
    }
    return accumulator;
  }, []);
  const getIdByValue = (entitiesMappedByName, authorValue) => {
    const id = authorValue?.id || entitiesMappedByName[authorValue]?.id;
    if (id) return id;
  };
  const onAuthorChange = newValue => {
    const ids = Array.from(newValue.reduce((accumulator, author) => {
      // Verify that new values point to existing entities.
      const id = getIdByValue(authorsInfo.mapByName, author);
      if (id) accumulator.add(id);
      return accumulator;
    }, new Set()));
    onChange({
      author: ids.join(',')
    });
  };
  return (0, _react.createElement)(_components.FormTokenField, {
    label: (0, _i18n.__)('Authors'),
    value: sanitizedValue,
    suggestions: authorsInfo.names,
    onChange: onAuthorChange,
    __experimentalShowHowTo: false
  });
}
var _default = exports.default = AuthorControl;
//# sourceMappingURL=author-control.js.map