jQuery(document).ready(function($){

	// toggle display defaults onchecked
	$('.simian_label').click(function() {
		if ( $(this).children('input').is(':checked') ) {
			$(this).parent().parent().children().children('.simian_additional_args').slideDown();
		} else {
			$(this).parent().parent().children().children('.simian_additional_args').slideUp();
		}
	});

	$('.wrap form > table').addClass( 'widefat' ).removeClass( 'form-table' );

});