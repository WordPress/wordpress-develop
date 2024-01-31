"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = PostExcerptEditor;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _coreData = require("@wordpress/core-data");
var _element = require("@wordpress/element");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _data = require("@wordpress/data");
var _hooks = require("../utils/hooks");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const ELLIPSIS = '…';
function PostExcerptEditor({
  attributes: {
    textAlign,
    moreText,
    showMoreOnNewLine,
    excerptLength
  },
  setAttributes,
  isSelected,
  context: {
    postId,
    postType,
    queryId
  }
}) {
  const isDescendentOfQueryLoop = Number.isFinite(queryId);
  const userCanEdit = (0, _hooks.useCanEditEntity)('postType', postType, postId);
  const [rawExcerpt, setExcerpt, {
    rendered: renderedExcerpt,
    protected: isProtected
  } = {}] = (0, _coreData.useEntityProp)('postType', postType, 'excerpt', postId);

  /**
   * Check if the post type supports excerpts.
   * Add an exception and return early for the "page" post type,
   * which is registered without support for the excerpt UI,
   * but supports saving the excerpt to the database.
   * See: https://core.trac.wordpress.org/browser/branches/6.1/src/wp-includes/post.php#L65
   * Without this exception, users that have excerpts saved to the database will
   * not be able to edit the excerpts.
   */
  const postTypeSupportsExcerpts = (0, _data.useSelect)(select => {
    if (postType === 'page') {
      return true;
    }
    return !!select(_coreData.store).getPostType(postType)?.supports?.excerpt;
  }, [postType]);

  /**
   * The excerpt is editable if:
   * - The user can edit the post
   * - It is not a descendent of a Query Loop block
   * - The post type supports excerpts
   */
  const isEditable = userCanEdit && !isDescendentOfQueryLoop && postTypeSupportsExcerpts;
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign
    })
  });

  /**
   * translators: If your word count is based on single characters (e.g. East Asian characters),
   * enter 'characters_excluding_spaces' or 'characters_including_spaces'. Otherwise, enter 'words'.
   * Do not translate into your own language.
   */
  const wordCountType = (0, _i18n._x)('words', 'Word count type. Do not translate!');

  /**
   * When excerpt is editable, strip the html tags from
   * rendered excerpt. This will be used if the entity's
   * excerpt has been produced from the content.
   */
  const strippedRenderedExcerpt = (0, _element.useMemo)(() => {
    if (!renderedExcerpt) return '';
    const document = new window.DOMParser().parseFromString(renderedExcerpt, 'text/html');
    return document.body.textContent || document.body.innerText || '';
  }, [renderedExcerpt]);
  if (!postType || !postId) {
    return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_blockEditor.AlignmentToolbar, {
      value: textAlign,
      onChange: newAlign => setAttributes({
        textAlign: newAlign
      })
    })), (0, _react.createElement)("div", {
      ...blockProps
    }, (0, _react.createElement)("p", null, (0, _i18n.__)('This block will display the excerpt.'))));
  }
  if (isProtected && !userCanEdit) {
    return (0, _react.createElement)("div", {
      ...blockProps
    }, (0, _react.createElement)(_blockEditor.Warning, null, (0, _i18n.__)('The content is currently protected and does not have the available excerpt.')));
  }
  const readMoreLink = (0, _react.createElement)(_blockEditor.RichText, {
    className: "wp-block-post-excerpt__more-link",
    tagName: "a",
    "aria-label": (0, _i18n.__)('“Read more” link text'),
    placeholder: (0, _i18n.__)('Add "read more" link text'),
    value: moreText,
    onChange: newMoreText => setAttributes({
      moreText: newMoreText
    }),
    withoutInteractiveFormatting: true
  });
  const excerptClassName = (0, _classnames.default)('wp-block-post-excerpt__excerpt', {
    'is-inline': !showMoreOnNewLine
  });

  /**
   * The excerpt length setting needs to be applied to both
   * the raw and the rendered excerpt depending on which is being used.
   */
  const rawOrRenderedExcerpt = (rawExcerpt || strippedRenderedExcerpt).trim();
  let trimmedExcerpt = '';
  if (wordCountType === 'words') {
    trimmedExcerpt = rawOrRenderedExcerpt.split(' ', excerptLength).join(' ');
  } else if (wordCountType === 'characters_excluding_spaces') {
    /*
     * 1. Split the excerpt at the character limit,
     * then join the substrings back into one string.
     * 2. Count the number of spaces in the excerpt
     * by comparing the lengths of the string with and without spaces.
     * 3. Add the number to the length of the visible excerpt,
     * so that the spaces are excluded from the word count.
     */
    const excerptWithSpaces = rawOrRenderedExcerpt.split('', excerptLength).join('');
    const numberOfSpaces = excerptWithSpaces.length - excerptWithSpaces.replaceAll(' ', '').length;
    trimmedExcerpt = rawOrRenderedExcerpt.split('', excerptLength + numberOfSpaces).join('');
  } else if (wordCountType === 'characters_including_spaces') {
    trimmedExcerpt = rawOrRenderedExcerpt.split('', excerptLength).join('');
  }
  const isTrimmed = trimmedExcerpt !== rawOrRenderedExcerpt;
  const excerptContent = isEditable ? (0, _react.createElement)(_blockEditor.RichText, {
    className: excerptClassName,
    "aria-label": (0, _i18n.__)('Excerpt text'),
    value: isSelected ? rawOrRenderedExcerpt : (!isTrimmed ? rawOrRenderedExcerpt : trimmedExcerpt + ELLIPSIS) || (0, _i18n.__)('No excerpt found'),
    onChange: setExcerpt,
    tagName: "p"
  }) : (0, _react.createElement)("p", {
    className: excerptClassName
  }, !isTrimmed ? rawOrRenderedExcerpt || (0, _i18n.__)('No excerpt found') : trimmedExcerpt + ELLIPSIS);
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_blockEditor.AlignmentToolbar, {
    value: textAlign,
    onChange: newAlign => setAttributes({
      textAlign: newAlign
    })
  })), (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Show link on new line'),
    checked: showMoreOnNewLine,
    onChange: newShowMoreOnNewLine => setAttributes({
      showMoreOnNewLine: newShowMoreOnNewLine
    })
  }), (0, _react.createElement)(_components.RangeControl, {
    label: (0, _i18n.__)('Max number of words'),
    value: excerptLength,
    onChange: value => {
      setAttributes({
        excerptLength: value
      });
    },
    min: "10",
    max: "100"
  }))), (0, _react.createElement)("div", {
    ...blockProps
  }, excerptContent, !showMoreOnNewLine && ' ', showMoreOnNewLine ? (0, _react.createElement)("p", {
    className: "wp-block-post-excerpt__more-text"
  }, readMoreLink) : readMoreLink));
}
//# sourceMappingURL=edit.js.map