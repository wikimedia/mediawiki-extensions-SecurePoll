<?php

/**
 * A field that will contain a date range
 *
 * Besides the parameters recognized by HTMLDateField, additional recognized
 * parameters in the field descriptor array include:
 *   absolute - Boolean, whether to select the end date absolutely rather
 *     than as a number of days offset from the start date.
 *   options - If specified, the "number of days" field is displayed as a
 *     <select>. Otherwise it's an 'int' textbox.
 *   min-days - If the "number of days" field is a textbox, this is the minimum
 *     allowed. If less than 1, 1 is used.
 *   max-days - If the "number of days" field is a textbox, this is the maximum
 *     allowed.
 *   layout-message - The starting date field (as $1) and "number of days" or
 *     ending date field (as $2) are layed out using this message. Default is
 *     'securepoll-htmlform-daterange-relative-layout' or
 *     'securepoll-htmlform-daterange-absolute-layout', depending on 'absolute'.
 *
 * The result is an empty array or an array with two values, the first being
 * the starting date and the second being either the number of days or the
 * ending date.
 *
 *  @todo These should be migrated to core, once the jquery.ui objectors write
 *  their own date picker.
 */
class SecurePoll_HTMLDateRangeField extends SecurePoll_HTMLDateField {
	function loadDataFromRequest( $request ) {
		$absolute = !empty( $this->mParams['absolute'] );
		if ( !$request->getCheck( $this->mName ) ||
			!$request->getCheck( $this->mName . ( $absolute ? '-end' : '-days' ) )
		) {
			return $this->getDefault();
		}

		$value1 = $request->getText( $this->mName );
		if ( $value1 !== '' ) {
			$startDate = $this->parseDate( $value1 );
			$value1 = $startDate ? $this->formatDate( $startDate ) : $value1;
		}

		if ( $absolute ) {
			$value2 = $request->getText( $this->mName . '-end' );
			if ( $value2 !== '' ) {
				$endDate = $this->parseDate( $value2 );
				$value2 = $endDate ? $this->formatDate( $endDate ) : $value2;
			}
		} else {
			$value2 = $request->getText( $this->mName . '-days' );
		}

		if ( $value1 === '' && ( !$absolute || $value2 === '' ) ) {
			return array();
		}

		return array( $value1, $value2 );
	}

	function validate( $value, $alldata ) {
		if ( $this->isHidden( $alldata ) ) {
			return true;
		}

		if ( $value === array() ) {
			if ( isset( $this->mParams['required'] ) && $this->mParams['required'] !== false ) {
				return $this->msg( 'htmlform-required' )->parse();
			}
			return true;
		}

		$p = parent::validate( $value[0], $alldata );
		if ( $p !== true ) {
			return $p;
		}

		if ( !empty( $this->mParams['absolute'] ) ) {
			if ( $value[0] === '' || $value[1] === '' ) {
				return $this->msg( 'securepoll-htmlform-daterange-error-partial' )->parse();
			}

			$endDate = $value[1];
			if ( $this->parseDate( $value[0] ) >= $this->parseDate( $value[1] ) ) {
				return $this->msg( 'securepoll-htmlform-daterange-end-before-start' )->parseAsBlock();
			}
		} else {
			$opts = $this->getOptions();
			if ( $opts ) {
				if ( !in_array( $value[1], $opts ) ) {
					return $this->msg( 'securepoll-htmlform-daterange-days-badoption' )->parseAsBlock();
				}
			} else {
				if ( !preg_match( '/^(\+|\-)?\d+$/', trim( $value[1] ) ) ) {
					return $this->msg( 'securepoll-htmlform-daterange-days-invalid' )->parseAsBlock();
				}

				if ( isset( $this->mParams['min-days'] ) ) {
					$min = max( 1, $this->mParams['min-days'] );
				} else {
					$min = 1;
				}
				if ( $min > $value[1] ) {
					return $this->msg( 'securepoll-htmlform-daterange-days-toolow' )->numParams( $min )
						->parseAsBlock();
				}

				if ( isset( $this->mParams['max-days'] ) ) {
					$max = $this->mParams['max-days'];

					if ( $max < $value[1] ) {
						return $this->msg( 'securepoll-htmlform-daterange-days-toohigh' )->numParams( $max )
							->parseAsBlock();
					}
				}
			}

			$endDate = $this->formatDate( $this->parseDate( $value[0] ) + $value[1] * 86400 );
		}

		$p = parent::validate( $endDate, $alldata );
		if ( $p !== true ) {
			return $p;
		}

		return true;
	}

	function getInputHTML( $value ) {
		if ( $value === array() ) {
			$value = array( '', '' );
		}

		$startpicker = parent::getInputHTML( $value[0] );

		if ( !empty( $this->mParams['absolute'] ) ) {
			$msg = 'securepoll-htmlform-daterange-absolute-layout';
			$oldName = $this->mName;
			$oldID= $this->mID;
			$this->mName .= '-end';
			$this->mID .= '-end';
			$endpicker = parent::getInputHTML( $value[1] );
			$this->mName = $oldName;
			$this->mID = $oldID;
		} else {
			$msg = 'securepoll-htmlform-daterange-relative-layout';
			$opts = $this->getOptions();
			if ( $opts ) {
				$select = new XmlSelect(
					$this->mName . '-days', $this->mID . '-days', strval( $value[1] )
				);

				if ( !empty( $this->mParams['disabled'] ) ) {
					$select->setAttribute( 'disabled', 'disabled' );
				}

				if ( isset( $this->mParams['tabindex'] ) ) {
					$select->setAttribute( 'tabindex', $this->mParams['tabindex'] );
				}

				if ( $this->mClass !== '' ) {
					$select->setAttribute( 'class', $this->mClass );
				}

				$select->addOptions( $this->getOptions() );

				$endpicker = $select->getHTML();
			} else {
				$textAttribs = array(
					'id' => $this->mID . '-days',
					'size' => $this->getSize(),
					'min' => 1,
				);

				if ( $this->mClass !== '' ) {
					$textAttribs['class'] = $this->mClass;
				}

				$allowedParams = array(
					'required',
					'autofocus',
					'disabled',
					'tabindex'
				);

				$textAttribs += $this->getAttributes( $allowedParams );

				if ( isset( $this->mParams['min-days'] ) ) {
					$textAttribs['min'] = max( 1, $this->mParams['min-days'] );
				}

				if ( isset( $this->mParams['max-days'] ) ) {
					$textAttribs['max'] = $this->mParams['max-days'];
				}

				$endpicker = Html::input( $this->mName . '-days', $value[1], 'number', $textAttribs );
			}
		}

		if ( isset( $this->mParams['layout-message'] ) ) {
			$msg = $this->mParams['layout-message'];
		}

		return $this->msg( $msg )->rawParams( $startpicker, $endpicker )->parse();
	}

}
