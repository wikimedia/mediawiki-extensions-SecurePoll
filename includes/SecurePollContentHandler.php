<?php

namespace MediaWiki\Extension\SecurePoll;

use ContentHandler;
use FormatJson;
use JsonContentHandler;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Exceptions\InvalidDataException;
use MediaWiki\Title\Title;

/**
 * SecurePoll Content Handler
 *
 * @file
 * @ingroup Extensions
 * @ingroup SecurePoll
 *
 * @author Brad Jorsch <bjorsch@wikimedia.org>
 */
class SecurePollContentHandler extends JsonContentHandler {
	public function __construct( $modelId = 'SecurePoll' ) {
		parent::__construct( $modelId );
	}

	/**
	 * Load data from an election as a PHP array structure
	 *
	 * @param Election $election
	 * @param string $subpage Subpage to get content for
	 * @param bool $useExclusion
	 * @return array
	 */
	public static function getDataFromElection(
		Election $election,
		$subpage = '',
		$useExclusion = false
	) {
		if ( $subpage === '' ) {
			$properties = $election->getAllProperties();
			if ( $useExclusion ) {
				$excludedNames = array_flip( $election->getPropertyDumpExclusion() ) + [
					'gpg-encrypt-key' => true,
					'gpg-sign-key' => true,
					'gpg-decrypt-key' => true,
				];

				foreach ( $properties as $k => $v ) {
					if ( isset( $excludedNames[$k] ) ) {
						$properties[$k] = '<redacted>';
					}
				}
				unset(
					$properties['list_job-key'],
					$properties['list_total-count'],
					$properties['list_complete-count']
				);
			}
			$data = [
				'id' => $election->getId(),
				'title' => $election->title,
				'ballot' => $election->ballotType,
				'tally' => $election->tallyType,
				'lang' => $election->getLanguage(),
				'startDate' => wfTimestamp( TS_ISO_8601, $election->getStartDate() ),
				'endDate' => wfTimestamp( TS_ISO_8601, $election->getEndDate() ),
				'authType' => $election->authType,
				'properties' => $properties,
				'questions' => [],
			];

			foreach ( $election->getQuestions() as $question ) {
				$properties = $question->getAllProperties();
				if ( $useExclusion ) {
					$excludedNames = array_flip( $question->getPropertyDumpExclusion() );
					foreach ( $properties as $k => $v ) {
						if ( isset( $excludedNames[$k] ) ) {
							$properties[$k] = '<redacted>';
						}
					}
				}
				$q = [
					'id' => $question->getId(),
					'properties' => $properties,
					'options' => [],
				];

				foreach ( $question->getOptions() as $option ) {
					$properties = $option->getAllProperties();
					if ( $useExclusion ) {
						$excludedNames = array_flip( $option->getPropertyDumpExclusion() );
						foreach ( $properties as $k => $v ) {
							if ( isset( $excludedNames[$k] ) ) {
								$properties[$k] = '<redacted>';
							}
						}
					}
					$o = [
						'id' => $option->getId(),
						'properties' => $properties,
					];
					$q['options'][] = $o;
				}

				$data['questions'][] = $q;
			}
		} elseif ( preg_match( '#^msg/(\S+)$#', $subpage, $m ) ) {
			$lang = $m[1];
			$data = [
				'id' => $election->getId(),
				'lang' => $lang,
				'messages' => [],
				'questions' => [],
			];
			foreach ( $election->getMessageNames() as $name ) {
				$value = $election->getRawMessage( $name, $lang );
				if ( $value !== false ) {
					$data['messages'][$name] = $value;
				}
			}

			foreach ( $election->getQuestions() as $question ) {
				$q = [
					'id' => $question->getId(),
					'messages' => [],
					'options' => [],
				];
				foreach ( $question->getMessageNames() as $name ) {
					$value = $question->getRawMessage( $name, $lang );
					if ( $value !== false ) {
						$q['messages'][$name] = $value;
					}
				}

				foreach ( $question->getOptions() as $option ) {
					$o = [
						'id' => $option->getId(),
						'messages' => [],
					];
					foreach ( $option->getMessageNames() as $name ) {
						$value = $option->getRawMessage( $name, $lang );
						if ( $value !== false ) {
							$o['messages'][$name] = $value;
						}
					}
					$q['options'][] = $o;
				}

				$data['questions'][] = $q;
			}
		} else {
			throw new InvalidDataException( __METHOD__ . ': Unsupported subpage format' );
		}

		return $data;
	}

	/**
	 * Create a SecurePollContent for an election
	 *
	 * @param Election $election
	 * @param string $subpage Subpage to get content for
	 * @return array ( Title, SecurePollContent )
	 */
	public static function makeContentFromElection( Election $election, $subpage = '' ) {
		$json = FormatJson::encode(
			self::getDataFromElection( $election, $subpage, true ),
			false,
			FormatJson::ALL_OK
		);
		$title = Title::makeTitle(
			NS_SECUREPOLL,
			$election->getId() . ( $subpage === '' ? '' : "/$subpage" )
		);

		return [
			$title,
			ContentHandler::makeContent( $json, $title, 'SecurePoll' )
		];
	}

	public function canBeUsedOn( Title $title ) {
		global $wgSecurePollUseNamespace;

		return $wgSecurePollUseNamespace && $title->getNamespace() == NS_SECUREPOLL;
	}

	public function getActionOverrides() {
		// Disable write actions
		return [
			'delete' => false,
			'edit' => false,
			'info' => false,
			'protect' => false,
			'revert' => false,
			'rollback' => false,
			'submit' => false,
			'unprotect' => false,
		];
	}

	protected function getContentClass() {
		return SecurePollContent::class;
	}
}
