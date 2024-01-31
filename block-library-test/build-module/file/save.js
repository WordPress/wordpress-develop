import { createElement, Fragment } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { RichText, useBlockProps, __experimentalGetElementClassName } from '@wordpress/block-editor';
export default function save({
  attributes
}) {
  const {
    href,
    fileId,
    fileName,
    textLinkHref,
    textLinkTarget,
    showDownloadButton,
    downloadButtonText,
    displayPreview,
    previewHeight
  } = attributes;
  const pdfEmbedLabel = RichText.isEmpty(fileName) ? 'PDF embed' :
  // To do: use toPlainText, but we need ensure it's RichTextData. See
  // https://github.com/WordPress/gutenberg/pull/56710.
  fileName.toString();
  const hasFilename = !RichText.isEmpty(fileName);

  // Only output an `aria-describedby` when the element it's referring to is
  // actually rendered.
  const describedById = hasFilename ? fileId : undefined;
  return href && createElement("div", {
    ...useBlockProps.save()
  }, displayPreview && createElement(Fragment, null, createElement("object", {
    className: "wp-block-file__embed",
    data: href,
    type: "application/pdf",
    style: {
      width: '100%',
      height: `${previewHeight}px`
    },
    "aria-label": pdfEmbedLabel
  })), hasFilename && createElement("a", {
    id: describedById,
    href: textLinkHref,
    target: textLinkTarget,
    rel: textLinkTarget ? 'noreferrer noopener' : undefined
  }, createElement(RichText.Content, {
    value: fileName
  })), showDownloadButton && createElement("a", {
    href: href,
    className: classnames('wp-block-file__button', __experimentalGetElementClassName('button')),
    download: true,
    "aria-describedby": describedById
  }, createElement(RichText.Content, {
    value: downloadButtonText
  })));
}
//# sourceMappingURL=save.js.map