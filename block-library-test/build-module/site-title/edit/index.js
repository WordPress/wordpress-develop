import { createElement, Fragment } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';
import { RichText, AlignmentControl, InspectorControls, BlockControls, useBlockProps, HeadingLevelDropdown } from '@wordpress/block-editor';
import { ToggleControl, PanelBody } from '@wordpress/components';
import { createBlock, getDefaultBlockName } from '@wordpress/blocks';
import { decodeEntities } from '@wordpress/html-entities';
const HEADING_LEVELS = [0, 1, 2, 3, 4, 5, 6];
export default function SiteTitleEdit({
  attributes,
  setAttributes,
  insertBlocksAfter
}) {
  const {
    level,
    textAlign,
    isLink,
    linkTarget
  } = attributes;
  const {
    canUserEdit,
    title
  } = useSelect(select => {
    const {
      canUser,
      getEntityRecord,
      getEditedEntityRecord
    } = select(coreStore);
    const canEdit = canUser('update', 'settings');
    const settings = canEdit ? getEditedEntityRecord('root', 'site') : {};
    const readOnlySettings = getEntityRecord('root', '__unstableBase');
    return {
      canUserEdit: canEdit,
      title: canEdit ? settings?.title : readOnlySettings?.name
    };
  }, []);
  const {
    editEntityRecord
  } = useDispatch(coreStore);
  function setTitle(newTitle) {
    editEntityRecord('root', 'site', undefined, {
      title: newTitle
    });
  }
  const TagName = level === 0 ? 'p' : `h${level}`;
  const blockProps = useBlockProps({
    className: classnames({
      [`has-text-align-${textAlign}`]: textAlign,
      'wp-block-site-title__placeholder': !canUserEdit && !title
    })
  });
  const siteTitleContent = canUserEdit ? createElement(TagName, {
    ...blockProps
  }, createElement(RichText, {
    tagName: isLink ? 'a' : 'span',
    href: isLink ? '#site-title-pseudo-link' : undefined,
    "aria-label": __('Site title text'),
    placeholder: __('Write site title…'),
    value: title,
    onChange: setTitle,
    allowedFormats: [],
    disableLineBreaks: true,
    __unstableOnSplitAtEnd: () => insertBlocksAfter(createBlock(getDefaultBlockName()))
  })) : createElement(TagName, {
    ...blockProps
  }, isLink ? createElement("a", {
    href: "#site-title-pseudo-link",
    onClick: event => event.preventDefault()
  }, decodeEntities(title) || __('Site Title placeholder')) : createElement("span", null, decodeEntities(title) || __('Site Title placeholder')));
  return createElement(Fragment, null, createElement(BlockControls, {
    group: "block"
  }, createElement(HeadingLevelDropdown, {
    options: HEADING_LEVELS,
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
    label: __('Make title link to home'),
    onChange: () => setAttributes({
      isLink: !isLink
    }),
    checked: isLink
  }), isLink && createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Open in new tab'),
    onChange: value => setAttributes({
      linkTarget: value ? '_blank' : '_self'
    }),
    checked: linkTarget === '_blank'
  }))), siteTitleContent);
}
//# sourceMappingURL=index.js.map