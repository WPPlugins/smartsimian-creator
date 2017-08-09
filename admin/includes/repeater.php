<?php
/**
 * Utility for creating UIs with repeatable/deletable fields. Used in the core
 * plugin with the Field UI and Query UI.
 */
class Simian_Repeater {


	/**
	 * Basic array of repeater args.
	 */
	public $args;


	/**
	 * Constructor. Save the args.
	 */
	public function __construct( $args ) {
		$this->args = wp_parse_args( $args, array(
			'id'         => '',		    // unique slug for this repeater
			'item'       => '',         // current item
			'sort'       => true,	    // allow sorting
			'display'    => true,	    // display an empty row if no values are passed
			'add_class'  => '',         // classes for Add button
			'add_label'  => 'Add Rule', // text for Add button
			'callback'   => '',         // callback called in build_row()
			'extra_args' => array()     // additional args to pass to the callback
		) );
	}


	/**
	 * Output existing rows or a default row.
	 *
	 * @param $values 		any existing rows that should be shown.
	 * @param $multiples 	array of row types that are allowed to be listed multiple times in the repeater
	 */
	public function html( $values = array(), $multiples = array() ) {

		extract( $this->args );

		// used passed item, or fall back to current item being edited
		if ( !$item ) {
			$item = isset( $_GET['item'] ) ? sanitize_key( $_GET['item'] ) : '';
		}

		// attempt to get PHP class of callback function (ex. Simian_Field_UI)
		$tool = isset( $callback[0] ) ? $callback[0] : '';
		if ( is_object( $tool ) )
			$tool = get_class( $tool );

		// start row count
		$count = 0;

		// output repeater container
		?><div id="<?php echo $id ?>" class="simian-repeater<?php echo $tool ? ' ' . sanitize_title( $tool ) : ''; ?>"<?php echo ( $values || $display ) ? '' : ' style="display:none;"'; ?>>
			<input type="hidden" name="item" value="<?php echo $item ? $item : ''; ?>" />
			<ul class="simian-repeater-list<?php echo $sort ? ' simian-sortable' : ''; ?>"><?php

				if ( $values ) {

					// add row for each $values element
					foreach( $values as $name => $value ) {

						// if flagged as multiple, build new row for each $value element
						if ( in_array( $name, $multiples ) ) {

							foreach( $value as $sub_value ) {
								$this->build_row( $name, $sub_value, $count );
								$count++;
							}

						// other row types will be limited to one row apiece
						} else {
							$this->build_row( $name, $value, $count );
							$count++;
						}

					}

				} else {
					$this->build_row();
				}

			?></ul>
			<div class="simian-repeater-add">
				<span class="simian-repeater-add-new <?php echo $add_class; ?>"><?php echo $add_label; ?></span>
			</div>
		</div><!-- .simian-repeater --><?php

	}


	/**
	 * Build out an HTML row.
	 */
	public function build_row( $name = '', $value = '', $count = 0 ) {

		extract( $this->args );

		?><li class="simian-repeater-row">
			<?php if ( $sort ) { ?>
			<span class="simian-repeater-drag simian-repeater-toplevel"></span>
			<?php } ?>
			<div class="simian-repeater-toplevel"><?php
				if ( is_callable( $callback ) )
					call_user_func( $callback, $id, $item, $name, $value, $count, $extra_args );
			?></div>
			<span class="simian-repeater-delete simian-repeater-toplevel"></span>
			<div class="simian-clear"></div>
		</li><?php

	}


}