/**
 * wp.media.view.MediaFrame.Manage.Router
 *
 * A router for handling the browser history and application state.
 *
 * @memberOf wp.media.view.MediaFrame.Manage
 *
 * @class
 * @augments Backbone.Router
 */
var Router = Backbone.Router.extend(/** @lends wp.media.view.MediaFrame.Manage.Router.prototype */{
	routes: {
		'upload.php?item=:slug&mode=edit': 'editItem',
		'upload.php?item=:slug':           'showItem',
		'upload.php?search=:query':        'search',
		'upload.php':                      'reset'
	},

	// Map routes against the page URL.
	baseUrl: function( url ) {
		return 'upload.php' + url;
	},

	reset: function() {
		var frame = wp.media.frames.edit;

		if ( frame ) {
			frame.close();
		}
	},

	// Respond to the search route by filling the search field and triggering the input event.
	search: function( query ) {
		jQuery( '#media-search-input' ).val( query ).trigger( 'input' );
	},

	// Show the modal with a specific item.
	showItem: function( query ) {
		var media = wp.media,
			frame = media.frames.browse,
			library = frame.state().get('library'),
			itemId = parseInt( query, 10 ),
			item;

		// Trigger the media frame to open the correct item.
		item = library.findWhere( { id: itemId } );

		if ( item ) {
			item.set( 'skipHistory', true );
			frame.trigger( 'edit:attachment', item );
		} else {
			item = media.attachment( itemId );
			frame.listenTo( item, 'change', function( model ) {
				frame.stopListening( item );
				frame.trigger( 'edit:attachment', model );

				// Load library items in batches until target item is found.
				var loadMore = function() {
					var foundItem = library.findWhere( { id: itemId } );

					if ( foundItem ) {
						// Item found - update edit frame to enable navigation.
						var editFrame = wp.media.frames.edit;
						if ( editFrame && editFrame.toggleNav ) {
							editFrame.model = foundItem;
							editFrame.toggleNav();
						}
					} else if ( library.hasMore() ) {
						library.more().done( loadMore );
					}
				};

				if ( library.hasMore() ) {
					loadMore();
				}
			} );
			item.fetch();
		}
	},

	// Show the modal in edit mode with a specific item.
	editItem: function( query ) {
		this.showItem( query );
		wp.media.frames.edit.content.mode( 'edit-details' );
	}
});

module.exports = Router;
