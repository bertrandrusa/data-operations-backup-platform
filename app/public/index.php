<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use DataOps\Security;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$render = static function (string $template, array $data = []) use ($auth): void {
    extract($data, EXTR_SKIP);
    $currentUser = $auth->user();
    ob_start();
    require dirname(__DIR__) . '/templates/' . $template . '.php';
    $content = (string) ob_get_clean();
    require dirname(__DIR__) . '/templates/layout.php';
};

$redirect = static function (string $location): never {
    header('Location: ' . $location, true, 303);
    exit;
};

if ($path === '/health' && $method === 'GET') {
    try {
        $db->query('SELECT 1');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok', 'database' => 'connected'], JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        http_response_code(503);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'degraded', 'database' => 'unavailable'], JSON_THROW_ON_ERROR);
    }
    exit;
}

if ($path === '/login' && $method === 'GET') {
    if ($auth->user() !== null) {
        $redirect('/');
    }
    $render('login', ['error' => $_SESSION['login_error'] ?? null]);
    unset($_SESSION['login_error']);
    exit;
}

if ($path === '/login' && $method === 'POST') {
    if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
        http_response_code(419);
        $render('error', ['title' => 'Session expired', 'message' => 'Refresh the page and try again.']);
        exit;
    }

    if ($auth->attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
        $redirect('/');
    }
    $_SESSION['login_error'] = 'The credentials were not accepted. Wait 15 minutes after five failed attempts.';
    $redirect('/login');
}

if ($path === '/logout' && $method === 'POST') {
    if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
    $auth->logout();
    $redirect('/login');
}

$currentUser = $auth->user();
if ($currentUser === null) {
    $redirect('/login');
}

if ($path === '/' && $method === 'GET') {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    $render('dashboard', array_merge($backupService->dashboard(), ['flash' => $flash]));
    exit;
}

if (preg_match('#^/jobs/([0-9a-f-]+)/run$#i', $path, $matches) === 1 && $method === 'POST') {
    if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
        http_response_code(419);
        $render('error', ['title' => 'Session expired', 'message' => 'Refresh the page and try again.']);
        exit;
    }

    try {
        $runId = $backupService->queue($matches[1], (string) $currentUser['id']);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Backup queued. Run ' . substr($runId, 0, 8) . ' will start shortly.'];
    } catch (Throwable $error) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => $error->getMessage()];
    }
    $redirect('/');
}

if (preg_match('#^/jobs/([0-9a-f-]+)/toggle$#i', $path, $matches) === 1 && $method === 'POST') {
    if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }

    try {
        $enabled = ($_POST['enabled'] ?? '') === '1';
        $backupService->setEnabled($matches[1], $enabled, (string) $currentUser['id']);
        $_SESSION['flash'] = ['type' => 'success', 'message' => $enabled ? 'Schedule enabled.' : 'Schedule paused.'];
    } catch (Throwable $error) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => $error->getMessage()];
    }
    $redirect('/');
}

http_response_code(404);
$render('error', ['title' => 'Page not found', 'message' => 'The requested route does not exist.']);

