<?php

$GLOBALS['NODEBUG'] = true;
$GLOBALS['AJAX'] = true;


// don't require log in when testing for used usernames and emails
if (isset($_POST['validity_test']) || (isset($_GET['validity_test']) && isset($_GET['DEBUG']))) {
	define('LOGIN', false);
}


require_once 'includes/inc.global.php';


// if we are debugging, change some things for us
// (although REQUEST_METHOD may not always be valid)
if (('GET' == $_SERVER['REQUEST_METHOD']) && defined('DEBUG') && DEBUG) {
	$GLOBALS['NODEBUG'] = false;
	$GLOBALS['AJAX'] = false;
	$_GET['token'] = $_SESSION['token'];
	$_GET['keep_token'] = true;
	$_POST = $_GET;
	$DEBUG = true;
	call('AJAX HELPER');
	call($_POST);
}


// run the index page refresh checks
if (isset($_POST['timer'])) {
	$message_count = (int) Message::check_new($_SESSION['player_id']);
	$turn_count = (int) Game::check_turns($_SESSION['player_id']);
	echo $message_count + $turn_count;
	exit;
}


// run registration checks
if (isset($_POST['validity_test'])) {
#	if (('email' == $_POST['type']) && ('' == $_POST['value'])) {
#		echo 'OK';
#		exit;
#	}

	$player_id = 0;
	if ( ! empty($_POST['profile'])) {
		$player_id = (int) $_SESSION['player_id'];
	}

	switch ($_POST['validity_test']) {
		case 'username' :
		case 'email' :
			$username = '';
			$email = '';
			${$_POST['validity_test']} = $_POST['value'];

			$player_id = (isset($_POST['player_id']) ? (int) $_POST['player_id'] : 0);

			try {
				Player::check_database($username, $email, $player_id);
			}
			catch (MyException $e) {
				echo $e->getCode( );
				exit;
			}
			break;

		default :
			break;
	}

	echo 'OK';
	exit;
}


// run the in game chat
if (isset($_POST['chat'])) {
	try {
		if ( ! isset($_SESSION['game_id'])) {
			$_SESSION['game_id'] = 0;
		}

		$Chat = new Chat((int) $_SESSION['player_id'], (int) $_SESSION['game_id']);
		$Chat->send_message($_POST['chat'], isset($_POST['private']), isset($_POST['lobby']));
		$return = $Chat->get_box_list(1);
		$return = $return[0];
	}
	catch (MyException $e) {
		$return['error'] = 'ERROR: '.$e->outputMessage( );
	}

	echo json_encode($return);
	exit;
}


// run the invites stuff
if (isset($_POST['action']) && ('delete' == $_POST['action'])) {
	try {
		Game::delete($_POST['game_id']);
		echo 'Game Deleted';
	}
	catch (MyEception $e) {
		echo 'ERROR: Could not delete game';
	}
	exit;
}


// we'll need a game id from here forward, so make sure we have one
if (empty($_SESSION['game_id'])) {
	echo 'ERROR: Game not found';
	exit;
}


// init our game
if ( ! isset($Game)) {
	$Game = new Game((int) $_SESSION['game_id']);
}


// run the game refresh check
if (isset($_POST['refresh'])) {
	echo $Game->last_move;
	exit;
}


// do some more validity checking for the rest of the functions

if (empty($DEBUG) && empty($_POST['notoken'])) {
	test_token( ! empty($_POST['keep_token']));
}


if ($_POST['game_id'] != $_SESSION['game_id']) {
	echo 'ERROR: Incorrect game id given';
	exit;
}


// make sure we are the player we say we are
// unless we're an admin, then it's ok
$player_id = (int) $_POST['player_id'];
if (($player_id != $_SESSION['player_id']) && ! $GLOBALS['Player']->is_admin) {
	throw new MyException('ERROR: Incorrect player id given');
}


// run the simple button actions
$actions = [
	'nudge',
	'resign',
	'offer_draw',
	'accept_draw',
	'reject_draw',
	'request_undo',
	'accept_undo',
	'reject_undo',
];

foreach ($actions as $action) {
	if (isset($_POST[$action])) {
		try {
			if ($Game->{$action}($player_id)) {
				echo 'OK';
			}
			else {
				echo 'ERROR';
			}
		}
		catch (MyException $e) {
			echo $e;
		}

		exit;
	}
}


// run the moves
if (isset($_POST['move'])) {
	$return = [];
	$return['token'] = $_SESSION['token'];

	try {
		// do the move first, we may just be ajax testing it
		$old_board = $Game->board;
		$outcome = $Game->do_move($_POST['move']);

		if ( ! $outcome && ('' != $_POST['piece'])) {
			$Game->choose_piece($_POST['piece']);
			$return['action'] = 'RELOAD';
		}
		elseif ($outcome) {
			$return['action'] = 'RELOAD';
		}
		else {
			// return the board to it's previous state
			// so we don't save anything
			$Game->board = $old_board;
			$return['outcome'] = 'OK';
		}
	}
	catch (MyException $e) {
		$return['error'] = 'ERROR: '.$e->outputMessage( );
	}

	echo json_encode($return);
	exit;
}

