jQuery( document ).ready( function( $ ){

	// turn on any prepopulated ajax select2s
	select2_ajax_searches( $( '.simian-section' ) );

	// disable appropriate select options whenever someone selects a rule dropdown
	$( '.simian-section' ).on( 'mousedown', '.simian-narrow-by', function(){

		var thisRule = this;

		// it's okay if these rules are repeated
		var canRepeat = ['meta_query','tax_query'];

		// get existing rules
		var otherRules = $( this ).closest( 'ul' ).find( '.simian-narrow-by' );
		var existingRules = [];
		otherRules.each( function(){
			// if value shouldn't be repeated, add to existingRules array
			var otherValue = $( this ).val();
			if ( otherValue.length && canRepeat.indexOf( otherValue ) === -1 && thisRule !== this )
				existingRules.push( otherValue );
		} );

		// loop each select option
		$( thisRule ).children().each( function(){

			// disable option if in existingRules
			if ( existingRules.indexOf( $( this ).attr( 'value' ) ) !== -1 )
				$( this ).attr( 'disabled','disabled' );
			// at the same time, enable any previously disabled if they shouldn't be disabled
			else
				$( this ).removeAttr( 'disabled' );

		} );

	} );

	// when someone selects from the rule dropdown
	$( '.simian-section' ).on( 'change', '.simian-narrow-by', function(){
		simian_query_ui_reveal( $( this ),'simian' );
	} );

	// select2 ajax for taxonomies
	$( '.simian-section' ).on( 'change', '.taxonomy-select', function(){
		simian_query_ui_reveal( $( this ),'taxonomy' );
	} );

	// select2 ajax for connections
	$( '.simian-section' ).on( 'change', '.simian-rule .ctype', function(){
		simian_query_ui_reveal( $( this ),'connection' );
	} );

	// select2 ajax for taxonomies
	//$( '.simian-section' ).on( 'change', '.ctype-select', function(){} );

	// specific/dynamic radio button options
	/* $( '.simian-section' ).on( 'change', '.specific-dynamic input', function() {
		var val = $( this ).val();
		var dropdown = $( this ).parent().parent().find( '.select2-container' );
		if ( val === 'dynamic' )
			dropdown.hide();
		else
			dropdown.show();
	} ); */

} );


/**
 * Generic ajax reveal function. Normally triggers when a rule is selected from
 * the Query UI rules dropdown.
 */
function simian_query_ui_reveal( selector, context ) {

	// get object(s) based on overall dropdown selection - 'user' or a post type
	var objects = selector.closest( '.simian-repeater' ).prevAll( '.query-ui-object-dropdown' ).val();

	// remove everything currently to the right of the dropdown
	selector.next( '.'+context+'-rule' ).remove();

	var dynamic = 0;
	if ( context === 'taxonomy' ) {
		if ( selector.parent().prev().hasClass( 'show-dynamic' ) )
			dynamic = 1;
	} else if ( context === 'connection' ) {
		if ( selector.parent().prev().hasClass( 'show-dynamic' ) )
			dynamic = 1;
	} else {
		if ( selector.hasClass( 'show-dynamic' ) )
			dynamic = 1;
	}

	// get dropdown value
	var rule = selector.val();

	// if no rule is selected, we're done
	if ( rule === '' )
		return false;

	// clean up args for taxonomy
	var innerObj = '';
	if ( context === 'taxonomy' ) {
		innerObj = rule;
		rule = 'tax_query';
	}

	if ( context === 'connection' ) {
		innerObj = rule;
		rule = 'connections';
	}

	// add extra rules container
	selector.after( '<div class="'+context+'-rule"></div>' );
	var container = selector.next();

	// get id, remove everything at first bracket
	var id = selector.attr( 'name' );
	id = id.replace( /\[.*/,'' );

	// extract counter from initial dropdown
	var getCounter = selector.attr( 'name' ); // ex. from[narrow][2][rule]
	var matches = getCounter.match( /\[([0-9]+)\]/ );
	counter = matches[1];

	// generate ajax args
	var ruleData = {
		action: 'simian_query_ui_inner',
		rule: rule,
		id: id,
		objects: objects,
		counter: counter,
		func: rule+'_rule',
		dynamic: dynamic
	};

	if ( context === 'taxonomy' ) {
		ruleData['inner_obj'] = innerObj;
		ruleData['func'] = 'tax_query_rule_inner';
	}

	if ( context === 'connection' ) {
		ruleData['inner_obj'] = innerObj;
		ruleData['func'] = 'connections_rule_inner';
	}

	// send rule to php. receive inner container html.
	jQuery.post( ajaxurl, ruleData, function( response ){

		// populate repeater
		container.html( response );

		// turn on any select2s
		container.find( '.select2' ).select2();

		// load any ajaxified searches that need to be shown immediately
		// if ( context === 'simian' )
		select2_ajax_searches( container );

	} );

}


/**
 * Generic select2 ajax search, used to search users and content.
 */
function select2_ajax_searches( container ){

	// find all select2s that need ajaxifying
	container.find( '.select2-ajax-search' ).each( function(){

		var types = jQuery( this ).attr( 'title' ).split( ',' );
		var object = 'content';
		var multi = false;
		var placeholder = 'Search all posts';

		// determine object and placeholder text
		if ( jQuery( this ).hasClass( 'select2-ajax-user' ) ) {
			object = 'user';
			placeholder = 'Search by username or email';
		}

		// determine multi or single select
		if ( jQuery( this ).hasClass( 'multi-select' ) ){
			multi = true;
		}

		// initiate the select2
		jQuery( this ).select2( {
			placeholder: placeholder,
			minimumInputLength: 1,
			quietMillis: 100,
			multiple: multi,
			initSelection : function ( element, callback ) { // get display data for existing values
				var data = [];

				// grab all item names and store in array
				var names = element.prevAll( '.'+object+'-labels' ).val().split( ',' );

				// match list of ids to item names
				jQuery( element.val().split( ',' ) ).each( function( index ) {
					data.push( {id: this, item_label: names[index]} );
				} );

				if ( !multi )
					data = data.shift();

				// return
				callback( data );
			},
			ajax: {
				url: ajaxurl,
				dataType: 'json',
				data: function( term,page ){
					return {
						action: 'simian_select2_search',
						q: term,
						count: 10,
						page_number: page,
						object: object,
						types: types
					};
				},
				results: function( data,page ){
					var is_more = ( page*10 ) < data.total;
					return {
						results: data.results,
						more: is_more
					};
				}
			},
			formatResult: function( item ){
				return item.item_label;
			},
			formatSelection: function( item,container ){
				return item.item_label;
			}
		} );

	} );

}