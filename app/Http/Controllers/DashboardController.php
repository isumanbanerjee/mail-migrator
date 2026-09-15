<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\JobRepository;
use App\Support\Auth;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\View;

final class DashboardController
{
    public function __construct(
        private JobRepository $jobs,
        private Auth $auth,
        private View $view,
        private Session $session,
    ) {}

    public function index(Request $req): Response
    {
        $jobs = $this->jobs->listForUser($this->auth->userId());
        return Response::html($this->view->render('dashboard/index', [
            'title' => 'Dashboard', 'jobs' => $jobs, 'session' => $this->session,
        ]));
    }

    public function progress(Request $req, array $vars): Response
    {
        $id = (int) $vars['id'];
        $job = $this->jobs->find($id, $this->auth->userId());
        if ($job === null) {
            return Response::json(['error' => 'not_found'], 404);
        }

        $filter = (string) $req->query('status', 'all');
        $filter = in_array($filter, ['copied', 'skipped', 'failed', 'pending'], true) ? $filter : 'all';
        $perPage = 50;
        $page = max(1, (int) $req->query('page', 1));

        $counts = $this->jobs->ledgerCounts($id);
        $rows = $this->jobs->ledgerMessages($id, $filter === 'all' ? null : $filter, $perPage + 1, ($page - 1) * $perPage);
        $hasMore = count($rows) > $perPage;
        $rows = array_slice($rows, 0, $perPage);

        $done = $counts['copied'] + $counts['skipped'];
        $percent = $counts['total'] > 0 ? (int) round($done / $counts['total'] * 100) : (int) $job['percent'];

        $messages = array_map(static function (array $m): array {
            $sent = (string) ($m['internal_date'] ?? '');
            if ($sent === '' || str_starts_with($sent, '0000-00-00')) {
                $sent = '';
            }
            $flags = json_decode((string) ($m['flags'] ?? '[]'), true) ?: [];
            $unread = !in_array('\\Seen', $flags, true) && !in_array('Seen', $flags, true);
            return [
                'folder' => (string) $m['source_folder'],
                'uid' => (int) $m['source_uid'],
                'subject' => (string) ($m['subject'] ?? ''),
                'message_id' => (string) ($m['message_id'] ?? ''),
                'sent_date' => $sent,
                'size' => (int) ($m['size_bytes'] ?? 0),
                'unread' => $unread,
                'status' => (string) $m['status'],
                'attempts' => (int) ($m['attempts'] ?? 0),
                'error' => (string) ($m['error'] ?? ''),
                'updated_at' => (string) ($m['updated_at'] ?? ''),
            ];
        }, $rows);

        $options = json_decode((string) ($job['options'] ?? '{}'), true) ?: [];

        return Response::json([
            'state' => $job['state'],
            'percent' => $percent,
            'current_folder' => $job['current_folder'],
            'last_error' => $job['last_error'] ?? null,
            'counts' => $counts,
            'filter' => $filter,
            'page' => $page,
            'per_page' => $perPage,
            'has_more' => $hasMore,
            'messages' => $messages,
            'limit' => $options['limit'] ?? null,
            'since' => $options['since'] ?? null,
        ]);
    }
}
