<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use CommentStoreComment;
use Exception;
use Linker;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\SecurePollContentHandler;
use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use Message;
use MWException;
use MWExceptionHandler;
use Title;
use WikiMap;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\LBFactory;

/**
 * A SecurePoll subpage for translating election messages.
 */
class TranslatePage extends ActionPage {
	/** @var bool|null */
	public $isAdmin;

	/** @var LBFactory */
	private $lbFactory;

	/** @var LanguageNameUtils */
	private $languageNameUtils;

	/**
	 * @param SpecialSecurePoll $specialPage
	 * @param LBFactory $lbFactory
	 * @param LanguageNameUtils $languageNameUtils
	 */
	public function __construct(
		SpecialSecurePoll $specialPage,
		LBFactory $lbFactory,
		LanguageNameUtils $languageNameUtils
	) {
		parent::__construct( $specialPage );
		$this->lbFactory = $lbFactory;
		$this->languageNameUtils = $languageNameUtils;
	}

	/**
	 * Execute the subpage.
	 * @param array $params Array of subpage parameters.
	 */
	public function execute( $params ) {
		$out = $this->specialPage->getOutput();
		$request = $this->specialPage->getRequest();
		$out->enableOOUI();

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
		$this->initLanguage( $this->specialPage->getUser(), $this->election );
		$out->setPageTitle(
			$this->msg(
				'securepoll-translate-title',
				$this->election->getMessage( 'title' )
			)->text()
		);

		$jumpUrl = $this->election->getProperty( 'jump-url' );
		if ( $jumpUrl ) {
			$jumpId = $this->election->getProperty( 'jump-id' );
			if ( !$jumpId ) {
				throw new MWException( 'Configuration error: no jump-id' );
			}
			$jumpUrl .= "/edit/$jumpId";
			if ( count( $params ) > 1 ) {
				$jumpUrl .= '/' . implode( '/', array_slice( $params, 1 ) );
			}

			$wiki = $this->election->getProperty( 'main-wiki' );
			if ( $wiki ) {
				$wiki = WikiMap::getWikiName( $wiki );
			} else {
				$wiki = $this->msg( 'securepoll-edit-redirect-otherwiki' )->text();
			}

			$out->addWikiMsg(
				'securepoll-edit-redirect',
				Message::rawParam( Linker::makeExternalLink( $jumpUrl, $wiki ) )
			);

			return;
		}

		$this->isAdmin = $this->election->isAdmin( $this->specialPage->getUser() );

		$primary = $this->election->getLanguage();
		$secondary = $request->getVal( 'secondary_lang' );
		if ( $secondary !== null ) {
			# Language selector submitted: redirect to the subpage
			$out->redirect( $this->getTitle( $secondary )->getFullURL() );

			return;
		}

		if ( !isset( $params[1] ) ) {
			# No language selected, show the selector
			$this->showLanguageSelector( $primary );
			return;
		}

		$secondary = $params[1];
		$inLanguage = $this->specialPage->getLanguage()->getCode();
		$primaryName = $this->languageNameUtils->getLanguageName( $primary, $inLanguage );
		$secondaryName = $this->languageNameUtils->getLanguageName( $secondary, $inLanguage );
		if ( strval( $secondaryName ) === '' ) {
			$out->addWikiMsg( 'securepoll-invalid-language', $secondary );
			$this->showLanguageSelector( $primary );
			return;
		}

		# Set a subtitle to return to the language selector
		$this->specialPage->setSubtitle(
			[
				$this->getTitle(),
				$this->msg(
					'securepoll-translate-title',
					$this->election->getMessage( 'title' )
				)->text()
			]
		);

		# If the request was posted, do the submission
		if ( $request->wasPosted() && $request->getVal( 'action' ) == 'submit' ) {
			$this->doSubmit( $secondary );
			return;
		}

		# Show the form
		$action = $this->getTitle( $secondary )->getLocalURL( 'action=submit' );
		$form = new \OOUI\FormLayout( [ 'method' => 'post', 'action' => $action ] );

		$table = new \OOUI\Tag( 'table' );
		$table->addClasses( [ 'mw-datatable', 'TablePager', 'securepoll-trans-table' ] )->appendContent(
			( new \OOUI\Tag( 'thead' ) )->appendContent( ( new \OOUI\Tag( 'tr' ) )->appendContent(
				( new \OOUI\Tag( 'th' ) )->appendContent( $this->msg( 'securepoll-header-trans-id' ) ),
				( new \OOUI\Tag( 'th' ) )->appendContent( $primaryName ),
				( new \OOUI\Tag( 'th' ) )->appendContent( $secondaryName )
			) )
		);
		$tbody = new \OOUI\Tag( 'tbody' );
		$table->appendContent( $tbody );

		$entities = array_merge( [ $this->election ], $this->election->getDescendants() );
		foreach ( $entities as $entity ) {
			$entityName = $entity->getType() . '(' . $entity->getId() . ')';
			foreach ( $entity->getMessageNames() as $messageName ) {
				$tbody->appendContent( ( new \OOUI\Tag( 'tr' ) )->appendContent(
					( new \OOUI\Tag( 'td' ) )->appendContent( "$entityName/$messageName" ),
					( new \OOUI\Tag( 'td' ) )->appendContent( new \OOUI\HtmlSnippet(
						nl2br( htmlspecialchars( $entity->getRawMessage( $messageName, $primary ) ) )
					) ),
					( new \OOUI\Tag( 'td' ) )->appendContent( new \OOUI\MultilineTextInputWidget( [
						'name' => 'trans_' . $entity->getId() . '_' . $messageName,
						'value' => $entity->getRawMessage( $messageName, $secondary ),
						'classes' => [ 'securepoll-translate-box' ],
						'readonly' => !$this->isAdmin,
						'rows' => 2,
					] ) )
				) );
			}
		}

		$fields = new \OOUI\FieldsetLayout();

		$fields->addItems( [ new \OOUI\Element( [ 'content' => [ $table ] ] ) ] );

		if ( $this->isAdmin && $this->specialPage->getConfig()->get( 'SecurePollUseNamespace' ) ) {
			$fields->addItems( [ new \OOUI\FieldLayout( new \OOUI\TextInputWidget( [
				'name' => 'comment',
				'maxlength' => 250,
			] ), [
				'label' => $this->msg( 'securepoll-translate-label-comment' ),
				'align' => 'top',
			] ) ] );
		}

		$fields->addItems( [ new \OOUI\FieldLayout( new \OOUI\ButtonInputWidget( [
			'label' => $this->msg( 'securepoll-submit-translate' )->text(),
			'flags' => [ 'primary', 'progressive' ],
			'type' => 'submit',
			'disabled' => !$this->isAdmin,
		] ) ) ] );

		$form->appendContent( $fields );

		$out->addHTML( $form );
	}

	/**
	 * @param string|false $lang
	 * @return Title
	 */
	public function getTitle( $lang = false ) {
		$subpage = 'translate/' . $this->election->getId();
		if ( $lang !== false ) {
			$subpage .= '/' . $lang;
		}

		return $this->specialPage->getPageTitle( $subpage );
	}

	/**
	 * Show a language selector to allow the user to choose the language to
	 * translate.
	 * @param string $selectedCode
	 */
	public function showLanguageSelector( $selectedCode ) {
		$languages = $this->languageNameUtils->getLanguageNames();
		ksort( $languages );

		$form = new \OOUI\FormLayout( [
			'action' => $this->getTitle( false )->getLocalURL(),
			'method' => 'get',
			'items' => [ new \OOUI\FieldsetLayout( [ 'items' => [
				new \OOUI\FieldLayout( new \OOUI\DropdownInputWidget( [
					'name' => 'secondary_lang',
					'value' => $selectedCode,
					'options' => array_map( static function ( $code, $name ) {
						return [
							'label' => "$code - $name",
							'data' => $code,
						];
					}, array_keys( $languages ), $languages )
				] ) ),
				new \OOUI\FieldLayout( new \OOUI\ButtonInputWidget( [
					'label' => $this->msg( 'securepoll-submit-select-lang' )->text(),
					'flags' => [ 'primary', 'progressive' ],
					'type' => 'submit',
				] ) ),
			] ] ) ]
		] );
		$this->specialPage->getOutput()->addHTML( $form );
	}

	/**
	 * Submit message text changes.
	 * @param string $secondary
	 */
	public function doSubmit( $secondary ) {
		$out = $this->specialPage->getOutput();
		$request = $this->specialPage->getRequest();

		if ( !$this->isAdmin ) {
			$out->addWikiMsg( 'securepoll-need-admin' );

			return;
		}

		$entities = array_merge( [ $this->election ], $this->election->getDescendants() );
		$replaceBatch = [];
		$jumpReplaceBatch = [];
		foreach ( $entities as $entity ) {
			foreach ( $entity->getMessageNames() as $messageName ) {
				$controlName = 'trans_' . $entity->getId() . '_' . $messageName;
				$value = $request->getText( $controlName );
				if ( $value !== '' ) {
					$replaceBatch[] = [
						'msg_entity' => $entity->getId(),
						'msg_lang' => $secondary,
						'msg_key' => $messageName,
						'msg_text' => $value
					];

					// Jump wikis don't have subentities
					if ( $entity === $this->election ) {
						$jumpReplaceBatch[] = [
							'msg_entity' => $entity->getId(),
							'msg_lang' => $secondary,
							'msg_key' => $messageName,
							'msg_text' => $value
						];
					}
				}
			}
		}
		if ( $replaceBatch ) {
			$wikis = $this->election->getProperty( 'wikis' );
			if ( $wikis ) {
				$wikis = explode( "\n", $wikis );
			} else {
				$wikis = [];
			}

			// First, the main wiki
			$dbw = $this->lbFactory->getMainLB()->getConnectionRef( ILoadBalancer::DB_PRIMARY );
			$dbw->replace(
				'securepoll_msgs',
				[
					[
						'msg_entity',
						'msg_lang',
						'msg_key'
					]
				],
				$replaceBatch,
				__METHOD__
			);

			if ( $this->specialPage->getConfig()->get( 'SecurePollUseNamespace' ) ) {
				// Create a new context to bypass caching
				$context = new Context;
				$election = $context->getElection( $this->election->getId() );

				list( $title, $content ) = SecurePollContentHandler::makeContentFromElection(
					$election,
					"msg/$secondary"
				);

				$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
				$updater = $page->newPageUpdater( $this->specialPage->getUser() );
				$updater->setContent( SlotRecord::MAIN, $content );
				$updater->saveRevision(
					CommentStoreComment::newUnsavedComment( trim( $request->getText( 'comment' ) ) )
				);
			}

			// Then each jump-wiki
			foreach ( $wikis as $dbname ) {
				if ( $dbname === WikiMap::getCurrentWikiId() ) {
					continue;
				}

				$lb = $this->lbFactory->getMainLB( $dbname );
				$dbw = $lb->getConnection( ILoadBalancer::DB_PRIMARY, [], $dbname );
				try {
					$id = $dbw->selectField(
						'securepoll_elections',
						'el_entity',
						[
							'el_title' => $this->election->title
						],
						__METHOD__
					);
					if ( $id ) {
						foreach ( $jumpReplaceBatch as &$row ) {
							$row['msg_entity'] = $id;
						}
						unset( $row );

						$dbw->replace(
							'securepoll_msgs',
							[
								[
									'msg_entity',
									'msg_lang',
									'msg_key'
								]
							],
							$jumpReplaceBatch,
							__METHOD__
						);
					}
				} catch ( Exception $ex ) {
					// Log the exception, but don't abort the updating of the rest of the jump-wikis
					MWExceptionHandler::logException( $ex );
				}
				$lb->reuseConnection( $dbw );
			}
		}
		$out->redirect( $this->getTitle( $secondary )->getFullURL() );
	}
}
