<?php
declare(strict_types=1);

namespace Belis\Controllers;

use Belis\Core\Auth;
use Belis\Core\Db;
use Belis\Domain\Orders;
use Belis\Support\Logger;
use Belis\Core\Request;
use Belis\Core\Response;
use Belis\Core\View;
use Belis\Support\PreviewData;

/** Staff sign-in and dashboard. NOT BUILT beyond structure: see AccountController. Nothing here writes data. */
final class AdminController
{
    /** @param array<string,string> $params */
    public function signIn(Request $request, array $params = []): Response
    {
        return Response::html(View::render('pages/auth/sign-in', ['title' => 'Staff sign in | Belis Bell', 'admin' => true]));
    }

    /** @param array<string,string> $params */
    public function verify(Request $request, array $params = []): Response
    {
        if (!AuthController::hasPending(true)) {
            return Response::redirect('/admin/sign-in');
        }
        return Response::html(View::render('pages/auth/verify', ['title' => 'Enter your code | Belis Bell', 'admin' => true]));
    }

    /** @param array<string,string> $params */
    public function dashboard(Request $request, array $params = []): Response
    {
        $staff = Auth::staff() ?? [];
        $stats = PreviewData::adminStats();
        $orders = PreviewData::adminOrders();
        $low = PreviewData::lowStock();
        if (isset($staff['id'])) {
            try {
                $o = new Orders(Db::fromEnv());
                $f = $o->adminFigures();
                $stats = [
                    ['label' => 'Orders today', 'value' => (string) $f['today'], 'note' => 'Since midnight UTC'],
                    ['label' => 'Paid this week', 'value' => money($f['week_pesewas']), 'note' => 'Last 7 days'],
                    ['label' => 'To pack', 'value' => (string) $f['to_pack'], 'note' => 'Paid, not yet packed'],
                    ['label' => 'Need review', 'value' => (string) $f['review'], 'note' => $f['unpaid'] . ' waiting for payment'],
                ];
                $orders = array_map(static fn (array $r): array => ['ref' => (string) $r['ref'], 'customer' => (string) $r['ship_name'], 'state' => (string) $r['status'], 'total' => (int) $r['total_pesewas']], $o->adminList([])['items']);
                $orders = array_slice($orders, 0, 8);
                $low = [];
            } catch (\Throwable $e) {
                Logger::error('Admin dashboard failed', ['type' => $e::class]);
                $stats = [];
                $orders = [];
                $low = [];
            }
        }
        return Response::html(View::render('pages/admin/dashboard', [
            'title' => 'Admin | Belis Bell',
            'staff' => array_replace($staff, ['role' => ucfirst((string) ($staff['role'] ?? ''))]),
            'stats' => $stats,
            'orders' => $orders,
            'low' => $low,
        ]));
    }
}
