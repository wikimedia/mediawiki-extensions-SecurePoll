<?php

namespace MediaWiki\Extension\SecurePoll;

use MediaWiki\Content\ContentHandler;
use MediaWiki\Content\JsonContentHandler;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Exceptions\InvalidDataException;
use MediaWiki\Json\FormatJson;
use MediaWiki\MediaWikiServices;
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
	/** @inheritDoc */
	public function __construct( $modelId = 'SecurePoll' ) {
		parent::__construct( $modelId );
	}

	/**
	 * Load data from an election as a PHP array structure. This data is safe for
	 * public posting (it redacts things like encryption keys).
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
				$excludedNames = array_flip( $election->getPropertyDumpExclusion() );

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
	 * Figure out the title and JSON string content of a SecurePoll log page.
	 *
	 * Exactly which title is calculated depends on if $wgSecurePollUseNamespace or
	 * wgSecurePollUseMediaWikiNamespace is set. Various transformations are made
	 * to the JSON to try to introduce consistency and prevent dirty diffs.
	 *
	 * @param Election $election
	 * @param string $subpage Subpage to get content for
	 * @return array ( Title, SecurePollContent )
	 */
	public static function makeContentFromElection(
		Election $election,
		string $subpage = ''
	): array {
		$data = self::getDataFromElection( $election, $subpage, true );

		// do some things to prevent dirty diffs
		$data = self::alphabetizeKeys( $data );
		$data = self::convertNonStringsToStrings( $data );
		$data = self::deleteKeysContainingEmptyArrays( $data );

		$json = FormatJson::encode( $data, false, FormatJson::ALL_OK );

		$title = self::getTitleForPage( $election->getId() . ( $subpage === '' ? '' : "/$subpage" ) );

		return [
			$title,
			ContentHandler::makeContent( $json, $title, 'SecurePoll' )
		];
	}

	/**
	 * Given an array, delete keys containing empty arrays. If a value is a non-empty array,
	 * recursively delete keys containing empty arrays in that array too.
	 */
	public static function deleteKeysContainingEmptyArrays( array $data ): array {
		foreach ( $data as $key => $value ) {
			if ( $value === [] ) {
				unset( $data[$key] );
			} elseif ( is_array( $value ) ) {
				$data[$key] = self::deleteKeysContainingEmptyArrays( $value );
			}
		}
		return $data;
	}

	/**
	 * Given an array, sort the keys alphabetically. If a value is an array, recursively sort its
	 * keys too.
	 */
	public static function alphabetizeKeys( array $data ): array {
		ksort( $data );
		foreach ( $data as &$value ) {
			if ( is_array( $value ) ) {
				$value = self::alphabetizeKeys( $value );
			}
		}
		return $data;
	}

	/**
	 * Given an array, convert non-string values to strings. If a boolean is encountered, convert
	 * it to '1' or '0'. If a value is an array, recursively do this to its keys too.
	 */
	public static function convertNonStringsToStrings( array $data ): array {
		foreach ( $data as &$value ) {
			if ( is_array( $value ) ) {
				$value = self::convertNonStringsToStrings( $value );
			} elseif ( is_bool( $value ) ) {
				$value = $value ? '1' : '0';
			} elseif ( !is_string( $value ) ) {
				$value = strval( $value );
			}
		}
		return $data;
	}

	public static function getTitleForPage( string $pageName ): Title {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		if ( $config->get( 'SecurePollUseNamespace' ) ) {
			return Title::makeTitle( NS_SECUREPOLL, $pageName );
		}
		return Title::makeTitle( NS_MEDIAWIKI, 'SecurePoll/' . $pageName );
	}

	/** @inheritDoc */
	public function canBeUsedOn( Title $title ) {
		return Context::isSecurePollPage( $title );
	}

	/** @inheritDoc */
	protected function getContentClass() {
		return SecurePollContent::class;
	}
}
