<?php

// $Id$

$GLOBALS['__EMAIL_DATA'] = array(
	'invite' => array(
		'subject' => 'Game Invitation',
		'message' => '
You have been invited by [[[sender]]] to play a game of [[[GAME_NAME]]] at [[[site_name]]].

If you wish to join this game, please log in and do so.'),



	'turn' => array(
		'subject' => 'Your Turn',
		'message' => '
It is now your turn in the [[[GAME_NAME]]] game you are playing against [[[sender]]] at [[[site_name]]].

Please log in and take your turn. Good Luck!'),



	'nudge' => array(
		'subject' => 'Your Turn',
		'message' => '
[[[sender]]] sent you a nudge to remind you of your turn in the [[[GAME_NAME]]] game you are playing at [[[site_name]]].

Please log in and take your turn. Good Luck!'),



	'defeated' => array(
		'subject' => 'Defeated',
		'message' => '
You have been pwninated by [[[sender]]] in the [[[GAME_NAME]]] game you are playing at [[[site_name]]].

Better luck next time, log on and invite someone to a new game if you still wish to play.'),



	'draw' => array(
		'subject' => 'Draw',
		'message' => '
The [[[GAME_NAME]]] game you are playing at [[[site_name]]] against [[[sender]]] has ended in a draw.

Nice job to both of you, log on and invite someone to a new game if you still wish to play.'),



	'resigned' => array(
		'subject' => 'Opponent Resigned',
		'message' => '
Your opponent, [[[sender]]], has resigned the [[[GAME_NAME]]] game you are playing at [[[site_name]]].

Log on and invite someone to a new game if you still wish to play. '),



	'register' => array(
		'subject' => 'Player Registered',
		'message' => '
A player has registered:
[[[export_data]]]'),



	'approved' => array(
		'subject' => 'Registration Approved',
		'message' => '
Your registration has been approved!
You can now log in and play.'),
);

