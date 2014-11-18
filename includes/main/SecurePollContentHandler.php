<?php
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
	 * @param SecurePoll_Election $election
	 * @param string $subpage Subpage to get content for
	 * @param bool $useBlacklist
	 * @return array
	 */
	public static function getDataFromElection(
		SecurePoll_Election $election, $subpage = '', $useBlacklist = false
	) {
		if ( $subpage === '' ) {
			$properties = $election->getAllProperties();
			if ( $useBlacklist ) {
				$blacklist = array_flip( $election->getPropertyDumpBlacklist() ) + array(
					'gpg-encrypt-key' => true,
					'gpg-sign-key' => true,
					'gpg-decrypt-key' => true,
				);
				foreach ( $properties as $k => $v ) {
					if ( isset( $blacklist[$k] ) ) {
						$properties[$k] = '<redacted>';
					}
				}
				unset(
					$properties['list_job-key'],
					$properties['list_total-count'],
					$properties['list_complete-count']
				);
			}
			$data = array(
				'id' => $election->getId(),
				'title' => $election->title,
				'ballot' => $election->ballotType,
				'tally' => $election->tallyType,
				'lang' => $election->getLanguage(),
				'startDate' => wfTimestamp( TS_ISO_8601, $election->getStartDate() ),
				'endDate' => wfTimestamp( TS_ISO_8601, $election->getEndDate() ),
				'authType' => $election->authType,
				'properties' => $properties,
				'questions' => array(),
			);

			foreach ( $election->getQuestions() as $question ) {
				$properties = $question->getAllProperties();
				if ( $useBlacklist ) {
					$blacklist = array_flip( $question->getPropertyDumpBlacklist() );
					foreach ( $properties as $k => $v ) {
						if ( isset( $blacklist[$k] ) ) {
							$properties[$k] = '<redacted>';
						}
					}
				}
				$q = array(
					'id' => $question->getId(),
					'properties' => $properties,
					'options' => array(),
				);

				foreach ( $question->getOptions() as $option ) {
					$properties = $option->getAllProperties();
					if ( $useBlacklist ) {
						$blacklist = array_flip( $option->getPropertyDumpBlacklist() );
						foreach ( $properties as $k => $v ) {
							if ( isset( $blacklist[$k] ) ) {
								$properties[$k] = '<redacted>';
							}
						}
					}
					$o = array(
						'id' => $option->getId(),
						'properties' => $properties,
					);
					$q['options'][] = $o;
				}

				$data['questions'][] = $q;
			}
		} elseif ( preg_match( '#^msg/(\S+)$#', $subpage, $m ) ) {
			$lang = $m[1];
			$data = array(
				'id' => $election->getId(),
				'lang' => $lang,
				'messages' => array(),
				'questions' => array(),
			);
			foreach ( $election->getMessageNames() as $name ) {
				$value = $election->getRawMessage( $name, $lang );
				if ( $value !== false ) {
					$data['messages'][$name] = $value;
				}
			}

			foreach ( $election->getQuestions() as $question ) {
				$q = array(
					'id' => $question->getId(),
					'messages' => array(),
					'options' => array(),
				);
				foreach ( $question->getMessageNames() as $name ) {
					$value = $question->getRawMessage( $name, $lang );
					if ( $value !== false ) {
						$q['messages'][$name] = $value;
					}
				}

				foreach ( $question->getOptions() as $option ) {
					$o = array(
						'id' => $option->getId(),
						'messages' => array(),
					);
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
			throw new MWException( __METHOD__ . ': Unsupported subpage format' );
		}

		return $data;
	}

	/**
	 * Create a SecurePollContent for an election
	 *
	 * @param SecurePoll_Election $election
	 * @param string $subpage Subpage to get content for
	 * @return array ( Title, SecurePollContent )
	 */
	public static function makeContentFromElection( SecurePoll_Election $election, $subpage = '' ) {
		$json = FormatJson::encode( self::getDataFromElection( $election, $subpage, true ),
			false, FormatJson::ALL_OK );
		$title = Title::makeTitle( NS_SECUREPOLL, $election->getId() .
			( $subpage === '' ? '' : "/$subpage" ) );
		return array( $title, ContentHandler::makeContent( $json, $title, 'SecurePoll' ) );
	}

	public function canBeUsedOn( Title $title ) {
		global $wgSecurePollUseNamespace;
		return $wgSecurePollUseNamespace && $title->getNamespace() == NS_SECUREPOLL;
	}

	public function getActionOverrides() {
		// Disable write actions
		return array(
			'delete' => false,
			'edit' => false,
			'info' => false,
			'protect' => false,
			'revert' => false,
			'rollback' => false,
			'submit' => false,
			'unprotect' => false,
		);
	}

	protected function getContentClass() {
		return 'SecurePollContent';
	}
}
