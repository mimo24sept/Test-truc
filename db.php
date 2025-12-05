<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $path = __DIR__ . '/data.sqlite';
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            credits INTEGER NOT NULL DEFAULT 10000,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )'
    );

    return $pdo;
}

function getUser(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, username, credits, password_hash FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function getLeaders(PDO $pdo, int $limit = 10): array
{
    $stmt = $pdo->prepare('SELECT username, credits FROM users ORDER BY credits DESC, id ASC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
