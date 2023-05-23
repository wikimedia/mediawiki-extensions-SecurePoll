function TranslationParser() {
	this.errors = [];
}

OO.initClass( TranslationParser );

/**
 * The content of a page will be parsed to a json. This will be structured in section
 * election and questions. Each question has a subsection with options.
 *
 * @param {string} content
 * @return {Object} Object which contains translatable content of an election
 */
TranslationParser.prototype.parseContent = function ( content ) {
	var parsedContent = {
		type: 'election'
	};
	var value = '';
	var questionOptions = [];

	content = content.replace( /<translate>/g, '' );
	content = content.replace( /<\/translate>/g, '' );
	content = content.replace( /<!--T:\d+-->/g, '' );
	var lines = content.split( '\n' );

	for ( var i = 0; i < lines.length; i++ ) {
		var line = lines[ i ];
		var startTagMatch = this.hasStartTag( line );
		if ( !startTagMatch ) {
			continue;
		}
		// get properties
		var property = this.getPropertyKey( startTagMatch[ 0 ] );
		if ( property === '' ) {
			continue;
		}
		var properties = property.split( '/' );
		var electionPart = properties[ 0 ];
		var propertyKey = properties[ 1 ];
		value = '';

		line = line.slice( startTagMatch[ 0 ].length + startTagMatch.index, line.length );
		var endTagMatch = this.hasEndTag( line );

		if ( electionPart === 'election' ) {
			if ( endTagMatch ) {
				line = line.slice( 0, endTagMatch.index );
				value = line;
				parsedContent[ propertyKey ] = value;
				continue;
			}
			if ( line.length > 0 ) {
				value += line;
			}
			value = this.getValueContent( value, lines, i );
			parsedContent[ propertyKey ] = value;
		}
		if ( electionPart === 'question' ) {
			if ( !parsedContent.questions ) {
				parsedContent.questions = [];
			}
			if ( endTagMatch ) {
				line = line.slice( 0, endTagMatch.index );
				value += line;
				questionOptions = this.getOptionsForQuestion( lines, i );
				parsedContent.questions.push( {
					type: 'question',
					text: value,
					options: questionOptions
				} );
				continue;
			}
			if ( line.length > 0 ) {
				value += line + '\n';
			}
			for ( var x = i + 1; x < lines.length; x++ ) {
				var contentLine = lines[ x ];
				endTagMatch = this.hasEndTag( contentLine );
				var newStartTag = this.hasStartTag( contentLine );
				if ( !endTagMatch || newStartTag ) {
					if ( !newStartTag ) {
						if ( contentLine.length !== 0 ) {
							value += contentLine + '\n';
						}
						continue;
					}
					endTagMatch = newStartTag;
				}

				if ( !newStartTag ) {
					contentLine = contentLine.slice( 0, endTagMatch.index );
					value += contentLine;
					questionOptions = this.getOptionsForQuestion( lines, x );
				} else {
					questionOptions = this.getOptionsForQuestion( lines, x - 1 );
				}

				parsedContent.questions.push( {
					type: 'question',
					text: value,
					options: questionOptions
				} );
				break;
			}
		}
	}

	if ( this.errors.length > 0 ) {
		parsedContent.errors = this.errors;
	}

	return parsedContent;
};

/**
 * Get all content from property type election like title, intro-text, jump-text etc.
 *
 * @param {string} value String with text of property
 * @param {Array} lines Array with lines of text
 * @param {number} lineNumber Current line number for array lines to start to append content
 * @return {string} contains text of property
 */
TranslationParser.prototype.getValueContent = function ( value, lines, lineNumber ) {
	for ( var x = lineNumber + 1; x < lines.length; x++ ) {
		var contentLine = lines[ x ];
		var endTagMatch = this.hasEndTag( contentLine );
		var newStartTag = this.hasStartTag( contentLine );

		if ( !endTagMatch || newStartTag ) {
			if ( !newStartTag ) {
				if ( contentLine.length !== 0 ) {
					value += contentLine + '\n';
				}
				if ( x + 1 === lines.length ) {
					return value;
				}
				continue;
			}
			endTagMatch = newStartTag;
		}

		if ( !newStartTag ) {
			contentLine = contentLine.slice( 0, endTagMatch.index );
			value += contentLine;
		}

		return value;
	}

};

/**
 * Get all following options of a question
 *
 * @param {Array} lines Array with lines of text
 * @param {number} lineNumber Current line number for array lines to start searching for options
 * @return {Array} array with all options for a question
 */
TranslationParser.prototype.getOptionsForQuestion = function ( lines, lineNumber ) {
	var options = [];
	var line = '';
	var value = '';
	var endTagMatch = null;

	for ( var i = lineNumber + 1; i < lines.length; i++ ) {
		line = lines[ i ];
		var startTagMatch = this.hasStartTag( line );
		if ( !startTagMatch ) {
			endTagMatch = this.hasEndTag( line );
			if ( endTagMatch ) {
				value += line.slice( 0, endTagMatch.index );
				options.push( {
					type: 'option',
					text: value
				} );
				value = '';
				continue;
			}
			if ( value !== '' && line !== '' ) {
				value += '\n' + line;
			}
			continue;
		}
		if ( value !== '' ) {
			options.push( {
				type: 'option',
				text: value
			} );
			value = '';
		}

		var property = this.getPropertyKey( startTagMatch[ 0 ] );
		if ( property === '' ) {
			continue;
		}
		var properties = property.split( '/' );
		var electionPart = properties[ 0 ];
		if ( electionPart !== 'option' ) {
			if ( electionPart === 'question' ) {
				return options;
			}
			continue;
		}

		line = line.slice( startTagMatch[ 0 ].length + startTagMatch.index, line.length );

		endTagMatch = this.hasEndTag( line );
		if ( endTagMatch ) {
			value += line.slice( 0, endTagMatch.index );
			options.push( {
				type: 'option',
				text: value
			} );
			value = '';
			continue;
		}

		value += line;
	}

	return options;
};

/**
 * Check, if string contains start tag
 *
 * @param {string} content
 * @return {Array|null}
 */
TranslationParser.prototype.hasStartTag = function ( content ) {
	return content.match( /<!--( )?###SecurePoll-START:[\w-/_]*( )?-->/ );
};

/**
 * Check, if string contains end tag
 *
 * @param {string} content
 * @return {Array|null}
 */
TranslationParser.prototype.hasEndTag = function ( content ) {
	return content.match( /<!--( )?###SecurePoll-STOP( )?-->/ );
};

/**
 * Find property key in tag
 *
 * Look in string like <!-- ###SecurePoll-START:question/text --> for property key
 *
 * @param {string} tag
 * @return {string} property key could look like like election/title, question/text, option/text
 */
TranslationParser.prototype.getPropertyKey = function ( tag ) {
	tag = tag.replace( /\s+/g, '' );

	var index = tag.indexOf( ':' );
	if ( index < 0 ) {
		this.errors.push( 'property index not found of ' + tag );
		return '';
	}

	var propertyKey = tag.slice( index + 1, tag.length );

	var matches = propertyKey.match( /-->/g );
	if ( !matches ) {
		this.errors.push( 'property key end not found in ' + tag );
		return '';
	}
	propertyKey = propertyKey.replace( matches[ 0 ], '' );

	if ( propertyKey.length === 0 ) {
		this.errors.push( 'property key not found in ' + tag );
		return '';
	}

	return propertyKey;
};

module.exports = TranslationParser;
