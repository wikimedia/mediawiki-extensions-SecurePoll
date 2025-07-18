<?php

namespace MediaWiki\Extension\SecurePoll;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Exception\MWExceptionHandler;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\DBError;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\LBFactory;

class TranslationRepo {

	/** @var LBFactory */
	private $lbFactory;

	/**
	 * @var WikiPageFactory
	 */
	private $wikiPageFactory = null;

	/**
	 *
	 * @param LBFactory $lbFactory
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct( LBFactory $lbFactory, WikiPageFactory $wikiPageFactory ) {
		$this->lbFactory = $lbFactory;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	/**
	 *
	 * @param Election $election
	 * @param array $data
	 * @param string $language
	 * @param User $user
	 * @param string $comment
	 * @return void
	 */
	public function setTranslation( $election, $data, $language, $user, $comment ) {
		$entities = array_merge( [ $election ], $election->getDescendants() );

		$replaceBatch = [];
		$jumpReplaceBatch = [];
		foreach ( $entities as $entity ) {
			foreach ( $entity->getMessageNames() as $messageName ) {
				$controlName = 'trans_' . $entity->getId() . '_' . $messageName;
				if ( isset( $data[ $controlName ] ) ) {
					$value = $data[ $controlName ];
					if ( $value !== '' ) {
						$replace = [
							'msg_entity' => $entity->getId(),
							'msg_lang' => $language,
							'msg_key' => $messageName,
							'msg_text' => $value
						];
						$replaceBatch[] = $replace;

						// Jump wikis don't have subentities
						if ( $entity === $election ) {
							$jumpReplaceBatch[] = $replace;
						}
					}
				}
			}
		}

		if ( $replaceBatch ) {
			$wikis = $election->getProperty( 'wikis' );
			if ( $wikis ) {
				$wikis = explode( "\n", $wikis );
				$wikis = array_map( 'trim', $wikis );
			} else {
				$wikis = [];
			}

			// First, the main wiki
			$dbw = $this->lbFactory->getMainLB()->getConnection( ILoadBalancer::DB_PRIMARY );

			$dbw->newReplaceQueryBuilder()
				->replaceInto( 'securepoll_msgs' )
				->uniqueIndexFields( [ 'msg_entity', 'msg_lang', 'msg_key' ] )
				->rows( $replaceBatch )
				->caller( __METHOD__ )
				->execute();

			if ( Context::isNamespacedLoggingEnabled() ) {
				// Create a new context to bypass caching
				$context = new Context;
				$contextElection = $context->getElection( $election->getId() );

				[ $title, $content ] = SecurePollContentHandler::makeContentFromElection(
					$contextElection,
					"msg/$language"
				);

				$page = $this->wikiPageFactory->newFromTitle( $title );
				$updater = $page->newPageUpdater( $user );
				$updater->setContent( SlotRecord::MAIN, $content );
				$updater->saveRevision(
					CommentStoreComment::newUnsavedComment( trim( $comment ) )
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
					$id = $dbw->newSelectQueryBuilder()
						->select( 'el_entity' )
						->from( 'securepoll_elections' )
						->where( [
							'el_title' => $election->title
						] )
						->caller( __METHOD__ )
						->fetchField();
					if ( $id && $jumpReplaceBatch ) {
						foreach ( $jumpReplaceBatch as &$row ) {
							$row['msg_entity'] = $id;
						}
						unset( $row );

						$dbw->newReplaceQueryBuilder()
							->replaceInto( 'securepoll_msgs' )
							->uniqueIndexFields( [ 'msg_entity', 'msg_lang', 'msg_key' ] )
							->rows( $jumpReplaceBatch )
							->caller( __METHOD__ )
							->execute();
					}
				} catch ( DBError $ex ) {
					// Log the exception, but don't abort the updating of the rest of the jump-wikis
					MWExceptionHandler::logException( $ex );
				}
			}
		}
	}

}
