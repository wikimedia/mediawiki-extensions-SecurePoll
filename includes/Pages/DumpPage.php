<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use Exception;
use InvalidArgumentException;
use MediaWiki\Extension\SecurePoll\DumpElection;

/**
 * Special:SecurePoll subpage for exporting encrypted election records.
 */
class DumpPage extends ActionPage {

	/**
	 * Execute the subpage.
	 *
	 * @param array $params Array of subpage parameters.
	 *
	 * @throws InvalidArgumentException
	 */
	public function execute( $params ) {
		$out = $this->specialPage->getOutput();

		if ( !count( $params ) ) {
			$out->addWikiMsg( 'securepoll-too-few-params' );

			return;
		}

		$electionId = intval( $params[0] );
		$format = $this->getFormatFromRequest();

		$this->election = $this->context->getElection( $electionId );
		if ( !$this->election ) {
			$out->addWikiMsg( 'securepoll-invalid-election', $electionId );

			return;
		}
		$this->initLanguage( $this->specialPage->getUser(), $this->election );

		$out->setPageTitleMsg( $this->msg( 'securepoll-dump-title', $this->election->getMessage( 'title' ) ) );

		if ( !$this->election->isFinished() ) {
			$out->addWikiMsg(
				'securepoll-dump-not-finished',
				$this->specialPage->getLanguage()->date( $this->election->getEndDate() ),
				$this->specialPage->getLanguage()->time( $this->election->getEndDate() )
			);

			return;
		}

		$isAdmin = $this->election->isAdmin( $this->specialPage->getUser() );
		if ( $this->election->getProperty( 'voter-privacy' ) && !$isAdmin ) {
			$out->addWikiMsg( 'securepoll-dump-private' );

			return;
		}

		try {
			if ( $format === "blt" ) {
				$dump = DumpElection::createBLTDump( $this->election );
			} else {
				$dump = DumpElection::createXMLDump( $this->election );
			}
		} catch ( Exception $e ) {
			$out->addWikiTextAsInterface( $e->getMessage() );

			return;
		}

		$this->sendHeaders();
		echo $dump;
	}

	public function sendHeaders() {
		$this->specialPage->getOutput()->disable();
		header( 'Content-Type: application/vnd.mediawiki.securepoll' );
		$electionId = $this->election->getId();
		$filename = urlencode( "$electionId-" . wfTimestampNow() . '.securepoll' );
		header( "Content-Disposition: attachment; filename=$filename" );
		$this->context->setLanguages( [ $this->election->getLanguage() ] );
	}

	/**
	 * Valid formats are
	 * - xml
	 * - blt
	 *
	 * Default is xml
	 *
	 * @return string
	 * @throws InvalidArgumentException
	 */
	private function getFormatFromRequest(): string {
		$request = $this->specialPage->getRequest();
		$queryParams = $request->getQueryValues();

		if ( empty( $queryParams['format'] ) ) {
			return "xml";
		}

		$format = $queryParams['format'];
		if ( $format !== "xml" && $format !== "blt" ) {
			throw new InvalidArgumentException( "Invalid format" );
		}

		return $format;
	}
}
