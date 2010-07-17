<?php

// $Id: invite.php 23 2010-03-15 08:05:51Z Benjam $

require_once 'includes/inc.global.php';

// this has nothing to do with creating a game
// but I'm running it here to prevent long load
// times on other pages where it would be ran more often
GamePlayer::delete_inactive(Settings::read('expire_users'));
Game::delete_finished(Settings::read('expire_finished_games'));
Game::delete_inactive(Settings::read('expire_games'));

$Game = new Game( );

if (isset($_POST['invite'])) {
	test_token( );

	try {
		$game_id = $Game->invite( );
		Flash::store('Invitation Sent Successfully');
	}
	catch (MyException $e) {
		Flash::store('Invitation FAILED !', false);
	}
}

$players = GamePlayer::get_list(true);
$opponent_selection = '';
foreach ($players as $player) {
	if ($_SESSION['player_id'] == $player['player_id']) {
		continue;
	}

	$opponent_selection .= '<option value="'.$player['player_id'].'">'.$player['username'].'</option>';
}

$pieces = $Game->get_available_pieces( );

$pieces_html = '';
$piece_selection = '<option value="random">Random</option>';
foreach ($pieces as $piece) {
	$pieces_html .= '<div>'.get_piece_image($piece).'</div>';
	$piece_selection .= '<option>'.strtoupper($piece).'</option>';
}

$meta['title'] = 'Send Game Invitation';
$meta['head_data'] = '
	<style type="text/css">
		div#pieces {
			width: 620px;
			float: right;
		}
		div#pieces:after {
		    content: ".";
		    display: block;
		    height: 0;
		    clear: both;
		    visibility: hidden;
		}
		div.formdiv:after {
		    content: ".";
		    display: block;
		    height: 0;
		    clear: both;
		    visibility: hidden;
		}

		#pieces img {
			float: left;
		}
	</style>
	<script type="text/javascript" src="scripts/invite.js"></script>
';

$hints = array(
	'Invite a player to a game by filling out your desired game options.' ,
	'You can click the displayed piece to select it.' ,
	'<span class="highlight">WARNING!</span><br />Games will be deleted after '.Settings::read('expire_games').' days of inactivity.' ,
);

$contents = '';

$contents .= <<< EOF
	<form method="post" action="{$_SERVER['REQUEST_URI']}"><div class="formdiv">
		<input type="hidden" name="token" value="{$_SESSION['token']}" />
		<input type="hidden" name="player_id" value="{$_SESSION['player_id']}" />

		<div id="pieces">
			<p>Select Initial Piece:</p>
			{$pieces_html}
		</div>

		<ul>
			<li><label for="opponent">Opponent</label><select id="opponent" name="opponent">{$opponent_selection}</select></li>
			<li><label for="piece">Piece</label><select id="piece" name="piece">{$piece_selection}</select></li>
			<li><input type="submit" name="invite" value="Send Invitation" /></li>
		</ul>

	</div></form>
EOF;

// create our invitation tables
$invites = Game::get_invites($_SESSION['player_id']);

$in_vites = $out_vites = array( );
if (is_array($invites)) {
	foreach ($invites as $game) {
		if ($game['invite']) {
			$in_vites[] = $game;
		}
		else {
			$out_vites[] = $game;
		}
	}
}

$contents .= <<< EOT
	<hr />
	<form method="post" action="{$_SERVER['REQUEST_URI']}"><div class="formdiv" id="invites">
EOT;

$table_meta = array(
	'sortable' => true ,
	'no_data' => '<p>There are no invites to show</p>' ,
	'caption' => 'Invitations Recieved' ,
);
$table_format = array(
	array('ID', 'game_id') ,
	array('Player #1', 'white') ,
	array('Player #2', 'black') ,
	array('Date Sent', '###date(Settings::read(\'long_date\'), strtotime(\'[[[last_move]]]\'))', null, ' class="date"') ,
	array('Action', '<input type="button" id="accept-[[[game_id]]]" value="Accept" /><input type="button" id="decline-[[[game_id]]]" value="Decline" />', false) ,
);
$contents .= get_table($table_format, $in_vites, $table_meta);

$table_meta = array(
	'sortable' => true ,
	'no_data' => '<p>There are no invites to show</p>' ,
	'caption' => 'Invitations Sent' ,
);
$table_format = array(
	array('ID', 'game_id') ,
	array('Player #1', 'white') ,
	array('Player #2', 'black') ,
	array('Date Sent', '###date(Settings::read(\'long_date\'), strtotime(\'[[[last_move]]]\'))', null, ' class="date"') ,
	array('Action', '<input type="button" id="withdraw-[[[game_id]]]" value="Withdraw" />', false) ,
);
$contents .= get_table($table_format, $out_vites, $table_meta);

$contents .= <<< EOT
	</div></form>
EOT;

echo get_header($meta);
echo get_item($contents, $hints, $meta['title']);
echo get_footer( );

