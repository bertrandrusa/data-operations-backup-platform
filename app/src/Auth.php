<?php

declare(strict_types=1);

namespace DataOps;

use PDO;

final class Auth
{
    public function __construct(
        private readonly PDO $db,
        private readonly Audit $audit
    ) {
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!is_string($userId) || !Security::validUuid($userId)) {
            return null;
        }

        $statement = $this->db->prepare(
            'SELECT id, email, role FROM users WHERE id = :id AND active = true'
        );
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    public function attempt(string $email, string $password): bool
    {
        $email = strtolower(trim($email));
        $ip = Security::clientIp();
        if ($this->isRateLimited($email, $ip)) {
            return false;
        }

        $statement = $this->db->prepare(
            'SELECT id, email, password_hash, role FROM users WHERE email = :email AND active = true'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        $succeeded = is_array($user) && password_verify($password, (string) $user['password_hash']);

        $attempt = $this->db->prepare(
            'INSERT INTO login_attempts (email, ip_address, succeeded)
             VALUES (:email, CAST(:ip AS inet), :succeeded)'
        );
        $attempt->execute(['email' => $email, 'ip' => $ip, 'succeeded' => $succeeded]);

        if (!$succeeded) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (string) $user['id'];
        $this->db->prepare('UPDATE users SET last_login_at = now() WHERE id = :id')
            ->execute(['id' => $user['id']]);
        $this->audit->record((string) $user['id'], 'auth.login', 'session');

        return true;
    }

    public function logout(): void
    {
        $user = $this->user();
        if ($user !== null) {
            $this->audit->record((string) $user['id'], 'auth.logout', 'session');
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    private function isRateLimited(string $email, string $ip): bool
    {
        $statement = $this->db->prepare(
            "SELECT count(*) FROM login_attempts
             WHERE email = :email AND ip_address = CAST(:ip AS inet)
               AND succeeded = false AND attempted_at > now() - interval '15 minutes'"
        );
        $statement->execute(['email' => $email, 'ip' => $ip]);

        return (int) $statement->fetchColumn() >= 5;
    }
}

