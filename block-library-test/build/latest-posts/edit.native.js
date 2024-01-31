"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _element = require("@wordpress/element");
var _compose = require("@wordpress/compose");
var _data = require("@wordpress/data");
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _blockEditor = require("@wordpress/block-editor");
var _apiFetch = _interopRequireDefault(require("@wordpress/api-fetch"));
var _components = require("@wordpress/components");
var _blocks = require("@wordpress/blocks");
var _style = _interopRequireDefault(require("./style.scss"));
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

class LatestPostsEdit extends _element.Component {
  constructor() {
    super(...arguments);
    this.state = {
      categoriesList: []
    };
    this.onSetDisplayPostContent = this.onSetDisplayPostContent.bind(this);
    this.onSetDisplayPostContentRadio = this.onSetDisplayPostContentRadio.bind(this);
    this.onSetExcerptLength = this.onSetExcerptLength.bind(this);
    this.onSetDisplayPostDate = this.onSetDisplayPostDate.bind(this);
    this.onSetDisplayFeaturedImage = this.onSetDisplayFeaturedImage.bind(this);
    this.onSetFeaturedImageAlign = this.onSetFeaturedImageAlign.bind(this);
    this.onSetAddLinkToFeaturedImage = this.onSetAddLinkToFeaturedImage.bind(this);
    this.onSetOrder = this.onSetOrder.bind(this);
    this.onSetOrderBy = this.onSetOrderBy.bind(this);
    this.onSetPostsToShow = this.onSetPostsToShow.bind(this);
    this.onSetCategories = this.onSetCategories.bind(this);
    this.getInspectorControls = this.getInspectorControls.bind(this);
  }
  componentDidMount() {
    this.isStillMounted = true;
    this.fetchRequest = (0, _apiFetch.default)({
      path: '/wp/v2/categories'
    }).then(categoriesList => {
      if (this.isStillMounted) {
        this.setState({
          categoriesList
        });
      }
    }).catch(() => {
      if (this.isStillMounted) {
        this.setState({
          categoriesList: []
        });
      }
    });
  }
  componentWillUnmount() {
    this.isStillMounted = false;
  }
  onSetDisplayPostContent(value) {
    const {
      setAttributes
    } = this.props;
    setAttributes({
      displayPostContent: value
    });
  }
  onSetDisplayPostContentRadio(value) {
    const {
      setAttributes
    } = this.props;
    setAttributes({
      displayPostContentRadio: value ? 'excerpt' : 'full_post'
    });
  }
  onSetExcerptLength(value) {
    const {
      setAttributes
    } = this.props;
    setAttributes({
      excerptLength: value
    });
  }
  onSetDisplayPostDate(value) {
    const {
      setAttributes
    } = this.props;
    setAttributes({
      displayPostDate: value
    });
  }
  onSetDisplayFeaturedImage(value) {
    const {
      setAttributes
    } = this.props;
    setAttributes({
      displayFeaturedImage: value
    });
  }
  onSetAddLinkToFeaturedImage(value) {
    const {
      setAttributes
    } = this.props;
    setAttributes({
      addLinkToFeaturedImage: value
    });
  }
  onSetFeaturedImageAlign(value) {
    const {
      setAttributes
    } = this.props;
    setAttributes({
      featuredImageAlign: value
    });
  }
  onSetOrder(value) {
    const {
      setAttributes
    } = this.props;
    setAttributes({
      order: value
    });
  }
  onSetOrderBy(value) {
    const {
      setAttributes
    } = this.props;
    setAttributes({
      orderBy: value
    });
  }
  onSetPostsToShow(value) {
    const {
      setAttributes
    } = this.props;
    setAttributes({
      postsToShow: value
    });
  }
  onSetCategories(value) {
    const {
      setAttributes
    } = this.props;
    setAttributes({
      categories: '' !== value ? value.toString() : undefined
    });
  }
  getInspectorControls() {
    const {
      attributes
    } = this.props;
    const {
      displayPostContent,
      displayPostContentRadio,
      excerptLength,
      displayPostDate,
      displayFeaturedImage,
      featuredImageAlign,
      addLinkToFeaturedImage,
      order,
      orderBy,
      postsToShow,
      categories
    } = attributes;
    const {
      categoriesList
    } = this.state;
    const displayExcerptPostContent = displayPostContentRadio === 'excerpt';
    return (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
      title: (0, _i18n.__)('Post content')
    }, (0, _react.createElement)(_components.ToggleControl, {
      label: (0, _i18n.__)('Show post content'),
      checked: displayPostContent,
      onChange: this.onSetDisplayPostContent
    }), displayPostContent && (0, _react.createElement)(_components.ToggleControl, {
      label: (0, _i18n.__)('Only show excerpt'),
      checked: displayExcerptPostContent,
      onChange: this.onSetDisplayPostContentRadio
    }), displayPostContent && displayExcerptPostContent && (0, _react.createElement)(_components.RangeControl, {
      label: (0, _i18n.__)('Excerpt length (words)'),
      value: excerptLength,
      onChange: this.onSetExcerptLength,
      min: _constants.MIN_EXCERPT_LENGTH,
      max: _constants.MAX_EXCERPT_LENGTH
    })), (0, _react.createElement)(_components.PanelBody, {
      title: (0, _i18n.__)('Post meta')
    }, (0, _react.createElement)(_components.ToggleControl, {
      label: (0, _i18n.__)('Display post date'),
      checked: displayPostDate,
      onChange: this.onSetDisplayPostDate
    })), (0, _react.createElement)(_components.PanelBody, {
      title: (0, _i18n.__)('Featured image')
    }, (0, _react.createElement)(_components.ToggleControl, {
      label: (0, _i18n.__)('Display featured image'),
      checked: displayFeaturedImage,
      onChange: this.onSetDisplayFeaturedImage
    }), displayFeaturedImage && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockAlignmentControl, {
      value: featuredImageAlign,
      onChange: this.onSetFeaturedImageAlign,
      controls: ['left', 'center', 'right'],
      isBottomSheetControl: true
    }), (0, _react.createElement)(_components.ToggleControl, {
      label: (0, _i18n.__)('Add link to featured image'),
      checked: addLinkToFeaturedImage,
      onChange: this.onSetAddLinkToFeaturedImage,
      separatorType: 'topFullWidth'
    }))), (0, _react.createElement)(_components.PanelBody, {
      title: (0, _i18n.__)('Sorting and filtering')
    }, (0, _react.createElement)(_components.QueryControls, {
      order,
      orderBy,
      numberOfItems: postsToShow,
      categoriesList: categoriesList,
      selectedCategoryId: undefined !== categories ? Number(categories) : '',
      onOrderChange: this.onSetOrder,
      onOrderByChange: this.onSetOrderBy,
      onCategoryChange:
      // eslint-disable-next-line no-undef
      __DEV__ ? this.onSetCategories : undefined,
      onNumberOfItemsChange: this.onSetPostsToShow
    })));
  }
  render() {
    const {
      blockTitle,
      getStylesFromColorScheme,
      openGeneralSidebar,
      isSelected
    } = this.props;
    const blockStyle = getStylesFromColorScheme(_style.default.latestPostBlock, _style.default.latestPostBlockDark);
    const iconStyle = getStylesFromColorScheme(_style.default.latestPostBlockIcon, _style.default.latestPostBlockIconDark);
    const titleStyle = getStylesFromColorScheme(_style.default.latestPostBlockMessage, _style.default.latestPostBlockMessageDark);
    return (0, _react.createElement)(_reactNative.TouchableWithoutFeedback, {
      accessible: !isSelected,
      disabled: !isSelected,
      onPress: openGeneralSidebar
    }, (0, _react.createElement)(_reactNative.View, {
      style: blockStyle
    }, isSelected && this.getInspectorControls(), (0, _react.createElement)(_components.Icon, {
      icon: _icons.postList,
      ...iconStyle
    }), (0, _react.createElement)(_reactNative.Text, {
      style: titleStyle
    }, blockTitle), (0, _react.createElement)(_reactNative.Text, {
      style: _style.default.latestPostBlockSubtitle
    }, (0, _i18n.__)('CUSTOMIZE'))));
  }
}
var _default = exports.default = (0, _compose.compose)([(0, _data.withSelect)((select, {
  name
}) => {
  const blockType = select(_blocks.store).getBlockType(name);
  return {
    blockTitle: blockType?.title || name
  };
}), (0, _data.withDispatch)(dispatch => {
  const {
    openGeneralSidebar
  } = dispatch('core/edit-post');
  return {
    openGeneralSidebar: () => openGeneralSidebar('edit-post/block')
  };
}), _compose.withPreferredColorScheme])(LatestPostsEdit);
//# sourceMappingURL=edit.native.js.map