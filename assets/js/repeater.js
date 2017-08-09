jQuery(document).ready(function($){

	// enable any repeaters already loaded
	$('.simian-repeater').each(function(index){
		simianRepeaterLoad($(this));
	});

	// add delete event
	$('.simian-section').on('click', '.simian-repeater-delete', function(){

		// reset row numbers of form elements in all the *other* rows
		var rows = $(this).parent().siblings();
		simianResetRows( rows );

		// delete selected row
		$(this).parent().css('background','#e22').slideUp('slow', function() {
			$(this).remove();
		});


	});

});

function simianRepeaterLoad(repeater) {

	// get default first row
	var defaultRow = repeater.find('.simian-repeater-list > li:first-child').html();

	// enable sorting for this repeater
	repeater.find('.simian-sortable').sortable({
		placeholder:'simian-repeater-highlight',
		handle:'.simian-repeater-drag',
		revert: 200,
		update: function(event,ui){
			var rows = jQuery(this).children('li');
			simianResetRows( rows );
		}
	});

	// enable add new button for this repeater
	repeater.on('click', '.simian-repeater-add-new', function(){

		// get list
		var ulList = jQuery(this).parent().prev();

		// remove any leftover selection or value
		defaultRow = defaultRow.replace('selected="selected"', '');

		// get amount of existing rows
		var newRowCount = ulList.children('li').length;

		// replace default row with new number 0-99
		var newRow = defaultRow.replace(/\[([0-9]|[1-9][0-9])\]/g, '['+newRowCount+']');
		newRow = newRow.replace(/_([0-9]|[1-9][0-9])_/g, '_'+newRowCount+'_');

		// add row to list
		ulList.append('<li class="simian-repeater-row" style="display:none;">'+newRow+'</li>');

		var editNewRow = ulList.children('li').filter(':last');

		// remove any extra rules from row (connection type)
		editNewRow.find('.simian-rule').remove();

		// remove any inner container
		editNewRow.find('.simian-hidden-inner-container').remove();

		// remove any options from row (content type)
		editNewRow.find('.simian-field-repeater-options').html('<div class="simian-faded">Select a field type to view additional options</div>');

		// don't disable types or sysnames for new row (submissions)
		editNewRow.find('.simian-field-repeater-type select, .simian-field-repeater .simian-sysname').removeAttr('disabled');

		// remove all but the block type (template creator)
		editNewRow.find('.simian-template-repeater-options').remove();
		var msg = editNewRow.find('.simian-faded');
		if ( !msg.length )
			editNewRow.find('.simian-template-repeater-display').after('<div class="simian-template-repeater-col simian-template-repeater-options"><div class="simian-faded">Make a selection to view more options.</div></div>');

		// remove any values
		editNewRow.find('input').val('');

		// animate row
		ulList.children('li').filter(':last').slideDown();

	});

}

function simianResetRows( rows ) {

	// loop each row
	rows.each(function(index,value){

		// find all form elements in the row, replace [#] with [currentindex]
		jQuery(this).find('select,input,textarea').attr('name',function(idx,attr){
			// console.log(this);
			var name = jQuery(this).attr('name');
			if ( name )
				return jQuery(this).attr('name').replace(/\[([0-9]|[1-9][0-9])\]/, '['+index+']');
			else
				return '';
		});

		// find all form elements in the row, replace _#_ with _currentindex_
		jQuery(this).find('select,input,textarea').attr('id',function(idx,attr){
			// console.log(this);
			var name = jQuery(this).attr('id');
			if ( name )
				return jQuery(this).attr('id').replace(/_([0-9]|[1-9][0-9])_/, '_'+index+'_');
			else
				return '';
		});

		// find all form elements in the row, replace _#_ with _currentindex_
		jQuery(this).find('label').attr('for',function(idx,attr){
			// console.log(this);
			var name = jQuery(this).attr('for');
			if ( name )
				return jQuery(this).attr('for').replace(/_([0-9]|[1-9][0-9])_/, '_'+index+'_');
			else
				return '';
		});

	});

}