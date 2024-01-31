"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = LatestPostsEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _date = require("@wordpress/date");
var _blockEditor = require("@wordpress/block-editor");
var _data = require("@wordpress/data");
var _icons = require("@wordpress/icons");
var _coreData = require("@wordpress/core-data");
var _notices = require("@wordpress/notices");
var _compose = require("@wordpress/compose");
var _element = require("@wordpress/element");
var _constants = require("./constants");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

/**
 * Module Constants
 */
const CATEGORIES_LIST_QUERY = {
  per_page: -1,
  context: 'view'
};
const USERS_LIST_QUERY = {
  per_page: -1,
  has_published_posts: ['post'],
  context: 'view'
};
function getFeaturedImageDetails(post, size) {
  var _image$media_details$;
  const image = post._embedded?.['wp:featuredmedia']?.['0'];
  return {
    url: (_image$media_details$ = image?.media_details?.sizes?.[size]?.source_url) !== null && _image$media_details$ !== void 0 ? _image$media_details$ : image?.source_url,
    alt: image?.alt_text
  };
}
function LatestPostsEdit({
  attributes,
  setAttributes
}) {
  var _categoriesList$reduc;
  const instanceId = (0, _compose.useInstanceId)(LatestPostsEdit);
  const {
    postsToShow,
    order,
    orderBy,
    categories,
    selectedAuthor,
    displayFeaturedImage,
    displayPostContentRadio,
    displayPostContent,
    displayPostDate,
    displayAuthor,
    postLayout,
    columns,
    excerptLength,
    featuredImageAlign,
    featuredImageSizeSlug,
    featuredImageSizeWidth,
    featuredImageSizeHeight,
    addLinkToFeaturedImage
  } = attributes;
  const {
    imageSizes,
    latestPosts,
    defaultImageWidth,
    defaultImageHeight,
    categoriesList,
    authorList
  } = (0, _data.useSelect)(select => {
    var _settings$imageDimens, _settings$imageDimens2;
    const {
      getEntityRecords,
      getUsers
    } = select(_coreData.store);
    const settings = select(_blockEditor.store).getSettings();
    const catIds = categories && categories.length > 0 ? categories.map(cat => cat.id) : [];
    const latestPostsQuery = Object.fromEntries(Object.entries({
      categories: catIds,
      author: selectedAuthor,
      order,
      orderby: orderBy,
      per_page: postsToShow,
      _embed: 'wp:featuredmedia'
    }).filter(([, value]) => typeof value !== 'undefined'));
    return {
      defaultImageWidth: (_settings$imageDimens = settings.imageDimensions?.[featuredImageSizeSlug]?.width) !== null && _settings$imageDimens !== void 0 ? _settings$imageDimens : 0,
      defaultImageHeight: (_settings$imageDimens2 = settings.imageDimensions?.[featuredImageSizeSlug]?.height) !== null && _settings$imageDimens2 !== void 0 ? _settings$imageDimens2 : 0,
      imageSizes: settings.imageSizes,
      latestPosts: getEntityRecords('postType', 'post', latestPostsQuery),
      categoriesList: getEntityRecords('taxonomy', 'category', CATEGORIES_LIST_QUERY),
      authorList: getUsers(USERS_LIST_QUERY)
    };
  }, [featuredImageSizeSlug, postsToShow, order, orderBy, categories, selectedAuthor]);

  // If a user clicks to a link prevent redirection and show a warning.
  const {
    createWarningNotice,
    removeNotice
  } = (0, _data.useDispatch)(_notices.store);
  let noticeId;
  const showRedirectionPreventedNotice = event => {
    event.preventDefault();
    // Remove previous warning if any, to show one at a time per block.
    removeNotice(noticeId);
    noticeId = `block-library/core/latest-posts/redirection-prevented/${instanceId}`;
    createWarningNotice((0, _i18n.__)('Links are disabled in the editor.'), {
      id: noticeId,
      type: 'snackbar'
    });
  };
  const imageSizeOptions = imageSizes.filter(({
    slug
  }) => slug !== 'full').map(({
    name,
    slug
  }) => ({
    value: slug,
    label: name
  }));
  const categorySuggestions = (_categoriesList$reduc = categoriesList?.reduce((accumulator, category) => ({
    ...accumulator,
    [category.name]: category
  }), {})) !== null && _categoriesList$reduc !== void 0 ? _categoriesList$reduc : {};
  const selectCategories = tokens => {
    const hasNoSuggestion = tokens.some(token => typeof token === 'string' && !categorySuggestions[token]);
    if (hasNoSuggestion) {
      return;
    }
    // Categories that are already will be objects, while new additions will be strings (the name).
    // allCategories nomalizes the array so that they are all objects.
    const allCategories = tokens.map(token => {
      return typeof token === 'string' ? categorySuggestions[token] : token;
    });
    // We do nothing if the category is not selected
    // from suggestions.
    if (allCategories.includes(null)) {
      return false;
    }
    setAttributes({
      categories: allCategories
    });
  };
  const hasPosts = !!latestPosts?.length;
  const inspectorControls = (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Post content')
  }, (0, _react.createElement)(_components.ToggleControl, {
    label: (0, _i18n.__)('Post content'),
    checked: displayPostContent,
    onChange: value => setAttributes({
      displayPostContent: value
    })
  }), displayPostContent && (0, _react.createElement)(_components.RadioControl, {
    label: (0, _i18n.__)('Show:'),
    selected: displayPostContentRadio,
    options: [{
      label: (0, _i18n.__)('Excerpt'),
      value: 'excerpt'
    }, {
      label: (0, _i18n.__)('Full post'),
      value: 'full_post'
    }],
    onChange: value => setAttributes({
      displayPostContentRadio: value
    })
  }), displayPostContent && displayPostContentRadio === 'excerpt' && (0, _react.createElement)(_components.RangeControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: (0, _i18n.__)('Max number of words'),
    value: excerptLength,
    onChange: value => setAttributes({
      excerptLength: value
    }),
    min: _constants.MIN_EXCERPT_LENGTH,
    max: _constants.MAX_EXCERPT_LENGTH
  })), (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Post meta')
  }, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Display author name'),
    checked: displayAuthor,
    onChange: value => setAttributes({
      displayAuthor: value
    })
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Display post date'),
    checked: displayPostDate,
    onChange: value => setAttributes({
      displayPostDate: value
    })
  })), (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Featured image')
  }, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Display featured image'),
    checked: displayFeaturedImage,
    onChange: value => setAttributes({
      displayFeaturedImage: value
    })
  }), displayFeaturedImage && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.__experimentalImageSizeControl, {
    onChange: value => {
      const newAttrs = {};
      if (value.hasOwnProperty('width')) {
        newAttrs.featuredImageSizeWidth = value.width;
      }
      if (value.hasOwnProperty('height')) {
        newAttrs.featuredImageSizeHeight = value.height;
      }
      setAttributes(newAttrs);
    },
    slug: featuredImageSizeSlug,
    width: featuredImageSizeWidth,
    height: featuredImageSizeHeight,
    imageWidth: defaultImageWidth,
    imageHeight: defaultImageHeight,
    imageSizeOptions: imageSizeOptions,
    imageSizeHelp: (0, _i18n.__)('Select the size of the source image.'),
    onChangeImage: value => setAttributes({
      featuredImageSizeSlug: value,
      featuredImageSizeWidth: undefined,
      featuredImageSizeHeight: undefined
    })
  }), (0, _react.createElement)(_components.BaseControl, {
    className: "editor-latest-posts-image-alignment-control"
  }, (0, _react.createElement)(_components.BaseControl.VisualLabel, null, (0, _i18n.__)('Image alignment')), (0, _react.createElement)(_blockEditor.BlockAlignmentToolbar, {
    value: featuredImageAlign,
    onChange: value => setAttributes({
      featuredImageAlign: value
    }),
    controls: ['left', 'center', 'right'],
    isCollapsed: false
  })), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Add link to featured image'),
    checked: addLinkToFeaturedImage,
    onChange: value => setAttributes({
      addLinkToFeaturedImage: value
    })
  }))), (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Sorting and filtering')
  }, (0, _react.createElement)(_components.QueryControls, {
    order,
    orderBy,
    numberOfItems: postsToShow,
    onOrderChange: value => setAttributes({
      order: value
    }),
    onOrderByChange: value => setAttributes({
      orderBy: value
    }),
    onNumberOfItemsChange: value => setAttributes({
      postsToShow: value
    }),
    categorySuggestions: categorySuggestions,
    onCategoryChange: selectCategories,
    selectedCategories: categories,
    onAuthorChange: value => setAttributes({
      selectedAuthor: '' !== value ? Number(value) : undefined
    }),
    authorList: authorList !== null && authorList !== void 0 ? authorList : [],
    selectedAuthorId: selectedAuthor
  }), postLayout === 'grid' && (0, _react.createElement)(_components.RangeControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: (0, _i18n.__)('Columns'),
    value: columns,
    onChange: value => setAttributes({
      columns: value
    }),
    min: 2,
    max: !hasPosts ? _constants.MAX_POSTS_COLUMNS : Math.min(_constants.MAX_POSTS_COLUMNS, latestPosts.length),
    required: true
  })));
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      'wp-block-latest-posts__list': true,
      'is-grid': postLayout === 'grid',
      'has-dates': displayPostDate,
      'has-author': displayAuthor,
      [`columns-${columns}`]: postLayout === 'grid'
    })
  });
  if (!hasPosts) {
    return (0, _react.createElement)("div", {
      ...blockProps
    }, inspectorControls, (0, _react.createElement)(_components.Placeholder, {
      icon: _icons.pin,
      label: (0, _i18n.__)('Latest Posts')
    }, !Array.isArray(latestPosts) ? (0, _react.createElement)(_components.Spinner, null) : (0, _i18n.__)('No posts found.')));
  }

  // Removing posts from display should be instant.
  const displayPosts = latestPosts.length > postsToShow ? latestPosts.slice(0, postsToShow) : latestPosts;
  const layoutControls = [{
    icon: _icons.list,
    title: (0, _i18n.__)('List view'),
    onClick: () => setAttributes({
      postLayout: 'list'
    }),
    isActive: postLayout === 'list'
  }, {
    icon: _icons.grid,
    title: (0, _i18n.__)('Grid view'),
    onClick: () => setAttributes({
      postLayout: 'grid'
    }),
    isActive: postLayout === 'grid'
  }];
  const dateFormat = (0, _date.getSettings)().formats.date;
  return (0, _react.createElement)("div", null, inspectorControls, (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_components.ToolbarGroup, {
    controls: layoutControls
  })), (0, _react.createElement)("ul", {
    ...blockProps
  }, displayPosts.map(post => {
    const titleTrimmed = post.title.rendered.trim();
    let excerpt = post.excerpt.rendered;
    const currentAuthor = authorList?.find(author => author.id === post.author);
    const excerptElement = document.createElement('div');
    excerptElement.innerHTML = excerpt;
    excerpt = excerptElement.textContent || excerptElement.innerText || '';
    const {
      url: imageSourceUrl,
      alt: featuredImageAlt
    } = getFeaturedImageDetails(post, featuredImageSizeSlug);
    const imageClasses = (0, _classnames.default)({
      'wp-block-latest-posts__featured-image': true,
      [`align${featuredImageAlign}`]: !!featuredImageAlign
    });
    const renderFeaturedImage = displayFeaturedImage && imageSourceUrl;
    const featuredImage = renderFeaturedImage && (0, _react.createElement)("img", {
      src: imageSourceUrl,
      alt: featuredImageAlt,
      style: {
        maxWidth: featuredImageSizeWidth,
        maxHeight: featuredImageSizeHeight
      }
    });
    const needsReadMore = excerptLength < excerpt.trim().split(' ').length && post.excerpt.raw === '';
    const postExcerpt = needsReadMore ? (0, _react.createElement)(_react.Fragment, null, excerpt.trim().split(' ', excerptLength).join(' '), (0, _element.createInterpolateElement)((0, _i18n.sprintf)( /* translators: 1: Hidden accessibility text: Post title */
    (0, _i18n.__)('… <a>Read more<span>: %1$s</span></a>'), titleTrimmed || (0, _i18n.__)('(no title)')), {
      a:
      // eslint-disable-next-line jsx-a11y/anchor-has-content
      (0, _react.createElement)("a", {
        className: "wp-block-latest-posts__read-more",
        href: post.link,
        rel: "noopener noreferrer",
        onClick: showRedirectionPreventedNotice
      }),
      span: (0, _react.createElement)("span", {
        className: "screen-reader-text"
      })
    })) : excerpt;
    return (0, _react.createElement)("li", {
      key: post.id
    }, renderFeaturedImage && (0, _react.createElement)("div", {
      className: imageClasses
    }, addLinkToFeaturedImage ? (0, _react.createElement)("a", {
      className: "wp-block-latest-posts__post-title",
      href: post.link,
      rel: "noreferrer noopener",
      onClick: showRedirectionPreventedNotice
    }, featuredImage) : featuredImage), (0, _react.createElement)("a", {
      href: post.link,
      rel: "noreferrer noopener",
      dangerouslySetInnerHTML: !!titleTrimmed ? {
        __html: titleTrimmed
      } : undefined,
      onClick: showRedirectionPreventedNotice
    }, !titleTrimmed ? (0, _i18n.__)('(no title)') : null), displayAuthor && currentAuthor && (0, _react.createElement)("div", {
      className: "wp-block-latest-posts__post-author"
    }, (0, _i18n.sprintf)( /* translators: byline. %s: current author. */
    (0, _i18n.__)('by %s'), currentAuthor.name)), displayPostDate && post.date_gmt && (0, _react.createElement)("time", {
      dateTime: (0, _date.format)('c', post.date_gmt),
      className: "wp-block-latest-posts__post-date"
    }, (0, _date.dateI18n)(dateFormat, post.date_gmt)), displayPostContent && displayPostContentRadio === 'excerpt' && (0, _react.createElement)("div", {
      className: "wp-block-latest-posts__post-excerpt"
    }, postExcerpt), displayPostContent && displayPostContentRadio === 'full_post' && (0, _react.createElement)("div", {
      className: "wp-block-latest-posts__post-full-content",
      dangerouslySetInnerHTML: {
        __html: post.content.raw.trim()
      }
    }));
  })));
}
//# sourceMappingURL=edit.js.map