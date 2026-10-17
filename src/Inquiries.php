<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Inquiry threads between a buyer and the farmer who owns a crop.
 *
 * Status lifecycle:
 *   pending   – buyer wrote; farmer has not opened the thread yet
 *   read      – farmer opened the thread
 *   responded – farmer replied last
 *   completed – either party marked the deal as done (buyer may rate)
 */
final class Inquiries
{
    /** Status after $role ('buyer'|'farmer') posts a message. */
    public static function statusAfterMessage(string $role, string $current): string
    {
        if ($current === 'completed') {
            return 'completed';
        }
        return $role === 'farmer' ? 'responded' : 'pending';
    }

    /** Status after the farmer opens the thread. */
    public static function statusAfterFarmerView(string $current): string
    {
        return $current === 'pending' ? 'read' : $current;
    }

    public static function canRate(array $thread, int $userId): bool
    {
        return $thread['status'] === 'completed'
            && (int) $thread['buyer_id'] === $userId
            && $thread['rating_score'] === null;
    }

    /**
     * Thread header with crop, farmer and buyer details, or null if it does
     * not exist or $userId is neither the buyer nor the crop's farmer.
     *
     * @return array<string,mixed>|null
     */
    public static function findForUser(PDO $db, int $inquiryId, int $userId): ?array
    {
        $stmt = $db->prepare(
            'SELECT i.*, c.name AS crop_name, c.image AS crop_image, c.price, c.unit, c.status AS crop_status,
                    c.user_id AS farmer_id,
                    f.name AS farmer_name, f.email AS farmer_email, f.phone AS farmer_phone,
                    b.name AS buyer_name,  b.email AS buyer_email,  b.phone AS buyer_phone,
                    fc.name AS farmer_county, bc.name AS buyer_county,
                    r.score AS rating_score, r.comment AS rating_comment
               FROM inquiries i
               JOIN crops c  ON c.id = i.crop_id
               JOIN users f  ON f.id = c.user_id
               JOIN users b  ON b.id = i.buyer_id
               LEFT JOIN counties fc ON fc.id = f.county_id
               LEFT JOIN counties bc ON bc.id = b.county_id
               LEFT JOIN ratings r   ON r.inquiry_id = i.id
              WHERE i.id = :id AND (i.buyer_id = :uid1 OR c.user_id = :uid2)'
        );
        $stmt->execute([':id' => $inquiryId, ':uid1' => $userId, ':uid2' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** @return list<array<string,mixed>> */
    public static function messages(PDO $db, int $inquiryId): array
    {
        $stmt = $db->prepare(
            'SELECT m.*, u.name AS sender_name
               FROM messages m JOIN users u ON u.id = m.sender_id
              WHERE m.inquiry_id = ?
              ORDER BY m.created_at, m.id'
        );
        $stmt->execute([$inquiryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Existing thread id for (crop, buyer), if any. */
    public static function existingId(PDO $db, int $cropId, int $buyerId): ?int
    {
        $stmt = $db->prepare('SELECT id FROM inquiries WHERE crop_id = ? AND buyer_id = ?');
        $stmt->execute([$cropId, $buyerId]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /**
     * Open a new thread with its first message, atomically.
     */
    public static function open(PDO $db, int $cropId, int $buyerId, string $message, ?string $contactPhone): int
    {
        $db->beginTransaction();
        try {
            $db->prepare('INSERT INTO inquiries (crop_id, buyer_id, contact_phone) VALUES (?, ?, ?)')
               ->execute([$cropId, $buyerId, $contactPhone !== '' ? $contactPhone : null]);
            $id = (int) $db->lastInsertId();
            $db->prepare('INSERT INTO messages (inquiry_id, sender_id, body) VALUES (?, ?, ?)')
               ->execute([$id, $buyerId, $message]);
            $db->commit();
            return $id;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Append a message and move the thread status, atomically.
     */
    public static function reply(PDO $db, array $thread, int $senderId, string $role, string $body): void
    {
        $db->beginTransaction();
        try {
            $db->prepare('INSERT INTO messages (inquiry_id, sender_id, body) VALUES (?, ?, ?)')
               ->execute([$thread['id'], $senderId, $body]);
            $db->prepare('UPDATE inquiries SET status = ? WHERE id = ?')
               ->execute([self::statusAfterMessage($role, $thread['status']), $thread['id']]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
