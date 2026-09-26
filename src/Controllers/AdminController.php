<?php
declare(strict_types=1);

namespace Belis\Controllers;

use Belis\Core\Auth;
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
        return Response::html(View::render('pages/admin/dashboard', [
            'title' => 'Admin | Belis Bell',
            'staff' => array_replace(Auth::staff() ?? [], ['role' => ucfirst((string) (Auth::staff()['role'] ?? ''))]),
            'stats' => PreviewData::adminStats(),
            'orders' => PreviewData::adminOrders(),
            'low' => PreviewData::lowStock(),
        ]));
    }
}
