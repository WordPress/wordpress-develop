/* global wp, sinon */

jQuery( function() {
	QUnit.module('Custom Header: ChoiceList', {
		beforeEach: function() {
			wp.customize.HeaderTool.currentHeader = new wp.customize.HeaderTool.ImageModel();
			this.apiStub = sinon.stub(wp.customize, 'get').returns('foo');
			this.choiceList = new wp.customize.HeaderTool.ChoiceList();
		},
		afterEach: function() {
			this.apiStub.restore();
		}
	});

	QUnit.test('should parse _wpCustomizeHeader.uploads into itself', function( assert ) {
		assert.equal(this.choiceList.length, 4);
	});

	QUnit.test('should sort by newest first', function( assert ) {
		assert.equal(this.choiceList.at(2).get('header').attachment_id, 1);
		assert.equal(this.choiceList.first().get('header').attachment_id, 3);
	});

	QUnit.module('Custom Header: DefaultsList', {
		beforeEach: function() {
			wp.customize.HeaderTool.currentHeader = new wp.customize.HeaderTool.ImageModel();
			this.apiStub = sinon.stub(wp.customize, 'get').returns('foo');
			this.choiceList = new wp.customize.HeaderTool.DefaultsList();
		},
		afterEach: function() {
			this.apiStub.restore();
		}
	});

	QUnit.test('it should parse _wpCustomizeHeader.defaults into itself', function( assert ) {
		assert.equal(this.choiceList.length, 4);
	});

	QUnit.test('it parses the default image names', function( assert ) {
		assert.equal(this.choiceList.first().get('header').defaultName, 'circle');
		assert.equal(this.choiceList.at(2).get('header').defaultName, 'star');
	});

	QUnit.module('Custom Header: HeaderImage shouldBeCropped()', {
		beforeEach: function() {
			wp.customize.HeaderTool.currentHeader = new wp.customize.HeaderTool.ImageModel();
			this.model = new wp.customize.HeaderTool.ImageModel();
			this.model.set({
				themeWidth: 1000,
				themeHeight: 200
			});
		}
	});

	QUnit.test('should not be cropped when the theme does not support flex width or height and the image has the same dimensions of the theme image', function( assert ) {
		this.model.set({
			themeFlexWidth: false,
			themeFlexHeight: false,
			imageWidth: 1000,
			imageHeight: 200
		});

		assert.equal(this.model.shouldBeCropped(), false);
	});

	QUnit.test('should be cropped when the image has the same dimensions of the theme image', function( assert ) {
		this.model.set({
			themeFlexWidth: false,
			themeFlexHeight: false,
			imageWidth: 2000,
			imageHeight: 400
		});

		assert.equal(this.model.shouldBeCropped(), true);
	});

	QUnit.test('should not be cropped when the theme only supports flex width and the image has the same height as the theme image', function( assert ) {
		this.model.set({
			themeFlexWidth: true,
			themeFlexHeight: false,
			imageWidth: 4000,
			imageHeight: 200
		});

		assert.equal(this.model.shouldBeCropped(), false);
	});

	QUnit.test('should not be cropped when the theme only supports flex height and the image has the same width as the theme image', function( assert ) {
		this.model.set({
			themeFlexWidth: false,
			themeFlexHeight: true,
			imageWidth: 1000,
			imageHeight: 600
		});

		assert.equal(this.model.shouldBeCropped(), false);
	});

	QUnit.test('should not be cropped when the theme supports flex height AND width', function( assert ) {
		this.model.set({
			themeFlexWidth: true,
			themeFlexHeight: true,
			imageWidth: 10000,
			imageHeight: 8600
		});

		assert.equal(this.model.shouldBeCropped(), false);
	});

	QUnit.test('should not be cropped when the image width is smaller than or equal to theme width', function( assert ) {
		this.model.set({
			themeFlexWidth: false,
			themeFlexHeight: false,
			imageWidth: 1000,
			imageHeight: 100
		});

		assert.equal(this.model.shouldBeCropped(), false);
	});

	QUnit.test('should not be cropped when the image width is smaller than or equal to theme width, theme supports flex height and width', function( assert ) {
		this.model.set({
			themeFlexWidth: true,
			themeFlexHeight: true,
			imageWidth: 900,
			imageHeight: 100
		});

		assert.equal(this.model.shouldBeCropped(), false);
	});
});
