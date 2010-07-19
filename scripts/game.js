
// $Id: game.js 21 2009-12-05 06:50:19Z cchristensen $

var reload = true; // do not change this
var timer = false;
var timeout = 2001;

$(document).ready( function( ) {
	// make the board clicks work
	if (my_turn) {
		$('div#board div:not(div:has(img))').click( function(evnt) {
			var $this = $(this);
			var id = $this.attr('id').slice(3).toUpperCase( );

			$('#move').val(id);

			// ajax off and see if we've won
			$.ajax({
				type: 'POST',
				url: 'ajax_helper.php',
				data: $('form#game').serialize( )+'&notoken=1',
				success: function(msg) {
					// if something happened, just reload
					if ('{' != msg[0]) {
						alert('ERROR: AJAX failed');
					}

					var reply = JSON.parse(msg);

					if (reply.error) {
						alert(reply.error);
					}

					if ((reply.error || ('RELOAD' == reply.action)) && reload) { window.location.reload( ); }
					return;
				}
			});

			// remove the click on the board
			$('div#board div').unbind('click').css('cursor', 'default');

			// update the next piece selector
			$('div#next')
				.append('<p><strong>SELECT A PIECE BELOW FOR YOUR OPPONENT TO PLAY</strong></p>')
				.find('img').appendTo($this)

			$('div#pieces img').click( function(evnt) {
				var $this = $(this);
				var id = $this.attr('id').slice(2).toUpperCase( );

				$('#piece').val(id);

				// run the move
				if (debug) {
					window.location = 'ajax_helper.php'+debug_query+'&'+$('form#game').serialize( );
					return;
				}

				// make the piece selection work
				$.ajax({
					type: 'POST',
					url: 'ajax_helper.php',
					data: $('form#game').serialize( ),
					success: function(msg) {
						// if something happened, just reload
						if ('{' != msg[0]) {
							alert('ERROR: AJAX failed');
						}

						var reply = JSON.parse(msg);

						if (reply.error) {
							alert(reply.error);
						}

						if (reload) { window.location.reload( ); }
						return;
					}
				});
			}).css('cursor', 'pointer');
		}).css('cursor', 'pointer');
	}


	// nudge button
	$('#nudge').click( function( ) {
		if (confirm('Are you sure you wish to nudge this person?')) {
			if (debug) {
				window.location = 'ajax_helper.php'+debug_query+'&'+$('form#game').serialize( )+'&nudge=1';
				return;
			}

			$.ajax({
				type: 'POST',
				url: 'ajax_helper.php',
				data: $('form#game').serialize( )+'&nudge=1',
				success: function(msg) {
					var reply = JSON.parse(msg);

					if (reply.error) {
						alert(reply.error);
					}
					else {
						alert('Nudge Sent');
					}

					if (reload) { window.location.reload( ); }
				}
			});
		}

		return false;
	});


	// resign button
	$('#resign').click( function( ) {
		if (confirm('Are you sure you wish to resign the game?')) {
			if (debug) {
				window.location = 'ajax_helper.php'+debug_query+'&'+$('form#game').serialize( )+'&resign=1';
				return;
			}

			$.ajax({
				type: 'POST',
				url: 'ajax_helper.php',
				data: $('form#game').serialize( )+'&resign=1',
				success: function(msg) {
					var reply = JSON.parse(msg);

					if (reply.error) {
						alert(reply.error);
					}

					if (reload) { window.location.reload( ); }
				}
			});
		}

		return false;
	});


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
					var entry = '<dt><span>'+reply.create_date+'</span> '+reply.username+'</dt>'+
						'<dd'+(('1' == reply.private) ? ' class="private"' : '')+'>'+reply.message+'</dd>';

					$('#chats').prepend(entry);
					$('#chatbox input').val('');
				}
			}
		});

		return false;
	});


	// run the ajax refresher
	if ( ! my_turn && ('playing' == state)) {
		ajax_refresh( );

		// set some things that will halt the timer
		$('#chatbox form input').focus( function( ) {
			clearTimeout(timer);
		});

		$('#chatbox form input').blur( function( ) {
			if ('' != $(this).val( )) {
				timer = setTimeout('ajax_refresh( )', timeout);
			}
		});
	}
});


function ajax_refresh( ) {
	// no debug redirect, just do it

	$.ajax({
		type: 'POST',
		url: 'ajax_helper.php',
		data: 'timer=1',
		success: function(msg) {
			if ((msg != last_move) && reload) {
				window.location.reload( );
			}
		}
	});

	// successively increase the timeout time in case someone
	// leaves their window open, don't poll the server every
	// two seconds for the rest of time
	if (0 == (timeout % 5)) {
		timeout += Math.floor(timeout * 0.001) * 1000;
	}

	++timeout;

	timer = setTimeout('ajax_refresh( )', timeout);
}

