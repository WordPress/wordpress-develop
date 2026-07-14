<?php return array(
	'a11y.js' => array(
		'dependencies' => array(
			'wp-dom-ready',
			'wp-i18n'
		),
		'version' => '31c6cec5a4ff7aff483d'
	),
	'annotations.js' => array(
		'dependencies' => array(
			'wp-data',
			'wp-hooks',
			'wp-i18n',
			'wp-rich-text'
		),
		'version' => '348a030f1b5717cfaba4'
	),
	'api-fetch.js' => array(
		'dependencies' => array(
			'wp-i18n',
			'wp-private-apis',
			'wp-url'
		),
		'version' => '6f2a4faeee3c722b1e57'
	),
	'autop.js' => array(
		'dependencies' => array(
			
		),
		'version' => '4e10a18cb6f21a043fc0'
	),
	'base-styles.js' => array(
		'dependencies' => array(
			
		),
		'version' => '67fd7250ac73fa2feba5'
	),
	'blob.js' => array(
		'dependencies' => array(
			
		),
		'version' => 'c7582a735ddd2edc9731'
	),
	'block-directory.js' => array(
		'dependencies' => array(
			'react',
			'react-dom',
			'react-jsx-runtime',
			'wp-a11y',
			'wp-api-fetch',
			'wp-block-editor',
			'wp-blocks',
			'wp-components',
			'wp-compose',
			'wp-core-data',
			'wp-data',
			'wp-editor',
			'wp-element',
			'wp-hooks',
			'wp-html-entities',
			'wp-i18n',
			'wp-notices',
			'wp-plugins',
			'wp-primitives',
			'wp-private-apis',
			'wp-theme',
			'wp-url'
		),
		'version' => 'c2d6e3ffde8b388c7334'
	),
	'block-editor.js' => array(
		'dependencies' => array(
			'react',
			'react-dom',
			'react-jsx-runtime',
			'wp-a11y',
			'wp-blob',
			'wp-block-serialization-default-parser',
			'wp-blocks',
			'wp-commands',
			'wp-components',
			'wp-compose',
			'wp-data',
			'wp-date',
			'wp-deprecated',
			'wp-dom',
			'wp-element',
			'wp-hooks',
			'wp-html-entities',
			'wp-i18n',
			'wp-is-shallow-equal',
			'wp-keyboard-shortcuts',
			'wp-keycodes',
			'wp-notices',
			'wp-preferences',
			'wp-primitives',
			'wp-priority-queue',
			'wp-private-apis',
			'wp-rich-text',
			'wp-style-engine',
			'wp-theme',
			'wp-token-list',
			'wp-upload-media',
			'wp-url',
			'wp-warning'
		),
		'version' => 'fc4292b5d92301aea03b'
	),
	'block-library.js' => array(
		'dependencies' => array(
			'react',
			'react-dom',
			'react-jsx-runtime',
			'wp-a11y',
			'wp-api-fetch',
			'wp-autop',
			'wp-blob',
			'wp-block-editor',
			'wp-blocks',
			'wp-components',
			'wp-compose',
			'wp-core-data',
			'wp-data',
			'wp-date',
			'wp-deprecated',
			'wp-dom',
			'wp-element',
			'wp-escape-html',
			'wp-hooks',
			'wp-html-entities',
			'wp-i18n',
			'wp-keyboard-shortcuts',
			'wp-keycodes',
			'wp-notices',
			'wp-patterns',
			'wp-primitives',
			'wp-private-apis',
			'wp-rich-text',
			'wp-server-side-render',
			'wp-shortcode',
			'wp-theme',
			'wp-upload-media',
			'wp-url',
			'wp-wordcount'
		),
		'module_dependencies' => array(
			array(
				'id' => '@wordpress/latex-to-mathml',
				'import' => 'dynamic'
			)
		),
		'version' => 'd086bb9a20932f655613'
	),
	'block-serialization-default-parser.js' => array(
		'dependencies' => array(
			
		),
		'version' => '4c6f3dd40077f7c17604'
	),
	'block-serialization-spec-parser.js' => array(
		'dependencies' => array(
			
		),
		'version' => '7b0b496c3d48b1ef3f9e'
	),
	'blocks.js' => array(
		'dependencies' => array(
			'react-jsx-runtime',
			'wp-autop',
			'wp-blob',
			'wp-block-serialization-default-parser',
			'wp-data',
			'wp-deprecated',
			'wp-dom',
			'wp-element',
			'wp-hooks',
			'wp-html-entities',
			'wp-i18n',
			'wp-is-shallow-equal',
			'wp-private-apis',
			'wp-rich-text',
			'wp-shortcode',
			'wp-warning'
		),
		'version' => 'e073ec0a7ca1505e03c6'
	),
	'commands.js' => array(
		'dependencies' => array(
			'react',
			'react-dom',
			'react-jsx-runtime',
			'wp-components',
			'wp-data',
			'wp-element',
			'wp-i18n',
			'wp-keyboard-shortcuts',
			'wp-preferences',
			'wp-primitives',
			'wp-private-apis'
		),
		'version' => '1a4910212c7ed2355300'
	),
	'components.js' => array(
		'dependencies' => array(
			'react',
			'react-dom',
			'react-jsx-runtime',
			'wp-a11y',
			'wp-compose',
			'wp-date',
			'wp-deprecated',
			'wp-dom',
			'wp-element',
			'wp-escape-html',
			'wp-hooks',
			'wp-html-entities',
			'wp-i18n',
			'wp-is-shallow-equal',
			'wp-keycodes',
			'wp-primitives',
			'wp-private-apis',
			'wp-rich-text',
			'wp-theme',
			'wp-warning'
		),
		'version' => '6d59123171ee2c6b81a2'
	),
	'compose.js' => array(
		'dependencies' => array(
			'react',
			'react-jsx-runtime',
			'wp-deprecated',
			'wp-dom',
			'wp-element',
			'wp-is-shallow-equal',
			'wp-keycodes',
			'wp-priority-queue',
			'wp-private-apis',
			'wp-undo-manager'
		),
		'version' => '8498c872e2aad0c0b731'
	),
	'core-commands.js' => array(
		'dependencies' => array(
			'react-jsx-runtime',
			'wp-commands',
			'wp-compose',
			'wp-core-data',
			'wp-data',
			'wp-element',
			'wp-html-entities',
			'wp-i18n',
			'wp-primitives',
			'wp-private-apis',
			'wp-router',
			'wp-url'
		),
		'version' => 'b4135429b9849a4d376e'
	),
	'core-data.js' => array(
		'dependencies' => array(
			'react-jsx-runtime',
			'wp-api-fetch',
			'wp-block-editor',
			'wp-blocks',
			'wp-compose',
			'wp-data',
			'wp-deprecated',
			'wp-element',
			'wp-html-entities',
			'wp-i18n',
			'wp-private-apis',
			'wp-rich-text',
			'wp-sync',
			'wp-undo-manager',
			'wp-url',
			'wp-warning'
		),
		'version' => '1dccb93abf1718ed7626'
	),
	'customize-widgets.js' => array(
		'dependencies' => array(
			'react',
			'react-dom',
			'react-jsx-runtime',
			'wp-a11y',
			'wp-block-editor',
			'wp-block-library',
			'wp-blocks',
			'wp-components',
			'wp-compose',
			'wp-core-data',
			'wp-data',
			'wp-dom',
			'wp-element',
			'wp-hooks',
			'wp-i18n',
			'wp-is-shallow-equal',
			'wp-keyboard-shortcuts',
			'wp-keycodes',
			'wp-media-utils',
			'wp-preferences',
			'wp-primitives',
			'wp-private-apis',
			'wp-theme',
			'wp-widgets'
		),
		'version' => '9801a8a70d6d68301b45'
	),
	'data.js' => array(
		'dependencies' => array(
			'react-jsx-runtime',
			'wp-compose',
			'wp-deprecated',
			'wp-element',
			'wp-is-shallow-equal',
			'wp-priority-queue',
			'wp-private-apis',
			'wp-redux-routine'
		),
		'version' => '14a216e0932d72c22976'
	),
	'data-controls.js' => array(
		'dependencies' => array(
			'wp-api-fetch',
			'wp-data',
			'wp-deprecated'
		),
		'version' => '7e8f932da184d5537725'
	),
	'date.js' => array(
		'dependencies' => array(
			'moment',
			'wp-deprecated'
		),
		'version' => '8173fc0fc12b7bb7eaf0'
	),
	'deprecated.js' => array(
		'dependencies' => array(
			'wp-hooks'
		),
		'version' => 'fe587bac92b7d0ef760e'
	),
	'dom.js' => array(
		'dependencies' => array(
			'wp-deprecated'
		),
		'version' => '137c4d95cc2bdd56da40'
	),
	'dom-ready.js' => array(
		'dependencies' => array(
			
		),
		'version' => '3fe927cab37bf38d6a23'
	),
	'edit-post.js' => array(
		'dependencies' => array(
			'media-models',
			'media-views',
			'postbox',
			'react',
			'react-dom',
			'react-jsx-runtime',
			'wp-a11y',
			'wp-api-fetch',
			'wp-block-editor',
			'wp-block-library',
			'wp-blocks',
			'wp-commands',
			'wp-components',
			'wp-compose',
			'wp-core-data',
			'wp-data',
			'wp-deprecated',
			'wp-editor',
			'wp-element',
			'wp-hooks',
			'wp-html-entities',
			'wp-i18n',
			'wp-keyboard-shortcuts',
			'wp-keycodes',
			'wp-notices',
			'wp-plugins',
			'wp-preferences',
			'wp-primitives',
			'wp-private-apis',
			'wp-style-engine',
			'wp-theme',
			'wp-url',
			'wp-widgets'
		),
		'module_dependencies' => array(
			array(
				'id' => '@wordpress/route',
				'import' => 'static'
			)
		),
		'version' => 'df6beba4e4204b983ba5'
	),
	'edit-site.js' => array(
		'dependencies' => array(
			'react',
			'react-dom',
			'react-jsx-runtime',
			'wp-a11y',
			'wp-api-fetch',
			'wp-blob',
			'wp-block-editor',
			'wp-block-library',
			'wp-blocks',
			'wp-commands',
			'wp-components',
			'wp-compose',
			'wp-core-data',
			'wp-data',
			'wp-date',
			'wp-deprecated',
			'wp-dom',
			'wp-dom-ready',
			'wp-editor',
			'wp-element',
			'wp-hooks',
			'wp-html-entities',
			'wp-i18n',
			'wp-keyboard-shortcuts',
			'wp-keycodes',
			'wp-media-utils',
			'wp-notices',
			'wp-patterns',
			'wp-plugins',
			'wp-preferences',
			'wp-primitives',
			'wp-private-apis',
			'wp-router',
			'wp-style-engine',
			'wp-theme',
			'wp-url',
			'wp-warning',
			'wp-widgets',
			'wp-wordcount'
		),
		'module_dependencies' => array(
			array(
				'id' => '@wordpress/route',
				'import' => 'static'
			)
		),
		'version' => '5299fc2a073b6f5e2f5c'
	),
	'edit-widgets.js' => array(
		'dependencies' => array(
			'react',
			'react-dom',
			'react-jsx-runtime',
			'wp-a11y',
			'wp-api-fetch',
			'wp-block-editor',
			'wp-block-library',
			'wp-blocks',
			'wp-components',
			'wp-compose',
			'wp-core-data',
			'wp-data',
			'wp-deprecated',
			'wp-dom',
			'wp-element',
			'wp-hooks',
			'wp-i18n',
			'wp-keyboard-shortcuts',
			'wp-keycodes',
			'wp-media-utils',
			'wp-notices',
			'wp-patterns',
			'wp-plugins',
			'wp-preferences',
			'wp-primitives',
			'wp-private-apis',
			'wp-theme',
			'wp-url',
			'wp-viewport',
			'wp-widgets'
		),
		'module_dependencies' => array(
			array(
				'id' => '@wordpress/route',
				'import' => 'static'
			)
		),
		'version' => '6becdfc58f5af42f1a18'
	),
	'editor.js' => array(
		'dependencies' => array(
			'react',
			'react-dom',
			'react-jsx-runtime',
			'wp-a11y',
			'wp-api-fetch',
			'wp-blob',
			'wp-block-editor',
			'wp-block-serialization-default-parser',
			'wp-blocks',
			'wp-commands',
			'wp-components',
			'wp-compose',
			'wp-core-data',
			'wp-data',
			'wp-date',
			'wp-deprecated',
			'wp-dom',
			'wp-element',
			'wp-hooks',
			'wp-html-entities',
			'wp-i18n',
			'wp-keyboard-shortcuts',
			'wp-keycodes',
			'wp-media-utils',
			'wp-notices',
			'wp-patterns',
			'wp-plugins',
			'wp-preferences',
			'wp-primitives',
			'wp-private-apis',
			'wp-rich-text',
			'wp-server-side-render',
			'wp-style-engine',
			'wp-theme',
			'wp-upload-media',
			'wp-url',
			'wp-viewport',
			'wp-warning',
			'wp-wordcount'
		),
		'module_dependencies' => array(
			array(
				'id' => '@wordpress/route',
				'import' => 'static'
			)
		),
		'version' => 'afcf624c31fdbcf5dade'
	),
	'element.js' => array(
		'dependencies' => array(
			'react',
			'react-dom',
			'wp-escape-html'
		),
		'version' => '4a4370b2b349066fd440'
	),
	'escape-html.js' => array(
		'dependencies' => array(
			
		),
		'version' => '87ebe53e97bba59805a5'
	),
	'format-library.js' => array(
		'dependencies' => array(
			'react',
			'react-dom',
			'react-jsx-runtime',
			'wp-a11y',
			'wp-block-editor',
			'wp-components',
			'wp-compose',
			'wp-data',
			'wp-element',
			'wp-html-entities',
			'wp-i18n',
			'wp-primitives',
			'wp-private-apis',
			'wp-rich-text',
			'wp-theme',
			'wp-url'
		),
		'module_dependencies' => array(
			array(
				'id' => '@wordpress/latex-to-mathml',
				'import' => 'dynamic'
			)
		),
		'version' => 'f3bed2e33de5b4c1a9bb'
	),
	'hooks.js' => array(
		'dependencies' => array(
			
		),
		'version' => 'f0f188028580e8dc1255'
	),
	'html-entities.js' => array(
		'dependencies' => array(
			
		),
		'version' => 'a976ff3a0f00bc2999a3'
	),
	'i18n.js' => array(
		'dependencies' => array(
			'wp-hooks'
		),
		'version' => '1dfe7db3940c23ea9216'
	),
	'is-shallow-equal.js' => array(
		'dependencies' => array(
			
		),
		'version' => '7ad271045c1fe60f5496'
	),
	'keyboard-shortcuts.js' => array(
		'dependencies' => array(
			'react-jsx-runtime',
			'wp-data',
			'wp-element',
			'wp-keycodes'
		),
		'version' => '37da95806f2339bc80d0'
	),
	'keycodes.js' => array(
		'dependencies' => array(
			'wp-i18n'
		),
		'version' => 'd0b4204e4bbeb412df6e'
	),
	'list-reusable-blocks.js' => array(
		'dependencies' => array(
			'react-jsx-runtime',
			'wp-api-fetch',
			'wp-blob',
			'wp-components',
			'wp-compose',
			'wp-element',
			'wp-i18n'
		),
		'version' => '68a57d388ce085b9691e'
	),
	'media-utils.js' => array(
		'dependencies' => array(
			'react',
			'react-dom',
			'react-jsx-runtime',
			'wp-a11y',
			'wp-api-fetch',
			'wp-blob',
			'wp-components',
			'wp-compose',
			'wp-core-data',
			'wp-data',
			'wp-date',
			'wp-deprecated',
			'wp-element',
			'wp-i18n',
			'wp-keycodes',
			'wp-notices',
			'wp-preferences',
			'wp-primitives',
			'wp-private-apis',
			'wp-theme',
			'wp-url',
			'wp-warning'
		),
		'version' => 'b998f434f428e815911e'
	),
	'notices.js' => array(
		'dependencies' => array(
			'react-jsx-runtime',
			'wp-components',
			'wp-data'
		),
		'version' => 'c09a068fdab0eb465e14'
	),
	'nux.js' => array(
		'dependencies' => array(
			'wp-data',
			'wp-deprecated'
		),
		'version' => '1a78c05bba2c02820a7e'
	),
	'patterns.js' => array(
		'dependencies' => array(
			'react',
			'react-dom',
			'react-jsx-runtime',
			'wp-a11y',
			'wp-block-editor',
			'wp-blocks',
			'wp-components',
			'wp-compose',
			'wp-core-data',
			'wp-data',
			'wp-element',
			'wp-html-entities',
			'wp-i18n',
			'wp-notices',
			'wp-primitives',
			'wp-private-apis',
			'wp-theme',
			'wp-url'
		),
		'version' => 'b5c2a31f7639dcc897fd'
	),
	'plugins.js' => array(
		'dependencies' => array(
			'react-jsx-runtime',
			'wp-compose',
			'wp-deprecated',
			'wp-element',
			'wp-hooks',
			'wp-is-shallow-equal',
			'wp-primitives'
		),
		'version' => '448957110ab2e60ed89a'
	),
	'preferences.js' => array(
		'dependencies' => array(
			'react-jsx-runtime',
			'wp-a11y',
			'wp-components',
			'wp-compose',
			'wp-data',
			'wp-deprecated',
			'wp-element',
			'wp-i18n',
			'wp-preferences-persistence',
			'wp-primitives',
			'wp-private-apis'
		),
		'version' => '2d18bacc3bd6b863d4f5'
	),
	'preferences-persistence.js' => array(
		'dependencies' => array(
			'wp-api-fetch'
		),
		'version' => 'a34abbdacd8f50f9acb1'
	),
	'primitives.js' => array(
		'dependencies' => array(
			'react-jsx-runtime',
			'wp-element'
		),
		'version' => '44cc5a35c7b9fe07a838'
	),
	'priority-queue.js' => array(
		'dependencies' => array(
			
		),
		'version' => '6c0aa59b65d55dfd509b'
	),
	'private-apis.js' => array(
		'dependencies' => array(
			
		),
		'version' => '7d845d3b7b2a79df3327'
	),
	'react-i18n.js' => array(
		'dependencies' => array(
			'react-jsx-runtime',
			'wp-element',
			'wp-i18n'
		),
		'version' => 'ba2bd3d7a3817f0494af'
	),
	'redux-routine.js' => array(
		'dependencies' => array(
			
		),
		'version' => 'acca2b4857d83ad1790e'
	),
	'reusable-blocks.js' => array(
		'dependencies' => array(
			'react-jsx-runtime',
			'wp-block-editor',
			'wp-blocks',
			'wp-components',
			'wp-core-data',
			'wp-data',
			'wp-element',
			'wp-i18n',
			'wp-notices',
			'wp-primitives',
			'wp-url'
		),
		'version' => 'bd1fa16781f9c1584c3d'
	),
	'rich-text.js' => array(
		'dependencies' => array(
			'wp-a11y',
			'wp-compose',
			'wp-data',
			'wp-deprecated',
			'wp-dom',
			'wp-element',
			'wp-escape-html',
			'wp-i18n',
			'wp-keycodes',
			'wp-private-apis'
		),
		'version' => '14161c1164dbd81d160f'
	),
	'router.js' => array(
		'dependencies' => array(
			'react-jsx-runtime',
			'wp-compose',
			'wp-element',
			'wp-private-apis',
			'wp-url'
		),
		'version' => '777b3adffc518d882297'
	),
	'server-side-render.js' => array(
		'dependencies' => array(
			'react-jsx-runtime',
			'wp-api-fetch',
			'wp-blocks',
			'wp-components',
			'wp-compose',
			'wp-data',
			'wp-element',
			'wp-i18n',
			'wp-url'
		),
		'version' => 'a9580135d74a3e0e8910'
	),
	'shortcode.js' => array(
		'dependencies' => array(
			
		),
		'version' => 'f6273476300cc5fad4cd'
	),
	'style-engine.js' => array(
		'dependencies' => array(
			
		),
		'version' => '914befb08774033e6265'
	),
	'sync.js' => array(
		'dependencies' => array(
			'wp-api-fetch',
			'wp-hooks',
			'wp-private-apis'
		),
		'version' => '892ba3b8f20f12f008fa'
	),
	'theme.js' => array(
		'dependencies' => array(
			'react-jsx-runtime',
			'wp-compose',
			'wp-deprecated',
			'wp-element',
			'wp-private-apis'
		),
		'version' => '2e249598931c7ea61d0f'
	),
	'token-list.js' => array(
		'dependencies' => array(
			
		),
		'version' => 'e86ab419d8302d57822c'
	),
	'undo-manager.js' => array(
		'dependencies' => array(
			'wp-is-shallow-equal'
		),
		'version' => '4554fce6276d8910a4ae'
	),
	'upload-media.js' => array(
		'dependencies' => array(
			'react-jsx-runtime',
			'wp-blob',
			'wp-compose',
			'wp-data',
			'wp-element',
			'wp-i18n',
			'wp-private-apis',
			'wp-url'
		),
		'module_dependencies' => array(
			array(
				'id' => '@wordpress/video-conversion/worker',
				'import' => 'dynamic'
			),
			array(
				'id' => '@wordpress/vips/worker',
				'import' => 'dynamic'
			)
		),
		'version' => '44d521c55f9b0b89949f'
	),
	'url.js' => array(
		'dependencies' => array(
			
		),
		'version' => '7b0de086d4ae11d55704'
	),
	'viewport.js' => array(
		'dependencies' => array(
			'wp-compose',
			'wp-data',
			'wp-element'
		),
		'version' => 'a56e3489ed4faeac7720'
	),
	'warning.js' => array(
		'dependencies' => array(
			
		),
		'version' => 'a0978839debc564a6608'
	),
	'widgets.js' => array(
		'dependencies' => array(
			'react-jsx-runtime',
			'wp-api-fetch',
			'wp-block-editor',
			'wp-blocks',
			'wp-components',
			'wp-compose',
			'wp-core-data',
			'wp-data',
			'wp-element',
			'wp-i18n',
			'wp-notices',
			'wp-primitives'
		),
		'version' => '7777f38a84e2f48bd936'
	),
	'wordcount.js' => array(
		'dependencies' => array(
			
		),
		'version' => 'f0b1f0e977b2ff6e0132'
	)
);