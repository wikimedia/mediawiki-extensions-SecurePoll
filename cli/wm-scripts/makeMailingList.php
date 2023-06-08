<?php

namespace MediaWiki\Extension\SecurePoll;

use ExtensionRegistry;
use Generator;
use Maintenance;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Store\DBStore;
use MediaWiki\Extension\SecurePoll\User\LocalAuth;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\WikiMap\WikiMap;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Wikimedia\Rdbms\ILBFactory;
use Wikimedia\Rdbms\ILoadBalancer;

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../../..';
}
require_once "$IP/maintenance/Maintenance.php";
require __DIR__ . '/includes/MailingListEntry.php';

class MakeMailingList extends Maintenance {
	/** @var Context */
	private $context;

	/** @var Election */
	private $election;

	/** @var array|null */
	private $nomailUsers;

	/** @var array|null */
	private $votersByName;

	/** @var resource */
	private $outFile;

	/** @var ILoadBalancer */
	private $localLoadBalancer;

	/** @var ILBFactory */
	private $loadBalancerFactory;

	/** @var UserFactory */
	private $userFactory;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var LoggerInterface */
	private $logger;

	/** @var int */
	private $numInNomail = 0;
	/** @var int */
	private $numInvalidEmail = 0;
	/** @var int */
	private $numAlreadyVoted = 0;
	/** @var int */
	private $numNotQualified = 0;
	/** @var int */
	private $numWritten = 0;
	/** @var int */
	private $numInExcludeList = 0;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Make a flat file mailing list for local qualified voters of an election' );
		$this->addOption( 'election', 'Election title or ID', true, true );
		$this->addOption( 'election-wiki',
			'The wiki on which the election is stored, or omit for the local wiki',
			false, true );
		$this->addOption( 'exclude-central-list',
			'Exclude voters in the specified central list',
			false, true );
		$this->addOption( 'output-file',
			'The filename to write to, or omit for stdout',
			false, true, 'o' );
		$this->setBatchSize( 1000 );
	}

	public function execute() {
		$this->initServices();
		$this->context = new Context;

		if ( $this->hasOption( 'election-wiki' ) ) {
			$electionWiki = $this->getOption( 'election-wiki' );
			$this->context->setStore(
				new DBStore(
					$this->loadBalancerFactory->getMainLB( $electionWiki ),
					$electionWiki
				)
			);
		}

		$electionTitle = $this->getOption( 'election' );
		$this->election = $this->context->getElectionByTitle( $electionTitle );
		if ( !$this->election && preg_match( '/^[0-9]+$/', $electionTitle ) ) {
			$this->election = $this->context->getElection( intval( $electionTitle ) );
		}
		if ( !$this->election ) {
			$this->fatalError( "Cannot find election \"$electionTitle\"\n" );
		}

		if ( $this->hasOption( 'output-file' ) ) {
			$this->outFile = fopen( $this->getOption( 'output-file' ), 'w' );
			if ( !$this->outFile ) {
				$this->fatalError( "Cannot open output file" );
			}
		} else {
			$this->outFile = STDOUT;
		}

		$excludeCentralList = $this->getOption( 'exclude-central-list' );

		$localAuth = new LocalAuth( $this->context );
		/** @var User $user */
		foreach ( $this->generateUsers() as $user ) {
			if ( !$user->isEmailConfirmed() ) {
				$this->debug( "{$user->getName()}: no valid email address" );
				$this->numInvalidEmail++;
				continue;
			}
			if ( $this->isInNomailList( $user ) ) {
				$this->debug( "{$user->getName()}: in nomail list" );
				$this->numInNomail++;
				continue;
			}
			if ( $this->alreadyVoted( $user ) ) {
				$this->debug( "{$user->getName()}: already voted" );
				$this->numAlreadyVoted++;
				continue;
			}
			$params = $localAuth->getUserParamsFast( $user );
			$status = $this->election->getQualifiedStatus( $params );
			if ( !$status->isOK() ) {
				$this->debug( "{$user->getName()}: not qualified" );
				$this->numNotQualified++;
				continue;
			}
			if ( $excludeCentralList
				&& in_array( $excludeCentralList, $params['properties']['central-lists'] )
			) {
				$this->debug( "{$user->getName()}: in exclusion list" );
				$this->numInExcludeList++;
				continue;
			}
			$this->numWritten++;
			$this->writeUser( $user );
		}
		fwrite( STDERR,
			"Users added: {$this->numWritten}\n" .
			"Not qualified: {$this->numNotQualified}\n" .
			"In exclude list: {$this->numInExcludeList}\n" .
			"Already voted: {$this->numAlreadyVoted}\n" .
			"No valid address: {$this->numInvalidEmail}\n" .
			"In nomail list: {$this->numInNomail}\n"
		);
	}

	private function initServices() {
		$services = MediaWikiServices::getInstance();
		$this->loadBalancerFactory = $services->getDBLoadBalancerFactory();
		$this->localLoadBalancer = $this->loadBalancerFactory->getMainLB();
		$this->userFactory = $services->getUserFactory();
		$this->userOptionsLookup = $services->getUserOptionsLookup();
		$this->logger = LoggerFactory::getInstance( 'MakeMailingList' );
	}

	/**
	 * Get an iterator over User objects for users that may be able to vote.
	 *
	 * @return Generator
	 */
	private function generateUsers() {
		$centralList = $this->election->getProperty( 'need-central-list' );
		if ( $centralList ) {
			return $this->generateCentralListUsers( $centralList );
		} else {
			return $this->generateAllUsers();
		}
	}

	/**
	 * Get an iterator over User objects for users that are in a given central
	 * list (centralauth.securepoll_lists).
	 *
	 * @param string $centralList
	 * @return Generator
	 */
	private function generateCentralListUsers( $centralList ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) ) {
			return;
		}
		$dbcr = CentralAuthServices::getDatabaseManager()->getCentralReplicaDB();
		$dbr = $this->localLoadBalancer->getConnection( DB_REPLICA );

		$offsetId = 0;
		do {
			// Get a list of local users who are in the central list and are
			// attached to their central account
			$centralRes = $dbcr->newSelectQueryBuilder()
				->select( [ 'gu_name', 'gu_id' ] )
				->from( 'globaluser' )
				// Note: no key on lu_global_id, it's conventional to join on name
				->join( 'localuser', 'localuser', 'lu_name=gu_name' )
				->join( 'securepoll_lists', null, 'li_member=gu_id' )
				->where( [
					'lu_wiki' => WikiMap::getCurrentWikiId(),
					'li_name' => $centralList,
					'gu_id > ' . $dbcr->addQuotes( $offsetId ),
				] )
				->orderBy( 'gu_id' )
				->limit( $this->getBatchSize() )
				->caller( __METHOD__ )
				->fetchResultSet();

			if ( $centralRes->numRows() ) {
				$names = [];
				foreach ( $centralRes as $row ) {
					$names[] = $row->gu_name;
					$offsetId = $row->gu_id;
				}
				$res = $dbr->newSelectQueryBuilder()
					->queryInfo( User::getQueryInfo() )
					->where( [
						'user_name' => $names,
						'user_editcount > 0'
					] )
					->caller( __METHOD__ )
					->fetchResultSet();
				foreach ( $res as $row ) {
					yield $this->userFactory->newFromRow( $row );
				}
			}
		} while ( $centralRes->numRows() === $this->getBatchSize() );
	}

	/**
	 * Get an iterator for all local users
	 *
	 * @return Generator
	 */
	private function generateAllUsers() {
		$dbr = $this->localLoadBalancer->getConnection( DB_REPLICA );
		$offsetId = 0;

		do {
			$res = $dbr->newSelectQueryBuilder()
				->queryInfo( User::getQueryInfo() )
				->where( 'user_id > ' . $dbr->addQuotes( $offsetId ) )
				->orderBy( 'user_id' )
				->limit( $this->getBatchSize() )
				->caller( __METHOD__ )
				->fetchResultSet();
			foreach ( $res as $row ) {
				yield User::newFromRow( $row );
			}
		} while ( $res->numRows() === $this->getBatchSize() );
	}

	/**
	 * Check if the user is in [[Wikimedia_Foundation_nomail_list]]
	 *
	 * @param User $user
	 * @return bool
	 */
	private function isInNomailList( $user ) {
		if ( $this->nomailUsers === null ) {
			$services = MediaWikiServices::getInstance();
			$raw = $services->getHttpRequestFactory()->get(
				'https://meta.wikimedia.org/wiki/Wikimedia_Foundation_nomail_list?action=raw'
			);
			if ( !$raw ) {
				throw new RuntimeException( "Unable to fetch Wikimedia nomail list" );
			}
			if ( preg_match( '/(?<=<pre>).*(?=<\/pre>)/s', $raw, $matches ) ) {
				$this->nomailUsers = array_fill_keys(
					array_filter(
						array_map( 'trim',
							explode( "\n", $matches[0] )
						)
					),
					true
				);
			}
		}
		return isset( $this->nomailUsers[$user->getName()] );
	}

	public function alreadyVoted( $user ) {
		if ( $this->votersByName === null ) {
			$this->votersByName = [];
			$db = $this->context->getDB();
			$res = $db->newSelectQueryBuilder()
				->select( [ 'voter_name' ] )
				->from( 'securepoll_voters' )
				->join( 'securepoll_votes', null, 'voter_id=vote_voter' )
				->where( [ 'vote_election' => $this->election->getId() ] )
				->caller( __METHOD__ )
				->fetchResultSet();
			foreach ( $res as $row ) {
				$this->votersByName[$row->voter_name] = 1;
			}
		}
		return isset( $this->votersByName[$user->getName()] );
	}

	/**
	 * Write a mailing list entry for the given user.
	 *
	 * @param User $user
	 */
	private function writeUser( $user ) {
		global $wgSitename;

		$entry = new MailingListEntry;
		$entry->wiki = WikiMap::getCurrentWikiId();
		$entry->siteName = $wgSitename;
		$entry->userName = $user->getName();
		$entry->email = $user->getEmail();
		$entry->language = $this->userOptionsLookup->getOption( $user, 'language' );
		$entry->editCount = $user->getEditCount();

		fwrite( $this->outFile, $entry->toString() );
	}

	private function debug( $msg ) {
		$this->logger->debug( $msg );
	}
}

$maintClass = MakeMailingList::class;
require_once RUN_MAINTENANCE_IF_MAIN;
