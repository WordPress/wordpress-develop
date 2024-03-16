<?php
function turn_into_multisite()
{
	if (current_user_can('setup_network')) :
?>
		<div class="card">
			<h2 class="title"><?php _e('Multisite Converter'); ?></h2>
			<p>
				<?php
				printf(
					__('Turn this Site into a Multisite'),
					'turn-into-multisite.php'
				);
				?>
			</p>
		</div>
<?php
	endif;
}
add_action('tool_box', 'turn_into_multisite');
