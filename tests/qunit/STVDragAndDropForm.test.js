const STVDragAndDropForm = require( 'ext.securepoll.htmlform/page.vote.stv.js' );
const STVQuestionLayout = require( 'ext.securepoll.htmlform/stv.vote/STVQuestionLayout.js' );

( function () {
	QUnit.module( 'ext.securepoll.stv.vote.test', QUnit.newMwEnvironment() );

	// Test data constants for option names and ids used by both
	// DragAndDropForm.html and DragAndDropFormReversed.html.
	const TEST_IDS = {
		UNSELECTED: '0',
		OPTION_A1: '104',
		OPTION_A2: '105',
		OPTION_A3: '106',
		OPTION_B1: '108',
		OPTION_B2: '109',
		OPTION_B3: '110'
	};

	const stvDragAndDropFormTemplate = mw.template.get(
		'test.SecurePoll', 'DragAndDropForm.html' );
	const stvDragAndDropFormTemplateReversed = mw.template.get(
		'test.SecurePoll', 'DragAndDropFormReversed.html' );

	function generateSTVQuestionLayout( config ) {
		const questionId = ( config || {} ).questionId || 1;
		const selectedItems = ( config || {} ).selectedItems || [];
		const candidates = ( config || {} ).candidates || {
			'Candidate 1': '1',
			'Candidate 2': '2',
			'Candidate 3': '3'
		};
		const maxSeats = ( config || {} ).maxSeats || Object.keys( candidates ).length;

		return new STVQuestionLayout( {
			outerPanel: new OO.ui.PanelLayout( {
				expanded: false,
				data: { questionId, maxSeats, selectedItems, candidates },
				classes: [
					'securepoll-option-preferential',
					'securepoll-option-stv-panel',
					'securepoll-option-stv-panel-outer'
				]
			} ),
			classes: [ 'securepoll-question-layout' ]
		} );
	}

	/**
	 * Helper function to simulate dragging a candidate from the unranked group
	 * to the ranked group.
	 *
	 * @param {STVQuestionLayout} layout
	 * @param {number} candidateIndex
	 */
	function rankCandidateAtIndex( layout, candidateIndex ) {
		const candidate = layout.unrankedGroup.getItems()[ candidateIndex ];
		if ( candidate ) {
			layout.unrankedGroup.emit( 'itemDragStart', candidate );

			const dropEvent = {
				currentTarget: layout.rankedGroup.$element[ 0 ],
				preventDefault: function () {},
				stopImmediatePropagation: function () {}
			};
			layout.rankedGroup.$element.trigger( 'drop', dropEvent );
		}
	}

	/**
	 * Helper function to simulate dragging a candidate from the ranked group
	 * to the unranked group.
	 *
	 * @param {STVQuestionLayout} layout
	 * @param {number} candidateIndex
	 */
	function unrankCandidateAtIndex( layout, candidateIndex ) {
		const candidate = layout.rankedGroup.getItems()[ candidateIndex ];
		if ( candidate ) {
			layout.rankedGroup.emit( 'itemDragStart', candidate );

			const dropEvent = {
				currentTarget: layout.unrankedGroup.$element[ 0 ],
				preventDefault: function () {},
				stopImmediatePropagation: function () {}
			};
			layout.unrankedGroup.$element.trigger( 'drop', dropEvent );
		}
	}

	QUnit.test( 'initializeSubmitButton returns correct button element', ( assert ) => {
		const data = {
			_: 'OO.ui.ButtonInputWidget',
			type: 'button',
			label: 'Submit vote',
			classes: [
				'submit-vote-button'
			]
		};
		const buttons = document.createElement( 'div' );
		buttons.classList.add( 'submit-vote-button' );
		buttons.id = 'submit-vote-button';
		buttons.setAttribute( 'data-ooui', JSON.stringify( data ) );
		$( '#qunit-fixture' ).append( buttons );

		const button = STVDragAndDropForm.initializeSubmitButton();
		assert.notStrictEqual( button, null, 'Button element should not be null' );

		buttons.remove();
	} );

	QUnit.test( 'getVoteState only returns true when there are selected candidates', ( assert ) => {
		let layouts = [
			generateSTVQuestionLayout( {
				questionId: 1,
				candidates: {
					'Candidate 1': '1',
					'Candidate 2': '2'
				},
				selectedItems: []
			} ),
			generateSTVQuestionLayout( {
				questionId: 2,
				candidates: {
					'Candidate 1': '1',
					'Candidate 2': '2'
				},
				selectedItems: [
					{ option: 'securepoll_q0000001_opt0000001', itemKey: 1 }
				]
			} ),
			generateSTVQuestionLayout( {
				questionId: 3,
				candidates: {
					'Candidate 1': '1',
					'Candidate 2': '2'
				}
			} )
		];
		let vote = STVDragAndDropForm.getVoteState( layouts );
		assert.strictEqual( vote, false, 'Vote should be false' );

		layouts = [
			generateSTVQuestionLayout( {
				questionId: 1,
				candidates: {
					'Candidate 1': '1',
					'Candidate 2': '2'
				},
				selectedItems: [
					{ option: 'securepoll_q0000001_opt0000001', itemKey: 1 }
				]
			} ),
			generateSTVQuestionLayout( {
				questionId: 2,
				candidates: {
					'Candidate 1': '1',
					'Candidate 2': '2'
				},
				selectedItems: [
					{ option: 'securepoll_q0000002_opt0000001', itemKey: 1 }
				]
			} )
		];
		vote = STVDragAndDropForm.getVoteState( layouts );
		assert.strictEqual( vote, true, 'Vote should be true' );
	} );

	QUnit.test( 'User can drag candidates to the ranked group', ( assert ) => {
		const layout = generateSTVQuestionLayout( {
			candidates: {
				'Candidate 1': '1',
				'Candidate 2': '2'
			}
		} );

		assert.strictEqual(
			layout.unrankedGroup.items.length,
			2,
			'Both candidates should start in unranked group'
		);
		assert.strictEqual(
			layout.rankedGroup.items.length,
			0,
			'The ranked group should be empty initially'
		);

		rankCandidateAtIndex( layout, 0 ); // Candidate 1

		assert.strictEqual(
			layout.rankedGroup.items.length,
			1,
			'There should be a single candidate in the ranked group'
		);
		assert.strictEqual(
			layout.rankedGroup.items[ 0 ].name,
			'Candidate 1',
			'Candidate 1 should be in ranked group'
		);
		assert.strictEqual(
			layout.unrankedGroup.items.length,
			1,
			'The unranked group should have one less candidate'
		);
		assert.strictEqual(
			layout.unrankedGroup.items[ 0 ].name,
			'Candidate 2',
			'Candidate 2 should be in unranked group'
		);

		const previousRankedCount = layout.rankedGroup.items.length;
		rankCandidateAtIndex( layout, 0 ); // Candidate 2

		assert.strictEqual(
			layout.rankedGroup.items.length,
			previousRankedCount + 1,
			'The second candidate should be added to the ranked group'
		);
		assert.strictEqual(
			layout.unrankedGroup.items.length,
			0,
			'The unranked group should be empty'
		);
	} );

	QUnit.test( 'Reordering votes in ranked group updates the list correctly', ( assert ) => {
		const layout = generateSTVQuestionLayout( {
			candidates: {
				'Candidate 1': '1',
				'Candidate 2': '2'
			}
		} );

		rankCandidateAtIndex( layout, 0 ); // Candidate 1
		rankCandidateAtIndex( layout, 0 ); // Candidate 2

		assert.strictEqual(
			layout.rankedGroup.items[ 0 ].name,
			'Candidate 1',
			'Candidate 1 should be first'
		);
		assert.strictEqual(
			layout.rankedGroup.items[ 1 ].name,
			'Candidate 2',
			'Candidate 2 should be second'
		);

		// Simulate reordering by swapping items in the ranked group.
		const item1 = layout.rankedGroup.items[ 0 ];
		const item2 = layout.rankedGroup.items[ 1 ];

		layout.rankedGroup.items = [ item2, item1 ];
		layout.rankedGroup.emit( 'reorder' );

		assert.strictEqual(
			layout.rankedGroup.items[ 0 ].name,
			'Candidate 2',
			'Candidate 2 should now be first'
		);
		assert.strictEqual(
			layout.rankedGroup.items[ 1 ].name,
			'Candidate 1',
			'Candidate 1 should now be second'
		);
	} );

	QUnit.test( 'Clearing votes removes all selections from ranked group', ( assert ) => {
		const layout = generateSTVQuestionLayout( {
			candidates: {
				'Candidate 1': '1',
				'Candidate 2': '2'
			}
		} );

		rankCandidateAtIndex( layout, 0 ); // Candidate 1
		rankCandidateAtIndex( layout, 0 ); // Candidate 2

		assert.strictEqual(
			layout.rankedGroup.items.length,
			2,
			'Both candidates should be in ranked group'
		);
		assert.strictEqual(
			layout.unrankedGroup.items.length,
			0,
			'Unranked group should be empty'
		);

		layout.clearButton.emit( 'click' );

		assert.strictEqual(
			layout.rankedGroup.items.length,
			0,
			'All votes should be cleared from ranked group'
		);
		assert.strictEqual(
			layout.unrankedGroup.items.length,
			2,
			'All candidates should be back in unranked group'
		);

		// Verify that the form is still usable after clearing the votes.

		rankCandidateAtIndex( layout, 1 ); // Candidate 2

		assert.strictEqual(
			layout.rankedGroup.items.length,
			1,
			'Candidate 2 should be added to ranked group'
		);
		assert.strictEqual(
			layout.rankedGroup.items[ 0 ].name,
			'Candidate 2',
			'Candidate 2 should be in ranked group'
		);
	} );

	QUnit.test( 'Reaching the seat limit prevents further additions to ranked group', ( assert ) => {
		const layout = generateSTVQuestionLayout( {
			candidates: {
				'Candidate 1': '1',
				'Candidate 2': '2',
				'Candidate 3': '3'
			},
			maxSeats: 2
		} );

		rankCandidateAtIndex( layout, 0 ); // Candidate 1
		rankCandidateAtIndex( layout, 0 ); // Candidate 2

		assert.strictEqual(
			layout.rankedGroup.items.length,
			2,
			'Two candidates should be in the ranked group'
		);
		assert.strictEqual(
			layout.unrankedGroup.items.length,
			1,
			'One candidate should remain in the unranked group'
		);

		// Ranking the third candidate should be prevented by the seat limit.

		rankCandidateAtIndex( layout, 0 ); // Candidate 3

		assert.strictEqual(
			layout.rankedGroup.items.length,
			2,
			'Two candidates should be in the ranked group'
		);
		assert.strictEqual(
			layout.unrankedGroup.items.length,
			1,
			'One candidate should remain in the unranked group'
		);
	} );

	QUnit.test( 'Clearing votes allows new additions after reaching seat limit', ( assert ) => {
		const layout = generateSTVQuestionLayout( {
			candidates: {
				'Candidate 1': '1',
				'Candidate 2': '2',
				'Candidate 3': '3'
			},
			maxSeats: 2
		} );

		rankCandidateAtIndex( layout, 0 ); // Candidate 1
		rankCandidateAtIndex( layout, 0 ); // Candidate 2

		assert.strictEqual(
			layout.rankedGroup.items.length,
			2,
			'Should be at seat limit'
		);

		layout.clearButton.emit( 'click' );

		assert.strictEqual(
			layout.rankedGroup.items.length,
			0,
			'Ranked group should be empty after clearing'
		);
		assert.strictEqual(
			layout.unrankedGroup.items.length,
			3,
			'All candidates should be back in unranked group'
		);

		rankCandidateAtIndex( layout, 0 ); // Candidate 3

		assert.strictEqual(
			layout.rankedGroup.items.length,
			1,
			'Should be able to add candidates after clearing'
		);
		assert.strictEqual(
			layout.rankedGroup.items[ 0 ].name,
			'Candidate 3',
			'Candidate 3 should be in ranked group'
		);
	} );

	QUnit.test( 'Removing an individual vote allows new additions if previously at seat limit', ( assert ) => {
		const layout = generateSTVQuestionLayout( {
			candidates: {
				'Candidate 1': '1',
				'Candidate 2': '2',
				'Candidate 3': '3'
			},
			maxSeats: 2
		} );

		rankCandidateAtIndex( layout, 0 ); // Candidate 1
		rankCandidateAtIndex( layout, 0 ); // Candidate 2

		assert.strictEqual(
			layout.rankedGroup.items.length,
			2,
			'Should be at seat limit'
		);

		unrankCandidateAtIndex( layout, 0 ); // Candidate 1

		assert.strictEqual(
			layout.rankedGroup.items.length,
			1,
			'Should have one less candidate in the ranked group'
		);
		assert.strictEqual(
			layout.unrankedGroup.items.length,
			2,
			'Should have one more candidate in the unranked group'
		);

		rankCandidateAtIndex( layout, 1 ); // Candidate 3

		assert.strictEqual(
			layout.rankedGroup.items.length,
			2,
			'Should be able to reach the seat limit again'
		);
	} );

	QUnit.test( 'Form submission works with drag and drop', ( assert ) => {
		$( '#qunit-fixture' ).append( stvDragAndDropFormTemplate.render() );
		STVDragAndDropForm.init();

		const layouts = STVDragAndDropForm.questionLayouts;
		assert.strictEqual( layouts.length, 2, 'Should have two question layouts' );

		rankCandidateAtIndex( layouts[ 0 ], 0 ); // Option A.1 (104)
		rankCandidateAtIndex( layouts[ 1 ], 1 ); // Option B.2 (109)

		assert.strictEqual(
			layouts[ 0 ].rankedGroup.items.length,
			1,
			'First question should have one ranked candidate'
		);
		assert.strictEqual(
			layouts[ 0 ].rankedGroup.items[ 0 ].name,
			'Option A.1',
			'First question should have Option A.1 ranked'
		);
		assert.strictEqual(
			layouts[ 1 ].rankedGroup.items.length,
			1,
			'Second question should have one ranked candidate'
		);
		assert.strictEqual(
			layouts[ 1 ].rankedGroup.items[ 0 ].name,
			'Option B.2',
			'Second question should have Option B.2 ranked'
		);

		const $form = $( '#qunit-fixture form' );
		$form.on( 'submit', ( e ) => {
			// Prevent the form from submitting to the server.
			e.preventDefault();
		} );
		$form.trigger( 'submit' );

		const ranked = Array.from( $( 'select.oo-ui-inputWidget-input' ) )
			.map( ( $select ) => $select.value );

		assert.deepEqual(
			ranked,
			[
				TEST_IDS.OPTION_A1,
				TEST_IDS.UNSELECTED,
				TEST_IDS.UNSELECTED,
				TEST_IDS.OPTION_B2,
				TEST_IDS.UNSELECTED,
				TEST_IDS.UNSELECTED
			],
			'The submission should have both A.1 and B.2 ranked for their respective questions'
		);
	} );

	QUnit.test( 'Form submission works when shuffled (reversed)', ( assert ) => {
		$( '#qunit-fixture' ).append( stvDragAndDropFormTemplateReversed.render() );
		STVDragAndDropForm.init();

		const layouts = STVDragAndDropForm.questionLayouts;
		assert.strictEqual( layouts.length, 2, 'Should have two question layouts' );

		rankCandidateAtIndex( layouts[ 0 ], 0 ); // Option B.1 (108)
		rankCandidateAtIndex( layouts[ 1 ], 1 ); // Option A.2 (105)

		assert.strictEqual(
			layouts[ 0 ].rankedGroup.items.length,
			1,
			'First question should have one ranked candidate'
		);
		assert.strictEqual(
			layouts[ 0 ].rankedGroup.items[ 0 ].name,
			'Option B.1',
			'First question should have Option B.1 ranked'
		);
		assert.strictEqual(
			layouts[ 1 ].rankedGroup.items.length,
			1,
			'Second question should have one ranked candidate'
		);
		assert.strictEqual(
			layouts[ 1 ].rankedGroup.items[ 0 ].name,
			'Option A.2',
			'Second question should have Option A.2 ranked'
		);

		const $form = $( '#qunit-fixture form' );
		$form.on( 'submit', ( e ) => {
			// Prevent the form from submitting to the server.
			e.preventDefault();
		} );
		$form.trigger( 'submit' );

		const ranked = Array.from( $( 'select.oo-ui-inputWidget-input' ) )
			.map( ( $select ) => $select.value );

		assert.deepEqual(
			ranked,
			[
				TEST_IDS.OPTION_B1,
				TEST_IDS.UNSELECTED,
				TEST_IDS.UNSELECTED,
				TEST_IDS.OPTION_A2,
				TEST_IDS.UNSELECTED,
				TEST_IDS.UNSELECTED
			],
			'The submission should have both B.1 and A.2 ranked for their respective questions'
		);
	} );
}() );
