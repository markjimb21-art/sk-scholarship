<?php
declare(strict_types=1);

final class AuthController
{
    public function login(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            $result = Auth::login($email, $password);
            if ($result['ok']) {
                $role = $result['user']['role_name'];
                redirect($role === 'applicant' ? 'applicant/dashboard' : 'admin/dashboard');
            }
            flash('danger', $result['error']);
            $_SESSION['_old'] = ['email' => $email];
            redirect('login');
        }
        require VIEW_PATH . '/auth/login.php';
        unset($_SESSION['_old']);
    }

    public function register(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();

            $v = new Validator($_POST);
            $v->required('full_name')->required('email')->email('email')
              ->required('password')->min('password', PASSWORD_MIN_LENGTH)
              ->required('confirm_password')->matches('password', 'confirm_password', 'Password confirmation');

            if ($v->fails()) {
                flash('danger', $v->firstError());
                $_SESSION['_old'] = $_POST;
                redirect('register');
            }

            $result = Auth::register(
                trim($_POST['full_name']),
                strtolower(trim($_POST['email'])),
                $_POST['password']
            );

            if (!$result['ok']) {
                flash('danger', $result['error']);
                $_SESSION['_old'] = $_POST;
                redirect('register');
            }

            flash('success', 'Registration successful. Please log in.');
            redirect('login');
        }
        require VIEW_PATH . '/auth/register.php';
        unset($_SESSION['_old']);
    }
}