<?php

require_once 'includes/inc.global.php';

// grab the game id
if (isset($_GET['id'])) {
	$_SESSION['game_id'] = (int) $_GET['id'];
}
elseif ( ! isset($_SESSION['game_id'])) {
	if ( ! defined('DEBUG') || ! DEBUG) {
		Flash::store('No Game Id Given !');
	}
	else {
		call('NO GAME ID GIVEN');
	}

	exit;
}

// ALL GAME FORM SUBMISSIONS ARE AJAXED THROUGH /scripts/game.js

// load the game
// always refresh the game data, there may be more than one person online
try {
	$Game = new Game((int) $_SESSION['game_id']);

	if ( ! empty($_GET['accept'])) {
		$Game->accept($_SESSION['player_id']);
	}
}
catch (MyException $e) {
	if ( ! defined('DEBUG') || ! DEBUG) {
		Flash::store('Error Accessing Game !');
	}
	else {
		call('ERROR ACCESSING GAME :'.$e->outputMessage( ));
	}

	exit;
}

$players = $Game->get_players( );
$Chat = new Chat($_SESSION['player_id'], $_SESSION['game_id']);
$chat_data = $Chat->get_box_list( );

$chat_html = '
		<div id="chatbox">
			<form action="'.$_SERVER['REQUEST_URI'].'" method="post"><div>
				<input id="chat" type="text" name="chat" />
				<label for="private" class="inline"><input type="checkbox" name="private" id="private" value="yes" /> Private</label>
			</div></form>
			<dl id="chats">';

if (is_array($chat_data)) {
	foreach ($chat_data as $chat) {
		if ('' == $chat['username']) {
			$chat['username'] = '[deleted]';
		}

		$color = '';
		if (isset($players[$chat['player_id']]['color'])) {
			$color = substr($players[$chat['player_id']]['color'], 0, 3);
		}

		// preserve spaces in the chat text
		$chat['message'] = htmlentities($chat['message'], ENT_QUOTES, 'ISO-8859-1', false);
		$chat['message'] = str_replace("\t", '    ', $chat['message']);
		$chat['message'] = str_replace('  ', ' &nbsp;', $chat['message']);

		$chat_html .= '
				<dt class="'.$color.'"><span>'.$chat['create_date'].'</span> '.$chat['username'].'</dt>
				<dd'.($chat['private'] ? ' class="private"' : '').'>'.$chat['message'].'</dd>';
	}
}

$chat_html .= '
			</dl> <!-- #chats -->
		</div> <!-- #chatbox -->';

// hide the chat from non-players
if (('Finished' == $Game->state) && ! $Game->is_player($_SESSION['player_id'])) {
	$chat_html = '';
}

$win_text = '';

$turn = ($Game->get_my_turn( )) ? 'Your Turn' : $Game->name.'\'s Turn';
$no_turn = false;

if ($Game->paused) {
	$turn = 'PAUSED';
	$no_turn = true;
}
elseif ('Finished' == $Game->state) {
	$turn = 'GAME OVER';
	$no_turn = true;
	list($win_text, $outcome, $outcome_data) = $Game->get_outcome($_SESSION['player_id']);
}

$info_bar = '<span class="turn">'.$turn.'</span>';

$matching_methods = $Game->get_matching_methods( );
if ($matching_methods) {
	$info_bar .= ' <span class="matches">'.implode(' | ', $matching_methods).'</span>';
}

$meta['title'] = $turn.' - '.$Game->name.' (#'.$_SESSION['game_id'].')';
$meta['show_menu'] = false;
$meta['head_data'] = '
	<link rel="stylesheet" type="text/css" media="screen" href="css/game.css" />

	<script type="text/javascript">/*<![CDATA[*/
		var state = "'.(( ! $Game->paused) ? strtolower($Game->state) : 'paused').'";
		var last_move = '.$Game->last_move.';
		var my_turn = '.(( ! $Game->get_my_turn( ) || $no_turn) ? 'false' : 'true').';
		var move_history = '.json_encode($Game->get_history( )).';
		var current_index = move_history.length - 1;
	//]]></script>
';

$meta['foot_data'] = '
	<script type="text/javascript" src="scripts/game.js"></script>
';

echo get_header($meta);

?>

		<div id="contents">
			<ul id="buttons">
				<li><a href="index.php<?php echo $GLOBALS['_?_DEBUG_QUERY']; ?>">Main Page</a></li>
				<li><a href="game.php<?php echo $GLOBALS['_?_DEBUG_QUERY']; ?>">Reload Game Board</a></li>
			</ul>
			<h2>Game #<?php echo $_SESSION['game_id'].' vs '.htmlentities($Game->name, ENT_QUOTES, 'ISO-8859-1', false); ?> <?php echo $info_bar; ?></h2>

			<?php if ('' != $win_text) { ?>
			<div class="msg <?php echo $outcome; ?>"><?php echo $win_text; ?></div>
			<?php } ?>

			<div id="board">
				<div class="corner"></div>
				<div class="top">A</div>
				<div class="top">B</div>
				<div class="top">C</div>
				<div class="top">D</div>

				<?php
					$board = $Game->board;
					$prev_move = $Game->get_previous_move( );

					// generate the board
					for ($i = 0; $i < 16; ++$i) {
						if (0 == ($i % 4)) {
							echo '
							<div class="side">'.(floor($i / 4) + 1).'</div>';
						}

						$class = '';
						if ($i == $prev_move) {
							$class = ' class="prevmove"';
						}

						echo '
						<div id="sq_'.$i.'"'.$class.'>';

						if ('.' !== $board[$i]) {
							echo get_piece_image($board[$i]);
						}

						echo '</div>';
					}
				?>

			</div> <!-- #board -->

			<div id="info">

				<div id="next">

					<?php if ('Playing' == $Game->state) { ?>

					<p>Next Piece:</p>

					<?php
						if ( ! is_null($Game->next_piece)) {
							echo get_piece_image($Game->next_piece);
						}
						else {
							echo '<p><strong>ERROR</strong></p>';
						}
					?>

					<?php } ?>

				</div> <!-- #next -->

				<div id="outcome">

					<?php if ('Finished' == $Game->state) { ?>

					<p>Outcome:</p>

					<?php if ( ! isset($outcome[0]) || ('DRAW' != $outcome[0])) { ?>

					<ul>
					<?php
						$attributes = ['Color', 'Size', 'Fill', 'Shape'];

						foreach ($outcome_data as $section => $matches) {
							$match = str_pad(decbin($matches), 4, '0', STR_PAD_LEFT);

							$desc = 'Col';
							if ((string) $section === (string) (int) $section) {
								$desc = 'Row';
							}
							elseif (1 < strlen($section)) {
								$desc = 'Diag';

								if ( ! in_array($section[0], ['\\', '/'])) {
									$desc = 'Square';
								}
							}

							$match_array = [];
							for ($i = 0; $i < 4; ++$i) {
								if ($match[$i]) {
									$match_array[] = $attributes[$i];
								}
							}

							echo '
							<li>'.$desc.' '.$section.': '.implode(', ', $match_array).'</li>';
						}
					?>
					</ul>

					<?php } else { ?>

					<p>Draw</p>

					<?php } ?>

					<?php } ?>

				</div> <!-- #outcome -->

			</div> <!-- #info -->

			<?php echo $chat_html; ?>

			<div id="pieces">
				<p>Available Pieces:</p>

				<?php

					$pieces = range(0, 15);
					shuffle($pieces);
					shuffle($pieces);

					foreach ($pieces as $piece) {
						echo '<div>'.get_piece_image(strtoupper(dechex($piece))).'</div>';
					}
				?>

			</div> <!-- #pieces -->

			<div id="playback">
				<form action="" method="post"><div>
					<input type="button" value="|&lt;&lt;" id="first" />
					<input type="button" value="&lt;&lt;" id="prev" />
					<input type="button" value="reset" id="reset" />
					<input type="button" value="&gt;&gt;" id="next" />
					<input type="button" value="&gt;&gt;|" id="last" />
				</div></form>
			</div> <!-- #playback -->

			<form id="game" method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>"><div class="formDiv">
				<input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>" />
				<input type="hidden" name="game_id" value="<?php echo $_SESSION['game_id']; ?>" />
				<input type="hidden" name="player_id" value="<?php echo $_SESSION['player_id']; ?>" />
				<input type="hidden" name="move" id="move" value="" />
				<input type="hidden" name="piece" id="piece" value="" />
				<?php if ('Playing' == $Game->state) { ?>
					<input type="button" name="resign" id="resign" value="Resign" />
				<?php } ?>
				<?php if ($Game->test_nudge( )) { ?>
					<input type="button" name="nudge" id="nudge" value="Nudge" />
				<?php } ?>
			</div></form>

		</div> <!-- #contents -->

<?php

call($GLOBALS);
echo get_footer($meta);

