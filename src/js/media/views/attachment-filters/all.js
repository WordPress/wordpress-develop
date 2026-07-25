var l10n = wp.media.view.l10n,
	All;

/**
 * wp.media.view.AttachmentFilters.All
 *
 * @memberOf wp.media.view.AttachmentFilters
 *
 * @class
 * @augments wp.media.view.AttachmentFilters
 * @augments wp.media.View
 * @augments wp.Backbone.View
 * @augments Backbone.View
 */
All = wp.media.view.AttachmentFilters.extend(/** @lends wp.media.view.AttachmentFilters.All.prototype */{
	createFilters: function() {
		var filters = {},
			uid = window.userSettings ? parseInt( window.userSettings.uid, 10 ) : 0;

		_.each( wp.media.view.settings.mimeTypes || {}, function( text, key ) {
			filters[ key ] = {
				text: text,
				props: {
					status:     null,
					type:       key,
					uploadedTo: null,
					orderby:    'date',
					order:      'DESC',
					author:     null,
					icon:       wp.media.view.settings.mimeIcons[key] || false
				},
			};
		});

		filters.all = {
			text:  l10n.allMediaItems,
			props: {
				status:     null,
				type:       null,
				uploadedTo: null,
				orderby:   'date',
				order:     'DESC',
				author:    null,
				icon:      'format-gallery'
			},
			priority: 10
		};

		if ( wp.media.view.settings.post.id ) {
			filters.uploaded = {
				text:  l10n.uploadedToThisPost,
				props: {
					status:     null,
					type:       null,
					uploadedTo: wp.media.view.settings.post.id,
					orderby:   'menuOrder',
					order:     'ASC',
					author:    null,
					icon:      'upload'
				},
				priority: 20
			};
		}

		filters.unattached = {
			text:  l10n.unattached,
			props: {
				status:     null,
				uploadedTo: 0,
				type:       null,
				orderby:    'menuOrder',
				order:      'ASC',
				author:     null,
				icon:       'no'
			},
			priority: 50
		};

		if ( uid ) {
			filters.mine = {
				text:  l10n.mine,
				props: {
					status:     null,
					type:       null,
					uploadedTo: null,
					orderby:    'date',
					order:      'DESC',
					author:     uid,
					icon:       'admin-users'
				},
				priority: 50
			};
		}

		if ( wp.media.view.settings.mediaTrash &&
			this.controller.isModeActive( 'grid' ) ) {

			filters.trash = {
				text:  l10n.trash,
				props: {
					uploadedTo: null,
					status:     'trash',
					type:       null,
					orderby:    'date',
					order:      'DESC',
					author:     null,
					icon:       'trash'
				},
				priority: 50
			};
		}

		this.filters = filters;
	}
});

module.exports = All;
