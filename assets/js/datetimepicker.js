jQuery(document).ready(function($){

	// save in MySQL datetime formats
	$( '.simian-datepicker' ).datepicker({
		dateFormat: 'yy-mm-dd'
	});

	$( '.simian-datetimepicker' ).datetimepicker({
		ampm: true,
		dateFormat: 'yy-mm-dd',
		timeFormat: 'hh:mm tt',
		altTimeFormat: 'HH:mm:ss',
		showSecond: false
	});

	$( '.simian-timepicker' ).timepicker({
		ampm: true,
		timeFormat: 'hh:mm tt',
		altTimeFormat: 'HH:mm:ss',
		showSecond: false
	});

	// maintain scope
	$( '#ui-datepicker-div' ).wrap( '<div class="simian" />' );

});