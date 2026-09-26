<?php
declare(strict_types=1);

namespace Belis\Domain;

use Belis\Core\Db;
use Belis\Support\Site;
use Belis\Support\Validator;

/** A customer's saved delivery addresses. Every read and write is limited to the owner of the address (CTL-AUTHZ-001). */
final class Addresses
{
    public const MAX = 10;

    public function __construct(private readonly Db $db, private readonly ?int $clock = null)
    {
    }

    /** @return list<array<string,mixed>> default first */
    public function forUser(int $userId): array
    {
        return $this->db->all('SELECT id, label, name, phone, street, city, region, is_default FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id', [$userId]);
    }

    /** @return array<string,mixed>|null only the person's own address */
    public function find(int $userId, int $id): ?array
    {
        return $this->db->one('SELECT id, label, name, phone, street, city, region, is_default FROM addresses WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    /**
     * @param array<string,mixed> $in label, name, phone, street, city, region
     * @return array<string,string> field errors, empty when saved
     */
    public function add(int $userId, array $in): array
    {
        $errors = Validator::check($in, ['label' => 'required|max:40', 'name' => 'required|max:120', 'phone' => 'required|max:20', 'street' => 'required|max:200', 'city' => 'required|max:80', 'region' => 'required|in:' . implode(',', Site::regions())]);
        $phone = is_string($in['phone'] ?? null) ? trim($in['phone']) : '';
        if (!isset($errors['phone']) && preg_match('/^\+?[0-9 ()-]{7,20}$/', $phone) !== 1) {
            $errors['phone'] = 'Enter a valid phone number.';
        }
        $have = $this->forUser($userId);
        if ($errors === [] && count($have) >= self::MAX) {
            $errors['label'] = 'You can save up to ' . self::MAX . ' addresses. Remove one first.';
        }
        if ($errors !== []) {
            return $errors;
        }
        $this->db->run(
            'INSERT INTO addresses (user_id, label, name, phone, street, city, region, is_default, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, trim((string) $in['label']), trim((string) $in['name']), $phone, trim((string) $in['street']), trim((string) $in['city']), (string) $in['region'], $have === [] ? 1 : 0, $this->clock ?? time()],
        );
        return [];
    }

    /** Save a checkout address unless the same street and city is already saved or the limit is reached. */
    public function saveFromCheckout(int $userId, array $in): void
    {
        foreach ($this->forUser($userId) as $a) {
            if (mb_strtolower((string) $a['street']) === mb_strtolower(trim((string) ($in['street'] ?? ''))) && mb_strtolower((string) $a['city']) === mb_strtolower(trim((string) ($in['city'] ?? '')))) {
                return;
            }
        }
        $this->add($userId, ['label' => 'Delivery'] + $in);
    }

    public function setDefault(int $userId, int $id): bool
    {
        if ($this->find($userId, $id) === null) {
            return false;
        }
        $this->db->run('UPDATE addresses SET is_default = 0 WHERE user_id = ?', [$userId]);
        $this->db->run('UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?', [$id, $userId]);
        return true;
    }

    public function delete(int $userId, int $id): bool
    {
        $a = $this->find($userId, $id);
        if ($a === null) {
            return false;
        }
        $this->db->run('DELETE FROM addresses WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ((int) $a['is_default'] === 1) {
            $next = $this->db->one('SELECT id FROM addresses WHERE user_id = ? ORDER BY id LIMIT 1', [$userId]);
            if ($next !== null) {
                $this->setDefault($userId, (int) $next['id']);
            }
        }
        return true;
    }
}
