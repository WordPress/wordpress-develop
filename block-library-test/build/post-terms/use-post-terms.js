"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = usePostTerms;
var _coreData = require("@wordpress/core-data");
var _data = require("@wordpress/data");
/**
 * WordPress dependencies
 */

const EMPTY_ARRAY = [];
function usePostTerms({
  postId,
  term
}) {
  const {
    slug
  } = term;
  return (0, _data.useSelect)(select => {
    const visible = term?.visibility?.publicly_queryable;
    if (!visible) {
      return {
        postTerms: EMPTY_ARRAY,
        isLoading: false,
        hasPostTerms: false
      };
    }
    const {
      getEntityRecords,
      isResolving
    } = select(_coreData.store);
    const taxonomyArgs = ['taxonomy', slug, {
      post: postId,
      per_page: -1,
      context: 'view'
    }];
    const terms = getEntityRecords(...taxonomyArgs);
    return {
      postTerms: terms,
      isLoading: isResolving('getEntityRecords', taxonomyArgs),
      hasPostTerms: !!terms?.length
    };
  }, [postId, term?.visibility?.publicly_queryable, slug]);
}
//# sourceMappingURL=use-post-terms.js.map