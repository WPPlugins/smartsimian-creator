jQuery(document).ready(function($){

	/**
	 * Show "are you sure" prompt when attempting to delete an item.
	 */
	$( '.simian-delete-item, .simian_name .delete a' ).click( function() {
		return confirm( 'Are you sure you want to delete this item?' );
	} );

	$( '.simian-list-page #doaction' ).click( function() {
		if ( $( this ).prev().val() === 'delete' )
			return confirm( 'Are you sure you want to delete the selected items?' );
	} );

} );