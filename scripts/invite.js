
var reload = true; // do not change this

// no doc ready, it's loaded at the bottom of the page

// this runs both the invites and outvites buttons
$('div#invites input').click( function( ) {
	var id = $(this).attr('id').split('-');

	if ('accept' == id[0]) { // invites only
		// send them off to the game
		window.location = 'game.php?accept=1&id='+id[1]+debug_query_;
		return;
	}
	else { // decline invites and withdraw outvites
		// delete the game
		if (debug) {
			window.location = 'ajax_helper.php'+debug_query+'&'+'action=delete&game_id='+id[1];
			return;
		}

		$.ajax({
			type: 'POST',
			url: 'ajax_helper.php',
			data: 'action=delete&game_id='+id[1],
			success: function(msg) {
				alert(msg);
				if (reload) { window.location.reload( ); }

				return;
			}
		});
	}
});

choose_piece($('#piece').val( ));

function choose_piece(id) {
	$('div#piece_selection img')
		.css('background-color', 'transparent')
		.filter('img#p_'+id).css('background-color', '#F00');
	$('#piece').val(id);
}