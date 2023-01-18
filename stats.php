<?php

require_once 'includes/inc.global.php';

$meta['title'] = 'Statistics';
$meta['head_data'] = '
	<script type="text/javascript" src="scripts/stats.js">></script>
';

$hints = [
	'View '.GAME_NAME.' Player statistics.',
];

$contents = '';

// grab the wins and losses for the players
$list = GamePlayer::get_list(true);

$table_meta = [
	'sortable' => true,
	'no_data' => '<p>There are no player stats to show</p>',
	'caption' => 'Player Stats',
	'init_sort_column' => [1 => 1],
];
$table_format = [
	['Player', 'username'],
	['Wins', 'wins'],
	['Losses', 'losses'],
	['Win-Loss', '###([[[wins]]] - [[[losses]]])', null, ' class="color"'],
	['Win %', '###((0 != ([[[wins]]] + [[[losses]]])) ? perc([[[wins]]] / ([[[wins]]] + [[[losses]]]), 1) : 0)'],
	['Last Online', '###date(Settings::read(\'long_date\'), strtotime(\'[[[last_online]]]\'))', null, ' class="date"'],
];
$contents .= get_table($table_format, $list, $table_meta);

// TODO: possibly add game stats ???

echo get_header($meta);
echo get_item($contents, $hints, $meta['title']);
call($GLOBALS);
echo get_footer( );

