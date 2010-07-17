<?php

// $Id: stats.php 14 2009-07-28 17:04:43Z cchristensen $

require_once 'includes/inc.global.php';

$meta['title'] = 'Statistics';
$meta['head_data'] = '
	<script type="text/javascript">//<![CDATA[
		$(document).ready( function( ) {
			$("td.color, .color td").each( function(i, elem) {
				var $elem = $(elem);
				var text = parseFloat($elem.text( ));

				if (0 < text) {
					$elem.css("color", "green");
				}
				else if (0 > text) {
					$elem.css("color", "red");
				}
			});
		});
	//]]></script>
';

$hints = array(
	'View '.GAME_NAME.' Player statistics.' ,
);

// grab the wins and losses for the players
$list = GamePlayer::get_list(true);

$table_meta = array(
	'sortable' => true ,
	'no_data' => '<p>There are no player stats to show</p>' ,
	'caption' => 'Player Stats' ,
	'init_sort_column' => array(1 => 1) ,
);
$table_format = array(
	array('Player', 'username') ,
	array('Wins', 'wins') ,
	array('Losses', 'losses') ,
	array('Win-Loss', '###([[[wins]]] - [[[losses]]])', null, ' class="color"') ,
	array('Win %', '###((0 != ([[[wins]]] + [[[losses]]])) ? perc([[[wins]]] / ([[[wins]]] + [[[losses]]]), 1) : 0)') ,
	array('Last Online', '###date(Settings::read(\'long_date\'), strtotime(\'[[[last_online]]]\'))', null, ' class="date"') ,
);
$contents = get_table($table_format, $list, $table_meta);

// TODO: possibly add game stats ???

echo get_header($meta);
echo get_item($contents, $hints, $meta['title']);
echo get_footer( );

