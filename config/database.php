<?php

declare(strict_types=1);

const APP_DB_PATH = __DIR__ . '/../data/app.db';
const APP_DEFAULT_TIMEZONE = 'UTC';

date_default_timezone_set(APP_DEFAULT_TIMEZONE);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dataDir = dirname(APP_DB_PATH);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0750, true);
    }

    $pdo = new PDO('sqlite:' . APP_DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    return $pdo;
}

function init_schema(): void
{
    $pdo = db();

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'merchant',
    created_by INTEGER,
    created_at TEXT NOT NULL,
    updated_at TEXT
)
SQL);
    migrate_admin_schema($pdo);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS cards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    km TEXT NOT NULL UNIQUE,
    group_name TEXT NOT NULL DEFAULT 'default',
    status TEXT NOT NULL DEFAULT 'unused' CHECK (status IN ('unused', 'bound', 'banned')),
    bound_sn TEXT,
    bound_at TEXT,
    created_at TEXT NOT NULL
)
SQL);

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_cards_status ON cards(status)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_cards_bound_sn ON cards(bound_sn)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_cards_group_name ON cards(group_name)');
}

function migrate_admin_schema(PDO $pdo): void
{
    $columns = [];
    foreach ($pdo->query('PRAGMA table_info(admins)') as $column) {
        $columns[(string) $column['name']] = true;
    }

    if (!isset($columns['role'])) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN role TEXT NOT NULL DEFAULT 'merchant'");
    }
    if (!isset($columns['created_by'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN created_by INTEGER');
    }
    if (!isset($columns['updated_at'])) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN updated_at TEXT');
    }

    $superCount = (int) $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'super'")->fetchColumn();
    if ($superCount === 0) {
        $firstAdminId = $pdo->query('SELECT id FROM admins ORDER BY id ASC LIMIT 1')->fetchColumn();
        if ($firstAdminId !== false) {
            $stmt = $pdo->prepare("UPDATE admins SET role = 'super', updated_at = :updated_at WHERE id = :id");
            $stmt->execute([':updated_at' => gmdate('c'), ':id' => $firstAdminId]);
        }
    }
}

function seed_admin(string $username, string $password): bool
{
    init_schema();

    $pdo = db();
    $exists = $pdo->prepare('SELECT id FROM admins WHERE username = :username LIMIT 1');
    $exists->execute([':username' => $username]);
    if ($exists->fetch()) {
        return false;
    }

    $now = gmdate('c');
    $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash, role, created_at, updated_at) VALUES (:username, :password_hash, :role, :created_at, :updated_at)');
    return $stmt->execute([
        ':username' => $username,
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ':role' => 'super',
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
}

function app_input(string $key): ?string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? null;
    if ($value === null) {
        return null;
    }

    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function find_card_by_km(string $km): ?array
{
    $stmt = db()->prepare('SELECT * FROM cards WHERE km = :km LIMIT 1');
    $stmt->execute([':km' => $km]);
    $card = $stmt->fetch();

    return $card ?: null;
}

function find_card_by_sn(string $sn): ?array
{
    $stmt = db()->prepare('SELECT * FROM cards WHERE bound_sn = :sn ORDER BY bound_at DESC, id DESC LIMIT 1');
    $stmt->execute([':sn' => $sn]);
    $card = $stmt->fetch();

    return $card ?: null;
}

function bind_card(string $km, string $sn): array
{
    init_schema();

    if (!preg_match('/^\d{9}$/', $km)) {
        return ['status' => 1, 'msg' => 'invalid_or_banned'];
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT * FROM cards WHERE km = :km LIMIT 1');
        $stmt->execute([':km' => $km]);
        $card = $stmt->fetch();

        if (!$card || $card['status'] === 'banned') {
            $pdo->rollBack();
            return ['status' => 1, 'msg' => 'invalid_or_banned'];
        }

        if ($card['status'] === 'bound') {
            $pdo->rollBack();
            if (hash_equals((string) $card['bound_sn'], $sn)) {
                return ['status' => 0, 'msg' => 'verified'];
            }

            return ['status' => 1, 'msg' => 'invalid_or_banned'];
        }

        $update = $pdo->prepare('UPDATE cards SET status = :status, bound_sn = :bound_sn, bound_at = :bound_at WHERE id = :id AND status = :unused_status');
        $update->execute([
            ':status' => 'bound',
            ':bound_sn' => $sn,
            ':bound_at' => gmdate('c'),
            ':id' => $card['id'],
            ':unused_status' => 'unused',
        ]);

        $pdo->commit();
        return ['status' => 0, 'msg' => 'verified'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['status' => 1, 'msg' => 'invalid_or_banned'];
    }
}

function check_card_status(?string $km, ?string $sn): array
{
    init_schema();

    if ($km === null && $sn === null) {
        return ['status' => 1, 'msg' => 'invalid_or_banned'];
    }

    if ($km !== null && !preg_match('/^\d{9}$/', $km)) {
        return ['status' => 1, 'msg' => 'invalid_or_banned'];
    }

    if ($km !== null && $sn !== null) {
        $stmt = db()->prepare('SELECT * FROM cards WHERE km = :km AND bound_sn = :sn LIMIT 1');
        $stmt->execute([':km' => $km, ':sn' => $sn]);
        $card = $stmt->fetch();
    } elseif ($km !== null) {
        $card = find_card_by_km($km);
    } else {
        $card = find_card_by_sn((string) $sn);
    }

    if (!$card || $card['status'] !== 'bound' || empty($card['bound_sn'])) {
        return ['status' => 1, 'msg' => 'invalid_or_banned'];
    }

    return ['status' => 0, 'msg' => 'verified'];
}

function json_response(array $payload): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
