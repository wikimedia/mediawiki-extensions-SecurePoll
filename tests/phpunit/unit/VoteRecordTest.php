<?php
namespace MediaWiki\Extension\SecurePoll\Test\Unit;

use MediaWiki\Extension\SecurePoll\VoteRecord;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\SecurePoll\VoteRecord
 */
class VoteRecordTest extends MediaWikiUnitTestCase {
	/**
	 * @dataProvider provideReadValidNewStyleBlob
	 */
	public function testReadValidNewStyleBlob(
		string $blob,
		string $expectedBallotData,
		string $expectedComment
	): void {
		$status = VoteRecord::readBlob( $blob );
		$record = $status->getValue();
		$roundtripBlob = $record->getBlob();

		$this->assertStatusGood( $status );
		$this->assertSame( $expectedBallotData, $record->getBallotData() );
		$this->assertSame( $expectedComment, $record->getComment() );
		$this->assertJsonStringEqualsJsonString( $blob, $roundtripBlob );
	}

	public static function provideReadValidNewStyleBlob(): iterable {
		yield 'JSON blob, without comment' => [
			'{"vote":"Q00000040-C00000041-R00000000--"}',
			'Q00000040-C00000041-R00000000--',
			''
		];
		yield 'JSON blob, with comment' => [
			'{"vote":"Q00000040-C00000041-R00000000--","comment":"foo"}',
			'Q00000040-C00000041-R00000000--',
			'foo'
		];
	}

	/**
	 * @dataProvider provideReadValidLegacyBlob
	 */
	public function testReadValidLegacyBlob(
		string $blob,
		string $expectedBallotData,
		string $expectedRoundtripBlob
	): void {
		$status = VoteRecord::readBlob( $blob );
		$record = $status->getValue();
		$roundtripBlob = $record->getBlob();

		$this->assertStatusGood( $status );
		$this->assertSame( $expectedBallotData, $record->getBallotData() );
		$this->assertSame( '', $record->getComment() );
		$this->assertJsonStringEqualsJsonString( $expectedRoundtripBlob, $roundtripBlob );
	}

	public static function provideReadValidLegacyBlob(): iterable {
		yield 'legacy blob' => [
			'Q00000040-C00000041-R00000000--',
			'Q00000040-C00000041-R00000000--',
			'{"vote":"Q00000040-C00000041-R00000000--"}'
		];

		yield 'legacy blob with trailing whitespace' => [
			'Q00000040-C00000041-R00000000--   ',
			'Q00000040-C00000041-R00000000--',
			'{"vote":"Q00000040-C00000041-R00000000--"}'
		];
	}

	public function testNewFromBallotData(): void {
		$ballotData = 'Q00000040-C00000041-R00000000--';
		$comment = 'foo';

		$record = VoteRecord::newFromBallotData( $ballotData, $comment );

		$this->assertSame( $ballotData, $record->getBallotData() );
		$this->assertSame( $comment, $record->getComment() );
		$this->assertJsonStringEqualsJsonString(
			'{"vote":"Q00000040-C00000041-R00000000--","comment":"foo"}',
			$record->getBlob()
		);
	}
}
