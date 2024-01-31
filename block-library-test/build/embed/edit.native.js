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
var _embedLinkSettings = _interopRequireDefault(require("./embed-link-settings"));
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

// The inline preview feature will be released progressible, for this reason
// the embed will only be considered previewable for the following providers list.
const PREVIEWABLE_PROVIDERS = ['youtube', 'twitter', 'instagram', 'vimeo'];
// Some providers are rendering the inline preview as a WordPress embed and
// are not supported yet, so we need to disallow them with a fixed providers list.
const NOT_PREVIEWABLE_WP_EMBED_PROVIDERS = ['pinterest'];
const WP_EMBED_TYPE = 'wp-embed';
const EmbedEdit = props => {
  const {
    attributes: {
      align,
      providerNameSlug,
      previewable,
      responsive,
      url
    },
    attributes,
    isSelected,
    onReplace,
    setAttributes,
    insertBlocksAfter,
    onFocus,
    clientId
  } = props;
  const defaultEmbedInfo = {
    title: (0, _i18n._x)('Embed', 'block title'),
    icon: _icons.embedContentIcon
  };
  const embedInfoByProvider = (0, _util.getEmbedInfoByProvider)(providerNameSlug);
  const {
    icon,
    title
  } = embedInfoByProvider || defaultEmbedInfo;
  const {
    wasBlockJustInserted
  } = (0, _data.useSelect)(select => ({
    wasBlockJustInserted: select(_blockEditor.store).wasBlockJustInserted(clientId, 'inserter_menu')
  }), [clientId]);
  const [isEditingURL, setIsEditingURL] = (0, _element.useState)(isSelected && wasBlockJustInserted && !url);
  const [showEmbedBottomSheet, setShowEmbedBottomSheet] = (0, _element.useState)(isEditingURL);
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
      hasFinishedResolution,
      isPreviewEmbedFallback,
      getThemeSupports
    } = select(_coreData.store);
    if (!url) {
      return {
        fetching: false,
        cannotEmbed: false
      };
    }
    const embedPreview = getEmbedPreview(url);
    const hasResolvedEmbedPreview = hasFinishedResolution('getEmbedPreview', [url]);
    const previewIsFallback = isPreviewEmbedFallback(url);

    // The external oEmbed provider does not exist. We got no type info and no html.
    const badEmbedProvider = embedPreview?.html === false && embedPreview?.type === undefined;
    // Some WordPress URLs that can't be embedded will cause the API to return
    // a valid JSON response with no HTML and `code` set to 404, rather
    // than generating a fallback response as other embeds do.
    const wordpressCantEmbed = embedPreview?.code === '404';
    const validPreview = !!embedPreview && !badEmbedProvider && !wordpressCantEmbed;
    return {
      preview: validPreview ? embedPreview : undefined,
      fetching: !hasResolvedEmbedPreview,
      themeSupportsResponsive: getThemeSupports()['responsive-embeds'],
      cannotEmbed: !validPreview || previewIsFallback
    };
  }, [url]);

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
    if (!preview?.html || !cannotEmbed || fetching) {
      return;
    }
    // At this stage, we're not fetching the preview and know it can't be embedded,
    // so try removing any trailing slash, and resubmit.
    const newURL = url.replace(/\/$/, '');
    setIsEditingURL(false);
    setAttributes({
      url: newURL
    });
  }, [preview?.html, url, cannotEmbed, fetching]);

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
  (0, _element.useEffect)(() => setShowEmbedBottomSheet(isEditingURL), [isEditingURL]);
  const onEditURL = (0, _element.useCallback)(value => {
    // If the embed URL was changed, we need to reset the aspect ratio class.
    // To do this we have to remove the existing ratio class so it can be recalculated.
    if (attributes.url !== value) {
      const blockClass = (0, _util.removeAspectRatioClasses)(attributes.className);
      setAttributes({
        className: blockClass
      });
    }

    // The order of the following calls is important, we need to update the URL attribute before changing `isEditingURL`,
    // otherwise the side-effect that potentially replaces the block when updating the local state won't use the new URL
    // for creating the new block.
    setAttributes({
      url: value
    });
    setIsEditingURL(false);
  }, [attributes, setAttributes]);
  const blockProps = (0, _blockEditor.useBlockProps)();
  if (fetching) {
    return (0, _react.createElement)(_primitives.View, {
      ...blockProps
    }, (0, _react.createElement)(_embedLoading.default, null));
  }
  const showEmbedPlaceholder = !preview || cannotEmbed;

  // Even though we set attributes that get derived from the preview,
  // we don't access them directly because for the initial render,
  // the `setAttributes` call will not have taken effect. If we're
  // rendering responsive content, setting the responsive classes
  // after the preview has been rendered can result in unwanted
  // clipping or scrollbars. The `getAttributesFromPreview` function
  // that `getMergedAttributes` uses is memoized so that we're not
  // calculating them on every render.
  const {
    type,
    allowResponsive,
    className: classFromPreview
  } = getMergedAttributes();
  const className = (0, _classnames.default)(classFromPreview, props.className);
  const isProviderPreviewable = PREVIEWABLE_PROVIDERS.includes(providerNameSlug) ||
  // For WordPress embeds, we enable the inline preview for all its providers
  // except the ones that are not supported yet.
  WP_EMBED_TYPE === type && !NOT_PREVIEWABLE_WP_EMBED_PROVIDERS.includes(providerNameSlug);
  const linkLabel = WP_EMBED_TYPE === type ? 'WordPress' : title;
  return (0, _react.createElement)(_react.Fragment, null, showEmbedPlaceholder ? (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_primitives.View, {
    ...blockProps
  }, (0, _react.createElement)(_embedPlaceholder.default, {
    icon: icon,
    isSelected: isSelected,
    label: title,
    onPress: event => {
      onFocus(event);
      setIsEditingURL(true);
    },
    cannotEmbed: cannotEmbed,
    fallback: () => (0, _util.fallback)(url, onReplace),
    tryAgain: () => {
      invalidateResolution('getEmbedPreview', [url]);
    },
    openEmbedLinkSettings: () => setShowEmbedBottomSheet(true)
  }))) : (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_embedControls.default, {
    themeSupportsResponsive: themeSupportsResponsive,
    blockSupportsResponsive: responsive,
    allowResponsive: allowResponsive,
    toggleResponsive: toggleResponsive,
    url: url,
    linkLabel: linkLabel,
    onEditURL: onEditURL
  }), (0, _react.createElement)(_primitives.View, {
    ...blockProps
  }, (0, _react.createElement)(_embedPreview.default, {
    align: align,
    className: className,
    clientId: clientId,
    icon: icon,
    insertBlocksAfter: insertBlocksAfter,
    isSelected: isSelected,
    label: title,
    onFocus: onFocus,
    preview: preview,
    isProviderPreviewable: isProviderPreviewable,
    previewable: previewable,
    type: type,
    url: url,
    isDefaultEmbedInfo: !embedInfoByProvider
  }))), (0, _react.createElement)(_embedLinkSettings.default
  // eslint-disable-next-line jsx-a11y/no-autofocus
  , {
    autoFocus: true,
    value: url,
    label: linkLabel,
    isVisible: showEmbedBottomSheet,
    onClose: () => setShowEmbedBottomSheet(false),
    onSubmit: onEditURL,
    withBottomSheet: true
  }));
};
var _default = exports.default = EmbedEdit;
//# sourceMappingURL=edit.native.js.map