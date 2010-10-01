<?php
/*
+---------------------------------------------------------------------------
|
|   game.class.php (php 5.x)
|
|   by Benjam Welker
|   http://iohelix.net
|
+---------------------------------------------------------------------------
|
|	This module is built to facilitate the game Quarto, it doesn't really
|	care about how to play, or the deep goings on of the game, only about
|	database structure and how to allow players to interact with the game.
|
+---------------------------------------------------------------------------
|
|   > Quarto Game module
|   > Date started: 2008-02-28
|
|   > Module Version Number: 0.8.0
|
|   $Id: game.class.php 27 2010-06-11 02:14:31Z Benjam $
+---------------------------------------------------------------------------
*/

// TODO: comments & organize better

require_once INCLUDE_DIR.'func.array.php';

class Game
{

	/**
	 *		PROPERTIES
	 * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/** const property GAME_TABLE
	 *		Holds the game table name
	 *
	 * @var string
	 */
	const GAME_TABLE = T_GAME;


	/** const property GAME_BOARD_TABLE
	 *		Holds the game board table name
	 *
	 * @var string
	 */
	const GAME_BOARD_TABLE = T_GAME_BOARD;


	/** const property GAME_NUDGE_TABLE
	 *		Holds the game nudge table name
	 *
	 * @var string
	 */
	const GAME_NUDGE_TABLE = T_GAME_NUDGE;


	/** static protected property _EXTRA_INFO_DEFAULTS
	 *		Holds the default extra info data
	 *
	 * @var array
	 */
	static protected $_EXTRA_INFO_DEFAULTS = array(
			'small_square_matches' => false,
			'small_square_torus' => false,
			'diagonal_torus' => false,
		);


	/** public property id
	 *		Holds the game's id
	 *
	 * @var int
	 */
	public $id;


	/** public property state
	 *		Holds the game's current state
	 *		can be one of 'Waiting', 'Playing', 'Finished', 'Draw'
	 *
	 * @var string (enum)
	 */
	public $state;


	/** public property turn
	 *		Holds the game's current turn
	 *		can be one of 'white', 'black'
	 *
	 * @var string
	 */
	public $turn;


	/** public property paused
	 *		Holds the game's current pause state
	 *
	 * @var bool
	 */
	public $paused;


	/** public property create_date
	 *		Holds the game's create date
	 *
	 * @var int (unix timestamp)
	 */
	public $create_date;


	/** public property modify_date
	 *		Holds the game's modified date
	 *
	 * @var int (unix timestamp)
	 */
	public $modify_date;


	/** public property last_move
	 *		Holds the game's last move date
	 *
	 * @var int (unix timestamp)
	 */
	public $last_move;


	/** protected property _extra_info
	 *		Holds the extra game info
	 *
	 * @var array
	 */
	protected $_extra_info;


	/** protected property _players
	 *		Holds our player's object references
	 *		along with other game data
	 *
	 * @var array of player data
	 */
	protected $_players;


	/** protected property _quarto
	 *		Holds the Quarto object reference
	 *
	 * @var object Quarto reference
	 */
	protected $_quarto;


	/** protected property _history
	 *		Holds the board history array
	 *
	 * @var array of moves
	 */
	protected $_history;


	/** protected property _mysql
	 *		Stores a reference to the Mysql class object
	 *
	 * @param Mysql object
	 */
	protected $_mysql;



	/**
	 *		METHODS
	 * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/** public function __construct
	 *		Class constructor
	 *		Sets all outside data
	 *
	 * @param int optional game id
	 * @param Mysql optional object reference
	 * @action instantiates object
	 * @return void
	 */
	public function __construct($id = 0, Mysql $Mysql = null)
	{
		call(__METHOD__);

		$this->id = (int) $id;
		call($this->id);

		if (is_null($Mysql)) {
			$Mysql = Mysql::get_instance( );
		}

		$this->_mysql = $Mysql;
		$this->_quarto = new Quarto( );

		try {
			$this->_pull( );
		}
		catch (MyException $e) {
			throw $e;
		}
	}


	/** public function __destruct
	 *		Class destructor
	 *		Gets object ready for destruction
	 *
	 * @param void
	 * @action saves changed data
	 * @action destroys object
	 * @return void
	 */
	public function __destruct( )
	{
		// save anything changed to the database
		// BUT... only if PHP didn't die because of an error
		$error = error_get_last( );

		if ($this->id && (0 == ((E_ERROR | E_WARNING | E_PARSE) & $error['type']))) {
			try {
				$this->_save( );
			}
			catch (MyException $e) {
				// do nothing, it will be logged
			}
		}
	}


	/** public function __get
	 *		Class getter
	 *		Returns the requested property if the
	 *		requested property is not _private
	 *
	 * @param string property name
	 * @return mixed property value
	 */
	public function __get($property)
	{
		switch ($property) {
			case 'name' :
				if ($_SESSION['player_id'] == $this->_players['player']['player_id']) {
					return $this->_players['opponent']['object']->username;
				}
				else {
					return $this->_players['white']['object']->username.' vs '.$this->_players['black']['object']->username;
				}
				break;

			case 'first_name' :
				if ($_SESSION['player_id'] == $this->_players['player']['player_id']) {
					return 'Your';
				}
				else {
					return $this->_players['white']['object']->username.'\'s';
				}
				break;

			case 'second_name' :
				if ($_SESSION['player_id'] == $this->_players['player']['player_id']) {
					return $this->_players['opponent']['object']->username.'\'s';
				}
				else {
					return $this->_players['black']['object']->username.'\'s';
				}
				break;

			case 'board' :
				return $this->_quarto->board;
				break;

			case 'next_piece' :
				return $this->_quarto->next_piece;
				break;

			default :
				// go to next step
				break;
		}

		if ( ! property_exists($this, $property)) {
			throw new MyException(__METHOD__.': Trying to access non-existant property ('.$property.')', 2);
		}

		if ('_' === $property[0]) {
			throw new MyException(__METHOD__.': Trying to access _private property ('.$property.')', 2);
		}

		return $this->$property;
	}


	/** public function __set
	 *		Class setter
	 *		Sets the requested property if the
	 *		requested property is not _private
	 *
	 * @param string property name
	 * @param mixed property value
	 * @action optional validation
	 * @return bool success
	 */
	public function __set($property, $value)
	{
		switch ($property) {
			case 'board' :
				$this->_quarto->board = $value;
				return;
				break;

			default :
				// go to next step
				break;
		}

		if ( ! property_exists($this, $property)) {
			throw new MyException(__METHOD__.': Trying to access non-existant property ('.$property.')', 3);
		}

		if ('_' === $property[0]) {
			throw new MyException(__METHOD__.': Trying to access _private property ('.$property.')', 3);
		}

		$this->$property = $value;
	}


	/** public function invite
	 *		Creates the game from _POST data
	 *
	 * @param void
	 * @action creates a game
	 * @return int game id
	 */
	public function invite( )
	{
		call(__METHOD__);

		// DON'T sanitize the data
		// it gets sani'd in the MySQL->insert method
		$_P = $_POST;

		// translate (filter/sanitize) the data
		$_P['white_id'] = $_P['player_id'];
		$_P['black_id'] = $_P['opponent'];
		$_P['small_square_matches'] = (isset($_P['small_square_matches']) && ('yes' == $_P['small_square_matches']));
		$_P['small_square_torus'] = (isset($_P['small_square_torus']) && ('yes' == $_P['small_square_torus']));
		$_P['diagonal_torus'] = (isset($_P['diagonal_torus']) && ('yes' == $_P['diagonal_torus']));

		$extra_info = array(
			'small_square_matches' => (bool) $_P['small_square_matches'],
			'small_square_torus' => (bool) $_P['small_square_torus'],
			'diagonal_torus' => (bool) $_P['diagonal_torus'],
		);
		call($extra_info);

		$diff = array_compare($extra_info, self::$_EXTRA_INFO_DEFAULTS);
		$extra_info = $diff[0];
		ksort($extra_info);

		call($extra_info);
		if ( ! empty($extra_info)) {
			$_P['extra_info'] = serialize($extra_info);
		}

		// create the game
		$required = array(
			'white_id' ,
			'black_id' ,
		);

		$key_list = array_merge($required, array(
			'extra_info' ,
		));

		try {
			$_DATA = array_clean($_P, $key_list, $required);
		}
		catch (MyException $e) {
			throw $e;
		}

		$_DATA['create_date '] = 'NOW( )'; // note the trailing space in the field name, this is not a typo

		// THIS IS THE ONLY PLACE IN THE CLASS WHERE IT BREAKS THE _pull / _save MENTALITY
		// BECAUSE I NEED THE INSERT ID FOR THE REST OF THE GAME FUNCTIONALITY

		$insert_id = $this->_mysql->insert(self::GAME_TABLE, $_DATA);

		if (empty($insert_id)) {
			throw new MyException(__METHOD__.': Game could not be created');
		}

		$this->id = $insert_id;

		Email::send('invite', $_P['black_id'], array('player' => $GLOBALS['_PLAYERS'][$_P['white_id']]));

		// set the modified date
		$this->_mysql->insert(self::GAME_TABLE, array('modify_date' => NULL), " WHERE game_id = '{$this->id}' ");

		// pull the fresh data
		$this->_pull( );

		// set the next piece
		try {
			if ('random' != $_P['piece']) {
				$this->_quarto->next_piece = $_P['piece'];
			}
			else {
				$pieces = '0123456789ABCDEF';
				$this->_quarto->next_piece = strtoupper(dechex(mt_rand(0, 15)));
			}
		}
		catch (MyException $e) {
			throw $e;
		}

		return $this->id;
	}


	/** public function accept
	 *		Accepts the game invitation
	 *
	 * @param int player id
	 * @return void
	 */
	public function accept($player_id)
	{
		call(__METHOD__);

		$player_id = (int) $player_id;

		// TODO: run the gauntlet of tests

		$this->state = 'Playing';
	}


	/** public function choose_piece
	 *		Selects the next piece in play
	 *
	 * @param string piece code
	 * @return void
	 */
	public function choose_piece($piece)
	{
		call(__METHOD__);

		try {
			$this->_quarto->set_next_piece($piece);
			Email::send('turn', $this->_players['opponent']['player_id'], array('player' => $this->_players['player']['object']->username));
		}
		catch (MyException $e) {
			throw $e;
		}
	}


	/** public function do_move
	 *		Places the next piece in the given square
	 *
	 * @param int board index
	 * @return game outcome
	 */
	public function do_move($index)
	{
		call(__METHOD__);

		try {
			$outcome = $this->_quarto->do_move($index);

			if ($outcome) {
				$this->state = 'Finished';

				if (isset($outcome[0]) && ('DRAW' == $outcome[0])) {
					$this->state = 'Draw';
					$this->_players['player']['object']->add_draw( );
					$this->_players['opponent']['object']->add_draw( );
					Email::send('draw', $this->_players['opponent']['player_id'], array('player' => $this->_players['player']['object']->username));
				}
				else {
					$this->_players['player']['object']->add_win( );
					$this->_players['opponent']['object']->add_loss( );
					Email::send('defeated', $this->_players['opponent']['player_id'], array('player' => $this->_players['player']['object']->username));
				}
			}
		}
		catch (MyException $e) {
			throw $e;
		}

		return $outcome;
	}


	/** public function get_outcome
	 *		Gets the outcome of the game
	 *
	 * @param void
	 * @return array outcome
	 */
	public function get_outcome( )
	{
		call(__METHOD__);

		$outcome = $this->_quarto->get_outcome( );

		if (isset($outcome[0]) && ('DRAW' == $outcome[0])) {
			$return = array('Draw', 'won', $outcome);
		}
		else {
			if ( ! $this->is_player($_SESSION['player_id'])) {
				$return = array($this->_players[$this->_players[$this->turn]['opp_color']]['object']->username.' Wins', 'won', $outcome);
			}
			elseif ($_SESSION['player_id'] == $this->_players[$this->turn]['player_id']) {
				$return = array('You Lost', 'lost', $outcome);
			}
			else {
				$return = array('You Won!', 'won', $outcome);
			}
		}

		return $return;
	}


	/** public function resign
	 *		Resigns the given player from the game
	 *
	 * @param int player id
	 * @return void
	 */
	public function resign($player_id)
	{
		call(__METHOD__);

		if ($this->paused) {
			throw new MyException(__METHOD__.': Trying to perform an action on a paused game');
		}

		$player_id = (int) $player_id;

		if (empty($player_id)) {
			throw new MyException(__METHOD__.': Missing required argument');
		}

		if ( ! $this->is_player($player_id)) {
			throw new MyException(__METHOD__.': Player (#'.$player_id.') trying to resign from a game (#'.$this->id.') they are not playing in');
		}

		if ($this->_players['player']['player_id'] != $player_id) {
			throw new MyException(__METHOD__.': Player (#'.$player_id.') trying to resign another player from a game (#'.$this->id.')');
		}

		// TODO: this may need some adjusting

		$this->_players['opponent']['object']->add_win( );
		$this->_players['player']['object']->add_loss( );
		$this->state = 'Finished';
		Email::send('resigned', $this->_players['opponent']['object']->id, array('name' => $this->_players['player']['object']->username));
	}


	/** public function is_player
	 *		Tests if the given ID is a player in the game
	 *
	 * @param int player id
	 * @return bool player is in game
	 */
	public function is_player($player_id)
	{
		$player_id = (int) $player_id;

		return ((isset($this->_players['white']['player_id']) && ($player_id == $this->_players['white']['player_id']))
			|| (isset($this->_players['black']['player_id']) && ($player_id == $this->_players['black']['player_id'])));
	}


	/** public function get_my_color
	 *		Returns the current player's color
	 *
	 * @param void
	 * @return string current player's color (or false on failure)
	 */
	public function get_my_color( )
	{
		return ((isset($this->_players['player']['color'])) ? $this->_players['player']['color'] : false);
	}


	/** public function get_my_turn
	 *		Returns the current player's turn
	 *
	 * @param void
	 * @return bool is the current players turn
	 */
	public function get_my_turn( )
	{
		return ((isset($this->_players['player']['turn'])) ? $this->_players['player']['turn'] : false);
	}


	/** public function get_previous_move
	 *		Grabs the previous move made
	 *
	 * @param void
	 * @return int computer string index
	 */
	public function get_previous_move( )
	{
		call(__METHOD__);

		if (1 >= count($this->_history)) {
			return false;
		}

		$history = $this->_history;

		for ($i = 0; $i < 16; ++$i) {
			if ($history[1]['board'][$i] != $history[0]['board'][$i]) {
				return $i;
			}
		}

		return false;
	}


	/** public function get_history
	 *		Grabs the game history array
	 *
	 * @param void
	 * @return array game history
	 */
	public function get_history( )
	{
		call(__METHOD__);

		$history = array_reverse($this->_history);

		// run through the history and attach the player username to the move they made
		foreach ($history as $i => & $item) {
			unset($item['game_id']);
			unset($item['move_date']);

			$item['color'] = (0 == ($i % 2)) ? 'white' : 'black';
			$item['username'] = $this->_players[$item['color']]['object']->username;

			// find out which square the piece was placed on
			if ($i) {
				for ($j = 0; $j < 16; ++$j) {
					if ($history[$i - 1]['board'][$j] != $item['board'][$j]) {
						$item['index'] = $j;
						break;
					}
				}
			}
		}
		unset($item); // kill the reference

		return $history;
	}


	/** public function nudge
	 *		Nudges the given player to tke their move
	 *
	 * @param void
	 * @return bool success
	 */
	public function nudge( )
	{
		call(__METHOD__);

		if ($this->paused) {
			throw new MyException(__METHOD__.': Trying to perform an action on a paused game');
		}

		$nudger = $this->_players['player']['object']->username;

		if ($this->test_nudge( )) {
			Email::send('nudge', $this->_players['opponent']['player_id'], array('id' => $this->id, 'name' => $this->name, 'player' => $nudger));
			$this->_mysql->delete(self::GAME_NUDGE_TABLE, " WHERE game_id = '{$this->id}' ");
			$this->_mysql->insert(self::GAME_NUDGE_TABLE, array('game_id' => $this->id, 'player_id' => $this->_players['opponent']['player_id']));
			return true;
		}

		return false;
	}


	/** public function test_nudge
	 *		Tests if the current player can be nudged or not
	 *
	 * @param void
	 * @return bool player can be nudged
	 */
	public function test_nudge( )
	{
		call(__METHOD__);

		$player_id = (int) $this->_players['opponent']['player_id'];

		if ($this->get_my_turn( ) || in_array($this->state, array('Finished', 'Draw')) || $this->paused) {
			return false;
		}

		try {
			$nudge_time = Settings::read('nudge_flood_control');
		}
		catch (MyException $e) {
			return false;
		}

		if (-1 == $nudge_time) {
			return false;
		}
		elseif (0 == $nudge_time) {
			return true;
		}

		// check the nudge status for this game/player
		// 'now' is taken from the DB because it may
		// have a different time from the PHP server
		$query = "
			SELECT NOW( ) AS now
				, G.modify_date AS move_date
				, GN.nudged
			FROM ".self::GAME_TABLE." AS G
				LEFT JOIN ".self::GAME_NUDGE_TABLE." AS GN
					ON (GN.game_id = G.game_id
						AND GN.player_id = '{$player_id}')
			WHERE G.game_id = '{$this->id}'
		";
		$dates = $this->_mysql->fetch_assoc($query);

		if ( ! $dates) {
			return false;
		}

		// check the dates
		// if the move date is far enough in the past
		//  AND the player has not been nudged
		//   OR the nudge date is far enough in the past
		if ((strtotime($dates['move_date']) <= strtotime('-'.$nudge_time.' hour', strtotime($dates['now'])))
			&& ((empty($dates['nudged']))
				|| (strtotime($dates['nudged']) <= strtotime('-'.$nudge_time.' hour', strtotime($dates['now'])))))
		{
			return true;
		}

		return false;
	}


	/** public function get_players
	 *		Grabs the player array
	 *
	 * @param void
	 * @return array player data
	 */
	public function get_players( )
	{
		$players = array( );

		foreach (array('white','black') as $color) {
			$player_id = $this->_players[$color]['player_id'];
			$players[$player_id] = $this->_players[$color];
			$players[$player_id]['username'] = $this->_players[$color]['object']->username;
			unset($players[$player_id]['object']);
		}

		return $players;
	}


	/** public function get_available_pieces
	 *		Returns the pieces available to place
	 *
	 * @param void
	 * @return array piece codes
	 */
	public function get_available_pieces( )
	{
		return $this->_quarto->get_missing_pieces( );
	}


	/** public function get_matching_methods
	 *		Returns the extra methods available to match
	 *
	 * @param void
	 * @return array matching methods
	 */
	public function get_matching_methods( )
	{
		$return = array( );

		if ($this->_quarto->small_square_matches) {
			$return[] = 'Small Square';
		}

		if ($this->_quarto->small_square_torus) {
			$return[] = 'Small Square Torus';
		}

		if ($this->_quarto->diagonal_torus) {
			$return[] = 'Diagonal Torus';
		}

		return $return;
	}


	/** public function write_game_file
	 *		TODO
	 *
	 * @param void
	 * @action void
	 * @return bool true
	 */
	public function write_game_file( )
	{
		// TODO: build a logging system to log game data
		return true;
	}


	/** protected function _pull
	 *		Pulls the data from the database
	 *		and sets up the objects
	 *
	 * @param void
	 * @action pulls the game data
	 * @return void
	 */
	protected function _pull( )
	{
		call(__METHOD__);

		if ( ! $this->id) {
			return false;
		}

		if ( ! $_SESSION['player_id']) {
			throw new MyException(__METHOD__.': Player id is not in session when pulling game data');
		}

		// grab the game data
		$query = "
			SELECT *
			FROM ".self::GAME_TABLE."
			WHERE game_id = '{$this->id}'
		";
		$result = $this->_mysql->fetch_assoc($query);
		call($result);

		if ( ! $result) {
			throw new MyException(__METHOD__.': Game data not found for game #'.$this->id);
		}

		// set the properties
		$this->state = $result['state'];
		$this->paused = (bool) $result['paused'];
		$this->create_date = strtotime($result['create_date']);
		$this->modify_date = strtotime($result['modify_date']);

		$this->_extra_info = array_merge_plus(self::$_EXTRA_INFO_DEFAULTS, unserialize($result['extra_info']));

		$this->_quarto->small_square_matches = $this->_extra_info['small_square_matches'];
		$this->_quarto->small_square_torus = $this->_extra_info['small_square_matches'] && $this->_extra_info['small_square_torus'];
		$this->_quarto->diagonal_torus = $this->_extra_info['diagonal_torus'];

		// set up the players
		$this->_players['white']['player_id'] = $result['white_id'];
		$this->_players['white']['object'] = new GamePlayer($result['white_id']);

		$this->_players['black']['player_id'] = $result['black_id'];
		if (0 != $result['black_id']) { // we may have an open game
			$this->_players['black']['object'] = new GamePlayer($result['black_id']);
		}

		// we test this first one against the black id, so if it fails because
		// the person viewing the game is not playing in the game (viewing it
		// after it's finished) we want "player" to be equal to "white"
		if ($_SESSION['player_id'] == $result['black_id']) {
			$this->_players['player'] = & $this->_players['black'];
			$this->_players['player']['color'] = 'black';
			$this->_players['player']['opp_color'] = 'white';
			$this->_players['opponent'] = & $this->_players['white'];
			$this->_players['opponent']['color'] = 'white';
			$this->_players['opponent']['opp_color'] = 'black';
		}
		else {
			$this->_players['player'] = & $this->_players['white'];
			$this->_players['player']['color'] = 'white';
			$this->_players['player']['opp_color'] = 'black';
			$this->_players['opponent'] = & $this->_players['black'];
			$this->_players['opponent']['color'] = 'black';
			$this->_players['opponent']['opp_color'] = 'white';
		}

		// set up the board
		$query = "
			SELECT *
			FROM ".self::GAME_BOARD_TABLE."
			WHERE game_id = '{$this->id}'
			ORDER BY move_date DESC
		";
		$result = $this->_mysql->fetch_array($query);
		call($result);

		if ($result) {
			$this->_history = $result;
			$this->last_move = strtotime($result[0]['move_date']);

			$this->turn = 'white';
			if (0 != (count($result) % 2)) {
				$this->turn = 'black';
			}

			try {
				$this->_quarto->board = $this->_history[0]['board'];
			}
			catch (MyException $e) {
				throw $e;
			}

			if ( ! is_null($result[0]['next_piece'])) {
				$this->_quarto->next_piece = $result[0]['next_piece'];
			}
		}
		else {
			$this->last_move = $this->create_date;
			$this->turn = 'white';
		}

		$this->_players[$this->turn]['turn'] = true;
	}


	/** protected function _save
	 *		Saves all changed data to the database
	 *
	 * @param void
	 * @action saves the game data
	 * @return void
	 */
	protected function _save( )
	{
		call(__METHOD__);

		// make sure we don't have a MySQL error here, it may be causing the issues
		$run_once = false;
		do {
			if ($run_once) {
				// pause for 3 seconds, then try again
				sleep(3);
			}

			// update the game data
			$query = "
				SELECT extra_info
					, state
					, modify_date
				FROM ".self::GAME_TABLE."
				WHERE game_id = '{$this->id}'
			";
			$game = $this->_mysql->fetch_assoc($query);
			call($game);

			// make sure we don't have a MySQL error here, it may be causing the issues
			$error = $this->_mysql->error;
			$errno = preg_replace('/(\\d+)/', '$1', $error);

			$run_once = true;
		}
		while (2006 == $errno || 2013 == $errno);

		$update_modified = false;

		if ( ! $game) {
			throw new MyException(__METHOD__.': Game data not found for game #'.$this->id);
		}

		$this->_log('DATA SAVE: #'.$this->id.' @ '.time( )."\n".' - '.$this->modify_date."\n".' - '.strtotime($game['modify_date']));

		// test the modified date and make sure we still have valid data
		call($this->modify_date);
		call(strtotime($game['modify_date']));
		if ($this->modify_date != strtotime($game['modify_date'])) {
			$this->_log('== FAILED ==');
			throw new MyException(__METHOD__.': Trying to save game (#'.$this->id.') with out of sync data');
		}

		$update_game = false;
		call($game['state']);
		call($this->state);
		if ($game['state'] != $this->state) {
			$update_game['state'] = $this->state;
		}

		$diff = array_compare($this->_extra_info, self::$_EXTRA_INFO_DEFAULTS);
		$update_game['extra_info'] = $diff[0];
		ksort($update_game['extra_info']);

		$update_game['extra_info'] = serialize($update_game['extra_info']);

		if ('a:0:{}' == $update_game['extra_info']) {
			$update_game['extra_info'] = null;
		}

		if (0 === strcmp($game['extra_info'], $update_game['extra_info'])) {
			unset($update_game['extra_info']);
		}

		if ($update_game) {
			$update_modified = true;
			$this->_mysql->insert(self::GAME_TABLE, $update_game, " WHERE game_id = '{$this->id}' ");
		}

		// update the board
		// grab the current board from the database
		$query = "
			SELECT *
			FROM ".self::GAME_BOARD_TABLE."
			WHERE game_id = '{$this->id}'
			ORDER BY move_date DESC
			LIMIT 1
		";
		$board = $this->_mysql->fetch_assoc($query);
		call($board);

		if ( ! isset($board['board']) || ($board['board'] != $this->_quarto->board)) {
			call('UPDATED BOARD');
			$update_modified = true;
			$this->_mysql->insert(self::GAME_BOARD_TABLE, array('board' => $this->_quarto->board, 'next_piece' => $this->_quarto->next_piece, 'game_id' => $this->id));
		}

		// update the game modified date
		if ($update_modified) {
			$this->_mysql->insert(self::GAME_TABLE, array('modify_date' => NULL), " WHERE game_id = '{$this->id}' ");
		}
	}


	/** protected function _log
	 *		Report messages to a file
	 *
	 * @param string message
	 * @action log messages to file
	 * @return void
	 */
	protected function _log($msg)
	{
		// log the error
		if (false && class_exists('Log')) {
			Log::write($msg, __CLASS__);
		}
	}


	/** protected function _diff
	 *		Compares two boards are returns the
	 *		indexes of any differences
	 *
	 * @param string board
	 * @param string board
	 * @return array of difference indexes
	 */
	protected function _diff($board1, $board2)
	{
		$diff = array( );
		for ($i = 0; $i < 16; ++$i) {
			if ($board1[$i] != $board2[$i]) {
				$diff[] = $i;
			}
		}
		call($diff);

		return $diff;
	}


	/**
	 *		STATIC METHODS
	 * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/** static public function get_list
	 *		Returns a list array of all the games in the database
	 *		with games which need the users attention highlighted
	 *
	 *		NOTE: $player_id is required when not pulling all games
	 *		(when $all is false)
	 *
	 * @param int optional player's id
	 * @param bool optional pull all games (vs only given player's games)
	 * @return array game list (or bool false on failure)
	 */
	static public function get_list($player_id = 0, $all = true)
	{
		$Mysql = Mysql::get_instance( );

		$player_id = (int) $player_id;

		if ( ! $all && ! $player_id) {
			throw new MyException(__METHOD__.': Player ID required when not pulling all games');
		}

		$WHERE = " WHERE G.state <> 'Waiting' ";
		if ( ! $all) {
			$WHERE .= "
					AND G.state NOT IN ('Finished', 'Draw')
					AND (G.white_id = {$player_id}
						OR G.black_id = {$player_id})
			";
		}

		$query = "
			SELECT G.*
				, IF((0 = MAX(GB.move_date)) OR MAX(GB.move_date) IS NULL, G.create_date, MAX(GB.move_date)) AS last_move
				, 0 AS my_turn
				, 0 AS in_game
				, W.username AS white
				, B.username AS black
			FROM ".self::GAME_TABLE." AS G
				LEFT JOIN ".self::GAME_BOARD_TABLE." AS GB
					ON GB.game_id = G.game_id
				LEFT JOIN ".Player::PLAYER_TABLE." AS W
					ON W.player_id = G.white_id
				LEFT JOIN ".Player::PLAYER_TABLE." AS B
					ON B.player_id = G.black_id
			{$WHERE}
			GROUP BY game_id
			ORDER BY state ASC
				, last_move ASC
		";
		$list = $Mysql->fetch_array($query);

		if (0 != $player_id) {
			// run though the list and find games the user needs action on
			foreach ($list as $key => $game) {
				$query = "
					SELECT COUNT(game_id) AS count
					FROM ".self::GAME_BOARD_TABLE."
					WHERE game_id = '{$game['game_id']}'
					GROUP BY game_id
				";
				$count = $Mysql->fetch_value($query);

				$game['in_game'] = (int) (($player_id == $game['white_id']) || ($player_id == $game['black_id']));

				$turn = 'white';
				if (0 != ($count % 2)) {
					$turn = 'black';
				}

				$game['my_turn'] = (int) ($player_id == $game[$turn.'_id']);

				if (in_array($game['state'], array('Finished', 'Draw'))) {
					$game['my_turn'] = 0;
					$game['in_game'] = 1;
				}

				$game['my_color'] = ($player_id == $game['white_id']) ? 'white' : 'black';
				$game['opp_color'] = ($player_id == $game['white_id']) ? 'black' : 'white';

				$game['opponent'] = ($player_id == $game['white_id']) ? $game['black'] : $game['white'];

				$list[$key] = $game;
			}
		}

		return $list;
	}


	/** static public function get_invites
	 *		Returns a list array of all the invites in the database
	 *		for the given player
	 *
	 * @param int player's id
	 * @return array game list (or bool false on failure)
	 */
	static public function get_invites($player_id)
	{
		$Mysql = Mysql::get_instance( );

		$player_id = (int) $player_id;

		$query = "
			SELECT G.*
				, IF((0 = MAX(GB.move_date)) OR MAX(GB.move_date) IS NULL, G.create_date, MAX(GB.move_date)) AS last_move
				, (G.black_id = {$player_id}) AS invite
				, W.username AS white
				, B.username AS black
			FROM ".self::GAME_TABLE." AS G
				LEFT JOIN ".self::GAME_BOARD_TABLE." AS GB
					ON GB.game_id = G.game_id
				LEFT JOIN ".Player::PLAYER_TABLE." AS W
					ON W.player_id = G.white_id
				LEFT JOIN ".Player::PLAYER_TABLE." AS B
					ON B.player_id = G.black_id
			WHERE G.state = 'Waiting'
				AND (G.white_id = {$player_id}
					OR G.black_id = {$player_id})
			GROUP BY game_id
			ORDER BY invite DESC
				, last_move DESC
		";
		$list = $Mysql->fetch_array($query);

		return $list;
	}


	/** static public function get_count
	 *		Returns a count of all games in the database,
	 *		as well as the highest game id (the total number of games played)
	 *
	 * @param void
	 * @return array (int current game count, int total game count)
	 */
	static public function get_count($player_id = 0)
	{
		$Mysql = Mysql::get_instance( );

		$player_id = (int) $player_id;

		// games in play
		$query = "
			SELECT COUNT(*)
			FROM ".self::GAME_TABLE."
			WHERE state NOT IN ('Finished', 'Draw')
		";
		$count = $Mysql->fetch_value($query);

		// total games
		$query = "
			SELECT MAX(game_id)
			FROM ".self::GAME_TABLE."
		";
		$next = $Mysql->fetch_value($query);

		return array($count, $next);
	}


	/** public function delete_inactive
	 *		Deletes the inactive games from the database
	 *
	 * @param int age in days
	 * @action deletes the inactive games
	 * @return void
	 */
	static public function delete_inactive($age)
	{
		$Mysql = Mysql::get_instance( );

		$age = (int) $age;

		$query = "
			SELECT game_id
			FROM ".self::GAME_TABLE."
			WHERE modify_date < DATE_SUB(NOW( ), INTERVAL {$age} DAY)
		";
		$game_ids = $Mysql->fetch_value_array($query);

		if ($game_ids) {
			self::delete($game_ids);
		}
	}


	/** public function delete_finished
	 *		Deletes the finished games from the database
	 *
	 * @param int age in days
	 * @action deletes the finished games
	 * @return void
	 */
	static public function delete_finished($age)
	{
		$Mysql = Mysql::get_instance( );

		$age = (int) $age;

		$query = "
			SELECT game_id
			FROM ".self::GAME_TABLE."
			WHERE state IN ('Finished', 'Draw')
				AND modify_date < DATE_SUB(NOW( ), INTERVAL {$age} DAY)
		";
		$game_ids = $Mysql->fetch_value_array($query);

		if ($game_ids) {
			self::delete($game_ids);
		}
	}


	/** static public function delete
	 *		Deletes the given game and all related data
	 *
	 * @param mixed array or csv of game ids
	 * @action deletes the game and all related data from the database
	 * @return void
	 */
	static public function delete($ids)
	{
		$Mysql = Mysql::get_instance( );

		array_trim($ids, 'int');

		if (empty($ids)) {
			throw new MyException(__METHOD__.': No game ids given');
		}

#		foreach ($ids as $id) {
#			self::write_game_file($id);
#		}

		$tables = array(
			self::GAME_BOARD_TABLE ,
			self::GAME_TABLE ,
		);

		$Mysql->multi_delete($tables, " WHERE game_id IN (".implode(',', $ids).") ");

		$query = "
			OPTIMIZE TABLE ".self::GAME_TABLE."
				, ".self::GAME_BOARD_TABLE."
		";
		$Mysql->query($query);
	}


	/** static public function player_deleted
	 *		Deletes the games the given players are in
	 *
	 * @param mixed array or csv of player ids
	 * @action deletes the players games
	 * @return void
	 */
	static public function player_deleted($ids)
	{
		$Mysql = Mysql::get_instance( );

		array_trim($ids, 'int');

		if (empty($ids)) {
			throw new MyException(__METHOD__.': No player ids given');
		}

		$query = "
			SELECT DISTINCT(game_id)
			FROM ".self::GAME_TABLE."
			WHERE white_id IN (".implode(',', $ids).")
				OR black_id IN (".implode(',', $ids).")
		";
		$game_ids = $Mysql->fetch_value_array($query);

		if ($game_ids) {
			self::delete($game_ids);
		}
	}


	/** static public function pause
	 *		Pauses the given games
	 *
	 * @param mixed array or csv of game ids
	 * @param bool optional pause game (false = unpause)
	 * @action pauses the games
	 * @return void
	 */
	static public function pause($ids, $pause = true)
	{
		$Mysql = Mysql::get_instance( );

		array_trim($ids, 'int');

		$pause = (int) (bool) $pause;

		if (empty($ids)) {
			throw new MyException(__METHOD__.': No game ids given');
		}

		$Mysql->insert(self::GAME_TABLE, array('paused' => $pause), " WHERE game_id IN (".implode(',', $ids).") ");
	}


} // end of Game class


/*		schemas
// ===================================

Game table
----------------------
CREATE TABLE IF NOT EXISTS `qu_game` (
  `game_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `white_id` int(10) unsigned DEFAULT NULL,
  `black_id` int(10) unsigned DEFAULT NULL,
  `extra_info` text DEFAULT NULL,
  `state` enum('Waiting', 'Playing', 'Finished', 'Draw') COLLATE latin1_general_ci NOT NULL DEFAULT 'Waiting',
  `paused` tinyint(1) NOT NULL DEFAULT '0',
  `create_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modify_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`game_id`),
  KEY `state` (`state`),
  KEY `white_id` (`white_id`),
  KEY `black_id` (`black_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci ;


Boards Table
----------------------
CREATE TABLE IF NOT EXISTS `qu_game_board` (
  `game_id` int(10) unsigned NOT NULL DEFAULT 0,
  `board` char(16) COLLATE latin1_general_ci DEFAULT NULL,
  `next_piece` char(1) COLLATE latin1_general_ci DEFAULT NULL,
  `move_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',

  KEY `game_id` (`game_id`),
  KEY `move_date` (`move_date`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci ;

-- --------------------------------------------------------

--
-- Table structure for table `qu_game_nudge`
--

DROP TABLE IF EXISTS `qu_game_nudge`;
CREATE TABLE IF NOT EXISTS `qu_game_nudge` (
  `game_id` int(10) unsigned NOT NULL DEFAULT '0',
  `player_id` int(10) unsigned NOT NULL DEFAULT '0',
  `nudged` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY `game_player` (`game_id`,`player_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci ;



*/


