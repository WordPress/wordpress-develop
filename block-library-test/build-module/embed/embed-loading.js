import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { Spinner } from '@wordpress/components';
const EmbedLoading = () => createElement("div", {
  className: "wp-block-embed is-loading"
}, createElement(Spinner, null));
export default EmbedLoading;
//# sourceMappingURL=embed-loading.js.map