<?php

namespace MediaWiki\Extension\SecurePoll;

use InvalidArgumentException;

class MailingListEntry {
	/** @var string */
	public $wiki;
	/** @var string */
	public $siteName;
	/** @var string */
	public $userName;
	/** @var string */
	public $email;
	/** @var string */
	public $language;
	/** @var string|int */
	public $editCount;

	public static function newFromString( $str ) {
		$fields = explode( "\t", rtrim( $str, "\n" ) );
		if ( count( $fields ) !== 6 ) {
			throw new InvalidArgumentException( 'Invalid mailing list entry' );
		}
		$entry = new self;
		$entry->wiki = $fields[0];
		$entry->siteName = $fields[1];
		$entry->userName = $fields[2];
		$entry->email = $fields[3];
		$entry->language = $fields[4];
		$entry->editCount = $fields[5];
		return $entry;
	}

	public function toString() {
		return implode( "\t",
			[
				$this->wiki,
				$this->siteName,
				$this->userName,
				$this->email,
				$this->language,
				$this->editCount
			]
		) . "\n";
	}
}
