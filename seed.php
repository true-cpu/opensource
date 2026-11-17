<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

try {
    $pdo = db();
    $faker = Faker\Factory::create('vi_VN');
    $pdo->beginTransaction();

    $branchRows = [
        ['Cơ sở Tân Phú', '16 Hòa Bình, Quận Tân Phú, TP. Hồ Chí Minh'],
        ['Cơ sở Quận 9', 'Khu Công nghệ cao, TP. Thủ Đức, TP. Hồ Chí Minh'],
        ['Trung tâm liên kết', '12 Nguyễn Văn Bảo, Quận Gò Vấp, TP. Hồ Chí Minh'],
    ];
    $branchInsert = $pdo->prepare(
        'INSERT INTO branches (branch_name, address) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE address = VALUES(address)'
    );
    foreach ($branchRows as $branch) {
        $branchInsert->execute($branch);
    }
    $branchIds = $pdo->query('SELECT branch_name, id FROM branches')->fetchAll(PDO::FETCH_KEY_PAIR);
    $branches = array_map(
        static fn (array $branch): int => (int) $branchIds[$branch[0]],
        $branchRows
    );

    $teacherInsert = $pdo->prepare(
        'INSERT IGNORE INTO teachers (full_name, email, phone, expertise) VALUES (?, ?, ?, ?)'
    );
    $userInsert = $pdo->prepare(
        'INSERT IGNORE INTO users (teacher_id, email, password_hash, role) VALUES (?, ?, ?, ?)'
    );
    $passwordHash = password_hash('password', PASSWORD_DEFAULT);

    $teacherInsert->execute(['Nguyễn Minh Anh', 'giangvien@huit.edu.vn', '0909000001', 'Công nghệ thông tin, Lập trình']);
    $teacherId = (int) $pdo->query("SELECT id FROM teachers WHERE email = 'giangvien@huit.edu.vn'")->fetchColumn();
    $userInsert->execute([$teacherId, 'giangvien@huit.edu.vn', $passwordHash, 'teacher']);
    $pdo->prepare(
        'INSERT INTO users (teacher_id, email, password_hash, role)
         VALUES (NULL, ?, ?, ?)
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = VALUES(role)'
    )->execute(['admin@huit.edu.vn', $passwordHash, 'admin']);

    $expertiseAreas = [
        'Công nghệ thông tin, Lập trình',
        'Kế toán, Tài chính',
        'Marketing, Quản trị kinh doanh',
        'Tiếng Anh, Ngoại ngữ',
        'Công nghệ thực phẩm, Hóa học',
        'Thương mại điện tử, Công nghệ thông tin',
    ];
    for ($index = 1; $index <= 12; $index++) {
        $email = sprintf('giangvien%02d@huit.edu.vn', $index);
        $teacherInsert->execute([
            $faker->name(),
            $email,
            $faker->phoneNumber(),
            $expertiseAreas[($index - 1) % count($expertiseAreas)],
        ]);
        $idStatement = $pdo->prepare('SELECT id FROM teachers WHERE email = ?');
        $idStatement->execute([$email]);
        $id = (int) $idStatement->fetchColumn();
        $userInsert->execute([$id, $email, $passwordHash, 'teacher']);
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM schedules')->fetchColumn();
    if ($count === 0) {
        $teachers = $pdo->query('SELECT id, expertise FROM teachers ORDER BY id')->fetchAll();
        $subjects = [
            ['Lập trình Web', 'Công nghệ thông tin'],
            ['Nguyên lý kế toán', 'Kế toán'],
            ['Marketing căn bản', 'Marketing'],
            ['Tiếng Anh chuyên ngành', 'Tiếng Anh'],
            ['Thương mại điện tử', 'Thương mại điện tử'],
        ];
        $insertSchedule = $pdo->prepare(
            'INSERT INTO schedules (teacher_id, branch_id, subject_name, room, day_of_week, shift_or_periods)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($subjects as $index => [$subject, $area]) {
            $matching = array_values(array_filter(
                $teachers,
                static fn (array $teacher): bool => stripos($teacher['expertise'], $area) !== false
            ));
            $teacher = $matching[$index % max(1, count($matching))] ?? $teachers[$index % count($teachers)];
            $insertSchedule->execute([
                $teacher['id'],
                $branches[$index % count($branches)],
                $subject,
                'P.' . (200 + $index),
                ($index % 5) + 1,
                ($index % 3 === 0) ? '1-3' : (($index % 3 === 1) ? '4-6' : '7-9'),
            ]);
        }
    }

    $pdo->commit();
    echo "Đã tạo dữ liệu mẫu.\nTài khoản quản trị: admin@huit.edu.vn / password\n";
    echo "Tài khoản giảng viên: giangvien@huit.edu.vn / password\n";
    echo "Các tài khoản giảng viên mẫu khác cũng dùng mật khẩu: password\n";
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Không thể tạo dữ liệu mẫu: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
