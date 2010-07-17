
// $Id: admin.js 6 2009-07-08 10:39:01Z cchristensen $

$(document).ready( function( ) {
	$('#player_all').click( function( ) {
		$('.player_box').attr('checked', $(this).attr('checked'));
	});

	$('#game_all').click( function( ) {
		$('.game_box').attr('checked', $(this).attr('checked'));
	});

	$('tbody tr').click( function(event) {
		if ($(event.target).is('input')) {
			return;
		}

		$input = $(this).find('input');

		if ($input.length) {
			$input.attr('checked', ! $input.attr('checked'));
		}
	});

	$('#player_action, #game_action').change( function( ) {
		var val = $(this).find('option:selected').val( );

		if (('delete' == val) && ! confirm('Are you sure?')) {
			$(this)
				.find('option[value=""]')
				.attr('selected', 'selected')
			return false;
		}

		$(this).parents('form').submit( )
	});
});