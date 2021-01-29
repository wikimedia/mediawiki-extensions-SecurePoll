<?php

namespace MediaWiki\Extensions\SecurePoll;

use HTML;
use Linker;
use ReverseChronologicalPager;
use User;

class SecurePollLogPager extends ReverseChronologicalPager {
	/** @var Context */
	private $context;

	/**
	 * @param Context $context
	 */
	public function __construct( Context $context ) {
		parent::__construct();
		$this->context = $context;
	}

	/**
	 * @inheritDoc
	 */
	public function getQueryInfo() {
		return [
			'tables' => [ 'securepoll_log' ],
			'fields' => [
				'spl_id',
				'spl_timestamp',
				'spl_election_id',
				'spl_user',
				'spl_type',
				'spl_target',
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getIndexField() {
		return [ [ 'spl_timestamp', 'spl_id' ] ];
	}

	/**
	 * @inheritDoc
	 */
	public function formatRow( $row ) {
		$timestamp = $this->getLanguage()->timeanddate(
			wfTimestamp( TS_MW, $row->spl_timestamp ),
			true
		);

		$user = User::newFromId( $row->spl_user );
		$userLink = Linker::userLink( $user->getId(), $user->getName() );

		$election = $this->context->getElection( $row->spl_election_id );
		$electionTitle = htmlspecialchars( $election->title );

		$messageParams = [
			$timestamp,
			$userLink,
			$electionTitle,
		];

		if ( $row->spl_target ) {
			$target = User::newFromId( $row->spl_target );
			$messageParams[] = Linker::userLink( $target->getId(), $target->getName() );
		}

		$message = $this->msg(
			'securepoll-log-action-type-' . $row->spl_type,
			$messageParams
		)->text();

		return HTML::rawElement( 'li', [], $message );
	}

	/**
	 * @inheritDoc
	 */
	public function getStartBody() {
		return $this->getNumRows() ? '<ul>' : '';
	}

	/**
	 * @inheritDoc
	 */
	public function getEndBody() {
		return $this->getNumRows() ? '</ul>' : '';
	}

	/**
	 * @inheritDoc
	 */
	public function getEmptyBody() {
		return HTML::rawElement( 'p', [], $this->msg( 'securepoll-log-empty' )->text() );
	}
}
