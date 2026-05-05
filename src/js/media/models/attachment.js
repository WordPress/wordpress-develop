var $ = Backbone.$,
	Attachment;

/**
 * wp.media.model.Attachment
 *
 * @memberOf wp.media.model
 *
 * @class
 * @augments Backbone.Model
 */
Attachment = Backbone.Model.extend(/** @lends wp.media.model.Attachment.prototype */{
	/**
	 * Triggered when attachment details change
	 * Overrides Backbone.Model.sync
	 *
	 * @param {string} method
	 * @param {wp.media.model.Attachment} model
	 * @param {Object} [options={}]
	 *
	 * @return {Promise}
	 */
	sync: function( method, model, options ) {
		// If the attachment does not yet have an `id`, return an instantly
		// rejected promise. Otherwise, all of our requests will fail.
		if ( _.isUndefined( this.id ) ) {
			return $.Deferred().rejectWith( this ).promise();
		}

		// Overload the `read` request so Attachment.fetch() functions correctly.
		if ( 'read' === method ) {
			options = options || {};
			options.context = this;
			options.data = _.extend( options.data || {}, {
				action: 'get-attachment',
				id: this.id
			});
			return wp.media.ajax( options );

		// Overload the `update` request so properties can be saved.
		} else if ( 'update' === method ) {
			// If we do not have the necessary nonce, fail immediately.
			if ( ! this.get('nonces') || ! this.get('nonces').update ) {
				return $.Deferred().rejectWith( this ).promise();
			}

			options = options || {};
			options.context = this;

			// Set the action and ID.
			options.data = _.extend( options.data || {}, {
				action:  'save-attachment',
				id:      this.id,
				nonce:   this.get('nonces').update,
				post_id: wp.media.model.settings.post.id
			});

			// Record the values of the changed attributes.
			if ( model.hasChanged() ) {
				options.data.changes = {};

				_.each( model.changed, function( value, key ) {
					options.data.changes[ key ] = this.get( key );
				}, this );
			}

			return wp.media.ajax( options );

		// Overload the `delete` request so attachments can be removed.
		// This will permanently delete an attachment.
		} else if ( 'delete' === method ) {
			options = options || {};

			if ( ! options.wait ) {
				this.destroyed = true;
			}

			options.context = this;
			options.data = _.extend( options.data || {}, {
				action:   'delete-post',
				id:       this.id,
				_wpnonce: this.get('nonces')['delete']
			});

			return wp.media.ajax( options ).done( function() {
				this.destroyed = true;
			}).fail( function() {
				this.destroyed = false;
			});

		// Otherwise, fall back to `Backbone.sync()`.
		} else {
			/**
			 * Call `sync` directly on Backbone.Model
			 */
			return Backbone.Model.prototype.sync.apply( this, arguments );
		}
	},
	/**
	 * Convert date strings into Date objects.
	 *
	 * @param {Object} resp The raw response object, typically returned by fetch()
	 * @return {Object} The modified response object, which is the attributes hash
	 *                  to be set on the model.
	 */
	parse: function( resp ) {
		if ( ! resp ) {
			return resp;
		}

		resp.date = new Date( resp.date );
		resp.modified = new Date( resp.modified );
		return resp;
	},
	/**
	 * @param {Object} data The properties to be saved.
	 * @param {Object} options Sync options. e.g. patch, wait, success, error.
	 *
	 * @this Backbone.Model
	 *
	 * @return {Promise}
	 */
	saveCompat: function( data, options ) {
		var model = this;

		// If we do not have the necessary nonce, fail immediately.
		if ( ! this.get('nonces') || ! this.get('nonces').update ) {
			return $.Deferred().rejectWith( this ).promise();
		}

		return wp.media.post( 'save-attachment-compat', _.defaults({
			id:      this.id,
			nonce:   this.get('nonces').update,
			post_id: wp.media.model.settings.post.id
		}, data ) ).done( function( resp, status, xhr ) {
			model.set( model.parse( resp, xhr ), options );
		});
	}
},/** @lends wp.media.model.Attachment */{
	/**
	 * Create a new model on the static 'all' attachments collection and return it.
	 *
	 * @static
	 *
	 * @param {Object} attrs
	 * @return {wp.media.model.Attachment}
	 */
	create: function( attrs ) {
		var Attachments = wp.media.model.Attachments;
		return Attachments.all.push( attrs );
	},
	/**
	 * Create a new model on the static 'all' attachments collection and return it.
	 *
	 * If this function has already been called for the id,
	 * it returns the specified attachment.
	 *
	 * @static
	 * @param {string} id A string used to identify a model.
	 * @param {Backbone.Model|undefined} attachment
	 * @return {wp.media.model.Attachment}
	 */
	get: _.memoize( function( id, attachment ) {
		var Attachments = wp.media.model.Attachments;
		return Attachments.all.push( attachment || { id: id } );
	}),

	/**
	 * Delete multiple attachments in a single batched AJAX request.
	 *
	 * Sends attachment IDs and their per-item nonces to the
	 * `delete-post-batch` endpoint, splitting into chunks of `batchSize`.
	 * On success, marks each successfully deleted model as destroyed and
	 * fires its `destroy` event so collections update automatically.
	 *
	 * @since 7.1.0
	 * @static
	 *
	 * @param {wp.media.model.Attachment[]} 	models    Array of Attachment models to delete.
	 * @param {number}                      	[batchSize=50] Max items per request.
	 * @return {jQuery.Promise} Resolves with 0 or 1 for failure and success respectively.
	 */
	batchDestroy: function( models, batchSize ) {
		batchSize = batchSize || 50;

		var promises = [],
			i, slice, data;

		for ( i = 0; i < models.length; i += batchSize ) {
			slice = models.slice( i, i + batchSize );
			data  = _.reduce( slice, function( acc, model ) {
				acc.ids.push( model.id );
				acc.nonces[ model.id ] = model.get( 'nonces' )['delete'];
				return acc;
			}, { ids: [], nonces: {} } );

			promises.push( wp.media.post( 'delete-post-batch', data ) );
		}

		return $.when.apply( null, promises ).then( function() {
			_.each( models, function( model ) {
				model.destroyed = true;
				model.stopListening();
				model.trigger( 'destroy', model, model.collection );
			});
		});
	}
});

module.exports = Attachment;
