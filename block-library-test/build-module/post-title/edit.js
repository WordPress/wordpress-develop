import { createElement, Fragment } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { AlignmentControl, BlockControls, InspectorControls, useBlockProps, PlainText, HeadingLevelDropdown, useBlockEditingMode } from '@wordpress/block-editor';
import { ToggleControl, TextControl, PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { createBlock, getDefaultBlockName } from '@wordpress/blocks';
import { useEntityProp } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import { useCanEditEntity } from '../utils/hooks';
export default function PostTitleEdit({
  attributes: {
    level,
    textAlign,
    isLink,
    rel,
    linkTarget
  },
  setAttributes,
  context: {
    postType,
    postId,
    queryId
  },
  insertBlocksAfter
}) {
  const TagName = 'h' + level;
  const isDescendentOfQueryLoop = Number.isFinite(queryId);
  /**
   * Hack: useCanEditEntity may trigger an OPTIONS request to the REST API via the canUser resolver.
   * However, when the Post Title is a descendant of a Query Loop block, the title cannot be edited.
   * In order to avoid these unnecessary requests, we call the hook without
   * the proper data, resulting in returning early without making them.
   */
  const userCanEdit = useCanEditEntity('postType', !isDescendentOfQueryLoop && postType, postId);
  const [rawTitle = '', setTitle, fullTitle] = useEntityProp('postType', postType, 'title', postId);
  const [link] = useEntityProp('postType', postType, 'link', postId);
  const onSplitAtEnd = () => {
    insertBlocksAfter(createBlock(getDefaultBlockName()));
  };
  const blockProps = useBlockProps({
    className: classnames({
      [`has-text-align-${textAlign}`]: textAlign
    })
  });
  const blockEditingMode = useBlockEditingMode();
  let titleElement = createElement(TagName, {
    ...blockProps
  }, __('Title'));
  if (postType && postId) {
    titleElement = userCanEdit ? createElement(PlainText, {
      tagName: TagName,
      placeholder: __('No Title'),
      value: rawTitle,
      onChange: setTitle,
      __experimentalVersion: 2,
      __unstableOnSplitAtEnd: onSplitAtEnd,
      ...blockProps
    }) : createElement(TagName, {
      ...blockProps,
      dangerouslySetInnerHTML: {
        __html: fullTitle?.rendered
      }
    });
  }
  if (isLink && postType && postId) {
    titleElement = userCanEdit ? createElement(TagName, {
      ...blockProps
    }, createElement(PlainText, {
      tagName: "a",
      href: link,
      target: linkTarget,
      rel: rel,
      placeholder: !rawTitle.length ? __('No Title') : null,
      value: rawTitle,
      onChange: setTitle,
      __experimentalVersion: 2,
      __unstableOnSplitAtEnd: onSplitAtEnd
    })) : createElement(TagName, {
      ...blockProps
    }, createElement("a", {
      href: link,
      target: linkTarget,
      rel: rel,
      onClick: event => event.preventDefault(),
      dangerouslySetInnerHTML: {
        __html: fullTitle?.rendered
      }
    }));
  }
  return createElement(Fragment, null, blockEditingMode === 'default' && createElement(BlockControls, {
    group: "block"
  }, createElement(HeadingLevelDropdown, {
    value: level,
    onChange: newLevel => setAttributes({
      level: newLevel
    })
  }), createElement(AlignmentControl, {
    value: textAlign,
    onChange: nextAlign => {
      setAttributes({
        textAlign: nextAlign
      });
    }
  })), createElement(InspectorControls, null, createElement(PanelBody, {
    title: __('Settings')
  }, createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Make title a link'),
    onChange: () => setAttributes({
      isLink: !isLink
    }),
    checked: isLink
  }), isLink && createElement(Fragment, null, createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Open in new tab'),
    onChange: value => setAttributes({
      linkTarget: value ? '_blank' : '_self'
    }),
    checked: linkTarget === '_blank'
  }), createElement(TextControl, {
    __nextHasNoMarginBottom: true,
    label: __('Link rel'),
    value: rel,
    onChange: newRel => setAttributes({
      rel: newRel
    })
  })))), titleElement);
}
//# sourceMappingURL=edit.js.map