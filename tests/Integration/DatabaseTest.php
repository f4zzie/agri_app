<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Inquiries;
use App\Migrator;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real migrations against a throwaway database (TEST_DB_NAME,
 * default agri_app_test) and checks the rules the schema is supposed to
 * enforce. Skipped when no MySQL/MariaDB server is reachable.
 */
final class DatabaseTest extends TestCase
{
    private static ?PDO $db = null;
    private static string $name;

    public static function setUpBeforeClass(): void
    {
        self::$name = getenv('TEST_DB_NAME') ?: 'agri_app_test';
        if (!preg_match('/^[a-z0-9_]+_test$/', self::$name)) {
            self::fail('TEST_DB_NAME must end in _test (it is dropped and recreated).');
        }
        try {
            $server = getServerConnection();
        } catch (PDOException $e) {
            return; // tests are skipped in setUp()
        }
        $server->exec('DROP DATABASE IF EXISTS `' . self::$name . '`');
        $server->exec('CREATE DATABASE `' . self::$name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $server->exec('USE `' . self::$name . '`');
        (new Migrator($server, __DIR__ . '/../../migrations'))->migrate();
        self::$db = $server;
    }

    public static function tearDownAfterClass(): void
    {
        self::$db?->exec('DROP DATABASE IF EXISTS `' . self::$name . '`');
        self::$db = null;
    }

    protected function setUp(): void
    {
        if (self::$db === null) {
            self::markTestSkipped('No database server reachable (set DB_HOST/DB_PORT/DB_USER/DB_PASS).');
        }
        self::$db->beginTransaction(); // each test runs inside a transaction that is rolled back
    }

    protected function tearDown(): void
    {
        if (self::$db?->inTransaction()) {
            self::$db->rollBack();
        }
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function user(string $role, string $name = 'Test User', ?int $county = 32): int
    {
        self::$db->prepare('INSERT INTO users (name, email, role, county_id, email_verified) VALUES (?, ?, ?, ?, 1)')
                 ->execute([$name, bin2hex(random_bytes(6)) . '@test.local', $role, $county]);
        return (int) self::$db->lastInsertId();
    }

    private function crop(int $farmer, array $cols = []): int
    {
        $cols += ['name' => 'Maize', 'price' => 50, 'unit' => 'kg'];
        $keys = array_keys($cols);
        self::$db->prepare('INSERT INTO crops (user_id, ' . implode(', ', $keys) . ') VALUES (?' . str_repeat(', ?', count($keys)) . ')')
                 ->execute(array_merge([$farmer], array_values($cols)));
        return (int) self::$db->lastInsertId();
    }

    private function countRows(string $sql, array $params = []): int
    {
        $stmt = self::$db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function assertRejected(callable $fn, string $constraint): void
    {
        try {
            $fn();
            self::fail("Expected the database to reject this ({$constraint})");
        } catch (PDOException $e) {
            self::assertStringContainsString($constraint, $e->getMessage());
        }
    }

    // ── schema ───────────────────────────────────────────────────────────────

    public function testAllMigrationsAppliedAndRerunIsANoOp(): void
    {
        $migrator = new Migrator(self::$db, __DIR__ . '/../../migrations');

        self::assertSame($migrator->all(), $migrator->applied());
        self::assertSame([], $migrator->pending());
    }

    public function testCheckConstraintsRejectInvalidListings(): void
    {
        $farmer = $this->user('farmer');

        $this->assertRejected(fn () => $this->crop($farmer, ['price' => -5]), 'chk_crops_price_positive');
        $this->assertRejected(fn () => $this->crop($farmer, ['quantity' => -1]), 'chk_crops_quantity_nonneg');
        $this->assertRejected(fn () => $this->crop($farmer, ['unit' => 'furlong']), 'chk_crops_unit');
        $this->assertRejected(fn () => $this->crop($farmer, ['name' => '   ']), 'chk_crops_name_not_blank');
        $this->assertRejected(
            fn () => $this->crop($farmer, ['planting_date' => '2026-05-01', 'expected_harvest' => '2026-04-01']),
            'chk_crops_harvest_after_planting'
        );
    }

    public function testStatusAndGrowthStageAreSeparateEnums(): void
    {
        $farmer = $this->user('farmer');
        $this->crop($farmer, ['status' => 'available', 'growth_stage' => 'harvested']);

        $this->assertRejected(fn () => $this->crop($farmer, ['status' => 'growing']), 'status');
    }

    public function testPriceHistoryTriggersRecordChangesAndSurviveDeletion(): void
    {
        $farmer = $this->user('farmer', 'Wanjiru', 32);
        $crop   = $this->crop($farmer, ['name' => ' Tomatoes ', 'price' => 80]);

        self::$db->prepare('UPDATE crops SET quantity = 10 WHERE id = ?')->execute([$crop]); // no price change
        self::$db->prepare('UPDATE crops SET price = 95 WHERE id = ?')->execute([$crop]);
        self::$db->prepare('UPDATE crops SET price = NULL WHERE id = ?')->execute([$crop]); // negotiable: not recorded

        $rows = self::$db->query("SELECT product_key, county_id, price FROM price_history WHERE crop_id = {$crop} ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        self::assertSame([
            ['product_key' => 'tomatoes', 'county_id' => 32, 'price' => '80.00'],
            ['product_key' => 'tomatoes', 'county_id' => 32, 'price' => '95.00'],
        ], $rows);

        self::$db->prepare('DELETE FROM crops WHERE id = ?')->execute([$crop]);
        self::assertSame(2, $this->countRows("SELECT COUNT(*) FROM price_history WHERE product_key = 'tomatoes' AND crop_id IS NULL"));
    }

    // ── inquiries ────────────────────────────────────────────────────────────

    public function testOpeningAThreadWritesInquiryAndFirstMessage(): void
    {
        $farmer = $this->user('farmer');
        $buyer  = $this->user('buyer');
        $crop   = $this->crop($farmer);

        $id = Inquiries::open(self::$db, $crop, $buyer, 'I need 200 kg delivered to Nakuru', '0712345678');

        self::assertSame($id, Inquiries::existingId(self::$db, $crop, $buyer));
        self::assertSame(1, $this->countRows('SELECT COUNT(*) FROM messages WHERE inquiry_id = ?', [$id]));
    }

    public function testFailedOpenRollsBackTheInquiry(): void
    {
        $farmer = $this->user('farmer');
        $buyer  = $this->user('buyer');
        $crop   = $this->crop($farmer);
        self::$db->commit(); // Inquiries::open manages its own transaction

        try {
            Inquiries::open(self::$db, $crop, $buyer, '   ', null); // blank message violates chk_msg_body_not_blank
            self::fail('blank message should be rejected');
        } catch (PDOException $e) {
            self::assertStringContainsString('chk_msg_body_not_blank', $e->getMessage());
        }
        self::assertSame(0, $this->countRows('SELECT COUNT(*) FROM inquiries WHERE crop_id = ?', [$crop]), 'no orphan inquiry');

        self::$db->prepare('DELETE FROM users WHERE id IN (?, ?)')->execute([$farmer, $buyer]); // clean up (committed)
        self::$db->beginTransaction();
    }

    public function testOneThreadPerBuyerAndProduct(): void
    {
        $farmer = $this->user('farmer');
        $buyer  = $this->user('buyer');
        $crop   = $this->crop($farmer);
        self::$db->prepare('INSERT INTO inquiries (crop_id, buyer_id) VALUES (?, ?)')->execute([$crop, $buyer]);

        $this->assertRejected(
            fn () => self::$db->prepare('INSERT INTO inquiries (crop_id, buyer_id) VALUES (?, ?)')->execute([$crop, $buyer]),
            'uq_inquiry_crop_buyer'
        );
    }

    public function testOnlyTheTwoPartiesCanReadAThread(): void
    {
        $farmer   = $this->user('farmer');
        $buyer    = $this->user('buyer');
        $stranger = $this->user('buyer');
        $crop     = $this->crop($farmer);
        self::$db->prepare('INSERT INTO inquiries (crop_id, buyer_id) VALUES (?, ?)')->execute([$crop, $buyer]);
        $id = (int) self::$db->lastInsertId();

        self::assertNotNull(Inquiries::findForUser(self::$db, $id, $buyer));
        self::assertSame($farmer, (int) Inquiries::findForUser(self::$db, $id, $farmer)['farmer_id']);
        self::assertNull(Inquiries::findForUser(self::$db, $id, $stranger));
    }

    public function testRatingsAreOnePerDealAndOneToFive(): void
    {
        $farmer = $this->user('farmer');
        $buyer  = $this->user('buyer');
        $crop   = $this->crop($farmer);
        self::$db->prepare("INSERT INTO inquiries (crop_id, buyer_id, status) VALUES (?, ?, 'completed')")->execute([$crop, $buyer]);
        $id = (int) self::$db->lastInsertId();

        $this->assertRejected(fn () => self::$db->exec("INSERT INTO ratings (inquiry_id, score) VALUES ({$id}, 6)"), 'chk_rating_score');
        self::$db->exec("INSERT INTO ratings (inquiry_id, score) VALUES ({$id}, 4)");
        $this->assertRejected(fn () => self::$db->exec("INSERT INTO ratings (inquiry_id, score) VALUES ({$id}, 5)"), 'PRIMARY');

        $row = self::$db->query("SELECT avg_rating, rating_count FROM v_farmer_ratings WHERE farmer_id = {$farmer}")->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['avg_rating' => '4.0', 'rating_count' => 1], ['avg_rating' => $row['avg_rating'], 'rating_count' => (int) $row['rating_count']]);
    }

    public function testDeletingAListingCascadesToItsConversations(): void
    {
        $farmer = $this->user('farmer');
        $buyer  = $this->user('buyer');
        $crop   = $this->crop($farmer);
        self::$db->prepare("INSERT INTO inquiries (crop_id, buyer_id, status) VALUES (?, ?, 'completed')")->execute([$crop, $buyer]);
        $id = (int) self::$db->lastInsertId();
        self::$db->prepare('INSERT INTO messages (inquiry_id, sender_id, body) VALUES (?, ?, ?)')->execute([$id, $buyer, 'Hello']);
        self::$db->exec("INSERT INTO ratings (inquiry_id, score) VALUES ({$id}, 5)");

        self::$db->prepare('DELETE FROM crops WHERE id = ?')->execute([$crop]);

        self::assertSame(0, $this->countRows('SELECT COUNT(*) FROM inquiries WHERE id = ?', [$id]));
        self::assertSame(0, $this->countRows('SELECT COUNT(*) FROM messages WHERE inquiry_id = ?', [$id]));
        self::assertSame(0, $this->countRows('SELECT COUNT(*) FROM ratings WHERE inquiry_id = ?', [$id]));
    }

    // ── views ────────────────────────────────────────────────────────────────

    public function testMarketplaceViewOnlyListsFarmersWithNames(): void
    {
        $farmer = $this->user('farmer', 'Otieno', 42);
        $this->crop($farmer, ['name' => 'Sorghum', 'category_id' => 1]);

        $row = self::$db->query("SELECT farmer_name, farmer_county, category FROM v_marketplace WHERE farmer_id = {$farmer}")->fetch(PDO::FETCH_ASSOC);

        self::assertSame(['farmer_name' => 'Otieno', 'farmer_county' => 'Kisumu', 'category' => 'Grains & Cereals'], $row);
    }

    public function testInquiryThreadViewShowsLatestMessageAndCount(): void
    {
        $farmer = $this->user('farmer', 'Chebet');
        $buyer  = $this->user('buyer', 'Hotel Buyer');
        $crop   = $this->crop($farmer, ['name' => 'Milk', 'unit' => 'litre']);
        self::$db->prepare('INSERT INTO inquiries (crop_id, buyer_id) VALUES (?, ?)')->execute([$crop, $buyer]);
        $id = (int) self::$db->lastInsertId();
        foreach ([[$buyer, 'Need 100 litres daily'], [$farmer, 'Yes, KSh 55 per litre']] as [$from, $body]) {
            self::$db->prepare('INSERT INTO messages (inquiry_id, sender_id, body) VALUES (?, ?, ?)')->execute([$id, $from, $body]);
        }

        $row = self::$db->query("SELECT farmer_id, buyer_name, farmer_name, last_body, message_count FROM v_inquiry_threads WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);

        self::assertSame($farmer, (int) $row['farmer_id']);
        self::assertSame('Yes, KSh 55 per litre', $row['last_body']);
        self::assertSame(2, (int) $row['message_count']);
    }
}
