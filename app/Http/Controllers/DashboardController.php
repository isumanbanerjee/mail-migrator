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
        $job = $this->jobs->find((int) $vars['id'], $this->auth->userId());
        if ($job === null) {
            return Response::json(['error' => 'not_found'], 404);
        }
        return Response::json([
            'state' => $job['state'],
            'percent' => (int) $job['percent'],
            'copied' => (int) $job['copied'],
            'skipped' => (int) $job['skipped'],
            'failed' => (int) $job['failed'],
            'total' => (int) $job['total_messages'],
            'current_folder' => $job['current_folder'],
        ]);
    }
}
