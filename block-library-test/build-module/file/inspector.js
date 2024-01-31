import { createElement, Fragment } from "react";
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { PanelBody, RangeControl, SelectControl, ToggleControl } from '@wordpress/components';
import { InspectorControls } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import { MIN_PREVIEW_HEIGHT, MAX_PREVIEW_HEIGHT } from './edit';
export default function FileBlockInspector({
  hrefs,
  openInNewWindow,
  showDownloadButton,
  changeLinkDestinationOption,
  changeOpenInNewWindow,
  changeShowDownloadButton,
  displayPreview,
  changeDisplayPreview,
  previewHeight,
  changePreviewHeight
}) {
  const {
    href,
    textLinkHref,
    attachmentPage
  } = hrefs;
  let linkDestinationOptions = [{
    value: href,
    label: __('URL')
  }];
  if (attachmentPage) {
    linkDestinationOptions = [{
      value: href,
      label: __('Media file')
    }, {
      value: attachmentPage,
      label: __('Attachment page')
    }];
  }
  return createElement(Fragment, null, createElement(InspectorControls, null, href.endsWith('.pdf') && createElement(PanelBody, {
    title: __('PDF settings')
  }, createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Show inline embed'),
    help: displayPreview ? __("Note: Most phone and tablet browsers won't display embedded PDFs.") : null,
    checked: !!displayPreview,
    onChange: changeDisplayPreview
  }), displayPreview && createElement(RangeControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: __('Height in pixels'),
    min: MIN_PREVIEW_HEIGHT,
    max: Math.max(MAX_PREVIEW_HEIGHT, previewHeight),
    value: previewHeight,
    onChange: changePreviewHeight
  })), createElement(PanelBody, {
    title: __('Settings')
  }, createElement(SelectControl, {
    __nextHasNoMarginBottom: true,
    label: __('Link to'),
    value: textLinkHref,
    options: linkDestinationOptions,
    onChange: changeLinkDestinationOption
  }), createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Open in new tab'),
    checked: openInNewWindow,
    onChange: changeOpenInNewWindow
  }), createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Show download button'),
    checked: showDownloadButton,
    onChange: changeShowDownloadButton
  }))));
}
//# sourceMappingURL=inspector.js.map