import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { __, _x } from '@wordpress/i18n';
import { Button, Placeholder, ExternalLink } from '@wordpress/components';
import { BlockIcon } from '@wordpress/block-editor';
const EmbedPlaceholder = ({
  icon,
  label,
  value,
  onSubmit,
  onChange,
  cannotEmbed,
  fallback,
  tryAgain
}) => {
  return createElement(Placeholder, {
    icon: createElement(BlockIcon, {
      icon: icon,
      showColors: true
    }),
    label: label,
    className: "wp-block-embed",
    instructions: __('Paste a link to the content you want to display on your site.')
  }, createElement("form", {
    onSubmit: onSubmit
  }, createElement("input", {
    type: "url",
    value: value || '',
    className: "components-placeholder__input",
    "aria-label": label,
    placeholder: __('Enter URL to embed here…'),
    onChange: onChange
  }), createElement(Button, {
    variant: "primary",
    type: "submit"
  }, _x('Embed', 'button label'))), createElement("div", {
    className: "wp-block-embed__learn-more"
  }, createElement(ExternalLink, {
    href: __('https://wordpress.org/documentation/article/embeds/')
  }, __('Learn more about embeds'))), cannotEmbed && createElement("div", {
    className: "components-placeholder__error"
  }, createElement("div", {
    className: "components-placeholder__instructions"
  }, __('Sorry, this content could not be embedded.')), createElement(Button, {
    variant: "secondary",
    onClick: tryAgain
  }, _x('Try again', 'button label')), ' ', createElement(Button, {
    variant: "secondary",
    onClick: fallback
  }, _x('Convert to link', 'button label'))));
};
export default EmbedPlaceholder;
//# sourceMappingURL=embed-placeholder.js.map