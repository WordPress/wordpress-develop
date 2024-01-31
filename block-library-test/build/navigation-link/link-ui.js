"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.LinkUI = LinkUI;
exports.getSuggestionsQuery = getSuggestionsQuery;
var _react = require("react");
var _dom = require("@wordpress/dom");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _coreData = require("@wordpress/core-data");
var _htmlEntities = require("@wordpress/html-entities");
var _blocks = require("@wordpress/blocks");
var _data = require("@wordpress/data");
/**
 * WordPress dependencies
 */

/**
 * Given the Link block's type attribute, return the query params to give to
 * /wp/v2/search.
 *
 * @param {string} type Link block's type attribute.
 * @param {string} kind Link block's entity of kind (post-type|taxonomy)
 * @return {{ type?: string, subtype?: string }} Search query params.
 */
function getSuggestionsQuery(type, kind) {
  switch (type) {
    case 'post':
    case 'page':
      return {
        type: 'post',
        subtype: type
      };
    case 'category':
      return {
        type: 'term',
        subtype: 'category'
      };
    case 'tag':
      return {
        type: 'term',
        subtype: 'post_tag'
      };
    case 'post_format':
      return {
        type: 'post-format'
      };
    default:
      if (kind === 'taxonomy') {
        return {
          type: 'term',
          subtype: type
        };
      }
      if (kind === 'post-type') {
        return {
          type: 'post',
          subtype: type
        };
      }
      return {
        // for custom link which has no type
        // always show pages as initial suggestions
        initialSuggestionsSearchOptions: {
          type: 'post',
          subtype: 'page',
          perPage: 20
        }
      };
  }
}

/**
 * Add transforms to Link Control
 *
 * @param {Object} props          Component props.
 * @param {string} props.clientId Block client ID.
 */
function LinkControlTransforms({
  clientId
}) {
  const {
    getBlock,
    blockTransforms
  } = (0, _data.useSelect)(select => {
    const {
      getBlock: _getBlock,
      getBlockRootClientId,
      getBlockTransformItems
    } = select(_blockEditor.store);
    return {
      getBlock: _getBlock,
      blockTransforms: getBlockTransformItems(_getBlock(clientId), getBlockRootClientId(clientId))
    };
  }, [clientId]);
  const {
    replaceBlock
  } = (0, _data.useDispatch)(_blockEditor.store);
  const featuredBlocks = ['core/page-list', 'core/site-logo', 'core/social-links', 'core/search'];
  const transforms = blockTransforms.filter(item => {
    return featuredBlocks.includes(item.name);
  });
  if (!transforms?.length) {
    return null;
  }
  if (!clientId) {
    return null;
  }
  return (0, _react.createElement)("div", {
    className: "link-control-transform"
  }, (0, _react.createElement)("h3", {
    className: "link-control-transform__subheading"
  }, (0, _i18n.__)('Transform')), (0, _react.createElement)("div", {
    className: "link-control-transform__items"
  }, transforms.map((item, index) => {
    return (0, _react.createElement)(_components.Button, {
      key: `transform-${index}`,
      onClick: () => replaceBlock(clientId, (0, _blocks.switchToBlockType)(getBlock(clientId), item.name)),
      className: "link-control-transform__item"
    }, (0, _react.createElement)(_blockEditor.BlockIcon, {
      icon: item.icon
    }), item.title);
  })));
}
function LinkUI(props) {
  const {
    saveEntityRecord
  } = (0, _data.useDispatch)(_coreData.store);
  const pagesPermissions = (0, _coreData.useResourcePermissions)('pages');
  const postsPermissions = (0, _coreData.useResourcePermissions)('posts');
  async function handleCreate(pageTitle) {
    const postType = props.link.type || 'page';
    const page = await saveEntityRecord('postType', postType, {
      title: pageTitle,
      status: 'draft'
    });
    return {
      id: page.id,
      type: postType,
      // Make `title` property consistent with that in `fetchLinkSuggestions` where the `rendered` title (containing HTML entities)
      // is also being decoded. By being consistent in both locations we avoid having to branch in the rendering output code.
      // Ideally in the future we will update both APIs to utilise the "raw" form of the title which is better suited to edit contexts.
      // e.g.
      // - title.raw = "Yes & No"
      // - title.rendered = "Yes &#038; No"
      // - decodeEntities( title.rendered ) = "Yes & No"
      // See:
      // - https://github.com/WordPress/gutenberg/pull/41063
      // - https://github.com/WordPress/gutenberg/blob/a1e1fdc0e6278457e9f4fc0b31ac6d2095f5450b/packages/core-data/src/fetch/__experimental-fetch-link-suggestions.js#L212-L218
      title: (0, _htmlEntities.decodeEntities)(page.title.rendered),
      url: page.link,
      kind: 'post-type'
    };
  }
  const {
    label,
    url,
    opensInNewTab,
    type,
    kind
  } = props.link;
  let userCanCreate = false;
  if (!type || type === 'page') {
    userCanCreate = pagesPermissions.canCreate;
  } else if (type === 'post') {
    userCanCreate = postsPermissions.canCreate;
  }

  // Memoize link value to avoid overriding the LinkControl's internal state.
  // This is a temporary fix. See https://github.com/WordPress/gutenberg/issues/50976#issuecomment-1568226407.
  const link = (0, _element.useMemo)(() => ({
    url,
    opensInNewTab,
    title: label && (0, _dom.__unstableStripHTML)(label)
  }), [label, opensInNewTab, url]);
  return (0, _react.createElement)(_components.Popover, {
    placement: "bottom",
    onClose: props.onClose,
    anchor: props.anchor,
    shift: true
  }, (0, _react.createElement)(_blockEditor.__experimentalLinkControl, {
    hasTextControl: true,
    hasRichPreviews: true,
    value: link,
    showInitialSuggestions: true,
    withCreateSuggestion: userCanCreate,
    createSuggestion: handleCreate,
    createSuggestionButtonText: searchTerm => {
      let format;
      if (type === 'post') {
        /* translators: %s: search term. */
        format = (0, _i18n.__)('Create draft post: <mark>%s</mark>');
      } else {
        /* translators: %s: search term. */
        format = (0, _i18n.__)('Create draft page: <mark>%s</mark>');
      }
      return (0, _element.createInterpolateElement)((0, _i18n.sprintf)(format, searchTerm), {
        mark: (0, _react.createElement)("mark", null)
      });
    },
    noDirectEntry: !!type,
    noURLSuggestion: !!type,
    suggestionsQuery: getSuggestionsQuery(type, kind),
    onChange: props.onChange,
    onRemove: props.onRemove,
    onCancel: props.onCancel,
    renderControlBottom: !url ? () => (0, _react.createElement)(LinkControlTransforms, {
      clientId: props.clientId
    }) : null
  }));
}
//# sourceMappingURL=link-ui.js.map