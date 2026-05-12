<?php
session_start();
require_once 'db.php';

function resolveUserId($conn)
{
    $userId = 0;
    $login = '';

    if (isset($_SESSION['user_id'])) {
        $userId = (int)$_SESSION['user_id'];
    }

    if ($userId <= 0 && isset($_SESSION['Id_U'])) {
        $userId = (int)$_SESSION['Id_U'];
    }

    if ($userId <= 0 && isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        if (isset($_SESSION['user']['Id_U'])) {
            $userId = (int)$_SESSION['user']['Id_U'];
        } elseif (isset($_SESSION['user']['id'])) {
            $userId = (int)$_SESSION['user']['id'];
        }
    }

    if (isset($_SESSION['login']) && trim($_SESSION['login']) !== '') {
        $login = trim($_SESSION['login']);
    }

    if ($login === '' && isset($_SESSION['username']) && trim($_SESSION['username']) !== '') {
        $login = trim($_SESSION['username']);
    }

    if ($userId <= 0 && $login !== '') {
        $stmt = $conn->prepare('SELECT Id_U FROM Users WHERE login = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $login);
            $stmt->execute();
            $stmt->bind_result($foundId);
            if ($stmt->fetch()) {
                $userId = (int)$foundId;
            }
            $stmt->close();
        }
    }

    return $userId;
}

function fail($message)
{
    header('Location: create_order.php?error=' . urlencode($message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Некорректный запрос');
}

$userId = resolveUserId($conn);

if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

// Extract posted values. In the redesigned form the user supplies a custom service
// name, description and price directly. We no longer combine the service name
// and description – the service name will be stored in the dedicated
// `service_name` column of the orders table, and the description remains
// the client comment. There is no longer a list of services or a scheduled date.
$addressId = isset($_POST['address_id']) ? (int)$_POST['address_id'] : 0;
// Извлекаем данные из формы. Новый заказ содержит название,
// описание, категорию, город и срочность. Название и описание обязательны.
$serviceName = isset($_POST['service_name']) ? trim($_POST['service_name']) : '';
$comment    = isset($_POST['client_comment']) ? trim($_POST['client_comment']) : '';
$category   = isset($_POST['category']) ? trim($_POST['category']) : '';
$city       = isset($_POST['city']) ? trim($_POST['city']) : '';
$urgency    = isset($_POST['urgency']) ? trim($_POST['urgency']) : '';
$price      = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;
$statusId   = 1; // id статуса "new" / открытого заказа
$scheduledAt = null;

// Validate inputs
if ($serviceName === '' || $comment === '' || $category === '' || $price < 0) {
    fail('Заполни название, описание, категорию и корректную цену');
}

// Perform a simple insert of the order. There are no order_items in this variant
// because the service details are included in their own columns on the orders
// table. The service name is persisted in the `service_name` column and the
// description remains in the `client_comment` field. We also persist the price.
$conn->autocommit(false);

try {
    // Вставляем заказ с новыми полями: категория, город и срочность.
    $stmtOrder = $conn->prepare('INSERT INTO orders (client_id, worker_id, address_id, status_id, scheduled_at, service_name, client_comment, category, city, urgency, total_price) VALUES (?, NULL, ?, ?, NULL, ?, ?, ?, ?, ?, ?)');

    if (!$stmtOrder) {
        throw new Exception($conn->error);
    }

    // Bind parameters: client_id, address_id, status_id, service_name, client_comment, category, city, urgency, total_price
    $stmtOrder->bind_param('iiisssssd', $userId, $addressId, $statusId, $serviceName, $comment, $category, $city, $urgency, $price);

    if (!$stmtOrder->execute()) {
        throw new Exception($stmtOrder->error);
    }

    $conn->commit();
    $conn->autocommit(true);
    // Получаем ID созданного заказа и перенаправляем на страницу заказа
    $newOrderId = $conn->insert_id;
    header('Location: order.php?id=' . $newOrderId);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    $conn->autocommit(true);
    fail('Заказ не записался в базу: ' . $e->getMessage());
}