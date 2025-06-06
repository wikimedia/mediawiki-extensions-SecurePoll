( function () {
	QUnit.module( 'ext.securepoll.stv.vote.test', QUnit.newMwEnvironment() );

	const STVDragAndDropForm = require( 'ext.securepoll.htmlform/page.vote.stv.js' );
	const STVQuestionLayout = require( 'ext.securepoll.htmlform/stv.vote/STVQuestionLayout.js' );

	function generateSTVQuestionLayout( config ) {
		const voteDone = !!( config || {} ).voteDone;
		const selectedItems = ( config || {} ).selectedItems || [];
		const options = ( config || {} ).options || [];
		const maxSeats = ( config || {} ).maxSeats || options.length;
		return new STVQuestionLayout( {
			comboBox: new OO.ui.ComboBoxInputWidget( {
				options: options,
				data: { selectedItems: selectedItems, maxSeats: maxSeats }
			} ),
			voteDone: voteDone
		} );
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

	QUnit.test( 'getVoteState', ( assert ) => {
		let layouts = [
			generateSTVQuestionLayout( { voteDone: false } ),
			generateSTVQuestionLayout( { voteDone: true } ),
			generateSTVQuestionLayout( { voteDone: false } )
		];
		let vote = STVDragAndDropForm.getVoteState( layouts );
		assert.strictEqual( vote, false, 'Vote should be false' );

		layouts = [
			generateSTVQuestionLayout( {
				options: [
					{
						data: 'securepoll_q0000001_opt0000001',
						label: 'Candidate 1'
					},
					{
						data: 'securepoll_q0000001_opt0000002',
						label: 'Candidate 2'
					}
				],
				selectedItems: [
					{ option: 'securepoll_q0000001_opt0000001', itemKey: 1 }
				]
			} )
		];
		vote = STVDragAndDropForm.getVoteState( layouts );
		assert.strictEqual( vote, true, 'Vote should be true' );
	} );

	QUnit.test( 'User can add votes, and duplicates are not allowed', ( assert ) => {
		// This is an async test
		const done = assert.async();
		const layout = generateSTVQuestionLayout( {
			options: [
				{ data: 'securepoll_q0000001_opt0000001', label: 'Candidate 1' },
				{ data: 'securepoll_q0000001_opt0000002', label: 'Candidate 2' }
			]
		} );

		layout.boxMenu.emit( 'choose', layout.boxMenu.items[ 0 ] );
		assert.strictEqual(
			layout.draggableGroup.items.length,
			1,
			'Candidate 1 should be added'
		);
		assert.strictEqual(
			layout.draggableGroup.items[ 0 ].data,
			'Candidate 1',
			'Candidate 1 should be selected'
		);

		// Attempt to add the same candidate
		layout.boxMenu.emit( 'choose', layout.boxMenu.items[ 0 ] );
		assert.strictEqual(
			layout.draggableGroup.items.length,
			1,
			'Duplicate votes should not be allowed'
		);

		done();
	} );

	QUnit.test( 'Reordering votes updates the list correctly', ( assert ) => {
		const done = assert.async();
		const layout = generateSTVQuestionLayout( {
			options: [
				{ data: 'securepoll_q0000001_opt0000001', label: 'Candidate 1' },
				{ data: 'securepoll_q0000001_opt0000002', label: 'Candidate 2' }
			]
		} );

		layout.boxMenu.emit( 'choose', layout.boxMenu.items[ 0 ] );
		layout.boxMenu.emit( 'choose', layout.boxMenu.items[ 1 ] );

		assert.strictEqual(
			layout.draggableGroup.items[ 0 ].data,
			'Candidate 1',
			'Candidate 1 should be first'
		);
		assert.strictEqual(
			layout.draggableGroup.items[ 1 ].data,
			'Candidate 2',
			'Candidate 2 should be second'
		);

		// Simulate dragging Candidate 2 to the first position
		const item1 = layout.draggableGroup.items[ 0 ];
		const item2 = layout.draggableGroup.items[ 1 ];

		layout.draggableGroup.items = [ item2, item1 ];
		layout.draggableGroup.emit( 'reorder' );

		assert.strictEqual(
			layout.draggableGroup.items[ 0 ].data,
			'Candidate 2',
			'Candidate 2 should now be first'
		);
		assert.strictEqual(
			layout.draggableGroup.items[ 1 ].data,
			'Candidate 1',
			'Candidate 1 should now be second'
		);

		done();
	} );

	QUnit.test( 'Clearing votes removes all selections', ( assert ) => {
		const done = assert.async();
		const layout = generateSTVQuestionLayout( {
			options: [
				{ data: 'securepoll_q0000001_opt0000001', label: 'Candidate 1' },
				{ data: 'securepoll_q0000001_opt0000002', label: 'Candidate 2' }
			]
		} );

		layout.boxMenu.emit( 'choose', layout.boxMenu.items[ 0 ] );
		layout.boxMenu.emit( 'choose', layout.boxMenu.items[ 1 ] );

		assert.strictEqual(
			layout.draggableGroup.items.length,
			2,
			'Both candidates should be added'
		);

		layout.clearButton.emit( 'click' );

		assert.strictEqual(
			layout.draggableGroup.items.length,
			0,
			'All votes should be cleared'
		);

		// Re-adding Candidate 2 and re-verify
		layout.boxMenu.emit( 'choose', layout.boxMenu.items[ 1 ] );

		assert.strictEqual(
			layout.draggableGroup.items.length,
			1,
			'Candidate 2 should be added'
		);

		done();
	} );

	QUnit.test( 'Reaching the seat limit disables the combo box', ( assert ) => {
		const done = assert.async();
		const layout = generateSTVQuestionLayout( {
			options: [
				{ data: 'securepoll_q0000001_opt0000001', label: 'Candidate 1' },
				{ data: 'securepoll_q0000001_opt0000002', label: 'Candidate 2' },
				{ data: 'securepoll_q0000001_opt0000003', label: 'Candidate 3' }
			],
			maxSeats: 3
		} );

		layout.boxMenu.emit( 'choose', layout.boxMenu.items[ 0 ] );
		layout.boxMenu.emit( 'choose', layout.boxMenu.items[ 1 ] );

		assert.strictEqual(
			layout.comboBox.isDisabled(),
			false,
			'Combo box should not be disabled'
		);

		layout.boxMenu.emit( 'choose', layout.boxMenu.items[ 2 ] );

		assert.strictEqual(
			layout.comboBox.isDisabled(),
			true,
			'Combo box should be disabled'
		);

		done();
	} );

	QUnit.test( 'Clearing votes enables the combo box if previously at the seat limit', ( assert ) => {
		const done = assert.async();
		const layout = generateSTVQuestionLayout( {
			options: [
				{ data: 'securepoll_q0000001_opt0000001', label: 'Candidate 1' },
				{ data: 'securepoll_q0000001_opt0000002', label: 'Candidate 2' }
			],
			maxSeats: 2
		} );

		layout.boxMenu.emit( 'choose', layout.boxMenu.items[ 0 ] );
		layout.boxMenu.emit( 'choose', layout.boxMenu.items[ 1 ] );

		assert.strictEqual(
			layout.comboBox.isDisabled(),
			true,
			'Combo box should be disabled'
		);

		layout.clearButton.emit( 'click' );

		assert.strictEqual(
			layout.comboBox.isDisabled(),
			false,
			'Combo box should not be disabled'
		);

		done();
	} );

	QUnit.test( 'Removing an individual vote enables the combo box if previously at the seat limit', ( assert ) => {
		const done = assert.async();
		const layout = generateSTVQuestionLayout( {
			options: [
				{ data: 'securepoll_q0000001_opt0000001', label: 'Candidate 1' },
				{ data: 'securepoll_q0000001_opt0000002', label: 'Candidate 2' }
			],
			maxSeats: 2
		} );

		layout.boxMenu.emit( 'choose', layout.boxMenu.items[ 0 ] );
		layout.boxMenu.emit( 'choose', layout.boxMenu.items[ 1 ] );

		assert.strictEqual(
			layout.comboBox.isDisabled(),
			true,
			'Combo box should be disabled'
		);

		layout.draggableGroup.onDeleteItem( 0 );

		assert.strictEqual(
			layout.comboBox.isDisabled(),
			false,
			'Combo box should not be disabled'
		);

		done();
	} );
}() );
