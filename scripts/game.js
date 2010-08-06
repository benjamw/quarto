
// $Id: game.js 21 2009-12-05 06:50:19Z cchristensen $

var reload = true; // do not change this
var timer = false;
var timeout = 2001;

$(document).ready( function( ) {
	// make the board clicks work
	if (my_turn) {
		$('div#board div:not(div:has(img))').click( function(evnt) {
			var $this = $(this);
			$this.addClass('curmove');
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


	// playback buttons
	$('#playback input').click( function( ) {
		// no more board clicks
		$('div#board div:not(div:has(img))').unbind('click');

		var id = $(this).attr('id');

		if ('reset' == id) {
			window.location.reload( );
		}

		switch (id) {
			case 'first' : current_index  = 0; break;
			case 'prev'  : current_index -= 1; break;
			case 'next'  : current_index += 1; break;
			case 'last'  : current_index = move_history.length - 1; break;
		}

		$("div#board").replaceWith(build_board(move_history[current_index]['board'], move_history[current_index]['index']));
		$("div#next").empty( ).append('<'+'p>Next Piece:</'+'p>'+get_piece_image(move_history[current_index]['next_piece']));

		filter_buttons( );
	});
	filter_buttons( );


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
		if ('' == $.trim($('#chatbox input#chat').val( ))) {
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
					$('#chatbox input#chat').val('');
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


function filter_buttons( ) {
	// show all buttons
	$('#playback input').attr('disabled', false);

	if (0 == current_index) {
		$('#playback input#first').attr('disabled', true);
		$('#playback input#prev').attr('disabled', true);
	}
	else if ((move_history.length - 1) == current_index) {
		$('#playback input#next').attr('disabled', true);
		$('#playback input#last').attr('disabled', true);
	}
}


function build_board(board, highlight) {
	if (undefined == typeof highlight) {
		highlight = false;
	}

	var html = '<'+'div id="board">'
		+ '<'+'div class="corner"></'+'div>'
		+ '<'+'div class="top">A</'+'div>'
		+ '<'+'div class="top">B</'+'div>'
		+ '<'+'div class="top">C</'+'div>'
		+ '<'+'div class="top">D</'+'div>';

	// generate the board
	for (var i = 0; i < 16; ++i) {
		if (0 == (i % 4)) {
			html += '<'+'div class="side">'+(Math.floor(i / 4) + 1)+'</'+'div>';
		}

		var cls = '';
		if ((false != highlight) && (highlight == i)) {
			cls = ' class="prevmove"';
		}

		html += '<'+'div id="sq_'+i+'"'+cls+'>';

		if ('.' !== board.charAt(i)) {
			html += get_piece_image(board.charAt(i));
		}

		html += '</'+'div>';
	}

	html += '</'+'div> <!-- #board -->';

	return html;
}


function get_piece_image(piece) {
	var pt = [ // pieces text
		['black', 'white'],
		['short', 'tall'],
		['hollow', 'solid'],
		['round', 'square'],
	];

	var p = str_pad(decbin(hexdec(piece)), 4, '0', 'STR_PAD_LEFT');

	return '<'+'img id="p_'+piece+'" src="images/'+pt[0][p.charAt(0)]+'-'+pt[1][p.charAt(1)]+'-'+pt[2][p.charAt(2)]+'-'+pt[3][p.charAt(3)]+'.png"'
		+ ' alt="'+pt[0][p.charAt(0)]+' '+pt[1][p.charAt(1)]+' '+pt[2][p.charAt(2)]+' '+pt[3][p.charAt(3)]+'"'
		+ ' class="piece '+pt[0][p.charAt(0)]+' '+pt[1][p.charAt(1)]+' '+pt[2][p.charAt(2)]+' '+pt[3][p.charAt(3)]+'" />';
}


// http://phpjs.org/functions/str_pad:525
function str_pad (input, pad_length, pad_string, pad_type) {
    // Returns input string padded on the left or right to specified length with pad_string
    //
    // version: 1006.1915
    // discuss at: http://phpjs.org/functions/str_pad
    // +   original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // + namespaced by: Michael White (http://getsprink.com)
    // +      input by: Marco van Oort
    // +   bugfixed by: Brett Zamir (http://brett-zamir.me)
    // *     example 1: str_pad('Kevin van Zonneveld', 30, '-=', 'STR_PAD_LEFT');
    // *     returns 1: '-=-=-=-=-=-Kevin van Zonneveld'
    // *     example 2: str_pad('Kevin van Zonneveld', 30, '-', 'STR_PAD_BOTH');
    // *     returns 2: '------Kevin van Zonneveld-----'
    var half = '', pad_to_go;

    var str_pad_repeater = function (s, len) {
        var collect = '', i;

        while (collect.length < len) {collect += s;}
        collect = collect.substr(0,len);

        return collect;
    };

    input += '';
    pad_string = pad_string !== undefined ? pad_string : ' ';

    if (pad_type != 'STR_PAD_LEFT' && pad_type != 'STR_PAD_RIGHT' && pad_type != 'STR_PAD_BOTH') { pad_type = 'STR_PAD_RIGHT'; }
    if ((pad_to_go = pad_length - input.length) > 0) {
        if (pad_type == 'STR_PAD_LEFT') { input = str_pad_repeater(pad_string, pad_to_go) + input; }
        else if (pad_type == 'STR_PAD_RIGHT') { input = input + str_pad_repeater(pad_string, pad_to_go); }
        else if (pad_type == 'STR_PAD_BOTH') {
            half = str_pad_repeater(pad_string, Math.ceil(pad_to_go/2));
            input = half + input + half;
            input = input.substr(0, pad_length);
        }
    }

    return input;
}


// http://phpjs.org/functions/decbin
function decbin (number) {
    // Returns a string containing a binary representation of the number
    //
    // version: 1006.1915
    // discuss at: http://phpjs.org/functions/decbin
    // +   original by: Enrique Gonzalez
    // +   bugfixed by: Onno Marsman
    // +   improved by: http://stackoverflow.com/questions/57803/how-to-convert-decimal-to-hex-in-javascript
    // +   input by: pilus
    // +   input by: nord_ua
    // *     example 1: decbin(12);
    // *     returns 1: '1100'
    // *     example 2: decbin(26);
    // *     returns 2: '11010'
    // *     example 3: decbin('26');
    // *     returns 3: '11010'
    if (number < 0) {
        number = 0xFFFFFFFF + number + 1;
    }
    return parseInt(number, 10).toString(2);
}


// http://phpjs.org/functions/hexdec
function hexdec (hex_string) {
    // Returns the decimal equivalent of the hexadecimal number
    //
    // version: 1006.1915
    // discuss at: http://phpjs.org/functions/hexdec
    // +   original by: Philippe Baumann
    // *     example 1: hexdec('that');
    // *     returns 1: 10
    // *     example 2: hexdec('a0');
    // *     returns 2: 160

    hex_string = (hex_string+'').replace(/[^a-f0-9]/gi, '');
    return parseInt(hex_string, 16);
}

