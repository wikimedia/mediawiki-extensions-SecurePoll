<?php

namespace MediaWiki\Extension\SecurePoll;

use MediaWiki\Extension\SecurePoll\Pages\ActionPage;
use MediaWiki\Html\Html;
use MediaWiki\Linker\Linker;
use MediaWiki\Pager\ReverseChronologicalPager;
use MediaWiki\User\UserFactory;

class SecurePollLogPager extends ReverseChronologicalPager {

	public function __construct(
		private readonly Context $context,
		private readonly UserFactory $userFactory,
		private readonly string $type,
		private readonly string $performer,
		private readonly string $target,
		private readonly string $electionName,
		int $year,
		int $month,
		int $day,
		/** @var int[] $actions */
		private readonly array $actions,
	) {
		parent::__construct();
		$this->getDateCond( $year, $month, $day );
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

	/**
	 * @inheritDoc
	 */
	public function getDefaultQuery() {
		parent::getDefaultQuery();
		unset( $this->mDefaultQuery['date'] );
		return $this->mDefaultQuery;
	}

	private function getFilterConds(): array {
		$conds = [];

		switch ( $this->type ) {
			case 'voter':
				$type = [ ActionPage::LOG_TYPE_VIEWVOTES, ActionPage::LOG_TYPE_VIEWVOTEDETAILS ];
				break;
			case 'admin':
				$type = $this->actions;
				break;
			default:
				$type = null;
				break;
		}
		if ( $type ) {
			$conds['spl_type'] = $type;
		}

		if ( $this->performer ) {
			$performer = $this->userFactory->newFromName( $this->performer )->getId();
			$conds['spl_user'] = $performer;
		}

		if ( $this->target ) {
			$target = $this->userFactory->newFromName( $this->target )->getId();
			$conds['spl_target'] = $target;
		}

		if ( $this->electionName ) {
			$electionId = $this->context->getElectionByTitle( $this->electionName )->getId();
			$conds['spl_election_id'] = $electionId;
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

		$user = $this->userFactory->newFromId( $row->spl_user );
		$userLink = Linker::userLink( $user->getId(), $user->getName() );

		$election = $this->context->getElection( $row->spl_election_id );
		// TODO: this is double escaped
		$electionTitle = htmlspecialchars( $election->title );

		$message = $this->msg(
			'securepoll-log-action-type-' . $row->spl_type, $timestamp );

		$message->rawParams( $userLink );
		$message->rawParams( $electionTitle );

		if ( $row->spl_target ) {
			$target = $this->userFactory->newFromId( $row->spl_target );
			$targetLink = Linker::userLink( $target->getId(), $target->getName() );

			$message->rawParams( $targetLink );
		}

		return Html::RawElement( 'li', [], $message->parse() );
	}

	/**
	 * @inheritDoc
	 */
	protected function getStartBody() {
		return $this->getNumRows() ? '<ul>' : '';
	}

	/**
	 * @inheritDoc
	 */
	protected function getEndBody() {
		return $this->getNumRows() ? '</ul>' : '';
	}

	/**
	 * @inheritDoc
	 */
	protected function getEmptyBody() {
		return Html::element( 'p', [], $this->msg( 'securepoll-log-empty' )->text() );
	}
}
