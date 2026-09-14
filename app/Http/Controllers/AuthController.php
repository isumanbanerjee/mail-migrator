<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Auth;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\Validator;
use App\Support\View;

final class AuthController
{
    public function __construct(private Auth $auth, private View $view, private Session $session) {}

    public function showRegister(Request $req): Response
    {
        return Response::html($this->view->render('auth/register', ['title' => 'Register', 'errors' => [], 'old' => [], 'session' => $this->session]));
    }

    public function register(Request $req): Response
    {
        $v = Validator::make([
            'name' => $req->input('name'), 'email' => $req->input('email'), 'password' => $req->input('password'),
        ], ['name' => 'required', 'email' => 'required|email', 'password' => 'required|minlen:8']);

        if (!$v->passes()) {
            return Response::html($this->view->render('auth/register', [
                'title' => 'Register', 'errors' => $v->errors(), 'old' => ['name' => $req->input('name'), 'email' => $req->input('email')], 'session' => $this->session,
            ]));
        }
        if ($this->auth->userExists((string) $req->input('email'))) {
            return Response::html($this->view->render('auth/register', [
                'title' => 'Register', 'errors' => ['email' => ['taken']], 'old' => ['name' => $req->input('name'), 'email' => $req->input('email')], 'session' => $this->session,
            ]));
        }
        $this->auth->register((string) $req->input('name'), (string) $req->input('email'), (string) $req->input('password'));
        return Response::redirect('/dashboard');
    }

    public function showLogin(Request $req): Response
    {
        return Response::html($this->view->render('auth/login', ['title' => 'Login', 'error' => null, 'old' => [], 'session' => $this->session]));
    }

    public function login(Request $req): Response
    {
        $email = (string) $req->input('email');
        $key = strtolower($email) . '|' . (string) $req->server('REMOTE_ADDR', 'cli');
        if ($this->auth->attempt($email, (string) $req->input('password'), $key)) {
            return Response::redirect('/dashboard');
        }
        $msg = $this->auth->lockedOut($key) ? 'Too many attempts. Try again later.' : 'Invalid credentials.';
        return Response::html($this->view->render('auth/login', ['title' => 'Login', 'error' => $msg, 'old' => ['email' => $email], 'session' => $this->session]));
    }

    public function logout(Request $req): Response
    {
        $this->auth->logout();
        return Response::redirect('/login');
    }
}
