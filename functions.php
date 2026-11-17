<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url = 'index.php'): never
{
    header('Location: ' . $url);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        exit('Phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.');
    }
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('index.php?page=login');
    }

    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('Bạn không có quyền thực hiện thao tác này.');
    }

    return $user;
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $message;
}

function day_names(): array
{
    return [
        1 => 'Thứ 2',
        2 => 'Thứ 3',
        3 => 'Thứ 4',
        4 => 'Thứ 5',
        5 => 'Thứ 6',
        6 => 'Thứ 7',
        7 => 'Chủ nhật',
    ];
}

function parse_periods(string $input): ?array
{
    $periods = [];
    foreach (preg_split('/[,;]+/', trim($input)) ?: [] as $part) {
        $part = trim($part);
        if (preg_match('/^(\d{1,2})(?:\s*-\s*(\d{1,2}))?$/', $part, $matches) !== 1) {
            return null;
        }

        $start = (int) $matches[1];
        $end = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : $start;
        if ($start < 1 || $end > 12 || $start > $end) {
            return null;
        }
        for ($period = $start; $period <= $end; $period++) {
            if (isset($periods[$period])) {
                return null;
            }
            $periods[$period] = true;
        }
    }

    if ($periods === []) {
        return null;
    }

    $numbers = array_keys($periods);
    sort($numbers);
    return $numbers;
}

function periods_overlap(array $first, array $second): bool
{
    return count(array_intersect($first, $second)) > 0;
}

function period_distance(array $first, array $second): int
{
    $distances = [];
    foreach ($first as $firstPeriod) {
        foreach ($second as $secondPeriod) {
            $distances[] = abs($firstPeriod - $secondPeriod);
        }
    }

    return $distances === [] ? PHP_INT_MAX : min($distances);
}

function schedule_rows(PDO $pdo, array $filters = []): array
{
    $sql = 'SELECT s.*, t.full_name, t.expertise, b.branch_name
            FROM schedules s
            JOIN teachers t ON t.id = s.teacher_id
            JOIN branches b ON b.id = s.branch_id';
    $where = [];
    $params = [];

    if (!empty($filters['branch_id'])) {
        $where[] = 's.branch_id = :branch_id';
        $params['branch_id'] = (int) $filters['branch_id'];
    }
    if (!empty($filters['teacher_id'])) {
        $where[] = 's.teacher_id = :teacher_id';
        $params['teacher_id'] = (int) $filters['teacher_id'];
    }
    if (!empty($filters['day_of_week'])) {
        $where[] = 's.day_of_week = :day_of_week';
        $params['day_of_week'] = (int) $filters['day_of_week'];
    }
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY s.day_of_week, s.shift_or_periods, b.branch_name, t.full_name';

    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}
