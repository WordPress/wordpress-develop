import { createElement, Fragment } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { AlignmentControl, BlockControls, InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { ComboboxControl, PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { store as coreStore } from '@wordpress/core-data';
const minimumUsersForCombobox = 25;
const AUTHORS_QUERY = {
  who: 'authors',
  per_page: 100
};
function PostAuthorEdit({
  isSelected,
  context: {
    postType,
    postId,
    queryId
  },
  attributes,
  setAttributes
}) {
  const isDescendentOfQueryLoop = Number.isFinite(queryId);
  const {
    authorId,
    authorDetails,
    authors
  } = useSelect(select => {
    const {
      getEditedEntityRecord,
      getUser,
      getUsers
    } = select(coreStore);
    const _authorId = getEditedEntityRecord('postType', postType, postId)?.author;
    return {
      authorId: _authorId,
      authorDetails: _authorId ? getUser(_authorId) : null,
      authors: getUsers(AUTHORS_QUERY)
    };
  }, [postType, postId]);
  const {
    editEntityRecord
  } = useDispatch(coreStore);
  const {
    textAlign,
    showAvatar,
    showBio,
    byline,
    isLink,
    linkTarget
  } = attributes;
  const avatarSizes = [];
  const authorName = authorDetails?.name || __('Post Author');
  if (authorDetails?.avatar_urls) {
    Object.keys(authorDetails.avatar_urls).forEach(size => {
      avatarSizes.push({
        value: size,
        label: `${size} x ${size}`
      });
    });
  }
  const blockProps = useBlockProps({
    className: classnames({
      [`has-text-align-${textAlign}`]: textAlign
    })
  });
  const authorOptions = authors?.length ? authors.map(({
    id,
    name
  }) => {
    return {
      value: id,
      label: name
    };
  }) : [];
  const handleSelect = nextAuthorId => {
    editEntityRecord('postType', postType, postId, {
      author: nextAuthorId
    });
  };
  const showCombobox = authorOptions.length >= minimumUsersForCombobox;
  const showAuthorControl = !!postId && !isDescendentOfQueryLoop && authorOptions.length > 0;
  return createElement(Fragment, null, createElement(InspectorControls, null, createElement(PanelBody, {
    title: __('Settings')
  }, showAuthorControl && (showCombobox && createElement(ComboboxControl, {
    __nextHasNoMarginBottom: true,
    label: __('Author'),
    options: authorOptions,
    value: authorId,
    onChange: handleSelect,
    allowReset: false
  }) || createElement(SelectControl, {
    __nextHasNoMarginBottom: true,
    label: __('Author'),
    value: authorId,
    options: authorOptions,
    onChange: handleSelect
  })), createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Show avatar'),
    checked: showAvatar,
    onChange: () => setAttributes({
      showAvatar: !showAvatar
    })
  }), showAvatar && createElement(SelectControl, {
    __nextHasNoMarginBottom: true,
    label: __('Avatar size'),
    value: attributes.avatarSize,
    options: avatarSizes,
    onChange: size => {
      setAttributes({
        avatarSize: Number(size)
      });
    }
  }), createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Show bio'),
    checked: showBio,
    onChange: () => setAttributes({
      showBio: !showBio
    })
  }), createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Link author name to author page'),
    checked: isLink,
    onChange: () => setAttributes({
      isLink: !isLink
    })
  }), isLink && createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Open in new tab'),
    onChange: value => setAttributes({
      linkTarget: value ? '_blank' : '_self'
    }),
    checked: linkTarget === '_blank'
  }))), createElement(BlockControls, {
    group: "block"
  }, createElement(AlignmentControl, {
    value: textAlign,
    onChange: nextAlign => {
      setAttributes({
        textAlign: nextAlign
      });
    }
  })), createElement("div", {
    ...blockProps
  }, showAvatar && authorDetails?.avatar_urls && createElement("div", {
    className: "wp-block-post-author__avatar"
  }, createElement("img", {
    width: attributes.avatarSize,
    src: authorDetails.avatar_urls[attributes.avatarSize],
    alt: authorDetails.name
  })), createElement("div", {
    className: "wp-block-post-author__content"
  }, (!RichText.isEmpty(byline) || isSelected) && createElement(RichText, {
    className: "wp-block-post-author__byline",
    "aria-label": __('Post author byline text'),
    placeholder: __('Write byline…'),
    value: byline,
    onChange: value => setAttributes({
      byline: value
    })
  }), createElement("p", {
    className: "wp-block-post-author__name"
  }, isLink ? createElement("a", {
    href: "#post-author-pseudo-link",
    onClick: event => event.preventDefault()
  }, authorName) : authorName), showBio && createElement("p", {
    className: "wp-block-post-author__bio",
    dangerouslySetInnerHTML: {
      __html: authorDetails?.description
    }
  }))));
}
export default PostAuthorEdit;
//# sourceMappingURL=edit.js.map