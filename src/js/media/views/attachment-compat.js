var View = wp.media.View,
	AttachmentCompat;

/**
 * wp.media.view.AttachmentCompat
 *
 * A view to display fields added via the `attachment_fields_to_edit` filter.
 *
 * @memberOf wp.media.view
 *
 * @class
 * @augments wp.media.View
 * @augments wp.Backbone.View
 * @augments Backbone.View
 */
AttachmentCompat = View.extend(/** @lends wp.media.view.AttachmentCompat.prototype */{
	tagName:   'form',
	className: 'compat-item',

	events: {
		'submit':          'preventDefault',
		'change input':    'save',
		'change select':   'save',
		'change textarea': 'save'
	},

	initialize: function() {
		// Render the view when a new item is added.
		this.listenTo( this.model, 'add', this.render );
	},

	/**
	 * Disposes of the view and its children.
	 *
	 * @return {wp.media.view.AttachmentCompat} Returns itself to allow chaining.
	 */
	dispose: function() {
		if ( this.$(':focus').length ) {
			this.save();
		}
		/**
		 * call 'dispose' directly on the parent class
		 */
		return View.prototype.dispose.apply( this, arguments );
	},
	/**
	 * Renders the view.
	 *
	 * @return {void|wp.media.view.AttachmentCompat} Returns itself to allow chaining.
	 */
	render: function() {
		var compat = this.model.get('compat');
		if ( ! compat || ! compat.item ) {
			return;
		}

		this.views.detach();
		this.$el.html( compat.item );
		this.views.render();
		return this;
	},
	/**
	 * Prevents the default action of the event.
	 *
	 * @param {Object} event
	 */
	preventDefault: function( event ) {
		event.preventDefault();
	},
	/**
	 * Saves the attachment compat data.
	 *
	 * @param {Object} event
	 */
	save: function( event ) {
		var data = {};

		if ( event ) {
			event.preventDefault();
		}

		_.each( this.$el.serializeArray(), function( pair ) {
			data[ pair.name ] = pair.value;
		});

		this.controller.trigger( 'attachment:compat:waiting', ['waiting'] );
		this.model.saveCompat( data ).always( _.bind( this.postSave, this ) );
	},

	/**
	 * Triggers the `attachment:compat:ready` event on the controller after saving the compat data.
	 */
	postSave: function() {
		this.controller.trigger( 'attachment:compat:ready', ['ready'] );
	}
});

module.exports = AttachmentCompat;
