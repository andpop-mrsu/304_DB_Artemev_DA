<?php

$dbPath = __DIR__ . '/database.sqlite';
$dsn = "sqlite:$dbPath";

$pdo = null;
$error = null;
$activeGroups = [];
$students = [];
$selectedGroup = '';

try {
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $currentYear = (int)date('Y');
    
    $query = "SELECT DISTINCT g.name, g.id
              FROM groups g
              WHERE CAST(SUBSTR(g.academic_year, 6, 4) AS INTEGER) <= :currentYear
              ORDER BY g.name";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['currentYear' => $currentYear]);
    $activeGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (isset($_GET['group']) && $_GET['group'] !== '') {
        $selectedGroup = $_GET['group'];
        
        $validGroupNames = array_column($activeGroups, 'name');
        if (in_array($selectedGroup, $validGroupNames)) {
            
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
                      WHERE CAST(SUBSTR(g.academic_year, 6, 4) AS INTEGER) <= :currentYear
                        AND g.name = :groupName
                      ORDER BY g.name, s.last_name, s.first_name";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                'currentYear' => $currentYear,
                'groupName' => $selectedGroup
            ]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        
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
                  WHERE CAST(SUBSTR(g.academic_year, 6, 4) AS INTEGER) <= :currentYear
                  ORDER BY g.name, s.last_name, s.first_name";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute(['currentYear' => $currentYear]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "Database connection error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список студентов</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .filter-form {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        .filter-form label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
            color: #555;
        }
        .filter-form select {
            padding: 8px 12px;
            font-size: 14px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 200px;
        }
        .filter-form button {
            padding: 8px 20px;
            font-size: 14px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }
        .filter-form button:hover {
            background-color: #0056b3;
        }
        .error {
            color: #d32f2f;
            padding: 15px;
            background-color: #ffebee;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Список студентов</h1>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php else: ?>
            <div class="filter-form">
                <form method="GET" action="">
                    <label for="group">Выберите группу:</label>
                    <select name="group" id="group">
                        <option value="">Все группы</option>
                        <?php foreach ($activeGroups as $group): ?>
                            <option value="<?= htmlspecialchars($group['name']) ?>" 
                                <?= $selectedGroup === $group['name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($group['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Показать</button>
                </form>
            </div>
            
            <?php if (empty($students)): ?>
                <div class="no-data">Студенты не найдены</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Группа</th>
                            <th>Направление подготовки</th>
                            <th>ФИО</th>
                            <th>Пол</th>
                            <th>Дата рождения</th>
                            <th>№ студенческого билета</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <?php
                            $fio = trim("{$student['last_name']} {$student['first_name']} {$student['middle_name']}");
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($student['group_name']) ?></td>
                                <td><?= htmlspecialchars($student['direction_name']) ?></td>
                                <td><?= htmlspecialchars($fio) ?></td>
                                <td><?= htmlspecialchars($student['gender']) ?></td>
                                <td><?= htmlspecialchars($student['birth_date']) ?></td>
                                <td><?= htmlspecialchars($student['student_id']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>

