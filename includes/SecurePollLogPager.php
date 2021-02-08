<?php

namespace MediaWiki\Extensions\SecurePoll;

use HTML;
use Linker;
use MediaWiki\Extensions\SecurePoll\Pages\ActionPage;
use ReverseChronologicalPager;
use User;

class SecurePollLogPager extends ReverseChronologicalPager {
	/** @var Context */
	private $context;

	/** @var string */
	private $type;

	/**
	 * @param Context $context
	 * @param string $type
	 */
	public function __construct(
		Context $context,
		string $type
	) {
		parent::__construct();
		$this->context = $context;
		$this->type = $type;
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
			'conds' => $this->getFilterConds(),
		];
	}

	private function getFilterConds() {
		$conds = [];

		switch ( $this->type ) {
			case 'voter':
				$type = ActionPage::LOG_TYPE_VIEWVOTES;
				break;
			case 'admin':
				$type = [
					ActionPage::LOG_TYPE_ADDADMIN,
					ActionPage::LOG_TYPE_REMOVEADMIN,
				];
				break;
			default:
				$type = null;
				break;
		}
		if ( $type ) {
			$conds['spl_type'] = $type;
		}

		return $conds;
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
