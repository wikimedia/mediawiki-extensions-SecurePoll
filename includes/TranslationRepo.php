<?php

namespace MediaWiki\Extension\SecurePoll;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Config\Config;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;
use MWExceptionHandler;
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
	 * @var bool
	 */
	private $useNamespace = false;

	/**
	 *
	 * @param LBFactory $lbFactory
	 * @param WikiPageFactory $wikiPageFactory
	 * @param Config $config
	 */
	public function __construct( LBFactory $lbFactory, WikiPageFactory $wikiPageFactory, Config $config ) {
		$this->lbFactory = $lbFactory;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->useNamespace = $config->get( 'SecurePollUseNamespace' );
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

			if ( $this->useNamespace ) {
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
					$id = $dbw->selectField(
						'securepoll_elections',
						'el_entity',
						[
							'el_title' => $election->title
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
				} catch ( DBError $ex ) {
					// Log the exception, but don't abort the updating of the rest of the jump-wikis
					MWExceptionHandler::logException( $ex );
				}
				$lb->reuseConnection( $dbw );
			}
		}
	}

}
