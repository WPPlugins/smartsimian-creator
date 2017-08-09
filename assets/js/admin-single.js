jQuery( document ).ready( function( $ ) {

	/* =Tabs
	------------------------------------------ */

	// show correct tab based on url hash
	var go = document.location.hash;
	if ( go ) {
		go = go.replace( '#', '' );
		$( '#tab-box-'+go ).show();
		$( '#tab-'+go ).addClass( 'nav-tab-active' );
	} else {
		$( '#tab-box-1' ).show();
		$( '#tab-1' ).addClass( 'nav-tab-active' );
	}

	// on tab click...
	$( '.simian-tab' ).click( function() {

		var tabClicked = $( this );

		if ( tabClicked.hasClass( 'nav-tab-active' ) )
			return false;

		if( $( '.simian-tab' ).length === 1 )
			return false;

		// hide all tabs
		$( '.simian-tab-box:visible' ).hide();

		$( '.simian-tab' ).removeClass( 'nav-tab-active' );
		tabClicked.addClass( 'nav-tab-active' );

		// display relevant tab
		var id = tabClicked.attr( 'id' );
		var num = id.replace( 'tab-', '' );
		$( '#tab-box-'+num ).show();

		// update hash
		window.location.hash = num;

		return false;

	} );

	/* =General Events
	------------------------------------------ */

	// handle hidden div below checkboxes
	$( '.simian-section' ).on( 'click', '.simian-if-checked', function() {
		var box = $( this ).next( '.simian-hidden-box' );
		if ( $( this ).children( 'input' ).is( ':checked' ) ) {
			box.slideDown( 200 );
		} else {
			box.slideUp( 200, simianMaybeClear( box ) );
		}
	} );

	// ...and the opposite
	$( '.simian-section' ).on( 'click', '.simian-if-unchecked', function() {
		var box = $( this ).next( '.simian-hidden-box' );
		if ( $( this ).children( 'input' ).is( ':checked' ) ) {
			box.slideUp( 200, simianMaybeClear( box ) );
		} else {
			box.slideDown( 200 );
		}
	} );

	// handle hidden div below inputs/dropdowns
	$( '.simian-section' ).on( 'change', '.simian-if-changed', function() {
		var box = $( this ).parent().next( '.simian-hidden-box' );
		var selection = box.attr( 'title' );

		// match a given selection
		if ( selection ) {
			if ( selection == $( this ).val() ) {
				box.slideDown( 200 );
			} else {
				box.slideUp( 200, simianMaybeClear( box ) );
			}
			return;
		}

		if ( $( this ).children( 'select, .select2' ).val() === '' || $( this ).val() === null || $( this ).val() === '' ) {
			box.slideUp( 200, simianMaybeClear( box ) );
		} else {
			box.slideDown( 200 );
		}

	} );

	// don't mess with auto-generated fields after editing has been done
	$( '.simian-section' ).on( 'keyup', '.simian-apply-name, .simian-apply-name-plural, .simian-apply-name-sys', function() {
		$( this ).removeClass( 'simian-apply-name simian-apply-name-plural simian-apply-name-sys' );
	} );

	// when setting a label...
	$( '#singular_label' ).change( function() {
		// ...generate system name for item
		simianGenKeyname( $( this ), $( '.simian-apply-name' ), '-' );
	} ).change();

	// same for plural names
	$( '#plural_label' ).change( function() {
		simianGenKeyname( $( this ), $( '.simian-apply-name-plural' ), '-' );
	} ).change();

	// same for sysnames
	$( '#singular_label, #label' ).change( function() {
		simianGenKeyname( $( this ), $( '.simian-apply-name-sys' ), '_' );
	} );

	// Now do the same for the fields ui
	$( '.simian-field-repeater' ).on( 'change', '.simian-field-label', function() {
		var label = $( this );
		var gen = label.closest( '.simian-repeater-toplevel' ).find( '.simian-apply-name' );
		simianGenKeyname( label, gen, '_' );
	} );

	// when form label is empty, hide upper border
	$( '.simian-form-table th label' ).each( function() {
		var label = $( this );
		if ( label.html().trim() === '' ) {
			label.closest( 'tr' ).prev().find( 'th' ).css( 'border-bottom', 'none' );
		}
	} );

	// disable submit onclick
	$( '.simian-admin-form' ).submit( function() {

		var form = $( this );

		// prevent multiple submits
		if ( form.hasClass( 'simian-form-disabled' ) )
			return false;
		form.addClass( 'simian-form-disabled' );

		// modify button display
		var allButtons = $( '.simian-admin-submit input' );
		allButtons.each( function() {
			var button = $( this );
			button.nextAll( '.spinner' ).show();
			button.addClass( 'button-primary-disabled' );
		} );

	} );

	// enable autoresize
	$( '.simian-admin-form textarea' ).autosize();

	// enable tooltips
	$( '.simian-tooltip' ).tooltip();

	// copy dropdown value into custom inputs
	$( '.simian-admin-form' ).on( 'change', '.simian-show-custom-input', function() {
		var dropdown = $( this );
		var val = dropdown.val();
		if ( val )
			val = '[' + val + ']';
		dropdown.next().val( val );
	} );


	/* =Content Type Pages
	------------------------------------------ */

	$( '.simian-section' ).on( 'click', '.menu-icon-inline-dialog-open', function() {
		$( this ).next( '.menu-icon-inline-dialog' ).slideToggle();
	} );

	$( '.simian-section' ).on( 'click', '.menu-icon-inline-dialog a', function( event ) {
		event.preventDefault();
		var value = $( this ).attr( 'title' );
		var id   = $( this ).attr( 'alt' );
		var parent = $( this ).parent();
		var icon  = parent.prevAll( '.dashicons' );
		var hidden = parent.prevAll( 'input[type="hidden"]' );

		// hide icon gallery
		parent.slideUp();

		// set new icon
		icon.hide( 0, function() {
			$( this ).after( '<a href="#" class="dashicons dashicons-' + value + '"></a>' );
			$( this ).remove();
		} );

		hidden.val( id );

	} );


	/* =Field Repeater
	------------------------------------------ */

	// display field type options upon field type selection
	$( '.simian-section' ).on( 'change', '.simian-field-repeater-type select', function() {

		var optionsCol = $( this ).parent().next();
		var type = $( this ).val();
		var name = $( this ).attr( 'name' ).replace( '[type]', '' );

		// slide the options back up
		if ( type === '' ) {
			optionsCol.slideUp( 200,function() {
				$( this ).html( '<div class="simian-faded">Select a field type to view additional options</div>' );
				$( this ).slideDown( 200 );
			} );
			return false;
		}

		var wrapper = $( this ).closest( '.simian-field-repeater' );
		var component = wrapper.attr( 'id' );
		component = component.replace( '-field-repeater', '' );

		// generate args
		var fieldTypeData = {
			component: component,
			action: 'simian_content_field_options',
			type: type,
			name: name
		};

		// send to php
		$.post( ajaxurl, fieldTypeData, function( response ) {

			// overwrite options column
			optionsCol.slideUp( 200,function() {

				// populate options with response
				$( this ).html( response );

				// auto-generate sysname
				var labelField = $( this ).parent().find( '.simian-field-label' );
				var nameField = $( this ).find( '.simian-apply-name' );
				if ( labelField.length && nameField.length )
					simianGenKeyname( labelField, nameField, '_' );

				// toggle showhide options to show
				simianToggleShowHide( $( this ) );

				$( this ).find( '.select2' ).select2();
				$( this ).find( '.simian-tooltip' ).tooltip();
				$( this ).find( 'textarea' ).autosize();

				// everything's ready, display options
				$( this ).slideDown( 200 );

			} );

		} );

	} );

	// display textarea or message upon taxonomy selection
	$( '.simian-field-repeater' ).on( 'change', '.taxonomy-container select', function() {

		var tax    = $( this ).val();
		var container = $( this ).parent();
		var box    = container.next();
		var note   = container.find( '.option-note' );

		if ( !note.length ) {
			$( this ).parent().append( '<div class="option-note"></div>' );
			note = $( this ).parent().find( '.option-note' );
		}

		if ( tax === '_add_new' ) {
			note.slideUp( 200 );
			box.slideDown( 200 );
		} else {
			box.slideUp( 200, function() {
				note.slideUp( 200, function() {
					note.empty();
					// note.html( '<a target="_blank" href="edit-tags.php?taxonomy=' + tax + '">Manage terms here.</a>' );
					note.slideDown( 200 );
				} );
			} );
		}

	} );

	// display or hide available connection types to sync to
	$( '.simian-field-repeater' ).on( 'change', 'select.connect-to, .connect-to-type', function() {

		var val  = $( this ).val();
		var name = $( this ).attr( 'name' ).replace( '[connect_to][]', '' ).replace( '[connect_to_type]', '' );
		var box  = $( this ).closest( '.connect_to_type-container, .simian-hidden-box' ).nextAll( '.connect-to-box' );

		box.slideUp( 200, function() {
			box.empty();

			if ( !val || val === 'content' )
				return;

			// generate args
			var connectToData = {
				action: 'simian_connection_sync',
				name: name,
				connect_to: val
			};

			// send to php
			$.post( ajaxurl, connectToData, function( response ) {
				box.html( response );
				box.slideDown( 200 );
			} );

		} );

	} );

	// display number options when a number data type is selected
	$( '.simian-field-repeater' ).on( 'change', '.data-type-select', function() {

		var val = $( this ).val();
		var numOptions = $( this ).parent().next( '.num-options' );

		if ( val == 'decimal' || val == 'integer' ) {

			if ( numOptions.css( 'display' ) == 'none' )
				numOptions.slideDown( 200 );

		} else {

			if ( numOptions.css( 'display' ) == 'block' )
				numOptions.slideUp( 200 );

		}

	} );

	// toggle label options when a display type is selected
	$( '.simian-field-repeater' ).on( 'change', '.display-type-select', function() {

		var falseLabel = $( this ).parent().next().next().children( 'input' );

		if ( $( this ).val() == 'checkbox' )
			falseLabel.attr( 'disabled', 'disabled' );
		else
			falseLabel.removeAttr( 'disabled' );

	} );

	// toggle Show Options/Hide Options link
	$( '.simian-section' ).on( 'click', '.simian-options-toggle a', function() {
		var showHide = $( this ).parent().next();
		if ( showHide.css( 'display' ) == 'none' ) {
			showHide.slideDown( 200 );
			$( this ).html( 'Hide Options' ).parent().removeClass( 'right' );
		} else {
			showHide.slideUp( 200 );
			$( this ).html( 'Show Options' ).parent().addClass( 'right' );
		}
		return false;
	} );

	// toggle Show/Hide All Options link
	$( '.simian-form-list, .simian-section' ).on( 'click', '.simian-toggle-all-options a', function() {

		var thisLink = $( this );

		// find all options fields with .simian-options-toggle in them
		var allOptions = $( this ).closest( '.simian-repeater-headings' ).next().find( '.simian-repeater-options' );
		allOptions.each( function() {

			var toggleLink = $( this ).find( '.simian-options-toggle a' );
			if ( toggleLink.length ) {

				var showHide = $( this ).find( '.simian-options-showhide' );
				if ( thisLink.hasClass( 'expand' ) ) {
					showHide.slideDown( 200 );
					toggleLink.html( 'Hide Options' ).parent().removeClass( 'right' );
				} else if ( thisLink.hasClass( 'collapse' ) ) {
					showHide.slideUp( 200 );
					toggleLink.html( 'Show Options' ).parent().addClass( 'right' );
				}

			}
		} );

		return false;

	} );

	// handle title/slug template building
	$( '.simian-section' ).on( 'change', '.simian-text-template-dropdown', function() {
		var fieldName = $( this ).val();
		var input = $( this ).next();
		var inputVal = input.val();
		if ( fieldName )
			input.val( inputVal+'%%'+fieldName+'%%' ); // append chosen name to text field
	} );


	/* =Connection Type Pages ( and Elsewhere )
	------------------------------------------ */

	// If "Content" is not selected in object type dropdown, hide Select Content Type(s)
	$( '.simian_content_or_user' ).change( function() {

		var val     = $( this ).val();
		var select2 = $( this ).nextAll( '.select2-container' );
		var select  = $( this ).nextAll( 'select.select2' );
		var narrow  = $( this ).nextAll( '.simian-narrow-further' );
		var queryui = $( this ).nextAll( '.simian_query_ui' );

		queryui.slideUp( function() {
			$( this ).remove();
		} );
		select.val( '' ).change();

		// reset select2 to nothing, show select2 box, hide narrow box
		if ( val === 'content' ) {
			$( this ).parent().find( 'input[type="hidden"].query-ui-object-dropdown' ).remove();
			select2.show();
			narrow.hide();

		// hide select2 box, set select2 to 'user', show narrow box
		} else if ( val === 'user' ) {
			select2.hide();
			narrow.before( '<input class="query-ui-object-dropdown" type="hidden" name="' + select.attr( 'name' ) + '" value="user" />' );
			narrow.show();

		// if no val, hide select2 box, remove hidden field, hide narrow box
		} else {
			select2.hide();
			$( this ).parent().find( 'input[type="hidden"].query-ui-object-dropdown' ).remove();
			narrow.hide();
		}

	} );

	// Connection to/from dropdowns: when anything is selected, roll out additional options
	$( '.query-ui-object-dropdown' ).change( function( event ) {

		var narrower = $( this ).next( '.simian-narrow-further' );
		var repeater = narrower.next( '.simian-repeater' );
		var val      = $( this ).val();

		// change label whenever object changes
		if ( val !== null ) {
			// kill any existing repeater. this allows the query ui to reset,
			// will allow it to respect object changes ( content vs. user )
			repeater.slideUp( 200,function() {
				$( this ).remove();
			} );
		}

		// if switching to object and stuff is hidden, show
		if ( val !== null && narrower.css( 'display' ) === 'none' ) {
			narrower.show();

		// if switching to blank and stuff is shown, hide
		} else if ( !val && narrower.css( 'display' ) !== 'none' ) {
			narrower.hide();
			repeater.slideUp( 200 );
		}

	} );

	// Enable narrowing sliding
	$( '.simian-narrow-further' ).click( function( e ) {
		e.preventDefault();

		var dynamic = 0;
		if ( $( this ).prev( 'select' ).hasClass( 'show-dynamic' ) )
			dynamic = 1;

		// $( this ).unbind( 'click' );

		var repeater = $( this ).next( '.simian-repeater' );

		// if repeater exists and is currently shown
		if ( repeater.length && repeater.css( 'display' ) === 'block' ) {
			repeater.slideUp( 200 );

		// if repeater exists but is hidden
		} else if ( repeater.length && repeater.css( 'display' ) === 'none' ) {
			repeater.slideDown( 200 );

		// if repeater doesn't exist
		} else if ( !repeater.length ) {

			// add repeater container
			var narrowButton = $( this );

			// add placeholder repeater to avoid initial double-click problem
			narrowButton.after( '<div style="display:none;" class="simian-repeater"></div>' );

			// get objects from dropdown selection
			var objects = $( this ).prev( '.query-ui-object-dropdown' ).val();

			// get identifier
			var id = $( this ).attr( 'id' );
			id = id.replace( 'narrow-', '' );

			// generate repeater args
			var repeaterData = {
				action: 'simian_query_ui_repeater',
				objects: objects,
				id: id,
				dynamic: dynamic
			};

			// ajax object. ajaxurl already set. replace repeater inner html with response.
			$.post( ajaxurl, repeaterData, function( response ) {

				// remove temp placeholder ( see double-click problem above )
				narrowButton.next( '.simian-repeater' ).remove();

				// populate repeater
				narrowButton.after( response );
				var repeater = narrowButton.next( '.simian-repeater' );

				// show repeater
				repeater.slideDown( 200 );

				// load repeater functions
				simianRepeaterLoad( repeater );

			} );

		}

		return false;

	} );

} );

// generate system names and slugs from labels
function simianGenKeyname( label, genName, dashOrUnder ) {
	genName.val( function( index,value ) {
		var newVal = label.val();
		newVal = newVal.replace( /[^a-zA-Z 0-9-]+/g,'' ).toLowerCase().replace( /\s/g,dashOrUnder );
		newVal = newVal.replace( '-', '_' );
		newVal = newVal.replace( '__', '_' );
		newVal = newVal.replace( '__', '_' );
		newVal = newVal.replace( '__', '_' );
		return newVal;
	} );
}

// clear values inside a hidden box after it hides
function simianMaybeClear( box ) {
	if ( !box.hasClass( 'simian-clear-on-hide' ) )
		return;
	box.find( 'input, select, textarea' ).val( '' ).change();
}

// on ajax load, toggle the correct options show/hide setting
function simianToggleShowHide( optionsCol ) {
	optionsCol.find( '.simian-options-toggle' ).removeClass( 'right' ).html( '<a href="#">Hide Options</a>' );
	optionsCol.find( '.simian-options-showhide' ).css( 'display', 'block' );
}

// load righthand column after a dropdown selection from lefthand column
// args: dropdown, selectname, actionslug, ajaxurl, placeholder
function simianLoadRightColumn( args ) {

	var getValue = args.dropdown.val();
	var column  = args.dropdown.parent();
	var getItem = column.closest( '.simian-repeater-list' ).prev( 'input[type="hidden"]' ).val(); // hidden input value

	if ( getValue.length ) {

		var getName = args.dropdown.attr( 'name' ).replace( '['+args.selectname+']', '' );

		var ajaxData = {
			action: args.actionslug,
			name:  getName,
			value:  getValue,
			item:  getItem,
			context: args.selectname,
			args:  args.moreargs
		};

		jQuery.post( args.ajaxurl, ajaxData, function( response ) {

			// remove anything to the right
			column.nextAll().slideUp( 200,function() {
				jQuery( this ).remove();
			} );

			// add response
			column.after( response );

			// enable select2 for HTML blocks ( select visibility )
			var genericCol = column.nextAll( '.simian-maybe-select2' );
			genericCol.find( '.select2' ).select2();

			// toggle showhide
			var optionsCol = column.nextAll( '.simian-maybe-showhide-column' );
			simianToggleShowHide( optionsCol );

			// display column
			column.nextAll().delay( 400 ).slideDown();

			// enable any select2s and autosizes
			optionsCol.find( '.select2' ).select2();
			optionsCol.find( 'textarea' ).autosize();

		} );

	} else {

		column.nextAll().slideUp( 400, function() {
			jQuery( this ).remove();
		} );
		column.after( args.placeholder );
		column.nextAll().delay( 400 ).slideDown();

	}

}