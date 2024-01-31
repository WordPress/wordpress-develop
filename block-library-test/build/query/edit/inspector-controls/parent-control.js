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
var _compose = require("@wordpress/compose");
var _utils = require("../../utils");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const EMPTY_ARRAY = [];
const BASE_QUERY = {
  order: 'asc',
  _fields: 'id,title',
  context: 'view'
};
function ParentControl({
  parents,
  postType,
  onChange
}) {
  const [search, setSearch] = (0, _element.useState)('');
  const [value, setValue] = (0, _element.useState)(EMPTY_ARRAY);
  const [suggestions, setSuggestions] = (0, _element.useState)(EMPTY_ARRAY);
  const debouncedSearch = (0, _compose.useDebounce)(setSearch, 250);
  const {
    searchResults,
    searchHasResolved
  } = (0, _data.useSelect)(select => {
    if (!search) {
      return {
        searchResults: EMPTY_ARRAY,
        searchHasResolved: true
      };
    }
    const {
      getEntityRecords,
      hasFinishedResolution
    } = select(_coreData.store);
    const selectorArgs = ['postType', postType, {
      ...BASE_QUERY,
      search,
      orderby: 'relevance',
      exclude: parents,
      per_page: 20
    }];
    return {
      searchResults: getEntityRecords(...selectorArgs),
      searchHasResolved: hasFinishedResolution('getEntityRecords', selectorArgs)
    };
  }, [search, parents]);
  const currentParents = (0, _data.useSelect)(select => {
    if (!parents?.length) return EMPTY_ARRAY;
    const {
      getEntityRecords
    } = select(_coreData.store);
    return getEntityRecords('postType', postType, {
      ...BASE_QUERY,
      include: parents,
      per_page: parents.length
    });
  }, [parents]);
  // Update the `value` state only after the selectors are resolved
  // to avoid emptying the input when we're changing parents.
  (0, _element.useEffect)(() => {
    if (!parents?.length) {
      setValue(EMPTY_ARRAY);
    }
    if (!currentParents?.length) return;
    const currentParentsInfo = (0, _utils.getEntitiesInfo)((0, _utils.mapToIHasNameAndId)(currentParents, 'title.rendered'));
    // Returns only the existing entity ids. This prevents the component
    // from crashing in the editor, when non existing ids are provided.
    const sanitizedValue = parents.reduce((accumulator, id) => {
      const entity = currentParentsInfo.mapById[id];
      if (entity) {
        accumulator.push({
          id,
          value: entity.name
        });
      }
      return accumulator;
    }, []);
    setValue(sanitizedValue);
  }, [parents, currentParents]);
  const entitiesInfo = (0, _element.useMemo)(() => {
    if (!searchResults?.length) return EMPTY_ARRAY;
    return (0, _utils.getEntitiesInfo)((0, _utils.mapToIHasNameAndId)(searchResults, 'title.rendered'));
  }, [searchResults]);
  // Update suggestions only when the query has resolved.
  (0, _element.useEffect)(() => {
    if (!searchHasResolved) return;
    setSuggestions(entitiesInfo.names);
  }, [entitiesInfo.names, searchHasResolved]);
  const getIdByValue = (entitiesMappedByName, entity) => {
    const id = entity?.id || entitiesMappedByName?.[entity]?.id;
    if (id) return id;
  };
  const onParentChange = newValue => {
    const ids = Array.from(newValue.reduce((accumulator, entity) => {
      // Verify that new values point to existing entities.
      const id = getIdByValue(entitiesInfo.mapByName, entity);
      if (id) accumulator.add(id);
      return accumulator;
    }, new Set()));
    setSuggestions(EMPTY_ARRAY);
    onChange({
      parents: ids
    });
  };
  return (0, _react.createElement)(_components.FormTokenField, {
    label: (0, _i18n.__)('Parents'),
    value: value,
    onInputChange: debouncedSearch,
    suggestions: suggestions,
    onChange: onParentChange,
    __experimentalShowHowTo: false
  });
}
var _default = exports.default = ParentControl;
//# sourceMappingURL=parent-control.js.map