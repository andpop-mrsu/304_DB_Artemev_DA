<?php

$dbPath = __DIR__ . '/database.sqlite';
$dsn = "sqlite:$dbPath";

try {
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection error: " . $e->getMessage() . "\n");
}


$currentYear = (int)date('Y');


$query = "SELECT DISTINCT g.name, g.id
          FROM groups g
          WHERE CAST(SUBSTR(g.academic_year, 6, 4) AS INTEGER) <= :currentYear
          ORDER BY g.name";
$stmt = $pdo->prepare($query);
$stmt->execute(['currentYear' => $currentYear]);
$activeGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($activeGroups)) {
    die("No active groups found.\n");
}


echo "Available active groups:\n";
foreach ($activeGroups as $group) {
    echo "  - {$group['name']}\n";
}
echo "\n";


echo "Enter group number to filter (or press Enter for all groups): ";
$input = trim(fgets(STDIN));
$selectedGroupName = $input;


$validGroupNames = array_column($activeGroups, 'name');
if (!empty($selectedGroupName) && !in_array($selectedGroupName, $validGroupNames)) {
    die("Error: Invalid group number '{$selectedGroupName}'. Please run the script again.\n");
}


$query = "SELECT 
            g.name AS group_name,
            d.name AS direction_name,
            s.last_name,
            s.first_name,
            s.middle_name,
            s.gender,
            s.birth_date,
            s.id AS student_id
          FROM students s
          INNER JOIN student_groups sg ON s.id = sg.student_id
          INNER JOIN groups g ON sg.group_id = g.id
          INNER JOIN directions d ON g.direction_id = d.id
          WHERE CAST(SUBSTR(g.academic_year, 6, 4) AS INTEGER) <= :currentYear";

$params = ['currentYear' => $currentYear];

if (!empty($selectedGroupName)) {
    $query .= " AND g.name = :groupName";
    $params['groupName'] = $selectedGroupName;
}

$query .= " ORDER BY g.name, s.last_name, s.first_name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($students)) {
    echo "No students found.\n";
    exit(0);
}


function displayTable($students) {
    
    $widths = [
        'group' => max(5, mb_strlen('Группа')),
        'direction' => max(8, mb_strlen('Направление')),
        'fio' => 0,
        'gender' => max(4, mb_strlen('Пол')),
        'birth_date' => max(10, mb_strlen('Дата рожд.')),
        'student_id' => max(8, mb_strlen('№ билета'))
    ];
    
    foreach ($students as $student) {
        $fio = trim("{$student['last_name']} {$student['first_name']} {$student['middle_name']}");
        $widths['fio'] = max($widths['fio'], mb_strlen($fio));
        $widths['group'] = max($widths['group'], mb_strlen($student['group_name']));
        $widths['direction'] = max($widths['direction'], mb_strlen($student['direction_name']));
        $widths['gender'] = max($widths['gender'], mb_strlen($student['gender']));
        $widths['birth_date'] = max($widths['birth_date'], mb_strlen($student['birth_date']));
        $widths['student_id'] = max($widths['student_id'], mb_strlen((string)$student['student_id']));
    }
    
    
    $widths['fio'] += 2;
    $widths['group'] += 2;
    $widths['direction'] += 2;
    $widths['gender'] += 2;
    $widths['birth_date'] += 2;
    $widths['student_id'] += 2;
    
    $totalWidth = array_sum($widths) + 7; 
    
    
    echo "┌" . str_repeat("─", $totalWidth - 2) . "┐\n";
    
    echo "│ " . str_pad("Группа", $widths['group'], " ", STR_PAD_RIGHT);
    echo "│ " . str_pad("Направление подготовки", $widths['direction'], " ", STR_PAD_RIGHT);
    echo "│ " . str_pad("ФИО", $widths['fio'], " ", STR_PAD_RIGHT);
    echo "│ " . str_pad("Пол", $widths['gender'], " ", STR_PAD_RIGHT);
    echo "│ " . str_pad("Дата рожд.", $widths['birth_date'], " ", STR_PAD_RIGHT);
    echo "│ " . str_pad("№ билета", $widths['student_id'], " ", STR_PAD_RIGHT) . "│\n";
    
    echo "├" . str_repeat("─", $widths['group'] + 1) . "┼";
    echo str_repeat("─", $widths['direction'] + 1) . "┼";
    echo str_repeat("─", $widths['fio'] + 1) . "┼";
    echo str_repeat("─", $widths['gender'] + 1) . "┼";
    echo str_repeat("─", $widths['birth_date'] + 1) . "┼";
    echo str_repeat("─", $widths['student_id'] + 1) . "┤\n";
    
    foreach ($students as $student) {
        $fio = trim("{$student['last_name']} {$student['first_name']} {$student['middle_name']}");
        echo "│ " . str_pad($student['group_name'], $widths['group'], " ", STR_PAD_RIGHT);
        echo "│ " . str_pad($student['direction_name'], $widths['direction'], " ", STR_PAD_RIGHT);
        echo "│ " . str_pad($fio, $widths['fio'], " ", STR_PAD_RIGHT);
        echo "│ " . str_pad($student['gender'], $widths['gender'], " ", STR_PAD_RIGHT);
        echo "│ " . str_pad($student['birth_date'], $widths['birth_date'], " ", STR_PAD_RIGHT);
        echo "│ " . str_pad((string)$student['student_id'], $widths['student_id'], " ", STR_PAD_RIGHT) . "│\n";
    }
    
    echo "└" . str_repeat("─", $totalWidth - 2) . "┘\n";
}

displayTable($students);

