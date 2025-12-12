<?php

class My_Test_Ability extends WP_Ability {

    protected function do_execute( $input = null ) {
		// Here is some complex logic.
		echo "SUCCESS!!!";
        return array( 'success' => true );
    }
}

add_action( 'wp_abilities_api_init', function() {
    wp_register_ability(
        'test/custom-class-ability',
        array(
            'label'         => 'Test Custom Ability',
            'description'   => 'Test ability with custom class.',
            'category'      => 'site',
            'ability_class' => My_Test_Ability::class,
			'execute_callback' => function() {
				// We have to provide an execute callback that will never be called because of checks in wp_ability.
				return null;
			},
			'permission_callback' => function() {
				return true;
			},
        )
    );
} );

wp_get_ability('test/custom-class-ability')->execute();
