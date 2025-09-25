<?php

namespace MediaWiki\Extension\SecurePoll;

use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Exceptions\InvalidDataException;

class DumpElection {
	/**
	 * @param Election $election
	 * @param array $confOptions
	 * @param bool $withVotes
	 *
	 *  Election conf xml options are:
	 *  - jump: boolean
	 *  - langs: array
	 *  - private: boolean
	 *
	 * @return string
	 * @throws InvalidDataException
	 */
	public static function createXMLDump( $election, $confOptions = [], $withVotes = true ) {
		$confXml = $election->getConfXml( $confOptions );
		$dump = "<SecurePoll>\n<election>\n$confXml";

		if ( $withVotes ) {
			$status = $election->dumpVotesToCallback( static function ( $election, $row ) use ( &$dump ) {
				$dump .= "<vote>\n" . htmlspecialchars( rtrim( $row->vote_record ) ) . "\n</vote>\n";
			} );

			if ( !$status->isOK() ) {
				throw new InvalidDataException( $status->getWikiText() );
			}
		}

		$dump .= "</election>\n</SecurePoll>\n";

		return $dump;
	}
}
