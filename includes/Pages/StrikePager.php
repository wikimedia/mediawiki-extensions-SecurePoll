<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use MediaWiki\Linker\Linker;
use MediaWiki\Pager\TablePager;

/**
 * Pager for the strike log. See TablePager documentation.
 */
class StrikePager extends TablePager {
	/**
	 * @var DetailsPage
	 */
	public $detailsPage;

	/** @var int */
	public $voteId;

	public function __construct( $detailsPage, $voteId ) {
		$this->detailsPage = $detailsPage;
		$this->voteId = $voteId;
		parent::__construct();
	}

	public function getQueryInfo() {
		return [
			'tables' => [
				'user',
				'securepoll_strike'
			],
			'fields' => '*',
			'conds' => [
				'st_vote' => $this->voteId,
				'st_user=user_id',
			],
			'options' => []
		];
	}

	/**
	 * @param string $name
	 * @param string $value
	 * @return string HTML
	 */
	public function formatValue( $name, $value ) {
		switch ( $name ) {
			case 'st_user':
				return Linker::userLink( (int)$value, $this->mCurrentRow->user_name );
			case 'st_timestamp':
				return htmlspecialchars( $this->getLanguage()->timeanddate( $value ) );
			default:
				return htmlspecialchars( $value );
		}
	}

	public function getDefaultSort() {
		return 'st_timestamp';
	}

	protected function getFieldNames() {
		return [
			'st_timestamp' => $this->msg( 'securepoll-header-timestamp' )->escaped(),
			'st_user' => $this->msg( 'securepoll-header-admin' )->escaped(),
			'st_action' => $this->msg( 'securepoll-header-action' )->escaped(),
			'st_reason' => $this->msg( 'securepoll-header-reason' )->escaped(),
		];
	}

	public function getTitle() {
		return $this->detailsPage->getTitle();
	}

	protected function isFieldSortable( $field ) {
		return $field === 'st_timestamp';
	}
}
