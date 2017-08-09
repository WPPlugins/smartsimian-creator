<?php
/**
 * Simian Connection Creator.
 *
 * This is a simple wrapper for p2p_register_connection_type, the backbone of
 * scribu's Posts 2 Posts plugin, which is baked into Simian.
 */
class Simian_Connection {


	/**
	 * Connection type system name.
	 * @var string
	 */
	public $name;


	/**
	 * Connection type arguments.
	 * @var array
	 */
	public $args;


	/**
	 * Constructor. Define properties.
	 */
	public function __construct( $name = '', $args = array() ) {

		$defaults = array(
			'label'       => $name,
			'description' => '',
			'to'          => array(),
			'from'        => array(),
			'sortable'    => 'any',
			'reciprocal'  => true
		);
		$args = wp_parse_args( $args, $defaults );
		$args['label'] = stripslashes( $args['label'] );
		$args['name'] = $name;
		$args['to'] = (array) $args['to'];
		$args['from'] = (array) $args['from'];

		// define properties
		$this->name = $name;
		$this->args = $args;

	}


	/**
	 * Add the hook to the registration function.
	 */
	public function init() {
		add_action( 'p2p_init', array( &$this, 'register' ), 100 );
	}


	/**
	 * Register the connection type with the provided args.
	 */
	public function register() {

		// check if all types exist
		$exist = true;
		foreach( array( 'from', 'to' ) as $side ) {
			foreach( (array) $this->args[$side] as $object ) {
				if ( $object !== 'user' && !get_post_type_object( $object ) )
					$exist = false;
			}
		}

		// 'user' shouldn't be in an array or it'll think it's a post type
		if ( $this->args['to'] === array( 'user' ) )
			$this->args['to'] = 'user';
		if ( $this->args['from'] === array( 'user' ) )
			$this->args['from'] = 'user';

		// remove ability to add posts inline. issues with entering title
		$this->args['admin_box']['can_create_post'] = false;

		// make args filterable
		$this->args = apply_filters( 'simian_p2p_args', $this->args, $this->name );

		// register
		if ( $exist )
			p2p_register_connection_type( $this->args );

	}


}