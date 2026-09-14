<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\JobRepository;
use App\Services\ConnectionTester;
use App\Support\Auth;
use App\Support\Encryptor;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\Validator;
use App\Support\View;
use EmailMigration\FolderMapper;

final class JobController
{
    public function __construct(
        private JobRepository $jobs,
        private Encryptor $enc,
        private ConnectionTester $tester,
        private Auth $auth,
        private View $view,
        private Session $session,
    ) {}

    public function create(Request $req): Response
    {
        return Response::html($this->view->render('jobs/form', [
            'title' => 'New Job', 'errors' => [], 'job' => null, 'old' => [], 'session' => $this->session,
        ]));
    }

    public function store(Request $req): Response
    {
        $errors = $this->validate($req);
        if ($errors !== []) {
            return Response::html($this->view->render('jobs/form', [
                'title' => 'New Job', 'errors' => $errors, 'job' => null, 'old' => $req->all(), 'session' => $this->session,
            ]));
        }
        $this->jobs->create($this->auth->userId(), $this->fromRequest($req));
        return Response::redirect('/dashboard');
    }

    public function show(Request $req, array $vars): Response
    {
        $job = $this->mustFind((int) $vars['id']);
        if ($job === null) {
            return Response::html('Not Found', 404);
        }
        return Response::html($this->view->render('jobs/show', ['title' => $job['name'], 'job' => $job, 'session' => $this->session]));
    }

    public function edit(Request $req, array $vars): Response
    {
        $job = $this->mustFind((int) $vars['id']);
        if ($job === null) {
            return Response::html('Not Found', 404);
        }
        $old = [
            'name' => $job['name'], 'mode' => $job['mode'],
            'source_host' => $job['source_host'], 'source_port' => $job['source_port'], 'source_encryption' => $job['source_encryption'],
            'source_username' => $this->enc->decrypt($job['source_username_enc']),
            'dest_host' => $job['dest_host'], 'dest_port' => $job['dest_port'], 'dest_encryption' => $job['dest_encryption'],
            'dest_username' => $this->enc->decrypt($job['dest_username_enc']),
        ];
        return Response::html($this->view->render('jobs/form', [
            'title' => 'Edit Job', 'errors' => [], 'job' => $job, 'old' => $old, 'session' => $this->session,
        ]));
    }

    public function update(Request $req, array $vars): Response
    {
        $job = $this->mustFind((int) $vars['id']);
        if ($job === null) {
            return Response::html('Not Found', 404);
        }
        $errors = $this->validate($req, requirePasswords: false);
        if ($errors !== []) {
            return Response::html($this->view->render('jobs/form', [
                'title' => 'Edit Job', 'errors' => $errors, 'job' => $job, 'old' => $req->all(), 'session' => $this->session,
            ]));
        }
        // Blank password = keep existing encrypted value.
        $data = $this->fromRequest($req);
        if ((string) $req->input('source_password') === '') { $data['source_password_enc'] = $job['source_password_enc']; }
        if ((string) $req->input('dest_password') === '') { $data['dest_password_enc'] = $job['dest_password_enc']; }
        $this->jobs->update((int) $job['id'], $this->auth->userId(), $data);
        return Response::redirect('/jobs/' . $job['id']);
    }

    public function destroy(Request $req, array $vars): Response
    {
        $job = $this->mustFind((int) $vars['id']);
        if ($job === null) {
            return Response::html('Not Found', 404);
        }
        $this->jobs->delete((int) $job['id'], $this->auth->userId());
        return Response::redirect('/dashboard');
    }

    public function queue(Request $req, array $vars): Response
    {
        return $this->transitionOr404((int) $vars['id'], 'queued', '/jobs/' . (int) $vars['id']);
    }

    public function cancel(Request $req, array $vars): Response
    {
        return $this->transitionOr404((int) $vars['id'], 'canceled', '/jobs/' . (int) $vars['id']);
    }

    public function testConnection(Request $req): Response
    {
        $mapDelimiterAgnostic = new FolderMapper();
        $result = $this->tester->test(
            $this->account($req, 'source'),
            $this->account($req, 'dest'),
            $mapDelimiterAgnostic,
        );
        return Response::json($result);
    }

    // --- helpers ---

    private function mustFind(int $id): ?array
    {
        return $this->jobs->find($id, $this->auth->userId());
    }

    private function transitionOr404(int $id, string $state, string $redirect): Response
    {
        if ($this->mustFind($id) === null) {
            return Response::html('Not Found', 404);
        }
        $this->jobs->transition($id, $this->auth->userId(), $state);
        return Response::redirect($redirect);
    }

    private function account(Request $req, string $prefix): array
    {
        return [
            'host' => (string) $req->input("{$prefix}_host"),
            'port' => (int) $req->input("{$prefix}_port"),
            'encryption' => (string) $req->input("{$prefix}_encryption"),
            'username' => (string) $req->input("{$prefix}_username"),
            'password' => (string) $req->input("{$prefix}_password"),
        ];
    }

    private function validate(Request $req, bool $requirePasswords = true): array
    {
        $rules = [
            'name' => 'required',
            'source_host' => 'required|host', 'source_port' => 'required|int', 'source_encryption' => 'in:ssl,tls,none',
            'source_username' => 'required',
            'dest_host' => 'required|host', 'dest_port' => 'required|int', 'dest_encryption' => 'in:ssl,tls,none',
            'dest_username' => 'required',
            'mode' => 'in:live,dry_run',
        ];
        if ($requirePasswords) {
            $rules['source_password'] = 'required';
            $rules['dest_password'] = 'required';
        }
        return Validator::make($req->all(), $rules)->errors();
    }

    private function fromRequest(Request $req): array
    {
        $options = json_encode([
            'batch_size' => (int) ($req->input('batch_size') ?: 200),
            'throttle_ms' => (int) ($req->input('throttle_ms') ?: 300),
            'since' => $req->input('since') ?: null,
            'limit' => $req->input('limit') !== null && $req->input('limit') !== '' ? (int) $req->input('limit') : null,
        ], JSON_UNESCAPED_SLASHES);

        return [
            'name' => (string) $req->input('name'),
            'mode' => in_array($req->input('mode'), ['live', 'dry_run'], true) ? (string) $req->input('mode') : 'live',
            'source_host' => (string) $req->input('source_host'),
            'source_port' => (int) $req->input('source_port'),
            'source_encryption' => (string) $req->input('source_encryption'),
            'source_username_enc' => $this->enc->encrypt((string) $req->input('source_username')),
            'source_password_enc' => $this->enc->encrypt((string) $req->input('source_password')),
            'dest_host' => (string) $req->input('dest_host'),
            'dest_port' => (int) $req->input('dest_port'),
            'dest_encryption' => (string) $req->input('dest_encryption'),
            'dest_username_enc' => $this->enc->encrypt((string) $req->input('dest_username')),
            'dest_password_enc' => $this->enc->encrypt((string) $req->input('dest_password')),
            'options' => $options,
        ];
    }
}
