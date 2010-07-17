<?php

// $Id: html.general.php 27 2010-06-11 02:14:31Z Benjam $

/** function get_header
 *		Generate the HTML header portion of the page
 *
 * @param array [optional] meta variables
 *   @option string 'title' the page title
 *   @option string 'head_data' any HTML to be inserted in the head tag
 *   @option array 'menu_data' the data for the counts in the menu
 *   @option array 'game_data' the game data for my game list under the menu
 *   @option bool 'show_menu' show the menu
 *   @option string 'file_name' becomes the body id with _page appended
 * @return string HTML header for page
 */
function get_header($meta = null) {
	if ( ! defined('GAME_NAME')) {
		define('GAME_NAME', 'Game');
	}

	$title = ( ! empty($meta['title'])) ? GAME_NAME.' :: '.$meta['title'] : GAME_NAME;
	$show_menu = (isset($meta['show_menu'])) ? (bool) $meta['show_menu'] : true;
	$show_nav_links = (isset($meta['show_nav_links'])) ? (bool) $meta['show_nav_links'] : true;
	$menu_data = (isset($meta['menu_data'])) ? $meta['menu_data'] : false;
	$head_data = (isset($meta['head_data'])) ? $meta['head_data'] : '';
	$file_name = (isset($meta['file_name'])) ? $meta['file_name'] : basename($_SERVER['SCRIPT_NAME']);
	$file_name = substr($file_name, 0, strrpos($file_name, '.'));

	// make sure we have these
	$GLOBALS['_&_DEBUG_QUERY'] = (isset($GLOBALS['_&_DEBUG_QUERY'])) ? $GLOBALS['_&_DEBUG_QUERY'] : '';
	$GLOBALS['_?_DEBUG_QUERY'] = (isset($GLOBALS['_?_DEBUG_QUERY'])) ? $GLOBALS['_?_DEBUG_QUERY'] : '';

	$flash = '';
	if (class_exists('Flash')) {
		$flash = Flash::retrieve( );
	}

	if ($show_menu) {
		if ( ! $menu_data) {
			$menu_data = array(
				'my_turn' => 0,
				'my_games' => 0,
				'games' => 0,
				'new_msgs' => 0,
				'msgs' => 0,
				'in_vites' => 0,
				'out_vites' => 0,
			);

			$list = Game::get_list($_SESSION['player_id']);
			$invites = Game::get_invites($_SESSION['player_id']);

			if (is_array($list)) {
				foreach ($list as $game) {
					++$menu_data['games'];

					if ($game['in_game']) {
						++$menu_data['my_games'];
					}

					if ($game['my_turn'] && ('Placing' != $game['state'])) {
						++$menu_data['my_turn'];
					}
				}
			}

			if (is_array($invites)) {
				foreach ($invites as $game) {
					if ($game['invite']) {
						++$menu_data['in_vites'];
					}
					else {
						++$menu_data['out_vites'];
					}
				}
			}

			$messages = Message::get_count($_SESSION['player_id']);
			$menu_data['msgs'] = (int) @$messages[0];
			$menu_data['new_msgs'] = (int) @$messages[1];

			$allow_blink = ('index.php' == basename($_SERVER['PHP_SELF']));
		}

		// highlight the important menu values
		foreach ($menu_data as $key => $value) {
			switch ($key) {
				case 'my_turn' :
				case 'new_msgs' :
				case 'in_vites' :
					if (0 < $value) {
						$menu_data[$key] = '<span class="notice">'.$value.'</span>';
					}
					break;

				default :
					// do nothing
					break;
			}
		}

		$game_data = (isset($meta['game_data'])) ? $meta['game_data'] : Game::get_list($_SESSION['player_id'], false);
	}

	// if we are admin logged in as someone else, let us know
	$admin_css = $admin_div = '';
	if (isset($_SESSION['admin_id']) && isset($_SESSION['player_id']) && ($_SESSION['player_id'] != $_SESSION['admin_id'])) {
		$admin_css = '
			<style type="text/css">
				html { border: 5px solid red; }
				#admin_username {
					background: red;
					color: black;
					position: fixed;
					top: 0;
					left: 50%;
					width: 200px;
					margin-left: -100px;
					text-align: center;
					font-weight: bold;
					font-size: larger;
					padding: 3px;
				}
			</style>';
		$admin_div = '<div id="admin_username">'.$GLOBALS['Player']->username.' [ '.$GLOBALS['Player']->id.' ]</div>';
	}

	$query_strings = 'var debug_query_ = "'.$GLOBALS['_&_DEBUG_QUERY'].'"; var debug_query = "'.$GLOBALS['_?_DEBUG_QUERY'].'";';
	$debug_string = (defined('DEBUG') && DEBUG) ? 'var debug = true;' : 'var debug = false;';

	$nav_links = '';
	if ($show_nav_links && class_exists('Settings') && Settings::test( )) {
		$nav_links = Settings::read('nav_links');
	}

	$GAME_NAME = GAME_NAME;

	$html = <<< EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>

	<title>{$title}</title>

	<meta http-equiv="Content-Language" content="en-us" />
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
	<meta http-equiv="Content-Style-Type" content="text/css" />

	<script type="text/javascript">//<![CDATA[
		{$debug_string}
		{$query_strings}
	/*]]>*/</script>

	<link rel="stylesheet" type="text/css" media="screen" href="css/reset.css" />
	<link rel="stylesheet" type="text/css" media="screen" href="css/layout.css" />
	<link rel="stylesheet" type="text/css" media="screen" href="css/datatable.css" />
	<link rel="stylesheet" type="text/css" media="screen" href="css/forms.css" />
	<link rel="stylesheet" type="text/css" media="screen" href="css/c_{$GLOBALS['_DEFAULT_COLOR']}.css" />

	<script type="text/javascript" src="scripts/json.js"></script>
	<script type="text/javascript" src="scripts/jquery-1.4.2.min.js"></script>
	<script type="text/javascript" src="scripts/jquery.livequery.js"></script>
	<script type="text/javascript" src="scripts/jquery.tablesorter.js"></script>
	<!-- <script type="text/javascript" src="scripts/jquery.color.js"></script> -->

	{$head_data}
	{$flash}
	{$admin_css}

</head>

<body id="{$file_name}_page">
	{$admin_div}

	<div id="links">{$nav_links}</div>
	<h1><a href="index.php">{$GAME_NAME}</a></h1>
	<div id="wrapper">
EOF;

	if ($show_menu) {
		$html .= '
		<div id="menuholder">';

		if ($menu_data) {
			$html .= '
		<div id="menu">
			<ul>
				<li'.get_active('index').'><a href="index.php'.$GLOBALS['_?_DEBUG_QUERY'].'" title="(Your Turn | Your Games | Total Games)"'.(($allow_blink && $menu_data['my_turn']) ? ' class="blink"' : '').'>Games ('.$menu_data['my_turn'].'|'.$menu_data['my_games'].'|'.$menu_data['games'].')</a></li>
				<li'.get_active('invite').'><a href="invite.php'.$GLOBALS['_?_DEBUG_QUERY'].'" title="(Invites Recieved | Invites Sent)"'.(($allow_blink && $menu_data['in_vites']) ? ' class="blink"' : '').'>Invitations ('.$menu_data['in_vites'].'|'.$menu_data['out_vites'].')</a></li>
				<li'.get_active('stats').'><a href="stats.php'.$GLOBALS['_?_DEBUG_QUERY'].'">Statistics</a></li>
				<li'.get_active('messages').'><a href="messages.php'.$GLOBALS['_?_DEBUG_QUERY'].'" title="(New Messages | Total Messages)"'.(($allow_blink && $menu_data['new_msgs']) ? ' class="blink"' : '').'>Messages ('.$menu_data['new_msgs'].'|'.$menu_data['msgs'].')</a></li>
				<li'.get_active('prefs').'><a href="prefs.php'.$GLOBALS['_?_DEBUG_QUERY'].'">Preferences</a></li>
				<li'.get_active('profile').'><a href="profile.php'.$GLOBALS['_?_DEBUG_QUERY'].'">Profile</a></li>
				';

				if (true == $GLOBALS['Player']->is_admin) {
					$html .= '<li'.get_active('admin').'><a href="admin.php'.$GLOBALS['_?_DEBUG_QUERY'].'">Admin</a></li>';
				}

			$html .= '
				<li><a href="login.php'.$GLOBALS['_?_DEBUG_QUERY'].'">Logout</a></li>
			</ul>
		</div>';
		}

		if ($game_data) {
			$html .= '
		<div id="mygames_title"><strong>My Games</strong></div>
		<div id="mygames">
			<ul>';

			foreach ($game_data as $game) {
				$class = ($game['my_turn']) ? 'playing' : 'waiting';
				$html .= '
				<li class="'.$class.'"><a href="game.php?id='.$game['game_id'].$GLOBALS['_&_DEBUG_QUERY'].'">'.$game['opponent'].'</a></li>';
			}

			$html .= '
			</ul>
		</div>';
		}

		$html .= '
		</div>';
	}

	return $html;
}


/** function get_footer
 *		Generate the HTML footer portion of the page
 *
 * @param array option meta info
 * @return string HTML footer for page
 */
function get_footer($meta = array( )) {
	$foot_data = (isset($meta['foot_data'])) ? $meta['foot_data'] : '';

	$players = GamePlayer::get_count( );
	list($cur_games, $total_games) = Game::get_count( );

	$Mysql = Mysql::get_instance( );

	$html = '
		<div id="footerspacer">&nbsp;</div>
		<div id="footer">
			<span>Total Players - '.$players.'</span>
			<span>Active Games - '.$cur_games.'</span>
			<span>Games Played - '.$total_games.'</span>
		</div>
	</div>

	'.$foot_data.'

	<!-- Queries = '.$Mysql->query_count.' -->
</body>
</html>';

	return $html;
}


/** function get_item
 *		Generate the HTML content portion of the page
 *
 * @param string contents
 * @param string instructions for page
 * @param string [optional] title for page
 * @return string HTML content for page
 */
function get_item($contents, $hint, $title = '', $extra_html = '') {
	$hint_html = "\n\t\t\t<p><strong>Welcome";
	if (isset($GLOBALS['Player']) && (0 != $_SESSION['player_id'])) {
		$hint_html .= ", {$GLOBALS['Player']->username}";
	}
	$hint_html .= '</strong></p>';

	if (is_array($hint)) {
		foreach ($hint as $line) {
			$hint_html .= "\n\t\t\t<p>{$line}</p>";
		}
	}
	else {
		$hint_html .= "\n\t\t\t<p>{$hint}</p>";
	}

	if ('' != $title) {
		$title = '<h2>'.$title.'</h2>';
	}

	$long_date = (class_exists('Settings') && Settings::test( )) ? Settings::read('long_date') : 'M j, Y g:i a';

	$html = '
		<div id="sidebar">
			<div id="notes">
				<div id="date">'.date($long_date).'</div>
				'.$hint_html.'
			</div>
			'.$extra_html.'
		</div>
		<div id="content">
			'.$title.'
			'.$contents.'
		</div>
	';

	return $html;
}


/** function get_active
 *		Returns an active class string based on
 *		our current location
 *
 * @param string link URL to test against
 * @return string HTML active class attribute (or empty string)
 */
function get_active($value) {
	$self = substr(basename($_SERVER['SCRIPT_NAME']), 0, -4);

	if ($value == $self) {
		return ' class="active"';
	}

	return '';
}


/** function get_piece_image
 *		Returns the img tag for the given piece
 *		NOTE: Quarto specific
 *
 * @param string piece code
 * @return string HTML img tag
 */
function get_piece_image($piece) {
	$pt = array( // pieces text
		array('black', 'white'),
		array('short', 'tall'),
		array('hollow', 'solid'),
		array('round', 'square'),
	);

	$p = str_pad(decbin(hexdec($piece)), 4, '0', STR_PAD_LEFT);

	return '<img id="p_'.$piece.'" src="images/'.$pt[0][$p[0]].'-'.$pt[1][$p[1]].'-'.$pt[2][$p[2]].'-'.$pt[3][$p[3]].'.png" alt="'.$pt[0][$p[0]].' '.$pt[1][$p[1]].' '.$pt[2][$p[2]].' '.$pt[3][$p[3]].'" class="piece '.$pt[0][$p[0]].' '.$pt[1][$p[1]].' '.$pt[2][$p[2]].' '.$pt[3][$p[3]].'" />';
}
