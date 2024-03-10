<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use MediaWiki\Extension\SecurePoll\Exceptions\InvalidDataException;
use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\Extension\SecurePoll\TranslationRepo;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Linker\Linker;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;
use Message;

/**
 * A SecurePoll subpage for translating election messages.
 */
class TranslatePage extends ActionPage {

	/** @var bool|null */
	public $isAdmin;

	/** @var LanguageNameUtils */
	private $languageNameUtils;

	/** @var TranslationRepo */
	private $translationRepo;

	/**
	 * @param SpecialSecurePoll $specialPage
	 * @param LanguageNameUtils $languageNameUtils
	 * @param TranslationRepo $translationRepo
	 */
	public function __construct(
		SpecialSecurePoll $specialPage,
		LanguageNameUtils $languageNameUtils,
		TranslationRepo $translationRepo
	) {
		parent::__construct( $specialPage );
		$this->languageNameUtils = $languageNameUtils;
		$this->translationRepo = $translationRepo;
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
		$out->setPageTitleMsg( $this->msg( 'securepoll-translate-title', $this->election->getMessage( 'title' ) ) );

		$jumpUrl = $this->election->getProperty( 'jump-url' );
		if ( $jumpUrl ) {
			$jumpId = $this->election->getProperty( 'jump-id' );
			if ( !$jumpId ) {
				throw new InvalidDataException( 'Configuration error: no jump-id' );
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
			$sourceConfig = MediaWikiServices::getInstance()->getMainConfig()
			->get( 'SecurePollTranslationImportSourceUrl' );
			$out->addJsConfigVars( 'SecurePollTranslationImportSourceUrl', $sourceConfig );

			$out->addJsConfigVars( 'SecurePollSubPage', 'translate' );
			$out->addModules( 'ext.securepoll.htmlform' );
			return;
		}

		$secondary = $params[1];
		$inLanguage = $this->specialPage->getLanguage()->getCode();
		$primaryName = $this->languageNameUtils->getLanguageName( $primary, $inLanguage );
		$secondaryName = $this->languageNameUtils->getLanguageName( $secondary, $inLanguage );
		if ( $secondaryName === '' ) {
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
					( new \OOUI\Tag( 'td' ) )->addClasses( [ 'trans-id' ] )
							->appendContent( "$entityName/$messageName" ),
					( new \OOUI\Tag( 'td' ) )->addClasses( [ 'trans-origin' ] )->appendContent( new \OOUI\HtmlSnippet(
						nl2br( htmlspecialchars( $entity->getRawMessage( $messageName, $primary ) ) )
					) ),
					( new \OOUI\Tag( 'td' ) )->addClasses( [ 'trans-lang' ] )->appendContent(
						new \OOUI\MultilineTextInputWidget( [
							'name' => 'trans_' . $entity->getId() . '_' . $messageName,
							'value' => $entity->getRawMessage( $messageName, $secondary ),
							'classes' => [ 'securepoll-translate-box' ],
							'readonly' => !$this->isAdmin,
							'autosize' => true
						] )
					) )
				);
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

		$apiEndpoint = $this->specialPage->getConfig()->get( 'SecurePollTranslationImportSourceUrl' );

		$form = new \OOUI\FormLayout( [
			'action' => $this->getTitle( false )->getLocalURL(),
			'method' => 'get',
			'items' => [ new \OOUI\FieldsetLayout( [ 'items' => [
				new \OOUI\HorizontalLayout( [
					'id' => 'sp-translation-selection',
					'items' => [
						new \OOUI\DropdownInputWidget( [
							'name' => 'secondary_lang',
							'value' => $selectedCode,
							'options' => array_map( static function ( $code, $name ) {
								return [
									'label' => "$code - $name",
									'data' => $code,
								];
							}, array_keys( $languages ), $languages )
						] ),
						new \OOUI\ButtonInputWidget( [
							'label' => $this->msg( 'securepoll-submit-select-lang' )->text(),
							'flags' => [ 'primary' ],
							'type' => 'submit',
						] )
					]
				] )
			] ] ) ]
		] );

		if ( $apiEndpoint !== '' ) {
			$form->addItems( [
				new \OOUI\FieldLayout(
					new \OOUI\LabelWidget( [
						'label' => $this->msg( 'securepoll-subpage-translate-info', $apiEndpoint )->text()
					] )
				),
				new \OOUI\FieldLayout(
					new \OOUI\ButtonInputWidget( [
						'id' => 'import-trans-btn',
						'infusable' => true,
						'label' => $this->msg( 'securepoll-translate-import-button-label' )->text(),
						'flags' => [ 'primary', 'progressive' ],
						'disabled' => !$this->isAdmin
					] )
				)
			], 0 );
		}
		$this->specialPage->getOutput()->addHTML( $form );
	}

	/**
	 * Submit message text changes.
	 * @param string $secondary
	 */
	public function doSubmit( $secondary ) {
		$out = $this->specialPage->getOutput();

		if ( !$this->isAdmin ) {
			$out->addWikiMsg( 'securepoll-need-admin' );
			return;
		}

		$request = $this->specialPage->getRequest();
		$data = $request->getValues();

		$this->translationRepo->setTranslation(
			$this->election,
			$data,
			$secondary,
			$this->specialPage->getContext()->getUser(),
			$this->specialPage->getContext()->getRequest()->getText( 'comment' )
		);

		$out->redirect( $this->getTitle( $secondary )->getFullURL() );
	}
}
