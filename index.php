<?php
require_once 'config.php';
session_start();

$is_logged_in = isset($_SESSION['user']);
$action = $_GET['action'] ?? 'list';
$table = $_GET['table'] ?? 'flights';
$id = $_GET['id'] ?? null;

$tables = [
    'flights' => 'Рейсы',
    'passengers' => 'Пассажиры',
    'tickets' => 'Билеты',
    'users' => 'Пользователи'
];

if (!isset($tables[$table])) {
    die('<p class="error">Неверная таблица</p>');
}

try {
    $stmt = $pdo->query("SELECT * FROM $table LIMIT 0");
    $columns = [];
    for ($i = 0; $i < $stmt->columnCount(); $i++) {
        $meta = $stmt->getColumnMeta($i);
        $columns[] = $meta['name'];
    }
    $pk = $columns[0] ?? 'id';
} catch (PDOException $e) {
    die('<p class="error">Ошибка получения структуры таблицы.</p>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_logged_in) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
    }
    $data = [];

    foreach ($columns as $col) {
    if ($col === $pk) continue;

    $value = $_POST[$col] ?? '';

    if ($value === '') {
        $errors[] = "Поле '" . translate($col) . "' не может быть пустым.";
        continue;
    }

    // Проверка числовых полей
    $numericFields = ['id', 'flight_id', 'passenger_id', 'ticket_price'];
    if (in_array($col, $numericFields)) {
        if (!is_numeric($value)) {
            $errors[] = "Поле '" . translate($col) . "' должно быть числом.";
            continue;
        }
    }

    // Проверка текстовых полей на отсутствие цифр
    $textFields = [
        'departure_point', 'arrival_point',
        'last_name', 'first_name', 'middle_name',
        'document_type', 'document_issue_country'
    ];
    if (in_array($col, $textFields)) {
        if (preg_match('/\d/', $value)) {
            $errors[] = "Поле '" . translate($col) . "' должно содержать только текст без цифр.";
            continue;
        }
    }

    // Email
    if ($col === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Неверный формат E-mail.";
        continue;
    }

    // Телефон
    if ($col === 'phone_number' && !preg_match('/^\+?[0-9\- ]+$/', $value)) {
        $errors[] = "Неверный формат номера телефона.";
        continue;
    }

    // Дата
    if (str_contains($col, 'date') && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        $errors[] = "Поле '" . translate($col) . "' должно быть в формате YYYY-MM-DD.";
        continue;
    }

    // Время
    if ($col === 'departure_time' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
        $errors[] = "Поле '" . translate($col) . "' должно быть в формате HH:MM или HH:MM:SS.";
        continue;
    }

    // Интервал
    if ($col === 'flight_duration' && !preg_match('/^\d+\s+(hour|minute|second|day)s?$/i', $value)) {
        $errors[] = "Поле '" . translate($col) . "' должно быть интервалом (например, '2 hours').";
        continue;
    }

    $data[$col] = $value;
}


    if (empty($errors)) {
        try {
            if ($action === 'create') {
                $cols = implode(', ', array_keys($data));
                $placeholders = implode(', ', array_fill(0, count($data), '?'));
                $stmt = $pdo->prepare("INSERT INTO $table ($cols) VALUES ($placeholders)");
                $stmt->execute(array_values($data));
            } elseif ($action === 'edit' && $id) {
                if ($table === 'users' && empty($data['password'])) {
                    unset($data['password']);
                }
                $set_clauses = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
                $stmt = $pdo->prepare("UPDATE $table SET $set_clauses WHERE $pk = ?");
                $stmt->execute([...array_values($data), $id]);
            }
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo json_encode(['success' => true]);
                exit;
            }

            header("Location: index.php?table=$table");
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Ошибка сохранения данных: ' . $e->getMessage();
        }
    }
}

if ($action === 'delete' && $id && $is_logged_in) {
    try {
        $stmt = $pdo->prepare("DELETE FROM $table WHERE $pk = ?");
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        die('<p class="error">Ошибка удаления: ' . $e->getMessage() . '</p>');
    }
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        echo json_encode(['success' => true]);
        exit;
    }

    header("Location: index.php?table=$table");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Авиакомпания: <?= $tables[$table] ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <nav>
            <?php foreach ($tables as $tbl_name => $tbl_title): ?>
                <a href="?table=<?= $tbl_name ?>" class="<?= $table === $tbl_name ? 'active' : '' ?>"><?= $tbl_title ?></a>
            <?php endforeach; ?>
        </nav>
    </header>

    <div class="container">
        <?php if ($action === 'list'): ?>
            <h2><?= $tables[$table] ?></h2>
            <?php
            $stmt = $pdo->query("SELECT * FROM $table ORDER BY $pk");
            $rows = $stmt->fetchAll();

            if (!$rows): ?>
                <p>В этой таблице пока нет данных.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($columns as $col):
                                if ($table === 'users' && $col === 'password' && !$is_logged_in) continue;
                            ?>
                                <th><?= translate($col) ?></th>
                            <?php endforeach; ?>
                            <?php if ($is_logged_in): ?><th>Действия</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php foreach ($row as $key => $val):
                                    if ($table === 'users' && $key === 'password' && !$is_logged_in) continue;
                                ?>
                                    <td><?= htmlspecialchars((string)$val, ENT_QUOTES) ?></td>
                                <?php endforeach; ?>

                                <?php if ($is_logged_in): ?>
                                    <td class="actions">
                                        <a href="?table=<?= $table ?>&action=edit&id=<?= $row[$pk] ?>" class="edit">✏️</a>
                                        <a href="?table=<?= $table ?>&action=delete&id=<?= $row[$pk] ?>" class="delete" onclick="return confirm('Вы уверены, что хотите удалить эту запись?')">❌</a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <?php if ($is_logged_in): ?>
                <a href="?table=<?= $table ?>&action=create" class="btn-add"><button>Добавить новую запись</button></a>
            <?php endif; ?>

        <?php elseif ($action === 'create' || $action === 'edit'): ?>
            <?php
            if (!$is_logged_in) die('Доступ запрещен.');

            $values = [];
            if ($action === 'edit' && $id) {
                $stmt = $pdo->prepare("SELECT * FROM $table WHERE $pk = ?");
                $stmt->execute([$id]);
                $values = $stmt->fetch();
                if (!$values) die('Запись не найдена.');
            }
            ?>
            <h2><?= $action === 'create' ? 'Добавление записи' : 'Редактирование записи' ?></h2>
            <form method="post" action="?table=<?= $table ?>&action=<?= $action ?><?= $id ? '&id='.$id : '' ?>">
                <?php foreach ($columns as $col):
                    if ($col === $pk) continue;
                    $val = $values[$col] ?? '';
                    $label = translate($col);
                    
                    $type = 'text';
                    if (str_contains($col, '_date')) $type = 'date';
                    elseif (str_contains($col, '_time')) $type = 'time';
                    elseif (in_array($col, ['capacity', 'manufacture_year'])) $type = 'number';
                    elseif (str_contains($col, 'email')) $type = 'email';
                    elseif (str_contains($col, 'password')) $type = 'password';
                    
                    if (str_contains($col, 'description')): ?>
                        <label for="<?= $col ?>"><?= $label ?></label>
                        <textarea id="<?= $col ?>" name="<?= $col ?>" required><?= htmlspecialchars($val) ?></textarea>
                    <?php else: ?>
                        <label for="<?= $col ?>"><?= $label ?></label>
                        <input type="<?= $type ?>" id="<?= $col ?>" name="<?= $col ?>" value="<?= htmlspecialchars($val) ?>" required>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="form-actions">
                    <input type="submit" value="Сохранить">
                    <a href="?table=<?= $table ?>"><button type="button" class="danger">Отмена</button></a>
                </div>
            </form>
            <?php if (!empty($errors)): ?>
            <script>
                alert("<?= implode('\n', array_map(fn($e) => addslashes($e), $errors)) ?>");
            </script>
            <?php endif; ?>

        <?php endif; ?>
    </div>

    <footer>
        <?php if (!$is_logged_in): ?>
            <a href="auth.php?mode=login">Войти</a> | <a href="auth.php?mode=register">Регистрация</a>
        <?php else: ?>
            Пользователь: <b><?= htmlspecialchars($_SESSION['user']['name']) ?></b> | <a href="logout.php">Выйти</a>
        <?php endif; ?>
    </footer>
    <script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form');
    if (!form) return;

    form.addEventListener('submit', async e => {
        e.preventDefault();
        const formData = new FormData(form);

        const res = await fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const data = await res.json();

        if (data.success) {
            window.location.href = `?table=<?= $table ?>`;
        } else if (data.errors) {
            alert(data.errors.join('\n'));
        } else {
            alert('Произошла ошибка при сохранении данных.');
        }
    });
});
</script>

</body>
</html>