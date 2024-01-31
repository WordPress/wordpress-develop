"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.TaxonomyControls = TaxonomyControls;
var _react = require("react");
var _components = require("@wordpress/components");
var _data = require("@wordpress/data");
var _coreData = require("@wordpress/core-data");
var _element = require("@wordpress/element");
var _compose = require("@wordpress/compose");
var _htmlEntities = require("@wordpress/html-entities");
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
  _fields: 'id,name',
  context: 'view'
};

// Helper function to get the term id based on user input in terms `FormTokenField`.
const getTermIdByTermValue = (terms, termValue) => {
  // First we check for exact match by `term.id` or case sensitive `term.name` match.
  const termId = termValue?.id || terms?.find(term => term.name === termValue)?.id;
  if (termId) {
    return termId;
  }

  /**
   * Here we make an extra check for entered terms in a non case sensitive way,
   * to match user expectations, due to `FormTokenField` behaviour that shows
   * suggestions which are case insensitive.
   *
   * Although WP tries to discourage users to add terms with the same name (case insensitive),
   * it's still possible if you manually change the name, as long as the terms have different slugs.
   * In this edge case we always apply the first match from the terms list.
   */
  const termValueLower = termValue.toLocaleLowerCase();
  return terms?.find(term => term.name.toLocaleLowerCase() === termValueLower)?.id;
};
function TaxonomyControls({
  onChange,
  query
}) {
  const {
    postType,
    taxQuery
  } = query;
  const taxonomies = (0, _utils.useTaxonomies)(postType);
  if (!taxonomies || taxonomies.length === 0) {
    return null;
  }
  return (0, _react.createElement)(_react.Fragment, null, taxonomies.map(taxonomy => {
    const termIds = taxQuery?.[taxonomy.slug] || [];
    const handleChange = newTermIds => onChange({
      taxQuery: {
        ...taxQuery,
        [taxonomy.slug]: newTermIds
      }
    });
    return (0, _react.createElement)(TaxonomyItem, {
      key: taxonomy.slug,
      taxonomy: taxonomy,
      termIds: termIds,
      onChange: handleChange
    });
  }));
}

/**
 * Renders a `FormTokenField` for a given taxonomy.
 *
 * @param {Object}   props          The props for the component.
 * @param {Object}   props.taxonomy The taxonomy object.
 * @param {number[]} props.termIds  An array with the block's term ids for the given taxonomy.
 * @param {Function} props.onChange Callback `onChange` function.
 * @return {JSX.Element} The rendered component.
 */
function TaxonomyItem({
  taxonomy,
  termIds,
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
    const selectorArgs = ['taxonomy', taxonomy.slug, {
      ...BASE_QUERY,
      search,
      orderby: 'name',
      exclude: termIds,
      per_page: 20
    }];
    return {
      searchResults: getEntityRecords(...selectorArgs),
      searchHasResolved: hasFinishedResolution('getEntityRecords', selectorArgs)
    };
  }, [search, termIds]);
  // `existingTerms` are the ones fetched from the API and their type is `{ id: number; name: string }`.
  // They are used to extract the terms' names to populate the `FormTokenField` properly
  // and to sanitize the provided `termIds`, by setting only the ones that exist.
  const existingTerms = (0, _data.useSelect)(select => {
    if (!termIds?.length) return EMPTY_ARRAY;
    const {
      getEntityRecords
    } = select(_coreData.store);
    return getEntityRecords('taxonomy', taxonomy.slug, {
      ...BASE_QUERY,
      include: termIds,
      per_page: termIds.length
    });
  }, [termIds]);
  // Update the `value` state only after the selectors are resolved
  // to avoid emptying the input when we're changing terms.
  (0, _element.useEffect)(() => {
    if (!termIds?.length) {
      setValue(EMPTY_ARRAY);
    }
    if (!existingTerms?.length) return;
    // Returns only the existing entity ids. This prevents the component
    // from crashing in the editor, when non existing ids are provided.
    const sanitizedValue = termIds.reduce((accumulator, id) => {
      const entity = existingTerms.find(term => term.id === id);
      if (entity) {
        accumulator.push({
          id,
          value: entity.name
        });
      }
      return accumulator;
    }, []);
    setValue(sanitizedValue);
  }, [termIds, existingTerms]);
  // Update suggestions only when the query has resolved.
  (0, _element.useEffect)(() => {
    if (!searchHasResolved) return;
    setSuggestions(searchResults.map(result => result.name));
  }, [searchResults, searchHasResolved]);
  const onTermsChange = newTermValues => {
    const newTermIds = new Set();
    for (const termValue of newTermValues) {
      const termId = getTermIdByTermValue(searchResults, termValue);
      if (termId) {
        newTermIds.add(termId);
      }
    }
    setSuggestions(EMPTY_ARRAY);
    onChange(Array.from(newTermIds));
  };
  return (0, _react.createElement)("div", {
    className: "block-library-query-inspector__taxonomy-control"
  }, (0, _react.createElement)(_components.FormTokenField, {
    label: taxonomy.name,
    value: value,
    onInputChange: debouncedSearch,
    suggestions: suggestions,
    displayTransform: _htmlEntities.decodeEntities,
    onChange: onTermsChange,
    __experimentalShowHowTo: false
  }));
}
//# sourceMappingURL=taxonomy-controls.js.map