<?php

/**
 * A field that will contain a date
 *
 * Currently recognizes only YYYY-MM-DD formatted dates.
 *
 * Besides the parameters recognized by HTMLTextField, additional recognized
 * parameters in the field descriptor array include:
 *  min - The minimum date to allow, in any recognized format.
 *  max - The maximum date to allow, in any recognized format.
 *  placeholder - The default comes from the htmlform-date-placeholder message.
 *
 * The result is a formatted date.
 *
 *  @todo These should be migrated to core, once the jquery.ui objectors write
 *  their own date picker.
 */
class SecurePoll_HTMLDateField extends HTMLTextField {
	function getSize() {
		return isset( $this->mParams['size'] ) ? $this->mParams['size'] : 10;
	}

	public function getAttributes( array $list ) {
		$parentList = array_diff( $list, array( 'min', 'max' ) );
		$ret = parent::getAttributes( $parentList );

		if ( in_array( 'placeholder', $list ) && !isset( $ret['placeholder'] ) ) {
			$ret['placeholder'] = $this->msg( 'securepoll-htmlform-date-placeholder' )->text();
		}

		if ( in_array( 'min', $list ) && isset( $this->mParams['min'] ) ) {
			$min = $this->parseDate( $this->mParams['min'] );
			if ( $min ) {
				$ret['min'] = $this->formatDate( $min );
				// Because Html::expandAttributes filters it out
				$ret['data-min'] = $ret['min'];
			}
		}
		if ( in_array( 'max', $list ) && isset( $this->mParams['max'] ) ) {
			$max = $this->parseDate( $this->mParams['max'] );
			if ( $max ) {
				$ret['max'] = $this->formatDate( $max );
				// Because Html::expandAttributes filters it out
				$ret['data-max'] = $ret['max'];
			}
		}

		$ret['type'] = 'date';

		return $ret;
	}

	function loadDataFromRequest( $request ) {
		if ( !$request->getCheck( $this->mName ) ) {
			return $this->getDefault();
		}

		$value = $request->getText( $this->mName );
		$date = $this->parseDate( $value );
		return $date ? $this->formatDate( $date ) : $value;
	}

	function validate( $value, $alldata ) {
		$p = parent::validate( $value, $alldata );

		if ( $p !== true ) {
			return $p;
		}

		if ( $value === '' ) {
			// required was already checked by parent::validate
			return true;
		}

		$date = $this->parseDate( $value );
		if ( !$date ) {
			return $this->msg( 'securepoll-htmlform-date-invalid' )->parseAsBlock();
		}

		if ( isset( $this->mParams['min'] ) ) {
			$min = $this->parseDate( $this->mParams['min'] );
			if ( $min && $date < $min ) {
				return $this->msg( 'securepoll-htmlform-date-toolow', $this->formatDate( $min ) )
					->parseAsBlock();
			}
		}

		if ( isset( $this->mParams['max'] ) ) {
			$max = $this->parseDate( $this->mParams['max'] );
			if ( $max && $date > $max ) {
				return $this->msg( 'securepoll-htmlform-date-toohigh', $this->formatDate( $max ) )
					->parseAsBlock();
			}
		}

		return true;
	}

	protected function parseDate( $value ) {
		$value = trim( $value );

		/* @todo: Language should probably provide a "parseDate" method of some sort. */
		try {
			$date = new DateTime( "$value T00:00:00+0000", new DateTimeZone( 'GMT' ) );
			return $date->getTimestamp();
		} catch ( Exception $ex ) {
			return 0;
		}
	}

	protected function formatDate( $value ) {
		// For now just use Y-m-d. At some point someone may want to add a
		// config option.
		return gmdate( 'Y-m-d', $value );
	}

}