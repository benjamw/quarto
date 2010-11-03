<?php

$GLOBALS['NODEBUG'] = true;
$GLOBALS['AJAX'] = true;


// don't require log in when testing for used usernames and emails
if (isset($_POST['validity_test']) || (isset($_GET['validity_test']) && isset($_GET['DEBUG']))) {
	define('LOGIN', false);
}


require_once 'includes/inc.global.php';


// make sure we are running this file directly
// (although this will always be a non-false value, so... ???)
$pos = strpos(__FILE__, preg_replace('%[\\/]+%', DIRECTORY_SEPARATOR, $_SERVER['SCRIPT_NAME']));

if ((false !== $pos) && test_debug( )) {
	$GLOBALS['NODEBUG'] = false;
	$_GET['token'] = $_SESSION['token'];
	$_POST = $_GET;
	$DEBUG = true;
}


// run registration checks
if (isset($_POST['validity_test'])) {
	if (('email' == $_POST['validity_test']) && ('' == $_POST['value'])) {
		echo 'OK';
		exit;
	}

	switch ($_POST['validity_test']) {
		case 'username' :
		case 'email' :
			$username = '';
			$email = '';
			${$_POST['validity_test']} = sani($_POST['value']);

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
		$return['error'] = $e->outputMessage( );
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


// init our game
$Game = new Game((int) $_SESSION['game_id']);


// run the game refresh check
if (isset($_POST['timer'])) {
	echo $Game->last_move;
	exit;
}


// do some more validity checking for the rest of the functions

if (empty($DEBUG) && empty($_POST['notoken'])) {
	test_token(isset($_POST['notest']) && $_POST['notest']);
}


if ($_POST['game_id'] != $_SESSION['game_id']) {
	echo 'ERROR: Incorrect game id given';
	exit;
}


// make sure we are the player we say we are
// unless we're an admin, then it's ok
$player_id = (int) $_POST['player_id'];
if (($player_id != $_SESSION['player_id']) && ! $GLOBALS['Player']->is_admin) {
	echo 'ERROR: Incorrect player id given';
	exit;
}


// run the 'Nudge' button
if (isset($_POST['nudge'])) {
	$return = array( );
	$return['token'] = $_SESSION['token'];

	try {
		$Game->nudge($player_id);
	}
	catch (MyException $e) {
		$return['error'] = $e->outputMessage( );
	}

	echo json_encode($return);
	exit;
}


// run the 'Resign' button
if (isset($_POST['resign'])) {
	$return = array( );
	$return['token'] = $_SESSION['token'];

	try {
		$Game->resign($_SESSION['player_id']);
	}
	catch (MyException $e) {
		$return['error'] = $e->outputMessage( );
	}

	echo json_encode($return);
	exit;
}


// run the moves
if (isset($_POST['move'])) {
	$return = array( );
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

