<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Inquiries;
use App\Migrator;
use App\Token;
use PHPUnit\Framework\TestCase;

final class TokenInquiriesMigratorTest extends TestCase
{
    public function testTokensAreRandomAndOnlyTheHashIsDerived(): void
    {
        [$a, $hashA] = Token::generate();
        [$b]         = Token::generate();

        self::assertNotSame($a, $b);
        self::assertTrue(Token::looksValid($a));
        self::assertSame(hash('sha256', $a), $hashA);
        self::assertNotSame($a, $hashA);
        self::assertFalse(Token::looksValid('../../etc'));
    }

    public function testInquiryStatusLifecycle(): void
    {
        self::assertSame('responded', Inquiries::statusAfterMessage('farmer', 'pending'));
        self::assertSame('pending', Inquiries::statusAfterMessage('buyer', 'responded'));
        self::assertSame('completed', Inquiries::statusAfterMessage('buyer', 'completed'), 'completed deals stay completed');
        self::assertSame('read', Inquiries::statusAfterFarmerView('pending'));
        self::assertSame('responded', Inquiries::statusAfterFarmerView('responded'));
    }

    public function testOnlyTheBuyerRatesACompletedUnratedDeal(): void
    {
        $thread = ['status' => 'completed', 'buyer_id' => 7, 'rating_score' => null];

        self::assertTrue(Inquiries::canRate($thread, 7));
        self::assertFalse(Inquiries::canRate($thread, 8), 'not the buyer');
        self::assertFalse(Inquiries::canRate(['status' => 'responded'] + $thread, 7), 'not completed');
        self::assertFalse(Inquiries::canRate(['rating_score' => 4] + $thread, 7), 'already rated');
    }

    public function testMigrationSplitterHandlesCommentsAndTriggers(): void
    {
        $sql = "-- header comment\nCREATE TABLE a (id INT);\n\n-- only a comment;\n"
             . "CREATE TRIGGER t AFTER INSERT ON a FOR EACH ROW\nINSERT INTO b SELECT NEW.id FROM DUAL WHERE NEW.id > 0;\n"
             . "INSERT INTO a VALUES (1); \n";

        $parts = Migrator::splitStatements($sql);

        self::assertCount(3, $parts);
        self::assertStringStartsWith('-- header comment', $parts[0]);
        self::assertStringContainsString('CREATE TRIGGER t', $parts[1]);
        self::assertStringEndsWith('WHERE NEW.id > 0', $parts[1]);
    }

    public function testMigrationFilesAreNumberedWithoutGaps(): void
    {
        $files = glob(__DIR__ . '/../../migrations/*.sql');
        $nums  = array_map(static fn ($f) => (int) substr(basename($f), 0, 3), $files);
        sort($nums);

        self::assertSame(range(1, count($nums)), $nums);
    }
}
