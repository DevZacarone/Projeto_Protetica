<?php
// auth.php — controle de sessão e autenticação
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Faz login: guarda dados mínimos do usuário na sessão.
 */
function login(string $username, string $password): bool {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $st->execute([$username]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if ($u && password_verify($password, $u['senha_hash'])) {
        $_SESSION['user'] = [
            'id'       => (int)$u['id'],
            'username' => $u['username'],
            'nome'     => $u['nome']
        ];
        return true;
    }
    return false;
}

/**
 * Exige login para continuar; se não tiver, redireciona para login.php
 */
function require_login(): void {
    global $BASE_URL;
    if (empty($_SESSION['user'])) {
        $return = urlencode($_SERVER['REQUEST_URI'] ?? ($BASE_URL . '/'));
        header('Location: ' . $BASE_URL . '/login.php?return=' . $return);
        exit;
    }
}

/** Usuário atual (ou null) */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/** Faz logout limpando a sessão */
function logout(): void {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
