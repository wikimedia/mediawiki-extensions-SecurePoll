function TranslationFlattener() {
	this.keywords = [
		'title',
		'intro',
		'jump-text',
		'return-text',
		'unqualified-error',
		'comment-prompt'
	];
}

OO.initClass( TranslationFlattener );

/**
 * Flatten structured data into flat object for saving translatable messages to DB
 * Structure should look like 'trans_81_intro' for election messages.
 * According to saved questions in DB for an election the ID increases,
 * so count has to be increased for every question and option entry
 *
 * @param {Array} data Translation array with all messages
 * @param {number} id ID of election
 * @return {Object} Flattened messages in the same level
 */
TranslationFlattener.prototype.flattenData = function ( data, id ) {
	const flattenData = {};
	let count = id;

	for ( const entry in data ) {
		if ( this.keywords.includes( entry ) ) {
			flattenData[ 'trans_' + id + '_' + entry ] = data[ entry ];
			continue;
		}
		if ( data[ entry ] instanceof Array && entry === 'questions' ) {
			for ( const x in data[ entry ] ) {
				// keep counter in snych with question
				count++;
				const question = data[ entry ][ x ];
				const questionKey = 'trans_' + count + '_text';
				flattenData[ questionKey ] = question.text;

				if ( question.options ) {
					for ( const y in question.options ) {
						// keep counter in snych with options
						count++;
						const option = data[ entry ][ x ].options[ y ];
						const optionKey = 'trans_' + count + '_text';
						flattenData[ optionKey ] = option.text;
					}
				}
			}
		}
	}
	return flattenData;
};

module.exports = TranslationFlattener;
