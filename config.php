<?php
$db   = 'tickets';
$host = 'dpg-d4847mbipnbc73d8hlm0-a.singapore-postgres.render.com';
$user = 'user';
$pass = 'RFa4bfOpswyRFBK3cZvaU9okEORsFxYO';
$dsn = "pgsql:host=$host;dbname=$db";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}

function translate($column) {
    static $map = [
        'id' => 'ID',
        'departure_point' => 'Пункт отбытия',
        'arrival_point' => 'Пункт прибытия',
        'departure_date' => 'Дата отбытия',
        'departure_time' => 'Время отбытия',
        'flight_duration' => 'Длительность полёта',
        'ticket_price' => 'Стоимость билета',
        'seats' => 'Информация о местах',

        'last_name' => 'Фамилия',
        'first_name' => 'Имя',
        'middle_name' => 'Отчество',
        'date_of_birth' => 'Дата рождения',
        'document_type' => 'Тип документа',
        'document_number' => 'Номер документа',
        'document_issue_country' => 'Страна выдачи документа',
        'document_expiry_date' => 'Срок действия документа',
        'phone_number' => 'Контактный номер',
        'email' => 'Электронная почта',

        'flight_id' => 'ID рейса',
        'passenger_id' => 'ID пассажира',
        'seat_number' => 'Номер места',
        'purchase_timestamp' => 'Время покупки',

        'user_id' => 'ID',
        'name' => 'ФИО',
        'email' => 'E-mail',
        'password' => 'Пароль'
    ];
    return $map[$column] ?? ucfirst(str_replace('_', ' ', $column));
}
?>
