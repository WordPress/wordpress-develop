/**
 * WordPress dependencies
 */
import { comment as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  __experimental: "fse",
  name: "core/post-comment",
  title: "Comment (deprecated)",
  category: "theme",
  allowedBlocks: ["core/avatar", "core/comment-author-name", "core/comment-content", "core/comment-date", "core/comment-edit-link", "core/comment-reply-link"],
  description: "This block is deprecated. Please use the Comments block instead.",
  textdomain: "default",
  attributes: {
    commentId: {
      type: "number"
    }
  },
  providesContext: {
    commentId: "commentId"
  },
  supports: {
    html: false,
    inserter: false
  }
};
import edit from './edit';
import save from './save';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  edit,
  save
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map