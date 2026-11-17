<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$page = (string) ($_GET['page'] ?? 'dashboard');
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'login') {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $statement = db()->prepare(
            'SELECT u.id, u.email, u.password_hash, u.role, u.teacher_id, t.full_name
             FROM users u LEFT JOIN teachers t ON t.id = u.teacher_id WHERE u.email = ?'
        );
        $statement->execute([$email]);
        $account = $statement->fetch();
        if ($account && password_verify($password, $account['password_hash'])) {
            session_regenerate_id(true);
            unset($account['password_hash']);
            $_SESSION['user'] = $account;
            redirect('index.php');
        }
        flash('Email hoặc mật khẩu không chính xác.', 'error');
        redirect('index.php?page=login');
    }

    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        redirect('index.php?page=login');
    }

    $user = require_admin();
    if ($action === 'save_schedule') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
        $teacherId = filter_input(INPUT_POST, 'teacher_id', FILTER_VALIDATE_INT);
        $branchId = filter_input(INPUT_POST, 'branch_id', FILTER_VALIDATE_INT);
        $subject = trim((string) ($_POST['subject_name'] ?? ''));
        $room = trim((string) ($_POST['room'] ?? ''));
        $day = filter_input(INPUT_POST, 'day_of_week', FILTER_VALIDATE_INT);
        $periodText = trim((string) ($_POST['shift_or_periods'] ?? ''));
        $periods = parse_periods($periodText);

        if (!$teacherId || !$branchId || !$day || $day < 1 || $day > 7 || $subject === '' || $room === '' || $periods === null) {
            flash('Vui lòng nhập đầy đủ thông tin. Tiết học hợp lệ từ 1 đến 12, ví dụ 1-3 hoặc 1-2,4.', 'error');
            redirect('index.php?page=schedules');
        }

        $pdo = db();
        $teacherCheck = $pdo->prepare('SELECT id FROM teachers WHERE id = ?');
        $teacherCheck->execute([$teacherId]);
        $branchCheck = $pdo->prepare('SELECT id FROM branches WHERE id = ?');
        $branchCheck->execute([$branchId]);
        if (!$teacherCheck->fetchColumn() || !$branchCheck->fetchColumn()) {
            flash('Giảng viên hoặc cơ sở được chọn không tồn tại.', 'error');
            redirect('index.php?page=schedules');
        }

        $conflicts = $pdo->prepare(
            'SELECT id, teacher_id, branch_id, room, shift_or_periods
             FROM schedules WHERE day_of_week = ? AND id <> ? AND (teacher_id = ? OR (branch_id = ? AND room = ?))'
        );
        $conflicts->execute([$day, $id, $teacherId, $branchId, $room]);
        foreach ($conflicts->fetchAll() as $existing) {
            $existingPeriods = parse_periods($existing['shift_or_periods']) ?? [];
            if (periods_overlap($periods, $existingPeriods)) {
                $reason = (int) $existing['teacher_id'] === $teacherId
                    ? 'Giảng viên đã có lịch trong khung tiết này.'
                    : 'Phòng học đã được sử dụng trong khung tiết này.';
                flash($reason, 'error');
                redirect('index.php?page=schedules');
            }
            if (
                (int) $existing['teacher_id'] === $teacherId
                && (int) $existing['branch_id'] !== $branchId
                && period_distance($periods, $existingPeriods) <= 1
            ) {
                flash('Cần có ít nhất một tiết trống để giảng viên di chuyển giữa hai cơ sở.', 'error');
                redirect('index.php?page=schedules');
            }
        }

        if ($id > 0) {
            $statement = $pdo->prepare(
                'UPDATE schedules SET teacher_id = ?, branch_id = ?, subject_name = ?, room = ?,
                 day_of_week = ?, shift_or_periods = ? WHERE id = ?'
            );
            $statement->execute([$teacherId, $branchId, $subject, $room, $day, $periodText, $id]);
            flash('Đã cập nhật lịch giảng dạy.');
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO schedules (teacher_id, branch_id, subject_name, room, day_of_week, shift_or_periods)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([$teacherId, $branchId, $subject, $room, $day, $periodText]);
            flash('Đã thêm lịch giảng dạy.');
        }
        redirect('index.php?page=schedules');
    }

    if ($action === 'delete_schedule') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            $statement = db()->prepare('DELETE FROM schedules WHERE id = ?');
            $statement->execute([$id]);
            flash('Đã xóa lịch giảng dạy.');
        }
        redirect('index.php?page=schedules');
    }

    http_response_code(400);
    exit('Thao tác không hợp lệ.');
}

if ($page === 'login') {
    if ($user !== null) {
        redirect('index.php');
    }
    $flash = take_flash();
    ?><!doctype html>
    <html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập · HUIT Schedule Hub</title><link rel="stylesheet" href="assets/style.css"></head>
    <body class="login-page"><main class="login-card">
      <a class="brand" href="index.php?page=login"><span class="brand-mark">H</span><span>HUIT <strong>Schedule Hub</strong></span></a>
      <p class="eyebrow">CỔNG THÔNG TIN ĐÀO TẠO</p><h1>Chào mừng trở lại</h1>
      <p class="muted">Đăng nhập để xem và quản lý lịch giảng dạy.</p>
      <?php if ($flash): ?><div class="notice <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
      <form method="post" class="stack-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="login">
        <label>Email trường<input required type="email" name="email" autocomplete="username" placeholder="name@huit.edu.vn"></label>
        <label>Mật khẩu<input required type="password" name="password" autocomplete="current-password" placeholder="Nhập mật khẩu"></label>
        <button class="button primary wide" type="submit">Đăng nhập <span aria-hidden="true">→</span></button>
      </form>
      <p class="login-hint">Dùng dữ liệu mẫu: <code>admin@huit.edu.vn</code> / <code>password</code></p>
    </main></body></html><?php
    exit;
}

if ($user === null) {
    redirect('index.php?page=login');
}

if ($page === 'suggestions') {
    require_admin();
    header('Content-Type: application/json; charset=utf-8');
    $subject = trim((string) ($_GET['subject'] ?? ''));
    $branchId = filter_input(INPUT_GET, 'branch_id', FILTER_VALIDATE_INT) ?: 0;
    $day = filter_input(INPUT_GET, 'day', FILTER_VALIDATE_INT) ?: 0;
    $periods = parse_periods((string) ($_GET['periods'] ?? ''));
    if ($subject === '' || $branchId < 1 || $day < 1 || $day > 7 || $periods === null) {
        http_response_code(422);
        echo json_encode(['error' => 'Nhập môn học, cơ sở, thứ và tiết học hợp lệ để xem gợi ý.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $teachers = db()->query('SELECT id, full_name, expertise FROM teachers ORDER BY full_name')->fetchAll();
    $daySchedules = db()->prepare(
        'SELECT teacher_id, branch_id, shift_or_periods FROM schedules WHERE day_of_week = ?'
    );
    $daySchedules->execute([$day]);
    $existingByTeacher = [];
    foreach ($daySchedules->fetchAll() as $schedule) {
        $existingByTeacher[(int) $schedule['teacher_id']][] = [
            'branch_id' => (int) $schedule['branch_id'],
            'periods' => parse_periods($schedule['shift_or_periods']) ?? [],
        ];
    }

    $suggestions = [];
    foreach ($teachers as $teacher) {
        $conflict = false;
        $sameBranchNeighbor = false;
        $gapScore = 0;
        foreach ($existingByTeacher[(int) $teacher['id']] ?? [] as $existing) {
            if (periods_overlap($periods, $existing['periods'])) {
                $conflict = true;
                break;
            }
            $distance = period_distance($periods, $existing['periods']);
            if ($existing['branch_id'] !== $branchId && $distance <= 1) {
                $conflict = true;
                break;
            }
            if ($existing['branch_id'] === $branchId && $distance <= 1) {
                $sameBranchNeighbor = true;
            } elseif ($distance <= 2) {
                $gapScore++;
            }
        }
        if ($conflict) {
            continue;
        }
        $matches = stripos($teacher['expertise'], $subject) !== false;
        $words = preg_split('/[\s,;]+/u', $subject, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (!$matches) {
            foreach ($words as $word) {
                if (mb_strlen($word) >= 4 && stripos($teacher['expertise'], $word) !== false) {
                    $matches = true;
                    break;
                }
            }
        }
        $suggestions[] = [
            'id' => (int) $teacher['id'],
            'name' => $teacher['full_name'],
            'expertise' => $teacher['expertise'],
            'match' => $matches,
            'same_branch' => $sameBranchNeighbor,
            'gap_score' => $gapScore,
        ];
    }
    usort($suggestions, static fn (array $a, array $b): int =>
        [$b['match'], $b['same_branch'], $a['gap_score'], $a['name']]
        <=> [$a['match'], $a['same_branch'], $b['gap_score'], $b['name']]
    );
    echo json_encode(['suggestions' => $suggestions], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = db();
$isAdmin = $user['role'] === 'admin';
if (!$isAdmin && $page !== 'calendar') {
    $page = 'calendar';
}
$page = in_array($page, ['dashboard', 'schedules', 'calendar'], true) ? $page : 'dashboard';
$flash = take_flash();
$branches = $pdo->query('SELECT id, branch_name, address FROM branches ORDER BY branch_name')->fetchAll();
$teachers = $pdo->query('SELECT id, full_name, email, expertise FROM teachers ORDER BY full_name')->fetchAll();
$filters = [
    'branch_id' => filter_input(INPUT_GET, 'branch_id', FILTER_VALIDATE_INT) ?: null,
    'teacher_id' => filter_input(INPUT_GET, 'teacher_id', FILTER_VALIDATE_INT) ?: null,
];
if (!$isAdmin) {
    $filters['teacher_id'] = (int) $user['teacher_id'];
}
$rows = schedule_rows($pdo, $filters);
$days = day_names();
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;
$editing = null;
if ($editId && $isAdmin) {
    $editStatement = $pdo->prepare('SELECT * FROM schedules WHERE id = ?');
    $editStatement->execute([$editId]);
    $editing = $editStatement->fetch() ?: null;
}
$month = (string) ($_GET['month'] ?? date('Y-m'));
if (
    preg_match('/^\d{4}-\d{2}$/', $month) !== 1
    || !checkdate((int) substr($month, 5, 2), 1, (int) substr($month, 0, 4))
) {
    $month = date('Y-m');
}
$monthStart = new DateTimeImmutable($month . '-01');
$monthEnd = $monthStart->modify('last day of this month');
$monthOccurrences = [];
if (!$isAdmin || $page === 'calendar') {
    foreach ($rows as $row) {
        for ($date = $monthStart; $date <= $monthEnd; $date = $date->modify('+1 day')) {
            if ((int) $date->format('N') === ((int) $row['day_of_week'])) {
                $monthOccurrences[] = ['date' => $date, 'row' => $row];
            }
        }
    }
}
?><!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $page === 'schedules' ? 'Lịch giảng dạy' : ($page === 'calendar' ? 'Lịch cá nhân' : 'Tổng quan') ?> · HUIT Schedule Hub</title>
<link rel="stylesheet" href="assets/style.css"><script src="assets/app.js" defer></script></head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <a class="brand" href="index.php"><span class="brand-mark">H</span><span>HUIT <strong>Schedule Hub</strong></span></a>
    <p class="nav-caption">KHÔNG GIAN LÀM VIỆC</p>
    <nav class="nav-links">
      <?php if ($isAdmin): ?>
      <a class="<?= $page === 'dashboard' ? 'active' : '' ?>" href="index.php"><span>▦</span>Tổng quan</a>
      <a class="<?= $page === 'schedules' ? 'active' : '' ?>" href="index.php?page=schedules"><span>▤</span>Quản lý lịch</a>
      <?php endif; ?>
      <a class="<?= $page === 'calendar' ? 'active' : '' ?>" href="index.php?page=calendar"><span>▣</span><?= $isAdmin ? 'Lịch giảng dạy' : 'Lịch của tôi' ?></a>
    </nav>
    <div class="sidebar-bottom">
      <div class="user-chip"><span class="avatar"><?= e(mb_strtoupper(mb_substr($user['full_name'] ?? 'AD', 0, 1))) ?></span>
        <span><strong><?= e($user['full_name'] ?? 'Quản trị viên') ?></strong><small><?= $isAdmin ? 'Quản trị đào tạo' : 'Giảng viên' ?></small></span></div>
      <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button class="logout-button" type="submit">↗ &nbsp;Đăng xuất</button></form>
    </div>
  </aside>
  <main class="main-content">
    <header class="topbar"><div><span class="eyebrow">HỆ THỐNG QUẢN LÝ ĐÀO TẠO</span><div class="breadcrumb">HUIT <span>/</span> <?= $page === 'schedules' ? 'Quản lý lịch' : ($page === 'calendar' ? 'Lịch giảng dạy' : 'Tổng quan') ?></div></div><div class="topbar-date"><?= e(date('d/m/Y')) ?></div></header>
    <?php if ($flash): ?><div class="notice <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>

    <?php if ($page === 'dashboard' && $isAdmin):
      $scheduleCount = count(schedule_rows($pdo));
      $teacherCount = count($teachers);
      $branchCount = count($branches);
      $todayRows = schedule_rows($pdo, ['day_of_week' => (int) date('N')]);
      $branchCounts = [];
      foreach ($branches as $branch) { $branchCounts[(int) $branch['id']] = ['name' => $branch['branch_name'], 'count' => 0]; }
      foreach (schedule_rows($pdo) as $row) { $branchCounts[(int) $row['branch_id']]['count']++; }
      $maxBranchCount = max(1, ...array_column($branchCounts, 'count'));
    ?>
      <section class="page-heading"><div><p class="eyebrow">THỨ <?= e(date('N')) ?>, <?= e(date('d/m/Y')) ?></p><h1>Chào buổi sáng 👋</h1><p class="muted">Đây là tình hình phân bổ lịch giảng dạy toàn hệ thống.</p></div><a class="button primary" href="index.php?page=schedules#schedule-form">＋ &nbsp;Thêm lịch mới</a></section>
      <section class="stat-grid">
        <article class="stat-card"><span class="stat-icon blue">▤</span><span class="stat-label">Tổng lịch giảng dạy</span><strong><?= $scheduleCount ?></strong><small>Lịch định kỳ trong tuần</small></article>
        <article class="stat-card"><span class="stat-icon violet">♙</span><span class="stat-label">Giảng viên</span><strong><?= $teacherCount ?></strong><small>Đang có trong hệ thống</small></article>
        <article class="stat-card"><span class="stat-icon green">⌖</span><span class="stat-label">Cơ sở đào tạo</span><strong><?= $branchCount ?></strong><small>Cơ sở và trung tâm liên kết</small></article>
        <article class="stat-card"><span class="stat-icon orange">◷</span><span class="stat-label">Lịch hôm nay</span><strong><?= count($todayRows) ?></strong><small><?= e($days[(int) date('N')]) ?> trên toàn hệ thống</small></article>
      </section>
      <section class="dashboard-grid">
        <article class="panel"><div class="panel-heading"><div><h2>Phân bổ theo cơ sở</h2><p class="muted">Số lịch giảng dạy trong tuần</p></div><span class="subtle-tag">TUẦN NÀY</span></div>
          <div class="branch-chart"><?php foreach ($branchCounts as $item): ?><div class="chart-row"><div class="chart-label"><span><?= e($item['name']) ?></span><strong><?= $item['count'] ?></strong></div><div class="bar-track"><span style="width:<?= (int) round($item['count'] / $maxBranchCount * 100) ?>%"></span></div></div><?php endforeach; ?></div>
          <a class="text-link" href="index.php?page=calendar">Xem toàn bộ lịch <span>→</span></a>
        </article>
        <article class="panel"><div class="panel-heading"><div><h2>Lịch hôm nay</h2><p class="muted"><?= e($days[(int) date('N')]) ?> · <?= e(date('d/m')) ?></p></div><span class="subtle-tag"><?= count($todayRows) ?> LỚP</span></div>
          <?php if ($todayRows): ?><div class="today-list"><?php foreach (array_slice($todayRows, 0, 5) as $row): ?><div class="today-item"><span class="time-dot"></span><div><strong><?= e($row['subject_name']) ?></strong><small><?= e($row['shift_or_periods']) ?> · <?= e($row['room']) ?> · <?= e($row['branch_name']) ?></small></div><span class="teacher-mini"><?= e($row['full_name']) ?></span></div><?php endforeach; ?></div><?php else: ?><div class="empty-state compact">Chưa có lịch giảng dạy hôm nay.</div><?php endif; ?>
          <a class="text-link" href="index.php?page=calendar">Mở lịch tuần <span>→</span></a>
        </article>
      </section>
      <section class="panel filter-panel"><div class="panel-heading"><div><h2>Tra cứu nhanh</h2><p class="muted">Lọc lịch theo cơ sở hoặc giảng viên.</p></div></div>
        <form class="filter-form" method="get"><input type="hidden" name="page" value="calendar"><label>Cơ sở<select name="branch_id"><option value="">Tất cả cơ sở</option><?php foreach ($branches as $branch): ?><option value="<?= (int) $branch['id'] ?>"><?= e($branch['branch_name']) ?></option><?php endforeach; ?></select></label><label>Giảng viên<select name="teacher_id"><option value="">Tất cả giảng viên</option><?php foreach ($teachers as $teacher): ?><option value="<?= (int) $teacher['id'] ?>"><?= e($teacher['full_name']) ?></option><?php endforeach; ?></select></label><button class="button secondary" type="submit">Lọc lịch</button></form>
      </section>
    <?php elseif ($page === 'schedules' && $isAdmin): ?>
      <section class="page-heading"><div><p class="eyebrow">PHÂN CÔNG VÀ ĐIỀU PHỐI</p><h1>Quản lý lịch giảng dạy</h1><p class="muted">Tạo lịch không trùng tiết, phòng học và tối ưu lịch di chuyển.</p></div></section>
      <section class="panel form-panel" id="schedule-form"><div class="panel-heading"><div><h2><?= $editing ? 'Cập nhật lịch' : 'Thêm lịch mới' ?></h2><p class="muted">Giờ học được nhập theo tiết, từ 1 đến 12.</p></div><?php if ($editing): ?><a class="button secondary small-button" href="index.php?page=schedules">Hủy sửa</a><?php endif; ?></div>
        <form method="post" class="schedule-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_schedule"><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
          <label>Tên học phần<input required name="subject_name" id="subject_name" value="<?= e($editing['subject_name'] ?? '') ?>" placeholder="Ví dụ: Lập trình Web"></label>
          <label>Cơ sở<select required name="branch_id" id="branch_id"><option value="">Chọn cơ sở</option><?php foreach ($branches as $branch): ?><option value="<?= (int) $branch['id'] ?>" <?= (int) ($editing['branch_id'] ?? 0) === (int) $branch['id'] ? 'selected' : '' ?>><?= e($branch['branch_name']) ?></option><?php endforeach; ?></select></label>
          <label>Thứ<select required name="day_of_week" id="day_of_week"><option value="">Chọn thứ</option><?php foreach ($days as $number => $dayName): ?><option value="<?= $number ?>" <?= (int) ($editing['day_of_week'] ?? 0) === $number ? 'selected' : '' ?>><?= e($dayName) ?></option><?php endforeach; ?></select></label>
          <label>Tiết học<input required name="shift_or_periods" id="shift_or_periods" value="<?= e($editing['shift_or_periods'] ?? '') ?>" placeholder="1-3 hoặc 1-2,4"><small>Khoảng tiết hoặc danh sách tiết, ví dụ 1-3, 5.</small></label>
          <label>Phòng học<input required name="room" value="<?= e($editing['room'] ?? '') ?>" placeholder="A.203"></label>
          <label>Giảng viên<select required name="teacher_id" id="teacher_id"><option value="">Chọn giảng viên</option><?php foreach ($teachers as $teacher): ?><option value="<?= (int) $teacher['id'] ?>" <?= (int) ($editing['teacher_id'] ?? 0) === (int) $teacher['id'] ? 'selected' : '' ?>><?= e($teacher['full_name']) ?> · <?= e($teacher['expertise']) ?></option><?php endforeach; ?></select></label>
          <div class="form-actions"><button class="button primary" type="submit"><?= $editing ? 'Lưu thay đổi' : 'Tạo lịch giảng dạy' ?></button><button class="button secondary" type="button" id="suggest-button">✦ Gợi ý giảng viên</button></div>
        </form>
        <div class="suggestion-box" id="suggestion-box" aria-live="polite"></div>
      </section>
      <section class="panel"><div class="panel-heading"><div><h2>Danh sách lịch</h2><p class="muted"><?= count($rows) ?> lịch giảng dạy</p></div><form method="get" class="inline-filter"><input type="hidden" name="page" value="schedules"><select name="branch_id"><option value="">Tất cả cơ sở</option><?php foreach ($branches as $branch): ?><option value="<?= (int) $branch['id'] ?>" <?= (int) $filters['branch_id'] === (int) $branch['id'] ? 'selected' : '' ?>><?= e($branch['branch_name']) ?></option><?php endforeach; ?></select><button class="button secondary small-button">Lọc</button></form></div>
        <?php $scheduleFilters = ['branch_id' => $filters['branch_id']]; $rows = schedule_rows($pdo, $scheduleFilters); ?>
        <?php if ($rows): ?><div class="table-wrap"><table><thead><tr><th>Học phần</th><th>Giảng viên</th><th>Cơ sở / Phòng</th><th>Thời gian</th><th></th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><strong><?= e($row['subject_name']) ?></strong><small><?= e($row['expertise']) ?></small></td><td><?= e($row['full_name']) ?></td><td><?= e($row['branch_name']) ?><small><?= e($row['room']) ?></small></td><td><?= e($days[(int) $row['day_of_week']] ?? '') ?><small>Tiết <?= e($row['shift_or_periods']) ?></small></td><td class="actions"><a class="action-link" href="index.php?page=schedules&amp;edit=<?= (int) $row['id'] ?>#schedule-form">Sửa</a><form method="post" onsubmit="return confirm('Bạn có chắc muốn xóa lịch này?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_schedule"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="action-link danger-link" type="submit">Xóa</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-state">Chưa có lịch nào. Hãy tạo lịch giảng dạy đầu tiên.</div><?php endif; ?>
      </section>
    <?php else: ?>
      <section class="page-heading"><div><p class="eyebrow"><?= $isAdmin ? 'THỜI KHÓA BIỂU TOÀN TRƯỜNG' : 'CỔNG THÔNG TIN GIẢNG VIÊN' ?></p><h1><?= $isAdmin ? 'Lịch giảng dạy' : 'Lịch của tôi' ?></h1><p class="muted"><?= $isAdmin ? 'Lọc thời khóa biểu theo cơ sở, giảng viên và tuần/tháng.' : 'Theo dõi lịch dạy và cơ sở bạn cần đến trong tháng.' ?></p></div></section>
      <?php if ($isAdmin): ?><section class="panel filter-panel"><form class="filter-form" method="get"><input type="hidden" name="page" value="calendar"><label>Cơ sở<select name="branch_id"><option value="">Tất cả cơ sở</option><?php foreach ($branches as $branch): ?><option value="<?= (int) $branch['id'] ?>" <?= (int) $filters['branch_id'] === (int) $branch['id'] ? 'selected' : '' ?>><?= e($branch['branch_name']) ?></option><?php endforeach; ?></select></label><label>Giảng viên<select name="teacher_id"><option value="">Tất cả giảng viên</option><?php foreach ($teachers as $teacher): ?><option value="<?= (int) $teacher['id'] ?>" <?= (int) $filters['teacher_id'] === (int) $teacher['id'] ? 'selected' : '' ?>><?= e($teacher['full_name']) ?></option><?php endforeach; ?></select></label><button class="button secondary" type="submit">Áp dụng bộ lọc</button></form></section><?php endif; ?>
      <section class="panel calendar-panel"><div class="panel-heading"><div><h2><?= $isAdmin ? 'Thời khóa biểu tuần' : 'Lịch giảng dạy theo tuần' ?></h2><p class="muted"><?= e($isAdmin ? 'Lịch định kỳ, nhóm theo thứ trong tuần.' : $user['full_name']) ?></p></div><form class="month-picker" method="get"><input type="hidden" name="page" value="calendar"><?php if ($isAdmin && $filters['branch_id']): ?><input type="hidden" name="branch_id" value="<?= (int) $filters['branch_id'] ?>"><?php endif; ?><?php if ($isAdmin && $filters['teacher_id']): ?><input type="hidden" name="teacher_id" value="<?= (int) $filters['teacher_id'] ?>"><?php endif; ?><label for="month">Xem tháng</label><input id="month" name="month" type="month" value="<?= e($month) ?>"><button class="button secondary small-button">Xem</button></form></div>
        <?php if ($rows): ?><div class="schedule-grid"><?php foreach ($days as $dayNumber => $dayName): $dayRows = array_values(array_filter($rows, static fn (array $row): bool => (int) $row['day_of_week'] === $dayNumber)); ?><section class="day-column"><h3><?= e($dayName) ?><span><?= count($dayRows) ?> lớp</span></h3><?php if (!$dayRows): ?><p class="day-empty">Không có lịch</p><?php endif; ?><?php foreach ($dayRows as $row): ?><article class="class-card"><span class="class-period">TIẾT <?= e($row['shift_or_periods']) ?></span><strong><?= e($row['subject_name']) ?></strong><span><?= e($row['branch_name']) ?></span><small><?= e($row['room']) ?><?= $isAdmin ? ' · ' . e($row['full_name']) : '' ?></small></article><?php endforeach; ?></section><?php endforeach; ?></div>
        <div class="month-list"><div class="month-list-heading"><h3>Lịch trong tháng <?= e($monthStart->format('m/Y')) ?></h3><span><?= count($monthOccurrences) ?> buổi học</span></div><?php if ($monthOccurrences): ?><div class="table-wrap"><table><thead><tr><th>Ngày</th><th>Học phần</th><th>Tiết</th><th>Cơ sở</th><th>Phòng</th><?php if ($isAdmin): ?><th>Giảng viên</th><?php endif; ?></tr></thead><tbody><?php foreach ($monthOccurrences as $occurrence): $row = $occurrence['row']; ?><tr><td><?= e($occurrence['date']->format('d/m/Y')) ?><small><?= e($days[(int) $row['day_of_week']]) ?></small></td><td><strong><?= e($row['subject_name']) ?></strong></td><td><?= e($row['shift_or_periods']) ?></td><td><?= e($row['branch_name']) ?></td><td><?= e($row['room']) ?></td><?php if ($isAdmin): ?><td><?= e($row['full_name']) ?></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-state compact">Không có lịch trong tháng này.</div><?php endif; ?></div>
        <?php else: ?><div class="empty-state">Chưa có lịch giảng dạy phù hợp với bộ lọc.</div><?php endif; ?>
      </section>
      <section class="branch-cards"><?php foreach ($branches as $branch): ?><article class="branch-card"><span class="stat-icon green">⌖</span><strong><?= e($branch['branch_name']) ?></strong><small><?= e($branch['address']) ?></small></article><?php endforeach; ?></section>
    <?php endif; ?>
    <footer class="footer">HUIT Schedule Hub <span>•</span> Hệ thống quản lý thời khóa biểu đa cơ sở</footer>
  </main>
</div>
</body></html>
