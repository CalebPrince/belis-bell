<?php
declare(strict_types=1);

use Belis\Core\App;
use Belis\Core\Auth;
use Belis\Core\Db;
use Belis\Core\Env;
use Belis\Core\Request;

// Category management: staff-editable, validated, audited, never deleted, slugs and parents fixed.

function categories_env(): OutboxMailer
{
    [$mail] = products_env();
    $pdo = Db::fromEnv()->pdo();
    foreach (['slug TEXT', 'blurb TEXT DEFAULT ""', 'sort_order INTEGER DEFAULT 0', 'is_mock INTEGER DEFAULT 0'] as $col) {
        $pdo->exec('ALTER TABLE categories ADD COLUMN ' . $col);
    }
    $pdo->exec("UPDATE categories SET slug = 'cleaning' WHERE id = 1");
    $pdo->exec("UPDATE categories SET slug = 'sub-cleaning' WHERE id = 2");
    $pdo->exec("UPDATE categories SET slug = 'washrooms' WHERE id = 3");
    $pdo->exec('UPDATE products SET category_id = 1, subcategory_id = 2 WHERE id = 1');
    return $mail;
}

function cat(int $id): array
{
    return Db::fromEnv()->one('SELECT * FROM categories WHERE id = ?', [$id]);
}

test('signed-out visitors and customers cannot reach category management', function (): void {
    categories_env();
    foreach (['/admin/categories', '/admin/categories/1'] as $p) {
        assert_same(302, App::router()->dispatch(new Request('GET', $p))->status, $p);
    }
    assert_same(302, post('/admin/categories', ['name' => 'Evil'])->status);
    assert_same(302, post('/admin/categories/1', ['name' => 'Evil'])->status);
    assert_same('Cleaning', cat(1)['name']);
    assert_same(3, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM categories')['n']);
    shop_done();
});

test('staff see the tree with product counts', function (): void {
    $mail = categories_env();
    login_as($mail, 'staff');
    $page = App::router()->dispatch(new Request('GET', '/admin/categories'));
    assert_same(200, $page->status);
    assert_contains('Cleaning', $page->body);
    assert_contains('Sub cleaning', $page->body);
    assert_contains('Washrooms', $page->body);
    assert_same(200, App::router()->dispatch(new Request('GET', '/admin/categories/1'))->status);
    assert_contains('Subcategory of Cleaning', App::router()->dispatch(new Request('GET', '/admin/categories/2'))->body);
    assert_same(404, App::router()->dispatch(new Request('GET', '/admin/categories/99'))->status);
    shop_done();
});

test('a new category starts hidden, gets a unique slug, and is audited', function (): void {
    $mail = categories_env();
    login_as($mail, 'staff');
    $res = post('/admin/categories', ['name' => 'Air Fresheners', 'blurb' => 'Smell nice', 'sort_order' => '30']);
    assert_same(302, $res->status);
    $c = Db::fromEnv()->one("SELECT * FROM categories WHERE name = 'Air Fresheners'");
    assert_same('air-fresheners', $c['slug']);
    assert_same(0, (int) $c['is_published'], 'a new category went live');
    assert_same(0, (int) $c['is_mock']);
    assert_same(30, (int) $c['sort_order']);
    assert_same(null, $c['parent_id']);
    Db::fromEnv()->run("INSERT INTO categories (id, is_published, name, parent_id, slug) VALUES (9, 1, 'Other', NULL, 'air-fresheners-2')");
    post('/admin/categories', ['name' => 'Air Fresheners!']);
    assert_same(1, (int) Db::fromEnv()->one("SELECT COUNT(*) AS n FROM categories WHERE slug = 'air-fresheners-3'")['n'], 'slug not made unique');
    assert_same(1, count_rows("action = 'category.create'") > 0 ? 1 : 0);
    shop_done();
});

test('subcategories go under a top-level category only', function (): void {
    $mail = categories_env();
    login_as($mail, 'staff');
    assert_same(302, post('/admin/categories', ['name' => 'Bowl cleaners', 'parent_id' => '3'])->status);
    $s = Db::fromEnv()->one("SELECT parent_id, is_published FROM categories WHERE name = 'Bowl cleaners'");
    assert_same(3, (int) $s['parent_id']);
    assert_same(0, (int) $s['is_published']);
    assert_same(1, count_rows("action = 'subcategory.create'"));
    assert_same(422, post('/admin/categories', ['name' => 'Too deep', 'parent_id' => '2'])->status);
    assert_same(404, post('/admin/categories', ['name' => 'Ghost parent', 'parent_id' => '77'])->status);
    assert_same(0, (int) Db::fromEnv()->one("SELECT COUNT(*) AS n FROM categories WHERE name IN ('Too deep', 'Ghost parent')")['n']);
    shop_done();
});

test('names, descriptions and order are validated, and sibling names cannot repeat', function (): void {
    $mail = categories_env();
    login_as($mail, 'staff');
    foreach ([['name' => ''], ['name' => str_repeat('x', 121)], ['name' => 'cleaning'], ['name' => 'Ok', 'blurb' => str_repeat('x', 256)], ['name' => 'Ok', 'sort_order' => 'first'], ['name' => 'Ok', 'sort_order' => '1.5']] as $bad) {
        assert_same(422, post('/admin/categories', $bad)->status, json_encode($bad));
    }
    assert_same(3, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM categories')['n']);
    assert_same(302, post('/admin/categories', ['name' => 'Cleaning', 'parent_id' => '3'])->status, 'the same name under another parent is fine');
    shop_done();
});

test('editing changes name, description, order and visibility but never the slug or the parent', function (): void {
    $mail = categories_env();
    login_as($mail, 'staff');
    post('/admin/categories/2', ['name' => 'Renamed sub', 'blurb' => 'x', 'sort_order' => '5', 'is_published' => '1', 'parent_id' => '3', 'slug' => 'hacked', 'id' => '50']);
    $c = cat(2);
    assert_same('Renamed sub', $c['name']);
    assert_same(5, (int) $c['sort_order']);
    assert_same(1, (int) $c['is_published']);
    assert_same(1, (int) $c['parent_id'], 'the parent was moved');
    assert_same('sub-cleaning', $c['slug'], 'the slug changed');
    assert_same('name, blurb, sort_order', Db::fromEnv()->one("SELECT detail FROM audit_log WHERE action = 'category.update'")['detail']);
    post('/admin/categories/2', ['name' => 'Renamed sub', 'blurb' => 'x', 'sort_order' => '5', 'is_published' => '1']);
    assert_same(1, count_rows("action = 'category.update'"), 'an unchanged save was audited');
    post('/admin/categories/2', ['name' => 'Renamed sub', 'blurb' => 'x', 'sort_order' => '5']);
    assert_same(0, (int) cat(2)['is_published'], 'the show box did not hide it');
    assert_same(404, post('/admin/categories/99', ['name' => 'Nope'])->status);
    shop_done();
});

test('a category cannot be renamed to a sibling name, and nothing can be deleted', function (): void {
    $mail = categories_env();
    login_as($mail, 'staff');
    assert_same(422, post('/admin/categories/1', ['name' => 'washrooms', 'is_published' => '1'])->status);
    assert_same('Cleaning', cat(1)['name']);
    $all = App::router()->inventory();
    foreach ($all as $r) {
        assert_true(!(str_contains($r['path'], 'categories') && (str_contains($r['path'], 'delete') || $r['method'] === 'DELETE')), 'a delete route exists');
    }
    shop_done();
});

test('category forms need a CSRF token, and the preview people cannot change anything', function (): void {
    $mail = categories_env();
    login_as($mail, 'staff');
    foreach (['/admin/categories', '/admin/categories/1'] as $p) {
        assert_same(419, App::router()->dispatch(new Request('POST', $p, [], ['_csrf' => 'bad', 'name' => 'x']))->status, $p);
    }
    Env::fake(['APP_ENV' => 'local', 'MOCK_DATA' => '1', 'PREVIEW_LOGIN' => 'staff', 'AUTH_PEPPER' => 'test-pepper-with-at-least-32-characters', 'SETTINGS_KEY' => KEY_A]);
    Auth::reset();
    assert_same(200, App::router()->dispatch(new Request('GET', '/admin/categories'))->status);
    assert_same(302, post('/admin/categories', ['name' => 'Preview made'])->status);
    assert_same(302, post('/admin/categories/1', ['name' => 'Preview renamed', 'is_published' => '1'])->status);
    assert_same('Cleaning', cat(1)['name']);
    assert_same(3, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM categories')['n']);
    shop_done();
});
