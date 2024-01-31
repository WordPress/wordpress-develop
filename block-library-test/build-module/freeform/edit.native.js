import { createElement } from "react";
/**
 * Internal dependencies
 */
import MissingEdit from '../missing/edit';
const ClassicEdit = props => createElement(MissingEdit, {
  ...props,
  attributes: {
    ...props.attributes,
    originalName: props.name
  }
});
export default ClassicEdit;
//# sourceMappingURL=edit.native.js.map