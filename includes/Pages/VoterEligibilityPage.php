<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use DateTime;
use DateTimeZone;
use Exception;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Exception\MWExceptionHandler;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Exceptions\InvalidDataException;
use MediaWiki\Extension\SecurePoll\Jobs\PopulateVoterListJob;
use MediaWiki\Extension\SecurePoll\SecurePollContentHandler;
use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Json\FormatJson;
use MediaWiki\Linker\Linker;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Message\Message;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\DBConnectionError;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LBFactory;
use Wikimedia\RequestTimeout\TimeoutException;

/**
 * Special:SecurePoll subpage for managing the voter list for a poll
 */
class VoterEligibilityPage extends ActionPage {
	/** @var string[] */
	private static $lists = [
		'voter' => 'need-list',
		'include' => 'include-list',
		'exclude' => 'exclude-list',
	];

	/** @var LBFactory */
	private $lbFactory;

	/** @var LinkRenderer */
	private $linkRenderer;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var UserGroupManager */
	private $userGroupManager;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/**
	 * @param SpecialSecurePoll $specialPage
	 * @param LBFactory $lbFactory
	 * @param LinkRenderer $linkRenderer
	 * @param TitleFactory $titleFactory
	 * @param UserGroupManager $userGroupManager
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct(
		SpecialSecurePoll $specialPage,
		LBFactory $lbFactory,
		LinkRenderer $linkRenderer,
		TitleFactory $titleFactory,
		UserGroupManager $userGroupManager,
		WikiPageFactory $wikiPageFactory
	) {
		parent::__construct( $specialPage );
		$this->lbFactory = $lbFactory;
		$this->linkRenderer = $linkRenderer;
		$this->titleFactory = $titleFactory;
		$this->userGroupManager = $userGroupManager;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	/**
	 * Execute the subpage.
	 * @param array $params Array of subpage parameters.
	 */
	public function execute( $params ) {
		$out = $this->specialPage->getOutput();

		if ( !count( $params ) ) {
			$out->addWikiMsg( 'securepoll-too-few-params' );

			return;
		}

		$electionId = intval( $params[0] );
		$this->election = $this->context->getElection( $electionId );
		if ( !$this->election ) {
			$out->addWikiMsg( 'securepoll-invalid-election', $electionId );

			return;
		}
		if ( !$this->election->isAdmin( $this->specialPage->getUser() ) ) {
			$out->addWikiMsg( 'securepoll-need-admin' );

			return;
		}

		$jumpUrl = $this->election->getProperty( 'jump-url' );
		if ( $jumpUrl ) {
			$jumpId = $this->election->getProperty( 'jump-id' );
			if ( !$jumpId ) {
				throw new InvalidDataException( 'Configuration error: no jump-id' );
			}
			$jumpUrl .= "/votereligibility/$jumpId";
			if ( count( $params ) > 1 ) {
				$jumpUrl .= '/' . implode( '/', array_slice( $params, 1 ) );
			}

			$wiki = $this->election->getProperty( 'main-wiki' );
			if ( $wiki ) {
				$wiki = WikiMap::getWikiName( $wiki );
			} else {
				$wiki = $this->msg( 'securepoll-votereligibility-redirect-otherwiki' )->text();
			}

			$out->addWikiMsg(
				'securepoll-votereligibility-redirect',
				Message::rawParam( Linker::makeExternalLink( $jumpUrl, $wiki ) )
			);

			return;
		}

		if ( count( $params ) >= 3 ) {
			$operation = $params[1];
		} else {
			$operation = 'config';
		}

		switch ( $operation ) {
			case 'edit':
				$this->executeEdit( $params[2] );
				break;
			case 'clear':
				$this->executeClear( $params[2] );
				break;
			default:
				$this->executeConfig();
				break;
		}
	}

	/**
	 * @param string[] $properties
	 * @param array $delete
	 * @param string $comment
	 */
	private function saveProperties( $properties, $delete, $comment ) {
		$localWiki = WikiMap::getCurrentWikiId();
		$wikis = $this->election->getProperty( 'wikis' );
		if ( $wikis ) {
			$wikis = explode( "\n", $wikis );
			$i = array_search( $localWiki, $wikis );
			if ( $i !== false ) {
				unset( $wikis[$i] );
			}
			array_unshift( $wikis, $localWiki );
		} else {
			$wikis = [ $localWiki ];
		}

		foreach ( $wikis as $dbname ) {
			$dbw = $this->getAutoCommitPrimaryConnectionForWiki( $dbname );
			if ( $dbw === null ) {
				continue;
			}

			$dbw->startAtomic( __METHOD__ );

			$id = $dbw->newSelectQueryBuilder()
				->select( 'el_entity' )
				->from( 'securepoll_elections' )
				->where( [ 'el_title' => $this->election->title ] )
				->caller( __METHOD__ )
				->fetchField();
			if ( $id ) {
				$ins = [];
				foreach ( $properties as $key => $value ) {
					$ins[] = [
						'pr_entity' => $id,
						'pr_key' => $key,
						'pr_value' => $value,
					];
				}

				$dbw->newDeleteQueryBuilder()
					->deleteFrom( 'securepoll_properties' )
					->where( [
						'pr_entity' => $id,
						'pr_key' => array_merge( $delete, array_keys( $properties ) ),
					] )
					->caller( __METHOD__ )
					->execute();

				if ( $ins ) {
					$dbw->newInsertQueryBuilder()
						->insertInto( 'securepoll_properties' )
						->rows( $ins )
						->caller( __METHOD__ )
						->execute();
				}
			}

			$dbw->endAtomic( __METHOD__ );
		}

		// Record this election to the SecurePoll namespace, if so configured.
		if ( Context::isNamespacedLoggingEnabled() ) {
			$election = $this->context->getElection( $this->election->getId() );
			$election->loadProperties();
			// Explicitly overwrite the values that have been changed, since the loaded
			// values could be out-of-date
			$election->properties = array_merge( $election->properties, $properties );
			foreach ( $delete as $key ) {
				unset( $election->properties[$key] );
			}

			[ $title, $content ] = SecurePollContentHandler::makeContentFromElection(
				$election
			);
			$wp = $this->wikiPageFactory->newFromTitle( $title );
			$wp->doUserEditContent( $content, $this->specialPage->getUser(), $comment );
		}
	}

	/**
	 * Gets a primary autocommit connection for the given wiki. If this is not the local
	 * wiki then it only returns a primary DB connection if the wiki is not in read only mode.
	 *
	 * @param string $dbname The DB name which we want to get the primary DB connection for
	 * @return IDatabase|null A primary DB connection, or null only if the DB is in read-only mode
	 *   and is not a local wiki
	 */
	private function getAutoCommitPrimaryConnectionForWiki( string $dbname ): ?IDatabase {
		$dbw = $this->lbFactory->getAutoCommitPrimaryConnection( $dbname );

		// If the wiki is not the current wiki then we need to check if the wiki is in read-only mode
		// before we try to use the DB connection to perform updates.
		if ( $dbname !== WikiMap::getCurrentWikiId() ) {
			try {
				if ( $dbw->isReadOnly() ) {
					return null;
				}
			} catch ( DBConnectionError $e ) {
				MWExceptionHandler::logException( $e );
				return null;
			}
		}

		return $dbw;
	}

	/**
	 * @param string $property
	 * @param int $db
	 * @return string[]
	 */
	private function fetchList( $property, $db = DB_REPLICA ) {
		$wikis = $this->election->getProperty( 'wikis' );
		$localWiki = WikiMap::getCurrentWikiId();
		if ( $wikis ) {
			$wikis = explode( "\n", $wikis );
			if ( !in_array( $localWiki, $wikis ) ) {
				$wikis[] = $localWiki;
			}
		} else {
			$wikis = [ $localWiki ];
		}

		$names = [];
		foreach ( $wikis as $dbname ) {
			$lb = $this->lbFactory->getMainLB( $dbname );
			$dbr = $lb->getConnection( $db, [], $dbname );

			$id = $dbr->newSelectQueryBuilder()
				->select( 'el_entity' )
				->from( 'securepoll_elections' )
				->where( [
					'el_title' => $this->election->title
				] )
				->caller( __METHOD__ )
				->fetchField();
			if ( !$id ) {
				// WTF?
				continue;
			}
			$list = $dbr->newSelectQueryBuilder()
				->select( 'pr_value' )
				->from( 'securepoll_properties' )
				->where( [
					'pr_entity' => $id,
					'pr_key' => $property,
				] )
				->caller( __METHOD__ )
				->fetchField();
			if ( !$list ) {
				continue;
			}

			$res = $dbr->newSelectQueryBuilder()
				->select( 'user_name' )
				->from( 'securepoll_lists' )
				->join( 'user', null, 'user_id=li_member' )
				->where( [
					'li_name' => $list,
				] )
				->caller( __METHOD__ )
				->fetchResultSet();
			foreach ( $res as $row ) {
				$names[] = str_replace( '_', ' ', $row->user_name ) . "@$dbname";
			}
		}
		sort( $names );

		return $names;
	}

	/**
	 * @param string $property
	 * @param string $names
	 * @param string $comment
	 */
	private function saveList( $property, $names, $comment ) {
		$localWiki = WikiMap::getCurrentWikiId();

		$wikiNames = [ '*' => [] ];
		foreach ( explode( "\n", $names ) as $name ) {
			$name = trim( $name );
			$i = strrpos( $name, '@' );
			if ( $i === false ) {
				$wiki = '*';
			} else {
				$wiki = trim( substr( $name, $i + 1 ) );
				$name = trim( substr( $name, 0, $i ) );
			}
			if ( $wiki !== '' && $name !== '' ) {
				$wikiNames[$wiki][] = str_replace( '_', ' ', $name );
			}
		}

		$list = "{$this->election->getId()}/list/$property";

		$wikis = $this->election->getProperty( 'wikis' );
		if ( $wikis ) {
			$wikis = explode( "\n", $wikis );
			$i = array_search( $localWiki, $wikis );
			if ( $i !== false ) {
				unset( $wikis[$i] );
			}
			array_unshift( $wikis, $localWiki );
		} else {
			$wikis = [ $localWiki ];
		}
		$localWikiPropertiesChanged = [];
		$userNamesForLog = [];

		foreach ( $wikis as $dbname ) {
			$dbw = $this->getAutoCommitPrimaryConnectionForWiki( $dbname );
			if ( $dbw === null ) {
				continue;
			}

			$dbw->startAtomic( __METHOD__ );

			$id = $dbw->newSelectQueryBuilder()
				->select( 'el_entity' )
				->from( 'securepoll_elections' )
				->where( [ 'el_title' => $this->election->title ] )
				->caller( __METHOD__ )
				->fetchField();
			if ( $id ) {
				$dbw->newReplaceQueryBuilder()
					->replaceInto( 'securepoll_properties' )
					->uniqueIndexFields( [ 'pr_entity', 'pr_key' ] )
					->row( [
						'pr_entity' => $id,
						'pr_key' => $property,
						'pr_value' => $list,
					] )
					->caller( __METHOD__ )
					->execute();
				if ( $dbname === $localWiki ) {
					$localWikiPropertiesChanged[$property] = $list;
				}

				if ( isset( $wikiNames[$dbname] ) ) {
					$queryNames = array_merge( $wikiNames['*'], $wikiNames[$dbname] );
				} else {
					$queryNames = $wikiNames['*'];
				}

				$dbw->newDeleteQueryBuilder()
					->deleteFrom( 'securepoll_lists' )
					->where( [ 'li_name' => $list ] )
					->caller( __METHOD__ )
					->execute();
				if ( $queryNames ) {
					$dbw->insertSelect(
						'securepoll_lists',
						'user',
						[
							'li_name' => $dbw->addQuotes( $list ),
							'li_member' => 'user_id'
						],
						[ 'user_name' => $queryNames ],
						__METHOD__
					);
					if ( Context::isNamespacedLoggingEnabled() ) {
						$names = $dbw->newSelectQueryBuilder()
							->select( 'user_name' )
							->from( 'securepoll_lists' )
							->join( 'user', null, 'user_id=li_member' )
							->where( [
								'li_name' => $list,
							] )
							->caller( __METHOD__ )
							->fetchFieldValues();
						foreach ( $names as &$name ) {
							$name .= "@{$dbname}";
						}
						$userNamesForLog = array_merge( $userNamesForLog, $names );
					}
				}
			}

			$dbw->endAtomic( __METHOD__ );
		}

		// Record this election to the SecurePoll namespace, if so configured.
		if ( Context::isNamespacedLoggingEnabled() ) {
			$election = $this->context->getElection( $this->election->getId() );
			$election->loadProperties();
			// Explicitly overwrite the values that have been changed, since the loaded
			// values could be outdated
			$election->properties = array_merge( $election->properties, $localWikiPropertiesChanged );

			[ $title, $content ] = SecurePollContentHandler::makeContentFromElection(
				$election
			);
			$wp = $this->wikiPageFactory->newFromTitle( $title );
			$wp->doUserEditContent( $content, $this->specialPage->getUser(), $comment );

			$json = FormatJson::encode(
				$userNamesForLog,
				false,
				FormatJson::ALL_OK
			);
			$title = SecurePollContentHandler::getTitleForPage( $list );
			$wp = $this->wikiPageFactory->newFromTitle( $title );
			$wp->doUserEditContent(
				SecurePollContentHandler::makeContent( $json, $title, 'SecurePoll' ),
				$this->specialPage->getUser(),
				$comment
			);
		}
	}

	private function executeConfig() {
		$out = $this->specialPage->getOutput();
		$out->addModuleStyles( [
			'mediawiki.widgets.TagMultiselectWidget.styles',
			'ext.securepoll',
		] );
		$out->setPageTitleMsg( $this->msg( 'securepoll-votereligibility-title' ) );

		$formItems = [];

		$formItems['default_submit'] = [
			'section' => 'basic',
			'type' => 'submit',
			'buttonlabel' => 'submit',
			'cssclass' => 'securepoll-default-submit'
		];

		$formItems['min-edits'] = [
			'section' => 'basic',
			'label-message' => 'securepoll-votereligibility-label-min_edits',
			'type' => 'int',
			'min' => 0,
			'default' => $this->election->getProperty( 'min-edits', '' ),
			'cssclass' => 'securepoll-medium-number-input',
		];

		$date = $this->election->getProperty( 'max-registration', null );
		if ( $date !== null ) {
			$date = gmdate( 'Y-m-d', (int)wfTimestamp( TS_UNIX, $date ) );
		}

		$formItems['max-registration'] = [
			'section' => 'basic',
			'label-message' => 'securepoll-votereligibility-label-max_registration',
			'type' => 'date',
			'default' => $date,
		];

		$formItems['not-sitewide-blocked'] = [
			'section' => 'basic',
			'type' => 'check',
			'label-message' => 'securepoll-votereligibility-label-not_blocked_sitewide',
			'default' => $this->election->getProperty( 'not-sitewide-blocked' ),
		];

		$formItems['not-partial-blocked'] = [
			'section' => 'basic',
			'type' => 'check',
			'label-message' => 'securepoll-votereligibility-label-not_blocked_partial',
			'default' => $this->election->getProperty( 'not-partial-blocked' ),
		];

		$formItems['not-centrally-blocked'] = [
			'section' => 'basic',
			'label-message' => 'securepoll-votereligibility-label-not_centrally_blocked',
			'type' => 'check',
			'hidelabel' => true,
			'default' => $this->election->getProperty( 'not-centrally-blocked', false ),
		];

		$formItems['central-block-threshold'] = [
			'section' => 'basic',
			'label-message' => 'securepoll-votereligibility-label-central_block_threshold',
			'type' => 'int',
			'min' => 1,
			'validation-callback' => fn ( $value, $formData ) =>
				$formData['not-centrally-blocked'] ? $this->checkRequired( $value ) : true,
			'hide-if' => [
				'===',
				'not-centrally-blocked',
				''
			],
			'default' => $this->election->getProperty( 'central-block-threshold', '' ),
			'cssclass' => 'securepoll-medium-number-input',
		];

		$formItems['not-bot'] = [
			'section' => 'basic',
			'label-message' => 'securepoll-votereligibility-label-not_bot',
			'type' => 'check',
			'hidelabel' => true,
			'default' => $this->election->getProperty( 'not-bot', false ),
		];

		$userGroupOptions = [];
		$userGroups = array_merge( $this->userGroupManager->listAllGroups(),
			$this->userGroupManager->listAllImplicitGroups() );
		// Remove '*' (no group- message exists) and 'user' (have to be logged in to vote)
		$userGroups = array_diff( $userGroups, [ '*', 'user' ] );
		foreach ( $userGroups as $group ) {
			$userGroupOptions[ 'group-' . $group ] = $group;
		}

		$formItems['need-group'] = [
			'section' => 'basic',
			'label-message' => 'securepoll-votereligibility-label-need_group',
			'type' => 'multiselect',
			'dropdown' => true,
			'options-messages' => $userGroupOptions,
			'default' => explode( '|', $this->election->getProperty( 'need-group', "" ) )
		];

		foreach ( self::$lists as $list => $property ) {
			$use = null;
			$links = [];
			if ( $list === 'voter' ) {
				$complete = $this->election->getProperty( 'list_complete-count', 0 );
				$total = $this->election->getProperty( 'list_total-count', 0 );
				if ( $complete !== $total ) {
					$use = $this->msg( 'securepoll-votereligibility-label-processing' )->numParams(
							round( $complete * 100.0 / $total, 1 )
						)->numParams( $complete, $total );
					$links = [ 'clear' ];
				}
			}
			if ( $use === null && $this->election->getProperty( $property ) ) {
				$use = $this->msg( 'securepoll-votereligibility-label-inuse' );
				$links = [
					'edit',
					'clear'
				];
			}
			if ( $use === null ) {
				$use = $this->msg( 'securepoll-votereligibility-label-notinuse' );
				$links = [ 'edit' ];
			}

			$formItems[] = [
				'section' => "lists/$list",
				'type' => 'info',
				'raw' => true,
				'default' => $use->parse(),
			];

			$prefix = 'votereligibility/' . $this->election->getId();
			foreach ( $links as $action ) {
				$title = SpecialPage::getTitleFor( 'SecurePoll', "$prefix/$action/$list" );
				$queryParams = [];

				if ( $action === 'clear' ) {
					$queryParams[ 'token' ] = $this->specialPage->getContext()
						->getCsrfTokenSet()->getToken();
				}

				$link = $this->linkRenderer->makeLink(
					$title,
					$this->msg( "securepoll-votereligibility-label-$action" )->text(),
					[],
					$queryParams
				);
				$formItems[] = [
					'section' => "lists/$list",
					'type' => 'info',
					'raw' => true,
					'default' => $link,
				];
			}

			if ( $list === 'voter' ) {
				$formItems['list_populate'] = [
					'section' => "lists/$list",
					'label-message' => 'securepoll-votereligibility-label-populate',
					'type' => 'check',
					'hidelabel' => true,
					'default' => $this->election->getProperty( 'list_populate', false ),
				];

				$formItems['list_edits-before'] = [
					'section' => "lists/$list",
					'label-message' => 'securepoll-votereligibility-label-edits_before',
					'type' => 'check',
					'default' => $this->election->getProperty( 'list_edits-before', false ),
					'hide-if' => [
						'===',
						'list_populate',
						''
					],
				];

				$formItems['list_edits-before-count'] = [
					'section' => "lists/$list",
					'label-message' => 'securepoll-votereligibility-label-edits_before_count',
					'type' => 'int',
					'min' => 1,
					'validation-callback' => fn ( $value, $formData ) =>
						$formData['list_edits-before'] ? $this->checkRequired( $value ) : true,
					'hide-if' => [
						'OR',
						[
							'===',
							'list_populate',
							''
						],
						[
							'===',
							'list_edits-before',
							''
						],
					],
					'default' => $this->election->getProperty( 'list_edits-before-count', '' ),
					'cssclass' => 'securepoll-narrow-number-input',
				];

				$date = $this->election->getProperty( 'list_edits-before-date', '' );
				if ( $date !== '' ) {
					$date = gmdate( 'Y-m-d', (int)wfTimestamp( TS_UNIX, $date ) );
				} else {
					$date = gmdate( 'Y-m-d', strtotime( 'yesterday' ) );
				}
				$formItems['list_edits-before-date'] = [
					'section' => "lists/$list",
					'label-message' => 'securepoll-votereligibility-label-edits_before_date',
					'type' => 'date',
					'max' => gmdate( 'Y-m-d', strtotime( 'yesterday' ) ),
					'required' => true,
					'hide-if' => [
						'OR',
						[
							'===',
							'list_populate',
							''
						],
						[
							'===',
							'list_edits-before',
							''
						],
					],
					'default' => $date,
				];

				$formItems['list_edits-between'] = [
					'section' => "lists/$list",
					'label-message' => 'securepoll-votereligibility-label-edits_between',
					'type' => 'check',
					'hide-if' => [
						'===',
						'list_populate',
						''
					],
					'default' => $this->election->getProperty( 'list_edits-between', false ),
				];

				$formItems['list_edits-between-count'] = [
					'section' => "lists/$list",
					'label-message' => 'securepoll-votereligibility-label-edits_between_count',
					'type' => 'int',
					'min' => 1,
					'validation-callback' => fn ( $value, $formData ) =>
						$formData['list_edits-between'] ? $this->checkRequired( $value ) : true,
					'hide-if' => [
						'OR',
						[
							'===',
							'list_populate',
							''
						],
						[
							'===',
							'list_edits-between',
							''
						],
					],
					'default' => $this->election->getProperty( 'list_edits-between-count', '' ),
					'cssclass' => 'securepoll-narrow-number-input',
				];

				$editCountStartDate = $this->election->getProperty( 'list_edits-startdate', '' );
				if ( $editCountStartDate !== '' ) {
					$editCountStartDate = gmdate(
						'Y-m-d',
						(int)wfTimestamp( TS_UNIX, $editCountStartDate )
					);
				}

				$formItems['list_edits-startdate'] = [
					'section' => "lists/$list",
					'label-message' => 'securepoll-votereligibility-label-edits_startdate',
					'type' => 'date',
					'max' => gmdate( 'Y-m-d', strtotime( 'yesterday' ) ),
					'required' => true,
					'hide-if' => [
						'OR',
						[
							'===',
							'list_populate',
							''
						],
						[
							'===',
							'list_edits-between',
							''
						],
					],
					'default' => $editCountStartDate,
				];

				$editCountEndDate = $this->election->getProperty( 'list_edits-enddate', '' );
				if ( $editCountEndDate === '' ) {
					$editCountEndDate = gmdate( 'Y-m-d', strtotime( 'yesterday' ) );
				} else {
					$editCountEndDate = gmdate(
						'Y-m-d',
						(int)wfTimestamp( TS_UNIX, $editCountEndDate )
					);
				}

				$formItems['list_edits-enddate'] = [
					'section' => "lists/$list",
					'label-message' => 'securepoll-votereligibility-label-edits_enddate',
					'type' => 'date',
					'max' => gmdate( 'Y-m-d', strtotime( 'yesterday' ) ),
					'required' => true,
					'validation-callback' => [
						$this,
						'checkListEditsEndDate'
					],
					'hide-if' => [
						'OR',
						[
							'===',
							'list_populate',
							''
						],
						[
							'===',
							'list_edits-between',
							''
						],
					],
					'default' => $editCountEndDate,
				];

				$groups = $this->election->getProperty( 'list_exclude-groups', [] );
				if ( $groups ) {
					$groups = array_map(
						static function ( $group ) {
							return [ 'group' => $group ];
						},
						explode( '|', $groups )
					);
				}
				$formItems['list_exclude-groups'] = [
					'section' => "lists/$list",
					'label-message' => 'securepoll-votereligibility-label-exclude_groups',
					'type' => 'cloner',
					'format' => 'raw',
					'default' => $groups,
					'fields' => [
						'group' => [
							'type' => 'text',
							'required' => true,
						],
					],
					'hide-if' => [
						'===',
						'list_populate',
						''
					],
				];

				$groups = $this->election->getProperty( 'list_include-groups', [] );
				if ( $groups ) {
					$groups = array_map(
						static function ( $group ) {
							return [ 'group' => $group ];
						},
						explode( '|', $groups )
					);
				}
				$formItems['list_include-groups'] = [
					'section' => "lists/$list",
					'label-message' => 'securepoll-votereligibility-label-include_groups',
					'type' => 'cloner',
					'format' => 'raw',
					'default' => $groups,
					'fields' => [
						'group' => [
							'type' => 'text',
							'required' => true,
						],
					],
					'hide-if' => [
						'===',
						'list_populate',
						''
					],
				];

			} elseif ( $list === 'include' ) {
				$formItems['allow-usergroups'] = [
					'section' => "lists/$list",
					'label-message' => 'securepoll-votereligibility-label-include_groups',
					'allowArbitrary' => false,
					'type' => 'multiselect',
					'dropdown' => true,
					'options-messages' => $userGroupOptions,
					'default' => explode( '|', $this->election->getProperty( 'allow-usergroups', "" ) )
				];
			}
		}

		if ( Context::isNamespacedLoggingEnabled() ) {
			$formItems['comment'] = [
				'type' => 'text',
				'label-message' => 'securepoll-votereligibility-label-comment',
				'maxlength' => 250,
			];
		}

		$form = HTMLForm::factory(
			'ooui',
			$formItems,
			$this->specialPage->getContext(),
			'securepoll-votereligibility'
		);
		$form->addHeaderHtml(
			$this->msg( 'securepoll-votereligibility-basic-info' )->parseAsBlock(),
			'basic'
		);
		$form->addHeaderHtml(
			$this->msg( 'securepoll-votereligibility-lists-info' )->parseAsBlock(),
			'lists'
		);

		$form->setSubmitTextMsg( 'securepoll-votereligibility-action' );
		$form->setSubmitCallback(
			[
				$this,
				'processConfig'
			]
		);
		$result = $form->show();

		if ( $result === true || ( $result instanceof Status && $result->isGood() ) ) {
			$out->setPageTitleMsg( $this->msg( 'securepoll-votereligibility-saved' ) );
			$out->addWikiMsg( 'securepoll-votereligibility-saved-text' );
			$out->returnToMain( false, SpecialPage::getTitleFor( 'SecurePoll' ) );
		}
	}

	/**
	 * Based on HTMLDateRangeField::praseDate()
	 *
	 * @param string $value Date to be parsed
	 * @return int
	 */
	protected function parseDate( $value ) {
		$value = trim( $value );
		$value .= ' T00:00:00+0000';

		try {
			$date = new DateTime( $value, new DateTimeZone( 'GMT' ) );

			return $date->getTimestamp();
		} catch ( TimeoutException $ex ) {
			// Unfortunately DateTime throws a generic Exception, but we can't
			// ignore an exception generated by the RequestTimeout library.
			throw $ex;
		} catch ( Exception ) {
			return 0;
		}
	}

	/**
	 * Check that a required field has been filled.
	 *
	 * This is a hack to allow OOUI to work with no-JS environments,
	 * because the browser will prevent submission if fields that
	 * would be hidden by JS are required but not filled.
	 *
	 * @param string $value
	 * @return true|Message true on success, Message on error
	 */
	public function checkRequired( $value ) {
		if ( $value === '' ) {
			return Status::newFatal( 'htmlform-required' )->getMessage();
		}
		return true;
	}

	/**
	 * Check the end date exists and is after the start date
	 *
	 * @param string $value
	 * @param mixed[] $formData
	 * @return bool|string true on success, string on error
	 */
	public function checkListEditsEndDate( $value, $formData ) {
		if ( !$formData['list_edits-between'] ) {
			return true;
		}

		$startDate = $this->parseDate( $formData['list_edits-startdate'] );
		$endDate = $this->parseDate( $value );

		if ( $startDate >= $endDate ) {
			return $this->msg( 'securepoll-htmlform-daterange-end-before-start' )->parseAsBlock();
		}

		return true;
	}

	/**
	 * @param array $formData
	 * @param HtmlForm $form
	 * @return Status
	 */
	public function processConfig( $formData, $form ) {
		static $props = [
			'min-edits',
			'not-sitewide-blocked',
			'not-partial-blocked',
			'not-centrally-blocked',
			'central-block-threshold',
			'not-bot',
			'list_populate',
			'list_edits-before',
			'list_edits-before-count',
			'list_edits-between',
			'list_edits-between-count',
		];
		static $dateProps = [
			'max-registration',
			'list_edits-before-date',
			'list_edits-startdate',
			'list_edits-enddate',
		];
		static $listProps = [
			'list_exclude-groups',
			'list_include-groups',
		];
		static $multiselectProps = [
			'allow-usergroups',
			'need-group',
		];

		static $propPrereqs = [
			'not-centrally-blocked' => [
				'central-block-threshold'
			],
			'list_edits-before' => [
				'list_edits-before-count',
				'list_edits-before-date',
			],
			'list_edits-between' => [
				'list_edits-between-count',
				'list_edits-startdate',
				'list_edits-enddate',
			]
		];

		if ( $formData['list_populate'] &&
			!$formData['list_edits-before'] &&
			!$formData['list_edits-between'] &&
			!$formData['list_exclude-groups'] &&
			!$formData['list_include-groups']
		) {
			return Status::newFatal( 'securepoll-votereligibility-fail-nothing-to-process' );
		}

		$properties = [];
		$deleteProperties = [];

		// Unset any properties where the parent property is not checked and
		// mark them for deletion from the database
		foreach ( $propPrereqs as $parentProp => $childrenProps ) {
			if ( $formData[$parentProp] === '' || $formData[$parentProp] === false ) {
				foreach ( $childrenProps as $childProp ) {
					$formData[ $childProp ] = '';
					$deleteProperties[] = $childProp;
				}
			}
		}

		foreach ( $props as $prop ) {
			if (
				$formData[$prop] !== '' &&
				$formData[$prop] !== false
			) {
				$properties[$prop] = $formData[$prop];
			} else {
				$deleteProperties[] = $prop;
			}
		}

		foreach ( $dateProps as $prop ) {
			if ( $formData[$prop] !== '' && $formData[$prop] !== [] ) {
				$dates = array_map(
					static function ( $date ) {
						$date = new DateTime( $date, new DateTimeZone( 'GMT' ) );

						return wfTimestamp( TS_MW, $date->format( 'YmdHis' ) );
					},
					(array)$formData[$prop]
				);
				$properties[$prop] = implode( '|', $dates );
			} else {
				$deleteProperties[] = $prop;
			}
		}

		foreach ( $listProps as $prop ) {
			if ( $formData[$prop] ) {
				$names = array_map(
					static function ( $entry ) {
						return $entry['group'];
					},
					$formData[$prop]
				);
				sort( $names );
				$properties[$prop] = implode( '|', $names );
			} else {
				$deleteProperties[] = $prop;
			}
		}

		foreach ( $multiselectProps as $prop ) {
			if ( $formData[$prop] ) {
				$properties[$prop] = implode( '|', $formData[$prop] );
			} else {
				$deleteProperties[] = $prop;
			}
		}

		// De-dupe the $deleteProperties array
		$deleteProperties = array_unique( $deleteProperties );

		$populate = !empty( $properties['list_populate'] );
		if ( $populate ) {
			$properties['need-list'] = 'need-list-' . $this->election->getId();
		}

		$comment = $formData['comment'] ?? '';

		$this->saveProperties( $properties, $deleteProperties, $comment );

		if ( $populate ) {
			// Run pushJobsForElection() in a deferred update to give it outer transaction
			// scope, but keep it presend, so that any errors bubble up to the user.
			DeferredUpdates::addCallableUpdate(
				function () {
					PopulateVoterListJob::pushJobsForElection( $this->election );
				},
				DeferredUpdates::PRESEND
			);
		}

		return Status::newGood();
	}

	private function executeEdit( string $which ) {
		$out = $this->specialPage->getOutput();

		if ( !isset( self::$lists[$which] ) ) {
			$out->addWikiMsg( 'securepoll-votereligibility-invalid-list' );

			return;
		}
		$property = self::$lists[$which];
		$name = $this->msg( "securepoll-votereligibility-$which" )->text();

		if ( $which === 'voter' ) {
			$complete = $this->election->getProperty( 'list_complete-count', 0 );
			$total = $this->election->getProperty( 'list_total-count', 0 );
			if ( $complete !== $total ) {
				$out->addWikiMsg( 'securepoll-votereligibility-list-is-processing' );

				return;
			}
		}

		$out->addModuleStyles( 'ext.securepoll' );
		$out->setPageTitleMsg( $this->msg( 'securepoll-votereligibility-edit-title', $name ) );

		$formItems = [];

		$formItems['names'] = [
			'label-message' => 'securepoll-votereligibility-label-names',
			'type' => 'textarea',
			'rows' => 20,
			'default' => implode( "\n", $this->fetchList( $property ) ),
		];

		if ( Context::isNamespacedLoggingEnabled() ) {
			$formItems['comment'] = [
				'type' => 'text',
				'label-message' => 'securepoll-votereligibility-label-comment',
				'maxlength' => 250,
			];
		}

		$form = HTMLForm::factory(
			'ooui', $formItems, $this->specialPage->getContext(), 'securepoll-votereligibility'
		);
		$form->addHeaderHtml(
			$this->msg( 'securepoll-votereligibility-edit-header' )->parseAsBlock()
		);
		$form->setSubmitTextMsg( 'securepoll-votereligibility-edit-action' );
		$form->setSubmitCallback(
			function ( $formData, $form ) use ( $property ) {
				$this->saveList( $property, $formData['names'], $formData['comment'] ?? '' );

				return Status::newGood();
			}
		);
		$result = $form->show();

		if ( $result === true || ( $result instanceof Status && $result->isGood() ) ) {
			$out->setPageTitleMsg( $this->msg( 'securepoll-votereligibility-saved' ) );
			$out->addWikiMsg( 'securepoll-votereligibility-saved-text' );
			$out->returnToMain(
				false,
				SpecialPage::getTitleFor(
					'SecurePoll',
					'votereligibility/' . $this->election->getId()
				)
			);
		}
	}

	private function executeClear( string $which ) {
		$out = $this->specialPage->getOutput();
		$localWiki = WikiMap::getCurrentWikiId();

		$token = $this->specialPage->getContext()->getCsrfTokenSet()->getToken();
		$request = $this->specialPage->getRequest();
		$tokenMatch = $token->match( $request->getVal( 'token' ) );
		if ( !$tokenMatch ) {
			$out->addWikiMsg( 'securepoll-votereligibility-token-mismatch' );
			return;
		}

		if ( !isset( self::$lists[$which] ) ) {
			$out->addWikiMsg( 'securepoll-votereligibility-invalid-list' );

			return;
		}
		$property = self::$lists[$which];
		$name = $this->msg( "securepoll-votereligibility-$which" )->text();

		$out = $this->specialPage->getOutput();
		$out->setPageTitleMsg( $this->msg( 'securepoll-votereligibility-clear-title', $name ) );

		$wikis = $this->election->getProperty( 'wikis' );
		if ( $wikis ) {
			$wikis = explode( "\n", $wikis );
			$i = array_search( $localWiki, $wikis );
			if ( $i !== false ) {
				unset( $wikis[$i] );
			}
			array_unshift( $wikis, $localWiki );
		} else {
			$wikis = [ $localWiki ];
		}
		$localWikiPropertiesDeleted = [];

		foreach ( $wikis as $dbname ) {
			$dbw = $this->lbFactory->getAutoCommitPrimaryConnection( $dbname );
			$dbw->startAtomic( __METHOD__ );

			$id = $dbw->newSelectQueryBuilder()
				->select( 'el_entity' )
				->from( 'securepoll_elections' )
				->where( [
					'el_title' => $this->election->title
				] )
				->caller( __METHOD__ )
				->fetchField();
			if ( $id ) {
				$list = $dbw->newSelectQueryBuilder()
					->select( 'pr_value' )
					->from( 'securepoll_properties' )
					->where( [
						'pr_entity' => $id,
						'pr_key' => $property,
					] )
					->caller( __METHOD__ )
					->fetchField();
				if ( $list ) {
					$dbw->newDeleteQueryBuilder()
						->deleteFrom( 'securepoll_lists' )
						->where( [ 'li_name' => $list ] )
						->caller( __METHOD__ )
						->execute();
					$dbw->newDeleteQueryBuilder()
						->deleteFrom( 'securepoll_properties' )
						->where( [
							'pr_entity' => $id,
							'pr_key' => $property
						] )
						->caller( __METHOD__ )
						->execute();
					if ( $dbname === $localWiki ) {
						$localWikiPropertiesDeleted[] = $property;
					}
				}

				if ( $which === 'voter' ) {
					$properties = [
						'list_populate',
						'list_job-key',
						'list_total-count',
						'list_complete-count',
					];
					$dbw->newDeleteQueryBuilder()
						->deleteFrom( 'securepoll_properties' )
						->where( [
							'pr_entity' => $id,
							'pr_key' => $properties,
						] )
						->caller( __METHOD__ )
						->execute();
					if ( $dbname === $localWiki ) {
						$localWikiPropertiesDeleted = array_merge( $localWikiPropertiesDeleted, $properties );
					}
				}
			}

			$dbw->endAtomic( __METHOD__ );
		}

		// Record this election to the SecurePoll namespace, if so configured.
		if ( Context::isNamespacedLoggingEnabled() ) {
			$election = $this->context->getElection( $this->election->getId() );
			$election->loadProperties();
			// Explicitly unset properties that are being deleted, as the loaded
			// values could be outdated
			foreach ( $localWikiPropertiesDeleted as $propToDelete ) {
				unset( $election->properties[$propToDelete] );
			}

			[ $title, $content ] = SecurePollContentHandler::makeContentFromElection(
				$election
			);
			$wp = $this->wikiPageFactory->newFromTitle( $title );
			$wp->doUserEditContent(
				$content,
				$this->specialPage->getUser(),
				$this->msg( 'securepoll-votereligibility-cleared-comment', $name )->text()
			);

			$title = SecurePollContentHandler::getTitleForPage( "{$election->getId()}/list/$property" );
			$wp = $this->wikiPageFactory->newFromTitle( $title );
			$wp->doUserEditContent(
				SecurePollContentHandler::makeContent( '[]', $title, 'SecurePoll' ),
				$this->specialPage->getUser(),
				$this->msg( 'securepoll-votereligibility-cleared-comment', $name )->text()
			);
		}

		$out->setPageTitleMsg( $this->msg( 'securepoll-votereligibility-cleared' ) );
		$out->addWikiMsg( 'securepoll-votereligibility-cleared-text', $name );
		$out->returnToMain(
			false,
			SpecialPage::getTitleFor( 'SecurePoll', 'votereligibility/' . $this->election->getId() )
		);
	}
}
