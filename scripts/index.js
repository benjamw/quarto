
// index javascript

// $Id: index.js 21 2009-12-05 06:50:19Z cchristensen $

$(document).ready( function( ) {
	// make the table row clicks work
	$('.datatable tbody tr').css('cursor', 'pointer').click( function( ) {
		var id = $(this).attr('id').substr(1);
		window.location = 'game.php?id='+id+debug_query_;
	});

	// blinky menu items
	$('.blink').fadeOut( ).fadeIn( ).fadeOut( ).fadeIn( ).fadeOut( ).fadeIn( );
//	var cur_background = $('.blink').css('backgroundColor');
//	var high_color = $('.active a').css('backgroundColor');
//	$('.blink')
//		.animate({ backgroundColor: high_color }, 400).animate({ backgroundColor: cur_background }, 400)
//		.animate({ backgroundColor: high_color }, 400).animate({ backgroundColor: cur_background }, 400)
//		.animate({ backgroundColor: high_color }, 400).animate({ backgroundColor: cur_background }, 400);

	// chat box functions
	$('#chatbox form').submit( function( ) {
		if ('' == $.trim($('#chatbox input').val( ))) {
			return false;
		}

		if (debug) {
			window.location = 'ajax_helper.php'+debug_query+'&'+$('#chatbox form').serialize( );
			return false;
		}

		$.ajax({
			type: 'POST',
			url: 'ajax_helper.php',
			data: $('#chatbox form').serialize( ),
			success: function(msg) {
				var reply = JSON.parse(msg);

				if (reply.error) {
					alert(reply.error);
				}
				else {
					var entry = '<dt>'+reply.username+'</dt>'+
						'<dd>'+reply.message+'</dd>';
					$('#chats').prepend(entry);
					$('#chatbox input').val('');
				}
			}
		});

		return false;
	});
});