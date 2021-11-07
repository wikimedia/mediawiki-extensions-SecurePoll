<?php

namespace MediaWiki\Extensions\SecurePoll;

use Generator;
use InvalidArgumentException;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\SlotRecord;
use Parser;
use ParserOptions;
use Title;

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../../..';
}
require_once "$IP/maintenance/Maintenance.php";
require __DIR__ . '/includes/MailingListEntry.php';

/**
 * For documentation please see
 * https://wikitech.wikimedia.org/wiki/SecurePoll#Email_spam
 */
class SendMail extends \Maintenance {
	/** @var Parser */
	private $parser;

	/** @var LanguageFactory */
	private $languageFactory;

	/** @var LanguageFallback */
	private $languageFallback;

	/** @var RevisionLookup */
	private $revisionLookup;

	/** @var string[] */
	private $textCache;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Send mail to users specified by a flat file mailing list' );
		$this->addOption( 'page',
			'The title of the page which will be used to get the message for ' .
			'the subject and body. Should be a Translate root page. ' .
			'See https://wikitech.wikimedia.org/wiki/SecurePoll#Email_spam for full requirements.',
			true, true );
		$this->addOption( 'sender',
			'The email address of the sender',
			true, true );
		$this->addArg( 'input-file',
			'The mailing list file generated by makeMailingList.php' );
	}

	private function initServices() {
		$services = MediaWikiServices::getInstance();
		$this->parser = $services->getParser();
		$this->languageFactory = $services->getLanguageFactory();
		$this->languageFallback = $services->getLanguageFallback();
		$this->revisionLookup = $services->getRevisionLookup();
	}

	public function execute() {
		$this->initServices();

		/** @var MailingListEntry $entry */
		foreach ( $this->getEntries() as $i => $entry ) {
			$message = $this->getMessage( $entry );
			$sender = new \MailAddress(
				$this->getOption( 'sender' ),
				$message['sender'] ?? null
			);
			\UserMailer::send(
				new \MailAddress( $entry->email, $entry->userName ),
				$sender,
				$message['subject'],
				[
					'html' => $message['html'],
					'text' => $message['text']
				]
			);
			print ( $i + 1 ) . ": {$entry->userName}\n";
		}
	}

	/**
	 * Get all mailing list entries as an iterator.
	 *
	 * @return Generator
	 */
	private function getEntries() {
		foreach ( $this->mArgs as $fileName ) {
			$file = fopen( $fileName, 'r' );
			if ( !$file ) {
				$this->fatalError( "Unable to open input file $fileName" );
			}
			foreach ( $this->readEntriesFromFile( $file, $fileName ) as $entry ) {
				yield $entry;
			}
		}
	}

	/**
	 * Read entries from one file-like resource.
	 *
	 * @param resource $file
	 * @param string $fileName
	 * @return Generator|iterable<MailingListEntry>
	 */
	private function readEntriesFromFile( $file, $fileName ) {
		for ( $lineNum = 1; true; $lineNum++ ) {
			$line = fgets( $file );
			if ( $line === false ) {
				break;
			}
			if ( $line === "\n" ) {
				continue;
			}
			try {
				yield MailingListEntry::newFromString( $line );
			} catch ( InvalidArgumentException $e ) {
				$this->error( "Skipping invalid entry in file $fileName line $lineNum" );
			}
		}
	}

	/**
	 * Get subject, HTML body and plaintext body for an entry
	 *
	 * @param MailingListEntry $entry
	 * @return array
	 */
	private function getMessage( MailingListEntry $entry ) {
		$baseTitleText = $this->getOption( 'page' );
		$realLanguage = $this->findLanguageSubpage(
			$baseTitleText,
			$entry->language
		);
		if ( !$realLanguage ) {
			$this->fatalError( "Can't find the /en subpage of the specified base page" );
		}
		$title = Title::newFromText( "$baseTitleText/$realLanguage" );
		$wikitext = $this->replaceVariables(
			$entry,
			$this->getPageText( $title )
		);
		return $this->parseWikitext(
			$realLanguage,
			$wikitext,
			$title
		);
	}

	/**
	 * Get the title of a subpage of the specified base page, following the
	 * language fallback sequence if it doesn't exist.
	 *
	 * @param string $base
	 * @param string $langCode
	 * @return string|null
	 */
	private function findLanguageSubpage( $base, $langCode ) {
		$languages = $this->languageFallback->getAll( $langCode );
		if ( $languages ) {
			$languages = array_merge(
				[ $langCode ],
				$languages
			);
		} else {
			$languages = [ 'en' ];
		}

		foreach ( $languages as $language ) {
			$title = Title::newFromText( $base . '/' . $language );
			if ( $title->exists() ) {
				return $language;
			}
		}
		return null;
	}

	/**
	 * @param Title $title
	 * @return string
	 */
	private function getPageText( $title ) {
		$pdbk = $title->getPrefixedDBkey();
		if ( !isset( $this->textCache[$pdbk] ) ) {
			$revision = $this->revisionLookup->getRevisionByTitle( $title );
			if ( !$revision ) {
				throw new \Exception( "Unable to load revision for title {$title}" );
			}
			$content = $revision->getContent( SlotRecord::MAIN );
			if ( !( $content instanceof \TextContent ) ) {
				throw new \Exception( "Page {$title} is not text" );
			}
			$this->textCache[$pdbk] = $content->getText();
		}
		return $this->textCache[$pdbk];
	}

	/**
	 * @param MailingListEntry $entry
	 * @param string $text
	 * @return string
	 */
	private function replaceVariables( MailingListEntry $entry, $text ) {
		$wikiRef = \WikiMap::getWiki( $entry->wiki );
		if ( !$wikiRef ) {
			$this->fatalError( "Invalid wiki: {$entry->wiki}" );
		}
		return strtr( $text, [
			'$USERNAME' => $entry->userName,
			'$ACTIVEPROJECT' => $entry->siteName,
			'$SERVER' => $wikiRef->getCanonicalServer()
		] );
	}

	/**
	 * @param string $text
	 * @param Title $title
	 * @return string
	 */
	private function extractPlainText( $text, $title ) {
		$titleText = $title->getPrefixedText();
		$start = strpos( $text, '<pre>' );
		$end = strpos( $text, '</pre>' );
		if ( $start === false || $end === false || $start > $end ) {
			$this->fatalError( "Error extracting pre element from $titleText" );
		}
		$start += strlen( '<pre>' );
		return trim( substr( $text, $start, $end - $start ) );
	}

	/**
	 * @param string $langCode
	 * @param string $text
	 * @param Title $title
	 * @return array
	 */
	private function parseWikitext( $langCode, $text, $title ) {
		$titleText = $title->getPrefixedText();

		$sec1 = $this->parser->getSection( $text, 1 );
		if ( $sec1 === '' ) {
			$this->fatalError( "Unable to find h2 section in page $titleText" );
		}
		if ( !preg_match( '/^==\s*(.*)==\s*$/m', $sec1, $m ) ) {
			$this->fatalError( "Unable to match heading in page $titleText" );
		}
		$subject = trim( $m[1] );
		$body = trim( substr( $sec1, strlen( $m[0] ) ) );
		$parserOptions = ParserOptions::newFromAnon();
		$lang = $this->languageFactory->getLanguage( $langCode );
		$parserOptions->setUserLang( $lang );
		$out = $this->parser->parse( $body, $title, $parserOptions );
		$html = \Html::rawElement(
			'div',
			[
				'dir' => $lang->getDir(),
				'lang' => $lang->getHtmlCode()
			],
			$out->getText( [
				'allowTOC' => false,
				'enableSectionEditLinks' => false,
				'unwrap' => true
			] )
		);

		$message = [
			'subject' => $subject,
			'html' => $html,
			'text' => $this->extractPlainText( $text, $title )
		];
		if ( preg_match( '/^From: *([^<\n]*)/m', $text, $m ) ) {
			$message['sender'] = $m[1];
		}
		return $message;
	}
}

$maintClass = SendMail::CLASS;
require_once RUN_MAINTENANCE_IF_MAIN;
