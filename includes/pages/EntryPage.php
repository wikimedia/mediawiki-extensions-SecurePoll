<?php

/**
 * The entry page for SecurePoll. Shows a list of elections.
 */
class SecurePoll_EntryPage extends SecurePoll_ActionPage {
	/**
	 * Execute the subpage.
	 * @param $params array Array of subpage parameters.
	 */
	function execute( $params ) {
		$pager = new SecurePoll_ElectionPager( $this );
		$out = $this->specialPage->getOutput();
		$out->addWikiMsg( 'securepoll-entry-text' );
		$out->addHTML(
			$pager->getBody() .
			$pager->getNavigationBar()
		);

		if ( $this->specialPage->getUser()->isAllowed( 'securepoll-create-poll' ) ) {
			$title = SpecialPage::getTitleFor( 'SecurePoll', 'create' );
			$out->addHTML(
				Html::rawElement( 'p', array(),
					Linker::link(
						$title,
						$this->msg( 'securepoll-entry-createpoll' )->text(),
						array(),
						array(),
						array( 'known' )
					)
				)
			);
		}
	}

	/**
	 * @return Title
	 */
	function getTitle() {
		return $this->specialPage->getTitle( 'entry' );
	}
}

/**
 * Pager for an election list. See TablePager documentation.
 */
class SecurePoll_ElectionPager extends TablePager {
	public $subpages = array(
		'vote' => array(
			'public' => true,
			'visible-after-start' => true,
			'visible-after-close' => false,
		),
		'translate' => array(
			'public' => true,
			'visible-after-start' => true,
			'visible-after-close' => true,
		),
		'list' => array(
			'public' => true,
			'visible-after-start' => true,
			'visible-after-close' => true,
		),
		'edit' => array(
			'public' => false,
			'visible-after-start' => false,
			'visible-after-close' => false,
		),
		'votereligibility' => array(
			'public' => false,
			'visible-after-start' => true,
			'visible-after-close' => true,
		),
		'dump' => array(
			'public' => false,
			'visible-after-start' => true,
			'visible-after-close' => true,
		),
		'tally' => array(
			'public' => false,
			'visible-after-start' => true,
			'visible-after-close' => true,
		),
	);
	public $fields = array(
		'el_title',
		'el_start_date',
		'el_end_date',
		'links'
	);
	public $entryPage;

	public function __construct( $specialPage ) {
		$this->entryPage = $specialPage;
		parent::__construct();
	}

	public function getQueryInfo() {
		return array(
			'tables' => 'securepoll_elections',
			'fields' => '*',
			'conds' => false,
			'options' => array()
		);
	}

	public function isFieldSortable( $field ) {
		return in_array( $field, array(
			'el_title', 'el_start_date', 'el_end_date'
		) );
	}

	/**
	 * Add classes based on whether the poll is open or closed
	 * @param $row database object
	 * @return String
	 * @see TablePager::getRowClass()
	 */
	public function getRowClass( $row ) {
		return $row->el_end_date > wfTimestampNow( TS_DB )
			? 'securepoll-election-open'
			: 'securepoll-election-closed';
	}

	public function formatValue( $name, $value ) {
		switch ( $name ) {
		case 'el_start_date':
		case 'el_end_date':
			return $this->getLanguage()->timeanddate( $value );
		case 'links':
			return $this->getLinks();
		default:
			return htmlspecialchars( $value );
		}
	}

	public function formatRow( $row ) {
		$id = $row->el_entity;
		$this->election = $this->entryPage->context->getElection( $id );
		if( !$this->election ) {
			$this->isAdmin = false;
		} else {
			$this->isAdmin = $this->election->isAdmin( $this->getUser() );
		}
		return parent::formatRow( $row );
	}

	public function getLinks() {
		$id = $this->mCurrentRow->el_entity;

		$s = '';
		$sep = $this->msg( 'pipe-separator' )->text();
		foreach ( $this->subpages as $subpage => $props ) {
			// Message keys used here:
			// securepoll-subpage-vote, securepoll-subpage-translate,
			// securepoll-subpage-list, securepoll-subpage-dump,
			// securepoll-subpage-tally, securepoll-subpage-votereligibility
			$linkText = $this->msg( "securepoll-subpage-$subpage" )->parse();
			if ( $s !== '' ) {
				$s .= $sep;
			}
			if( ( $this->isAdmin || $props['public'] )
				&& ( !$this->election->isStarted() || $props['visible-after-start'] )
				&& ( !$this->election->isFinished() || $props['visible-after-close'] )
			) {
				$title = $this->entryPage->specialPage->getTitle( "$subpage/$id" );
				$s .= Linker::linkKnown( $title, $linkText );
			} else {
				$s .= "<span class=\"securepoll-link-disabled\">" .
					$linkText . "</span>";
			}
		}
		return $s;
	}

	public function getDefaultSort() {
		return 'el_start_date';
	}

	public function getFieldNames() {
		$names = array();
		foreach ( $this->fields as $field ) {
			if ( $field == 'links' ) {
				$names[$field] = '';
			} else {
				// Give grep a chance to find the usages:
				// securepoll-header-title, securepoll-header-start-date,
				// securepoll-header-end-date
				$msgName = 'securepoll-header-' .
					strtr( $field, array( 'el_' => '', '_' => '-' ) );
				$names[$field] = $this->msg( $msgName )->text();
			}
		}
		return $names;
	}

	public function getTitle() {
		return $this->entryPage->getTitle();
	}
}

