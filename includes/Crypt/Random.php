<?php

namespace MediaWiki\Extension\SecurePoll\Crypt;

use MWException;
use Status;

class Random {
	/** @var resource|null */
	public $urandom;

	/**
	 * Open a /dev/urandom file handle
	 * @return Status
	 */
	public function open() {
		if ( $this->urandom ) {
			return Status::newGood();
		}

		if ( wfIsWindows() ) {
			return Status::newFatal( 'securepoll-urandom-not-supported' );
		}
		$this->urandom = fopen( '/dev/urandom', 'rb' );
		if ( !$this->urandom ) {
			return Status::newFatal( 'securepoll-dump-no-urandom' );
		}

		return Status::newGood();
	}

	/**
	 * Close any open file handles
	 */
	public function close() {
		if ( $this->urandom ) {
			fclose( $this->urandom );
			$this->urandom = null;
		}
	}

	/**
	 * Get a random integer between 0 and ($maxp1 - 1).
	 * Should only be called after open() succeeds.
	 * @param int $maxp1
	 * @return int
	 */
	public function getInt( $maxp1 ) {
		$numBytes = ceil( strlen( base_convert( (string)$maxp1, 10, 16 ) ) / 2 );
		if ( $numBytes == 0 ) {
			return 0;
		}
		$data = fread( $this->urandom, $numBytes );
		if ( strlen( $data ) != $numBytes ) {
			throw new MWException( __METHOD__ . ': not enough bytes' );
		}
		$x = 0;
		for ( $i = 0; $i < $numBytes; $i++ ) {
			$x *= 256;
			$x += ord( substr( $data, $i, 1 ) );
		}

		return $x % $maxp1;
	}

	/**
	 * Works like shuffle() except more secure. Returns the new array instead
	 * of modifying it. The algorithm is the Knuth/Durstenfeld kind.
	 * @param array $a
	 * @return array
	 */
	public function shuffle( $a ) {
		$a = array_values( $a );
		for ( $i = count( $a ) - 1; $i >= 0; $i-- ) {
			$target = $this->getInt( $i + 1 );
			$tmp = $a[$i];
			$a[$i] = $a[$target];
			$a[$target] = $tmp;
		}

		return $a;
	}
}
