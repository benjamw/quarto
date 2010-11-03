<?php
/*
+---------------------------------------------------------------------------
|
|   quarto.class.php (php 5.x)
|
|   by Benjam Welker
|   http://iohelix.net
|
+---------------------------------------------------------------------------
|
|	This module is built to play the game of Quarto, it cares not about
|	database structure or the goings on of the website, only about Quarto
|
+---------------------------------------------------------------------------
|
|   > Quarto Game module
|   > Date started: 2009-05-12
|
|   > Module Version Number: 0.8.0
|
+---------------------------------------------------------------------------
*/

// TODO: comments & organize better

require_once INCLUDE_DIR.'func.bitwise.php';

class Quarto
{

	/**
	 *		PROPERTIES
	 * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/** protected property board
	 *		Holds the game board
	 *
	 * @var string
	 */
	protected $board;


	/** protected property next_piece
	 *		Holds the next piece to be played
	 *
	 * @var string
	 */
	protected $next_piece;


	/** protected property small_square_matches
	 *		Holds the flag showing whether or not
	 *		small square matching is allowed
	 *
	 * @var bool
	 */
	protected $small_square_matches = false;


	/** protected property small_square_torus
	 *		Holds the flag showing whether or not
	 *		small square matching is allowed on edges
	 *		and wrapping the board
	 *
	 * @var bool
	 */
	protected $small_square_torus = false;


	/** protected property diagonal_torus
	 *		Holds the flag showing whether or not
	 *		diagonal matching is allowed on edges
	 *		and wrapping the board
	 *
	 * @var bool
	 */
	protected $diagonal_torus = false;


	/** protected property target_human
	 *		Holds the human readable target
	 *		(A4, C3, D1, etc)
	 *
	 * @var string
	 */
	protected $target_human;


	/** protected property target_index
	 *		Holds the computer readable
	 *		string index (12, 10, 3, etc)
	 *
	 * @var int
	 */
	protected $target_index;



	/**
	 *		METHODS
	 * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/** public function __construct
	 *		Class constructor
	 *		Sets all outside data
	 *
	 * @param string optional board
	 * @action instantiates object
	 * @return void
	 */
	public function __construct($board = null)
	{
		call(__METHOD__);

		$this->clear_board( );

		if ( ! empty($board)) {
			try {
				$this->set_board($board);
			}
			catch (MyException $e) {
				throw $e;
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
		if ( ! property_exists($this, $property)) {
			throw new MyException(__METHOD__.': Trying to access non-existent property ('.$property.')', 2);
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
		if ( ! property_exists($this, $property)) {
			throw new MyException(__METHOD__.': Trying to access non-existent property ('.$property.')', 3);
		}

		if ('_' === $property[0]) {
			throw new MyException(__METHOD__.': Trying to access _private property ('.$property.')', 3);
		}

		switch ($property) {
			case 'board' :
				try {
					$this->set_board($value);
				}
				catch (MyException $e) {
					throw $e;
				}
				break;

			case 'next_piece' :
				try {
					$this->set_next_piece($value);
				}
				catch (MyException $e) {
					throw $e;
				}
				break;

			default :
				// do nothing
				break;
		}

		$this->$property = $value;
	}


	/** public function __toString
	 *		Returns the ascii version of the board
	 *		when asked to output the object
	 *
	 * @param void
	 * @return string ascii version of the board
	 */
	public function __toString( )
	{
		return $this->get_board_ascii( );
	}


	/** public function set_board
	 *		Sets the board
	 *
	 * @param string board
	 * @action validation
	 * @return void
	 */
	public function set_board($board)
	{
		call(__METHOD__);
		call($board);

		$board = (string) $board;

		if (16 != strlen($board)) {
			throw new MyException(__METHOD__.': The board given is not the right size');
		}

		if (preg_match('/[^0-9a-f.]/i', $board)) {
			throw new MyException(__METHOD__.': The board given has invalid characters');
		}

		$this->board = strtoupper($board);
	}


	/** public function clear_board
	 *		Clears the board by setting all
	 *		squares to '.'
	 *
	 * @param void
	 * @action clears the board
	 * @return void
	 */
	public function clear_board( )
	{
		call(__METHOD__);

		$this->board = str_repeat('.', 16);
	}


	/** public function random_board
	 *		Randomizes the board by setting all
	 *		squares to a random piece
	 *
	 * @param void
	 * @action sets a random board
	 * @return void
	 */
	public function random_board( )
	{
		call(__METHOD__);

		for ($i = 0; $i < 16; ++$i) {
			$pieces[] = strtoupper(dechex($i));
		}

		shuffle($pieces);
		shuffle($pieces);

		$this->board = implode('', $pieces);

		return $this->board;
	}


	/** public function get_board_ascii
	 *		Gets an ascii version of the board
	 *		for debuging
	 *
	 * @param void
	 * @return string ascii version of the board
	 */
	public function get_board_ascii($board = null)
	{
		call(__METHOD__);

		if (is_null($board)) {
			$board = $this->board;
		}
		call($board);

		$output = '';
		for ($i = 0; $i < 4; ++$i) {
			for ($j = 0; $j < 4; ++$j) {
				$output .= $board[($i * 4) + $j] . ' ';
			}

			$output = trim($output) . "\n";
		}

		return $output;
	}


	/** public function get_missing_pieces
	 *		Finds all the pieces that need to be placed
	 *
	 * @param bool include the next piece
	 * @return array missing pieces
	 */
	public function get_missing_pieces($include_next_piece = false)
	{
		call(__METHOD__);
		$include_next_piece = (bool) $include_next_piece;

		$pieces = array( );
		$board = $this->board;
		call($board);

		for ($i = 0; $i < 16; ++$i) {
			// convert to hex
			$piece = strtoupper(dechex($i));

			if (false === strpos($this->board, $piece)) {
				if (($piece == $this->next_piece) && ! $include_next_piece) {
					call($this->next_piece);
					continue;
				}

				$pieces[] = strtoupper($piece);
			}
		}
		call($pieces);

		return $pieces;
	}


	public function set_next_piece($piece)
	{
		call(__METHOD__);
		$piece = strtoupper($piece);

		// make sure piece is a proper piece
		if ( ! preg_match('/^[0-9A-F]$/', $piece)) {
			throw new MyException(__METHOD__.': Piece given ('.$piece.') is not a proper piece');
		}

		// test if this piece is available
		if ( ! in_array($piece, $this->get_missing_pieces( ))) {
			throw new MyException(__METHOD__.': Piece given ('.$piece.') is not available');
		}

		$this->next_piece = $piece;
	}


	/** public function do_move
	 *		Places the piece on the given square
	 *
	 * @param int board index (or target)
	 * @action places the piece
	 * @return void
	 */
	public function do_move($index, $piece = null)
	{
		call(__METHOD__);
		call($index);

		try {
			$index = $this->target_to_index($index);
		}
		catch (MyException $e) {
			throw $e;
		}

		if (null == $piece) {
			if (null == $this->next_piece) {
				throw new MyException(__METHOD__.': No piece given to place');
			}

			$piece = $this->next_piece;
		}

		try {
			$this->_place_piece($piece, $index);
		}
		catch (MyException $e) {
			throw $e;
		}

		$this->next_piece = null;

		return $this->get_outcome( );
	}


	/** public function get_outcome
	 *		Returns the current outcome of the previous move
	 *
	 * @param void
	 * @return bool game winner
	 */
	public function get_outcome( )
	{
		call(__METHOD__);
		call($this->get_board_ascii( ));

		$return = array( );

		// search the board for matching attributes

		// search vertical
		for ($x = 0; $x < 4; ++$x) {
			for ($y = 0; $y < 4; ++$y) {
				$index = $x + ($y * 4);
				$piece = $this->board[$index];

				if ('.' == $piece) {
					$and = $not = false;
					break;
				}

				if (0 == $y) {
					$and = hexdec($piece);
					$not = trunc( ~ hexdec($piece), 4);
				}
				else {
					$and = $and & hexdec($piece);
					$not = trunc($not & ( ~ hexdec($piece)), 4);
				}

				// no need to keep searching if the ones we have don't match
				if ( ! $and && ! $not) {
					break;
				}
			}

			if ($and || $not) {
				$cols = array('A', 'B', 'C', 'D');
				$return[$cols[$x]] = $and | $not;
			}
		}

		// search horizontal
		for ($y = 0; $y < 4; ++$y) {
			for ($x = 0; $x < 4; ++$x) {
				$index = $x + ($y * 4);
				$piece = $this->board[$index];

				if ('.' == $piece) {
					$and = $not = false;
					break;
				}

				if (0 == $x) {
					$and = hexdec($piece);
					$not = trunc( ~ hexdec($piece), 4);
				}
				else {
					$and = $and & hexdec($piece);
					$not = trunc($not & ( ~ hexdec($piece)), 4);
				}

				// no need to keep searching if the ones we have don't match
				if ( ! $and && ! $not) {
					break;
				}
			}

			if ($and || $not) {
				$return[$y + 1] = $and | $not;
			}
		}

		// search diagonal
		for ($i = 0; $i < 2; ++$i) {
			for ($j = 0; $j < 4; ++$j) {
				for ($k = 0; $k < 4; ++$k) {
					if ( ! $this->diagonal_torus && (0 < $j)) {
						$and = $not = false;
						continue;
					}

					$x = $k + $j;
					$y = $k;

					while (4 <= $x) {
						$x -= 4;
					}

					if ($i) {
						$x = 3 - $x;
					}

					$index = $x + ($y * 4);
					$piece = $this->board[$index];

					if ('.' == $piece) {
						$and = $not = false;
						break;
					}

					if (0 == $k) {
						$and = hexdec($piece);
						$not = trunc( ~ hexdec($piece), 4);
					}
					else {
						$and = $and & hexdec($piece);
						$not = trunc($not & ( ~ hexdec($piece)), 4);
					}

					// no need to keep searching if the ones we have don't match
					if ( ! $and && ! $not) {
						break;
					}
				}

				if ($and || $not) {
					$diags = array('\\', '/');

					$l = $j;
					if ($i) {
						$l = 3 - $j;
					}

					$return[$diags[$i].' '.chr(65 + $l)] = $and | $not;
				}
			}
		}

		// search small squares
		if ($this->small_square_matches) {
			for ($i = 0; $i < 16; ++$i) {
				for ($j = 0; $j < 4; ++$j) {
					$x = $j % 2; // vert addition
					$y = (int) floor($j / 2) * 4; // horz addition

					// fix for edges
					if (3 == ($i % 4)) { // right edge
						if ( ! $this->small_square_torus) {
							$and = $not = false;
							break;
						}

						$x *= -3; // adjust vert
					}

					if (12 <= $i) { // bottom edge
						if ( ! $this->small_square_torus) {
							$and = $not = false;
							break;
						}

						$y *= -3; // adjust horz
					}

					if ( ! $this->small_square_torus && (3 == ($i % 4)) && (12 <= $i)) {
						$and = $not = false;
						break;
					}

					$index = $i + $x + $y;
					$piece = $this->board[$index];

					if ('.' == $piece) {
						$and = $not = false;
						break;
					}

					if (0 == $j) {
						$and = hexdec($piece);
						$not = trunc( ~ hexdec($piece), 4);
					}
					else {
						$and = $and & hexdec($piece);
						$not = trunc($not & ( ~ hexdec($piece)), 4);
					}

					// no need to keep searching if the ones we have don't match
					if ( ! $and && ! $not) {
						break;
					}
				}

				if ($and || $not) {
					$return[$this->index_to_target($i)] = $and | $not;
				}
			}
		}

		// check if the game is over due to draw
		if ( ! $return && (false === strpos($this->board, '.'))) {
			$return = array('DRAW');
		}

		call($return);
		return $return;
	}


	/** public function target_to_index
	 *		Converts a human target (E5) to
	 *		a computer string index
	 *
	 * @param optional string human target (E5)
	 * @return int computer string index
	 */
	public function target_to_index($target = false)
	{
		call(__METHOD__);

		// if it's already an index, just return it
		if ((string) $target === (string) (int) $target) {
			$this->target_index = (int) $target;
			return $this->target_index;
		}

		if (false === $target) {
			$target = $this->target_human;
		}
		else {
			try {
				$target = $this->validate_target($target);
				$this->target_human = $target;
			}
			catch (MyException $e) {
				throw $e;
			}
		}

		switch (strtoupper($target[0])) {
			case 'A' : $index = 0; break;
			case 'B' : $index = 1; break;
			case 'C' : $index = 2; break;
			case 'D' : $index = 3; break;
		}

		$number = (int) substr($target, 1);

		$this->target_index = (int) ($index + (($number - 1) * 4));

		return $this->target_index;
	}


	/** public function index_to_target
	 *		Converts a computer string index to
	 *		a human target (E5)
	 *
	 * @param int computer string index
	 * @return string human target (E5)
	 */
	public function index_to_target($index)
	{
#		call(__METHOD__);

		// if it's already a target, just return it
		if ((string) $index !== (string) (int) $index) {
			return $index;
		}

		$cols = array('A','B','C','D');

		$target = $cols[($index % 4)];
		$target .= floor($index / 4) + 1;

		return $target;
	}


	/** protected function _place_piece
	 *		Places the piece on the board
	 *
	 * @param string piece code in hex format
	 * @param int piece location index
	 * @action places the boat
	 * @return void
	 */
	protected function _place_piece($piece, $index)
	{
		call(__METHOD__);
		call($piece);
		call($index);

		// get a local copy of the board
		// so we don't bork things later if it fails
		$board = $this->board;

		$pieces = $this->get_missing_pieces(true);

		// make sure it's a valid index
		if ((0 > $index) || (15 < $index)) {
			throw new MyException(__METHOD__.': Invalid board index given ('.$index.')');
		}

		// make sure we can place this piece
		if ( ! in_array($piece, $pieces)) {
			throw new MyException(__METHOD__.': This piece is not available to place', 103);
		}

		// make sure the space is not occupied
		if ('.' != $this->board[$index]) {
			throw new MyException(__METHOD__.': This space is already occupied', 103);
		}

		$this->board[$index] = $piece;
	}


	/** protected function _validate_target
	 *		Validates the given human readable target
	 *		and saves it internally
	 *
	 * @param string human readable target
	 * @action validates and stores human readable target
	 * @return string human readable target
	 */
	protected function _validate_target($target)
	{
		call(__METHOD__);

		// make sure the first character is A-D
		// and the second is 1-4

		$target = strtoupper((string) $target);

		if (0 == preg_match('/^[A-D]/', $target)) {
			throw new MyException(__METHOD__.': Target has invalid first character');
		}

		// strip off the first character to get the number
		$number = (int) substr($target, 1);

		if ((0 >= $number) || (5 <= $number)) {
			throw new MyException(__METHOD__.': Target has invalid second characters');
		}

		// if it makes it here, it's all good
		$this->target_human = $target;
		return $this->target_human;
	}

} // end of Quarto class

