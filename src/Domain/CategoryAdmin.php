<?php
declare(strict_types=1);

namespace Belis\Domain;

use Belis\Core\Db;

/**
 * Category and subcategory management. Two levels only: a category, and subcategories under it. A category's web
 * address (slug) is fixed when it is created so links keep working. Nothing is deleted: hide a category instead,
 * because products and photos point at it. Moving a category to another parent is not allowed.
 */
final class CategoryAdmin
{
    public function __construct(private readonly Db $db)
    {
    }

    /** @return list<array<string,mixed>> top-level categories, each with its subcategories, and product counts */
    public function tree(): array
    {
        $tops = $this->db->all('SELECT c.id, c.slug, c.name, c.blurb, c.sort_order, c.is_published, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS products FROM categories c WHERE c.parent_id IS NULL ORDER BY c.sort_order, c.name');
        foreach ($tops as $i => $t) {
            $tops[$i]['subs'] = $this->db->all('SELECT c.id, c.slug, c.name, c.sort_order, c.is_published, (SELECT COUNT(*) FROM products p WHERE p.subcategory_id = c.id) AS products FROM categories c WHERE c.parent_id = ? ORDER BY c.sort_order, c.name', [(int) $t['id']]);
        }
        return $tops;
    }

    /** @return array<string,mixed>|null a category or subcategory, with its parent's name and its subcategories */
    public function find(int $id): ?array
    {
        $c = $this->db->one('SELECT c.id, c.slug, c.name, c.blurb, c.sort_order, c.is_published, c.parent_id, c.is_mock, (SELECT p.name FROM categories p WHERE p.id = c.parent_id) AS parent_name FROM categories c WHERE c.id = ?', [$id]);
        if ($c === null) {
            return null;
        }
        $c['products'] = (int) ($this->db->one('SELECT COUNT(*) AS n FROM products WHERE category_id = ? OR subcategory_id = ?', [$id, $id])['n'] ?? 0);
        $c['subs'] = $c['parent_id'] === null ? $this->db->all('SELECT id, slug, name, sort_order, is_published FROM categories WHERE parent_id = ? ORDER BY sort_order, name', [$id]) : [];
        return $c;
    }

    /**
     * @param array<string,mixed> $in name, blurb, sort_order, is_published
     * @return array{errors:array<string,string>,clean:array<string,mixed>}
     */
    public function validate(array $in, ?int $parentId, ?int $selfId): array
    {
        $str = static fn (string $k): string => is_string($in[$k] ?? null) ? trim($in[$k]) : '';
        $name = $str('name');
        $blurb = $str('blurb');
        $sort = $str('sort_order');
        $errors = [];
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['name'] = 'Enter a name of up to 120 characters.';
        } elseif ($this->siblingNamed($name, $parentId, $selfId)) {
            $errors['name'] = 'Another category here already has that name.';
        }
        if (mb_strlen($blurb) > 255) {
            $errors['blurb'] = 'Use 255 characters or fewer.';
        }
        if ($sort !== '' && preg_match('/^-?\d{1,5}$/', $sort) !== 1) {
            $errors['sort_order'] = 'Enter a whole number, for example 10.';
        }
        return ['errors' => $errors, 'clean' => ['name' => $name, 'blurb' => $blurb, 'sort_order' => $sort === '' ? 0 : (int) $sort, 'is_published' => ($in['is_published'] ?? '') === '1' ? 1 : 0]];
    }

    private function siblingNamed(string $name, ?int $parentId, ?int $selfId): bool
    {
        $rows = $this->db->all('SELECT id, name FROM categories WHERE (parent_id IS NULL AND ? = 0) OR parent_id = ?', [$parentId ?? 0, $parentId ?? 0]);
        foreach ($rows as $r) {
            if ((int) $r['id'] !== (int) $selfId && mb_strtolower((string) $r['name']) === mb_strtolower($name)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Create a category, or a subcategory when $parentId is a top-level category. New ones start hidden.
     *
     * @param array<string,mixed> $in
     * @return array{errors:array<string,string>,id:int}
     */
    public function create(array $in, ?int $parentId): array
    {
        if ($parentId !== null) {
            $parent = $this->db->one('SELECT id, parent_id FROM categories WHERE id = ?', [$parentId]);
            if ($parent === null || $parent['parent_id'] !== null) {
                return ['errors' => ['parent' => 'Subcategories can only go under a top-level category.'], 'id' => 0];
            }
        }
        $v = $this->validate($in, $parentId, null);
        if ($v['errors'] !== []) {
            return ['errors' => $v['errors'], 'id' => 0];
        }
        $c = $v['clean'];
        $slug = $this->uniqueSlug((string) $c['name']);
        $this->db->run('INSERT INTO categories (parent_id, slug, name, blurb, sort_order, is_published, is_mock) VALUES (?, ?, ?, ?, ?, 0, 0)', [$parentId, $slug, $c['name'], (string) $c['blurb'], $c['sort_order']]);
        return ['errors' => [], 'id' => (int) ($this->db->one('SELECT id FROM categories WHERE slug = ?', [$slug])['id'] ?? 0)];
    }

    /**
     * Save name, blurb, order and visibility. The slug and the parent never change.
     *
     * @param array<string,mixed> $in
     * @return array{errors:array<string,string>,changed:list<string>,found:bool}
     */
    public function update(int $id, array $in): array
    {
        $old = $this->db->one('SELECT name, blurb, sort_order, is_published, parent_id FROM categories WHERE id = ?', [$id]);
        if ($old === null) {
            return ['errors' => [], 'changed' => [], 'found' => false];
        }
        $v = $this->validate($in, $old['parent_id'] === null ? null : (int) $old['parent_id'], $id);
        if ($v['errors'] !== []) {
            return ['errors' => $v['errors'], 'changed' => [], 'found' => true];
        }
        $c = $v['clean'];
        $changed = [];
        foreach (['name', 'blurb', 'sort_order', 'is_published'] as $k) {
            if ((string) $old[$k] !== (string) $c[$k]) {
                $changed[] = $k;
            }
        }
        if ($changed !== []) {
            $this->db->run('UPDATE categories SET name = ?, blurb = ?, sort_order = ?, is_published = ? WHERE id = ?', [$c['name'], (string) $c['blurb'], $c['sort_order'], $c['is_published'], $id]);
        }
        return ['errors' => [], 'changed' => $changed, 'found' => true];
    }

    private function uniqueSlug(string $name): string
    {
        $base = CatalogueAdmin::slugify($name);
        $slug = $base;
        for ($i = 2; $this->db->one('SELECT id FROM categories WHERE slug = ?', [$slug]) !== null; $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }
}
