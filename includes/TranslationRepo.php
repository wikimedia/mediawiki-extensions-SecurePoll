<?php

declare( strict_types=1 );

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

	public function __construct(
		private readonly LBFactory $lbFactory,
		private readonly WikiPageFactory $wikiPageFactory,
	) {
	}

	/**
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
		$deleteBatch = [];
		$jumpReplaceBatch = [];
		$jumpDeleteBatch = [];
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
					} else {
						$delete = [
							'msg_entity' => $entity->getId(),
							'msg_lang' => $language,
							'msg_key' => $messageName,
						];
						$deleteBatch[] = $delete;
						// Jump wikis don't have subentities
						if ( $entity === $election ) {
							$jumpDeleteBatch[] = $delete;
						}
					}
				}
			}
		}

		if ( $replaceBatch || $deleteBatch ) {
			$wikis = $election->getProperty( 'wikis' );
			if ( $wikis ) {
				$wikis = explode( "\n", $wikis );
				$wikis = array_map( 'trim', $wikis );
			} else {
				$wikis = [];
			}

			// First, the main wiki
			$dbw = $this->lbFactory->getMainLB()->getConnection( ILoadBalancer::DB_PRIMARY );

			if ( $replaceBatch ) {
				$dbw->newReplaceQueryBuilder()
					->replaceInto( 'securepoll_msgs' )
					->uniqueIndexFields( [ 'msg_entity', 'msg_lang', 'msg_key' ] )
					->rows( $replaceBatch )
					->caller( __METHOD__ )
					->execute();
			}
			if ( $deleteBatch ) {
				$deleteGroup = array_map( static fn ( $row ) => $dbw->andExpr( $row ), $deleteBatch );

				$dbw->newDeleteQueryBuilder()
					->deleteFrom( 'securepoll_msgs' )
					->where( $dbw->orExpr( $deleteGroup ) )
					->caller( __METHOD__ )
					->execute();
			}

			if ( Context::isNamespacedLoggingEnabled() ) {
				$context = new Context();
				$contextElection = $context->getElection( $election->getId() );
				$contextElection->loadMessages( $language );
				// Explicitly overwrite the values that have been changed, since the loaded
				// values could be outdated.
				foreach ( $replaceBatch as $row ) {
					$context->messageCache[$language][$row['msg_entity']][$row['msg_key']] = $row['msg_text'];
				}
				foreach ( $deleteBatch as $row ) {
					unset( $context->messageCache[$language][$row['msg_entity']][$row['msg_key']] );
				}

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
					if ( $id ) {
						if ( $jumpReplaceBatch ) {
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
						if ( $jumpDeleteBatch ) {
							foreach ( $jumpDeleteBatch as &$row ) {
								$row['msg_entity'] = $id;
							}
							unset( $row );
							$deleteGroup = array_map( static fn ( $row ) => $dbw->andExpr( $row ), $jumpDeleteBatch );

							$dbw->newDeleteQueryBuilder()
								->deleteFrom( 'securepoll_msgs' )
								->where( $dbw->orExpr( $deleteGroup ) )
								->caller( __METHOD__ )
								->execute();
						}
					}
				} catch ( DBError $ex ) {
					// Log the exception, but don't abort the updating of the rest of the jump-wikis
					MWExceptionHandler::logException( $ex );
				}
			}
		}
	}

}
