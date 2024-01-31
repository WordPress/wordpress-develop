"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _util = require("./util");
var _embedControls = _interopRequireDefault(require("./embed-controls"));
var _icons = require("./icons");
var _embedLoading = _interopRequireDefault(require("./embed-loading"));
var _embedPlaceholder = _interopRequireDefault(require("./embed-placeholder"));
var _embedPreview = _interopRequireDefault(require("./embed-preview"));
var _classnames = _interopRequireDefault(require("classnames"));
var _i18n = require("@wordpress/i18n");
var _element = require("@wordpress/element");
var _data = require("@wordpress/data");
var _blockEditor = require("@wordpress/block-editor");
var _coreData = require("@wordpress/core-data");
var _primitives = require("@wordpress/primitives");
var _url = require("@wordpress/url");
/**
 * Internal dependencies
 */

/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

const EmbedEdit = props => {
  const {
    attributes: {
      providerNameSlug,
      previewable,
      responsive,
      url: attributesUrl
    },
    attributes,
    isSelected,
    onReplace,
    setAttributes,
    insertBlocksAfter,
    onFocus
  } = props;
  const defaultEmbedInfo = {
    title: (0, _i18n._x)('Embed', 'block title'),
    icon: _icons.embedContentIcon
  };
  const {
    icon,
    title
  } = (0, _util.getEmbedInfoByProvider)(providerNameSlug) || defaultEmbedInfo;
  const [url, setURL] = (0, _element.useState)(attributesUrl);
  const [isEditingURL, setIsEditingURL] = (0, _element.useState)(false);
  const {
    invalidateResolution
  } = (0, _data.useDispatch)(_coreData.store);
  const {
    preview,
    fetching,
    themeSupportsResponsive,
    cannotEmbed
  } = (0, _data.useSelect)(select => {
    const {
      getEmbedPreview,
      isPreviewEmbedFallback,
      isRequestingEmbedPreview,
      getThemeSupports
    } = select(_coreData.store);
    if (!attributesUrl) {
      return {
        fetching: false,
        cannotEmbed: false
      };
    }
    const embedPreview = getEmbedPreview(attributesUrl);
    const previewIsFallback = isPreviewEmbedFallback(attributesUrl);

    // The external oEmbed provider does not exist. We got no type info and no html.
    const badEmbedProvider = embedPreview?.html === false && embedPreview?.type === undefined;
    // Some WordPress URLs that can't be embedded will cause the API to return
    // a valid JSON response with no HTML and `data.status` set to 404, rather
    // than generating a fallback response as other embeds do.
    const wordpressCantEmbed = embedPreview?.data?.status === 404;
    const validPreview = !!embedPreview && !badEmbedProvider && !wordpressCantEmbed;
    return {
      preview: validPreview ? embedPreview : undefined,
      fetching: isRequestingEmbedPreview(attributesUrl),
      themeSupportsResponsive: getThemeSupports()['responsive-embeds'],
      cannotEmbed: !validPreview || previewIsFallback
    };
  }, [attributesUrl]);

  /**
   * Returns the attributes derived from the preview, merged with the current attributes.
   *
   * @return {Object} Merged attributes.
   */
  const getMergedAttributes = () => (0, _util.getMergedAttributesWithPreview)(attributes, preview, title, responsive);
  const toggleResponsive = () => {
    const {
      allowResponsive,
      className
    } = attributes;
    const {
      html
    } = preview;
    const newAllowResponsive = !allowResponsive;
    setAttributes({
      allowResponsive: newAllowResponsive,
      className: (0, _util.getClassNames)(html, className, responsive && newAllowResponsive)
    });
  };
  (0, _element.useEffect)(() => {
    if (preview?.html || !cannotEmbed || fetching) {
      return;
    }

    // At this stage, we're not fetching the preview and know it can't be embedded,
    // so try removing any trailing slash, and resubmit.
    const newURL = attributesUrl.replace(/\/$/, '');
    setURL(newURL);
    setIsEditingURL(false);
    setAttributes({
      url: newURL
    });
  }, [preview?.html, attributesUrl, cannotEmbed, fetching, setAttributes]);

  // Try a different provider in case the embed url is not supported.
  (0, _element.useEffect)(() => {
    if (!cannotEmbed || fetching || !url) {
      return;
    }

    // Until X provider is supported in WordPress, as a workaround we use Twitter provider.
    if ((0, _url.getAuthority)(url) === 'x.com') {
      const newURL = new URL(url);
      newURL.host = 'twitter.com';
      setAttributes({
        url: newURL.toString()
      });
    }
  }, [url, cannotEmbed, fetching, setAttributes]);

  // Handle incoming preview.
  (0, _element.useEffect)(() => {
    if (preview && !isEditingURL) {
      // When obtaining an incoming preview,
      // we set the attributes derived from the preview data.
      const mergedAttributes = getMergedAttributes();
      setAttributes(mergedAttributes);
      if (onReplace) {
        const upgradedBlock = (0, _util.createUpgradedEmbedBlock)(props, mergedAttributes);
        if (upgradedBlock) {
          onReplace(upgradedBlock);
        }
      }
    }
  }, [preview, isEditingURL]);
  const blockProps = (0, _blockEditor.useBlockProps)();
  if (fetching) {
    return (0, _react.createElement)(_primitives.View, {
      ...blockProps
    }, (0, _react.createElement)(_embedLoading.default, null));
  }

  // translators: %s: type of embed e.g: "YouTube", "Twitter", etc. "Embed" is used when no specific type exists
  const label = (0, _i18n.sprintf)((0, _i18n.__)('%s URL'), title);

  // No preview, or we can't embed the current URL, or we've clicked the edit button.
  const showEmbedPlaceholder = !preview || cannotEmbed || isEditingURL;
  if (showEmbedPlaceholder) {
    return (0, _react.createElement)(_primitives.View, {
      ...blockProps
    }, (0, _react.createElement)(_embedPlaceholder.default, {
      icon: icon,
      label: label,
      onFocus: onFocus,
      onSubmit: event => {
        if (event) {
          event.preventDefault();
        }

        // If the embed URL was changed, we need to reset the aspect ratio class.
        // To do this we have to remove the existing ratio class so it can be recalculated.
        const blockClass = (0, _util.removeAspectRatioClasses)(attributes.className);
        setIsEditingURL(false);
        setAttributes({
          url,
          className: blockClass
        });
      },
      value: url,
      cannotEmbed: cannotEmbed,
      onChange: event => setURL(event.target.value),
      fallback: () => (0, _util.fallback)(url, onReplace),
      tryAgain: () => {
        invalidateResolution('getEmbedPreview', [url]);
      }
    }));
  }

  // Even though we set attributes that get derived from the preview,
  // we don't access them directly because for the initial render,
  // the `setAttributes` call will not have taken effect. If we're
  // rendering responsive content, setting the responsive classes
  // after the preview has been rendered can result in unwanted
  // clipping or scrollbars. The `getAttributesFromPreview` function
  // that `getMergedAttributes` uses is memoized so that we're not
  // calculating them on every render.
  const {
    caption,
    type,
    allowResponsive,
    className: classFromPreview
  } = getMergedAttributes();
  const className = (0, _classnames.default)(classFromPreview, props.className);
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_embedControls.default, {
    showEditButton: preview && !cannotEmbed,
    themeSupportsResponsive: themeSupportsResponsive,
    blockSupportsResponsive: responsive,
    allowResponsive: allowResponsive,
    toggleResponsive: toggleResponsive,
    switchBackToURLInput: () => setIsEditingURL(true)
  }), (0, _react.createElement)(_primitives.View, {
    ...blockProps
  }, (0, _react.createElement)(_embedPreview.default, {
    preview: preview,
    previewable: previewable,
    className: className,
    url: url,
    type: type,
    caption: caption,
    onCaptionChange: value => setAttributes({
      caption: value
    }),
    isSelected: isSelected,
    icon: icon,
    label: label,
    insertBlocksAfter: insertBlocksAfter
  })));
};
var _default = exports.default = EmbedEdit;
//# sourceMappingURL=edit.js.map