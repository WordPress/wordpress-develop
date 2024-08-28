/* global addComment, WP, I10n */

/**
 * WordPress Comment Reply 6.6.1
 *
 * Adds a reply link to comments.
 * Used to reply to specific comments and reference them in replies.
 *
 * @output wp-includes/js/comment-reply.js
 */

window.addComment = {

	moveForm : function( commId, parentId, respondId, postId ) {
		var t           = this,
			div         = document.getElementById( 'wp-temp-form-div' ),
			respond     = document.getElementById( respondId ),
			comment     = document.getElementById( commId ),
			parentIdField = document.getElementById( 'comment_parent' ),
			postIdField  = document.getElementById( 'comment_post_ID' );

		if ( ! div || ! respond || ! comment || ! parentIdField || ! postIdField ) {
			return;
		}

		t.respondId = respondId;
		postId      = postId || false;

		if ( ! t.childs ) {
			t.childs = [];
		}

		t.childs.push( comment );

		if ( comment.parentNode ) {
			if ( respond.firstChild ) {
				respond.insertBefore( div, respond.firstChild );
			} else {
				respond.appendChild( div );
			}
		}

		/*
		 * Set the value of the 'comment_parent' input field to the
		 * comment_ID of the parent comment.
		 */
		parentIdField.value = parentId;

		/*
		 * Set the value of the 'comment_post_ID' hidden input field to
		 * the post_ID of the post being commented on.
		 */
		if ( postId && postIdField ) {
			postIdField.value = postId;
		}

		t.ariaFocusForm();

		t.cancelVisible = true;

		t.updateCancelReplyLink();

		/*
		 * Lastly, hide any replies to the current comment that
		 * happen to be showing.
		 */
		t.toggleCommentDisplay( commId, parentId );
	},

	toggleCommentDisplay : function( commId, parentId ) {
		var t = this,
			comm = t.childs[ 0 ],
			display = t.childs[ 0 ] && 'none' !== t.childs[ 0 ].style.display;

		for ( var i = 0; i < t.childs.length; i++ ) {
			if ( t.childs[ i ] && t.childs[ i ].style ) {
				t.childs[ i ].style.display = display ? 'none' : 'block';
			}
		}

		if ( ! display && ( parentId === commId || parentId === '0' ) ) {
			if ( comm && comm.style ) {
				comm.style.display = 'block';
			}
		}
	},

	cancelReply : function() {
		var self        = addComment,
			temp        = document.getElementById( 'wp-temp-form-div' ),
			respond     = document.getElementById( self.respondId ),
			cancelLink  = self.cancelLink;

		self.childs       = [];
		self.cancelVisible = false;

		if ( ! temp || ! respond || ! cancelLink ) {
			return;
		}

		self.toggleCommentDisplay( temp.firstChild.value, '0' );
		respond.parentNode.insertBefore( temp, respond );

		self.ariaUnfocusForm();

		cancelLink.style.display = 'none';

		cancelLink.onclick = null;

		return false;
	},

	updateCancelReplyLink : function() {
		var t           = this,
			cancelLink  = t.cancelLink;

		if ( ! cancelLink ) {
			return;
		}

		if ( t.cancelVisible ) {
			cancelLink.style.display = '';
		} else {
			cancelLink.style.display = 'none';
		}
	},

	ariaFocusForm : function() {
		var div       = document.getElementById( 'wp-temp-form-div' );

		if ( div && div.firstChild ) {
			div.firstChild.setAttribute( 'aria-hidden', 'false' );
		}
	},

	ariaUnfocusForm : function() {
		var div       = document.getElementById( 'wp-temp-form-div' );

		if ( div && div.firstChild ) {
			div.firstChild.setAttribute( 'aria-hidden', 'true' );
		}
	},

	init : function() {
		var t                 = this,
			commId,
			parentId,
			respondId,
			postId,
			onReplyLinkClick,
			element,
			responseLinks = document.querySelectorAll( '.comment-reply-link' ),
			cancelElement,
			cancelLink,
			i;

		onReplyLinkClick = function( event ) {
			// Check if the 'moveForm' function has been overridden
			if (window.addComment.moveForm !== t.moveForm) {
				// If overridden, maintain the original behavior (preventDefault) for compatibility
				event.preventDefault();
			}
			commId      = this.dataset.commentid;
			parentId    = this.dataset.postid;
			respondId   = this.dataset.belowelement;
			postId      = this.dataset.respondelement;
			t.moveForm( commId, parentId, respondId, postId );
		};

		for ( i = 0; i < responseLinks.length; i++ ) {
			element = responseLinks[ i ];
			// Use passive listeners if 'moveForm' is not overridden
			element.addEventListener( 'click', onReplyLinkClick, { passive: window.addComment.moveForm === t.moveForm });
		}

		cancelElement = document.getElementById( 'cancel-comment-reply-link' );
		cancelLink    = t.cancelLink = cancelElement || document.getElementById( 'wp-cancel-comment-reply-link' );

		if ( cancelLink ) {
			cancelLink.onclick = function() {
				if ( typeof WP !== 'undefined' && typeof WP.mce !== 'undefined' && WP.mce.activeEditor ) {
					WP.mce.activeEditor.setContent( '' );
				}

				return t.cancelReply();
			};
		}

		/*
		 * The following is mainly for accessibility. The cancel link
		 * should only be visible if the form is being displayed and a
		 * comment is being replied to.
		 */
		t.updateCancelReplyLink();
	}
}; // end addComment

if ( typeof WP === 'undefined' ) {
	window.WP = {};
}

WP.commentReply = addComment;

addComment.init();