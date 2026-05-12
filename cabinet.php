<?php
ob_start();
session_start();
require_once 'db.php';

function resolveUser($conn)
{
    $userId = 0;
    $login = 'Гость';

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

    if ($login === 'Гость' && isset($_SESSION['username']) && trim($_SESSION['username']) !== '') {
        $login = trim($_SESSION['username']);
    }

    if ($userId <= 0 && $login !== 'Гость') {
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

    $user = array(
        'id' => $userId,
        'login' => $login,
        'role' => '',
        'status' => '',
        'avatar' => 'images/icon-account.png',
        'phone' => '',
        'email' => ''
    );

    if ($userId > 0) {
        $stmt = $conn->prepare('SELECT login, role, status, avatar, phone, email FROM Users WHERE Id_U = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->bind_result($dbLogin, $dbRole, $dbStatus, $dbAvatar, $dbPhone, $dbEmail);
            if ($stmt->fetch()) {
                $user['login'] = $dbLogin !== '' ? $dbLogin : $user['login'];
                $user['role'] = $dbRole;
                $user['status'] = $dbStatus;
                $user['avatar'] = $dbAvatar !== '' ? $dbAvatar : $user['avatar'];
                $user['phone'] = $dbPhone;
                $user['email'] = $dbEmail;
            }
            $stmt->close();
        }
    }

    return $user;
}

function loadOrderItems($conn, $orderId)
{
    $items = array();
    $stmt = $conn->prepare('SELECT s.title, oi.qty, oi.price FROM order_items oi LEFT JOIN services s ON s.id = oi.service_id WHERE oi.order_id = ?');

    if ($stmt) {
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $stmt->bind_result($title, $qty, $price);

        while ($stmt->fetch()) {
            $items[] = array(
                'title' => $title,
                'qty' => $qty,
                'price' => $price
            );
        }

        $stmt->close();
    }

    return $items;
}

function loadOrderResponses($conn, $orderId)
{
    $responses = array();
    $stmt = $conn->prepare('SELECT r.id, r.user_id, r.name, r.phone, r.comment, r.created_at, u.login, u.email FROM responses r LEFT JOIN Users u ON u.Id_U = r.user_id WHERE r.order_id = ? ORDER BY r.id DESC');
    if ($stmt) {
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $stmt->bind_result($id, $userId, $name, $phone, $comment, $createdAt, $login, $email);
        while ($stmt->fetch()) {
            $responses[] = array(
                'id' => $id,
                'user_id' => $userId,
                'name' => $name,
                'phone' => $phone,
                'comment' => $comment,
                'created_at' => $createdAt,
                'login' => $login,
                'email' => $email
            );
        }
        $stmt->close();
    }
    return $responses;
}

function ensureChat($conn, $orderId, $clientId, $workerId)
{
    $chatId = 0;
    $stmt = $conn->prepare('SELECT id FROM order_chats WHERE order_id = ? AND client_id = ? AND worker_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('iii', $orderId, $clientId, $workerId);
        $stmt->execute();
        $stmt->bind_result($foundId);
        if ($stmt->fetch()) {
            $chatId = (int)$foundId;
        }
        $stmt->close();
    }

    if ($chatId <= 0) {
        $stmt = $conn->prepare('INSERT INTO order_chats (order_id, client_id, worker_id) VALUES (?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('iii', $orderId, $clientId, $workerId);
            if ($stmt->execute()) {
                $chatId = (int)$conn->insert_id;
            }
            $stmt->close();
        }
    }

    return $chatId;
}

function loadUserChats($conn, $userId)
{
    $chats = array();
    $sql = 'SELECT c.id, c.order_id, c.client_id, c.worker_id, c.created_at, o.service_name, o.total_price,
                   uc.login AS client_login, uw.login AS worker_login,
                   (SELECT m.message FROM chat_messages m WHERE m.chat_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message,
                   (SELECT m.created_at FROM chat_messages m WHERE m.chat_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message_at
            FROM order_chats c
            LEFT JOIN orders o ON o.id = c.order_id
            LEFT JOIN Users uc ON uc.Id_U = c.client_id
            LEFT JOIN Users uw ON uw.Id_U = c.worker_id
            WHERE c.client_id = ? OR c.worker_id = ?
            ORDER BY COALESCE((SELECT m.id FROM chat_messages m WHERE m.chat_id = c.id ORDER BY m.id DESC LIMIT 1), c.id) DESC';
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ii', $userId, $userId);
        $stmt->execute();
        $stmt->bind_result($id, $orderId, $clientId, $workerId, $createdAt, $serviceName, $totalPrice, $clientLogin, $workerLogin, $lastMessage, $lastMessageAt);
        while ($stmt->fetch()) {
            $partner = ((int)$clientId === (int)$userId) ? $workerLogin : $clientLogin;
            $chats[] = array(
                'id' => $id,
                'order_id' => $orderId,
                'service_name' => $serviceName,
                'total_price' => $totalPrice,
                'partner' => $partner,
                'last_message' => $lastMessage,
                'last_message_at' => $lastMessageAt
            );
        }
        $stmt->close();
    }
    return $chats;
}

$user = resolveUser($conn);

if ($user['id'] <= 0) {
    header('Location: login.php');
    exit;
}

if (!is_dir(__DIR__ . '/uploads')) {
    mkdir(__DIR__ . '/uploads', 0777, true);
}

// Open or resume a chat with a specific responder. This does not assign the
// worker to the order; it only ensures a dialog exists and redirects to it.
if (isset($_GET['open_chat']) && isset($_GET['order_id']) && isset($_GET['response_id'])) {
    $orderId = (int)$_GET['order_id'];
    $responseId = (int)$_GET['response_id'];

    $stmt = $conn->prepare('SELECT o.id, o.client_id, r.user_id FROM orders o INNER JOIN responses r ON r.order_id = o.id WHERE o.id = ? AND r.id = ? AND o.client_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('iii', $orderId, $responseId, $user['id']);
        $stmt->execute();
        $stmt->bind_result($foundOrderId, $clientId, $workerId);
        if ($stmt->fetch() && (int)$workerId > 0) {
            $stmt->close();
            $chatId = ensureChat($conn, $orderId, $user['id'], (int)$workerId);
            if ($chatId > 0) {
                header('Location: chat.php?chat_id=' . $chatId);
                exit;
            }
        } else {
            $stmt->close();
        }
    }
    header('Location: cabinet.php?error=chat_create_failed');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $newLogin = isset($_POST['login']) ? trim($_POST['login']) : '';
    $newPassword = isset($_POST['password']) ? trim($_POST['password']) : '';

    if ($newLogin !== '') {
        if ($newPassword !== '') {
            $stmt = $conn->prepare('UPDATE Users SET login = ?, password = ? WHERE Id_U = ?');
            if ($stmt) {
                $stmt->bind_param('ssi', $newLogin, $newPassword, $user['id']);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare('UPDATE Users SET login = ? WHERE Id_U = ?');
            if ($stmt) {
                $stmt->bind_param('si', $newLogin, $user['id']);
                $stmt->execute();
                $stmt->close();
            }
        }

        $_SESSION['login'] = $newLogin;
        $_SESSION['username'] = $newLogin;
    }

    header('Location: cabinet.php?success=profile_saved');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar'])) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
        $tmpName = $_FILES['avatar']['tmp_name'];
        $originalName = $_FILES['avatar']['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (in_array($ext, array('jpg', 'jpeg', 'png', 'webp'))) {
            $fileName = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
            $relativePath = 'uploads/' . $fileName;
            $fullPath = __DIR__ . '/' . $relativePath;

            if (move_uploaded_file($tmpName, $fullPath)) {
                $stmt = $conn->prepare('UPDATE Users SET avatar = ? WHERE Id_U = ?');
                if ($stmt) {
                    $stmt->bind_param('si', $relativePath, $user['id']);
                    $stmt->execute();
                    $stmt->close();
                    // Обновляем сессию, чтобы хедер сразу показал новую аватарку
                    $_SESSION['avatar'] = $relativePath;
                }

                header('Location: cabinet.php?success=avatar_saved');
                exit;
            }
        }
    }

    header('Location: cabinet.php?error=avatar_upload_failed');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['choose_response'])) {
    $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $responseId = isset($_POST['response_id']) ? (int)$_POST['response_id'] : 0;

    $stmt = $conn->prepare('SELECT o.id, o.client_id, r.user_id FROM orders o INNER JOIN responses r ON r.order_id = o.id WHERE o.id = ? AND r.id = ? AND o.client_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('iii', $orderId, $responseId, $user['id']);
        $stmt->execute();
        $stmt->bind_result($foundOrderId, $clientId, $workerId);
        if ($stmt->fetch()) {
            $stmt->close();
            $stmtUpdate = $conn->prepare('UPDATE orders SET worker_id = ?, status_id = 2 WHERE id = ? AND client_id = ?');
            if ($stmtUpdate) {
                $stmtUpdate->bind_param('iii', $workerId, $orderId, $user['id']);
                $stmtUpdate->execute();
                $stmtUpdate->close();
            }
            $chatId = ensureChat($conn, $orderId, $user['id'], $workerId);
            if ($chatId > 0) {
                header('Location: chat.php?chat_id=' . $chatId . '&success=worker_selected');
                exit;
            }
            header('Location: cabinet.php?error=chat_create_failed');
            exit;
        }
        $stmt->close();
    }
    header('Location: cabinet.php?error=choose_worker_failed');
    exit;
}

$orders = array();
$stmtOrders = $conn->prepare('SELECT o.id, o.worker_id, o.status_id, o.scheduled_at, o.service_name, o.client_comment, o.category, o.city, o.urgency, o.total_price, o.created_at, os.name
    FROM orders o
    LEFT JOIN order_statuses os ON os.id = o.status_id
    WHERE o.client_id = ?
    ORDER BY o.id DESC');

if ($stmtOrders) {
    $stmtOrders->bind_param('i', $user['id']);
    $stmtOrders->execute();
    $stmtOrders->bind_result($orderId, $workerId, $statusId, $scheduledAt, $serviceName, $clientComment, $category, $city, $urgency, $totalPrice, $createdAt, $statusName);

    while ($stmtOrders->fetch()) {
        $orders[] = array(
            'id' => $orderId,
            'worker_id' => $workerId,
            'status_name' => $statusName,
            'scheduled_at' => $scheduledAt,
            'service_name' => $serviceName,
            'client_comment' => $clientComment,
            'category' => $category,
            'city' => $city,
            'urgency' => $urgency,
            'total_price' => $totalPrice,
            'created_at' => $createdAt
        );
    }

    $stmtOrders->close();
}

$chats = loadUserChats($conn, $user['id']);
$user = resolveUser($conn);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'header.php'; ?>

<div class="page-shell">
    <div class="profile-hero">
        <div class="profile-hero-left">
            <div class="profile-avatar-wrap">
                <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="" class="profile-avatar-large">
            </div>
            <div class="profile-meta">
                <h1><?php echo htmlspecialchars($user['login']); ?></h1>
                <div class="profile-subline">Роль: <?php echo htmlspecialchars($user['role']); ?></div>
                <div class="profile-subline">Статус: <?php echo htmlspecialchars($user['status']); ?></div>
                <?php if ($user['phone'] !== '' || $user['email'] !== ''): ?>
                    <div class="profile-subline">Контакты: <?php echo htmlspecialchars(trim($user['phone'] . ' ' . $user['email'])); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['success']) && $_GET['success'] === 'order_created'): ?>
        <div class="steam-alert steam-alert-success">Заказ успешно записан в базу данных</div>
    <?php endif; ?>
    <?php if (isset($_GET['success']) && $_GET['success'] === 'worker_selected'): ?>
        <div class="steam-alert steam-alert-success">Исполнитель выбран. Чат открыт.</div>
    <?php endif; ?>
    <?php if (isset($_GET['success']) && $_GET['success'] === 'profile_saved'): ?>
        <div class="steam-alert steam-alert-success">Логин и пароль обновлены</div>
    <?php endif; ?>
    <?php if (isset($_GET['success']) && $_GET['success'] === 'avatar_saved'): ?>
        <div class="steam-alert steam-alert-success">Аватарка обновлена</div>
    <?php endif; ?>
    <?php if (isset($_GET['success']) && $_GET['success'] === 'order_deleted'): ?>
        <div class="steam-alert steam-alert-success">Заказ удален</div>
    <?php endif; ?>
    <?php if (isset($_GET['error']) && $_GET['error'] === 'avatar_upload_failed'): ?>
        <div class="steam-alert steam-alert-error">Не удалось загрузить аватарку</div>
    <?php endif; ?>
    <?php if (isset($_GET['error']) && $_GET['error'] === 'delete_forbidden'): ?>
        <div class="steam-alert steam-alert-error">Нельзя удалить чужой заказ</div>
    <?php endif; ?>
    <?php if (isset($_GET['error']) && $_GET['error'] === 'choose_worker_failed'): ?>
        <div class="steam-alert steam-alert-error">Не удалось выбрать исполнителя</div>
    <?php endif; ?>
    <?php if (isset($_GET['error']) && $_GET['error'] === 'chat_create_failed'): ?>
        <div class="steam-alert steam-alert-error">Не удалось открыть чат. Проверь, что отклик оставил зарегистрированный пользователь и таблицы чата добавлены в базу.</div>
    <?php endif; ?>

    <div class="steam-grid">
        <div class="steam-panel">
            <h2>Настройки аккаунта</h2>
            <form action="cabinet.php" method="post" class="steam-form">
                <label class="steam-label">Новый логин</label>
                <input type="text" name="login" value="<?php echo htmlspecialchars($user['login']); ?>" class="steam-input">
                <label class="steam-label">Новый пароль</label>
                <input type="password" name="password" class="steam-input">
                <button type="submit" name="save_profile" value="1" class="steam-btn steam-btn-primary steam-btn-block">Сохранить изменения</button>
            </form>
        </div>

        <div class="steam-panel">
            <h2>Быстрые действия</h2>
            <div class="steam-actions">
                <a href="index.php" class="steam-btn steam-btn-dark steam-btn-block">На главную</a>
                <a href="create_order.php" class="steam-btn steam-btn-primary steam-btn-block">Создать заказ</a>
            </div>
            <h2 class="section-title-spacer">Аватарка профиля</h2>
            <form action="cabinet.php" method="post" enctype="multipart/form-data" class="steam-form">
                <label class="steam-label">Выбери изображение</label>
                <input type="file" name="avatar" class="steam-input" accept=".jpg,.jpeg,.png,.webp">
                <button type="submit" name="upload_avatar" value="1" class="steam-btn steam-btn-primary steam-btn-block">Загрузить аватарку</button>
            </form>
        </div>
    </div>

    <div class="steam-panel steam-panel-orders">
        <div class="section-head">
            <h2>Мои чаты</h2>
            <span class="section-note">Открывай диалоги с выбранными исполнителями</span>
        </div>
        <?php if (count($chats) === 0): ?>
            <div class="empty-box">Пока чатов нет</div>
        <?php else: ?>
            <div class="chat-list">
                <?php foreach ($chats as $chat): ?>
                    <a class="chat-list-card" href="chat.php?chat_id=<?php echo (int)$chat['id']; ?>">
                        <div>
                            <div class="chat-list-title"><?php echo htmlspecialchars($chat['service_name'] !== '' ? $chat['service_name'] : 'Чат по заказу #' . $chat['order_id']); ?></div>
                            <div class="chat-list-sub">Собеседник: <?php echo htmlspecialchars($chat['partner']); ?></div>
                            <div class="chat-list-preview"><?php echo htmlspecialchars($chat['last_message'] !== null && $chat['last_message'] !== '' ? $chat['last_message'] : 'Сообщений пока нет'); ?></div>
                        </div>
                        <div class="chat-list-meta"><?php echo $chat['last_message_at'] ? htmlspecialchars(date('d.m.Y H:i', strtotime($chat['last_message_at']))) : 'Новый чат'; ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="steam-panel steam-panel-orders">
        <h2>Мои заказы</h2>
        <?php if (count($orders) === 0): ?>
            <div class="empty-box">Пока заказов нет</div>
        <?php else: ?>
            <div class="order-list">
                <?php foreach ($orders as $order): ?>
                    <?php $items = loadOrderItems($conn, $order['id']); ?>
                    <?php $responses = loadOrderResponses($conn, $order['id']); ?>
                    <div class="order-card">
                        <div class="order-card-head">
                            <div>
                                <div class="order-id">Заказ #<?php echo (int)$order['id']; ?></div>
                                <div class="order-date">Создан: <?php echo htmlspecialchars($order['created_at']); ?></div>
                            </div>
                            <div class="order-status"><?php echo htmlspecialchars($order['status_name']); ?></div>
                        </div>

                        <?php if ($order['scheduled_at'] !== null && $order['scheduled_at'] !== ''): ?>
                            <div class="order-row">Дата выполнения: <?php echo htmlspecialchars($order['scheduled_at']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($order['service_name'])): ?>
                            <div class="order-row">Услуга: <?php echo htmlspecialchars($order['service_name']); ?></div>
                        <?php endif; ?>
                        <?php if ($order['client_comment'] !== null && $order['client_comment'] !== ''): ?>
                            <div class="order-row">Комментарий: <?php echo htmlspecialchars($order['client_comment']); ?></div>
                        <?php endif; ?>

                        <?php if (!empty($order['category'])): ?>
                            <div class="order-row">Категория: <?php echo htmlspecialchars($order['category']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($order['city'])): ?>
                            <div class="order-row">Город: <?php echo htmlspecialchars($order['city']); ?></div>
                        <?php endif; ?>

                        <?php if (count($items) > 0): ?>
                        <div class="order-items">
                            <?php foreach ($items as $item): ?>
                                <div class="order-item">
                                    <span><?php echo htmlspecialchars($item['title']); ?></span>
                                    <span><?php echo (int)$item['qty']; ?> × <?php echo number_format((float)$item['price'], 0, '.', ' '); ?> ₽</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <div class="response-section">
                            <div class="response-section-title">Отклики на заказ</div>
                            <?php if (count($responses) === 0): ?>
                                <div class="empty-box">Пока никто не откликнулся</div>
                            <?php else: ?>
                                <div class="response-list">
                                    <?php foreach ($responses as $response): ?>
                                        <div class="response-card<?php echo ((int)$order['worker_id'] === (int)$response['user_id'] && (int)$response['user_id'] > 0) ? ' response-card-selected' : ''; ?>">
                                            <div class="response-card-head">
                                                <div>
                                                    <div class="response-name"><?php echo htmlspecialchars($response['name']); ?></div>
                                                    <div class="response-meta">@<?php echo htmlspecialchars($response['login'] !== null && $response['login'] !== '' ? $response['login'] : 'гость'); ?> · <?php echo htmlspecialchars($response['phone']); ?></div>
                                                    <?php if ($response['email'] !== null && $response['email'] !== ''): ?>
                                                        <div class="response-meta"><?php echo htmlspecialchars($response['email']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ((int)$order['worker_id'] === (int)$response['user_id'] && (int)$response['user_id'] > 0): ?>
                                                    <span class="response-badge">Выбран</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($response['comment'] !== null && $response['comment'] !== ''): ?>
                                                <div class="response-message"><?php echo htmlspecialchars($response['comment']); ?></div>
                                            <?php endif; ?>
                                            <div class="response-actions">
                                                <?php if ((int)$response['user_id'] > 0): ?>
                                                    <a href="cabinet.php?open_chat=1&order_id=<?php echo (int)$order['id']; ?>&response_id=<?php echo (int)$response['id']; ?>" class="steam-btn steam-btn-primary">Открыть чат</a>
                                                    <?php if ((int)$order['worker_id'] !== (int)$response['user_id']): ?>
                                                        <form action="cabinet.php" method="post">
                                                            <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                                            <input type="hidden" name="response_id" value="<?php echo (int)$response['id']; ?>">
                                                            <button type="submit" name="choose_response" value="1" class="steam-btn steam-btn-dark">Выбрать исполнителя</button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <div class="response-meta">Для гостя чат недоступен</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="order-card-actions">
                            <div class="order-total">Итого: <?php echo number_format((float)$order['total_price'], 0, '.', ' '); ?> ₽</div>
                            <form action="delete_order.php" method="post" onsubmit="return confirm('Удалить этот заказ?');">
                                <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                <button type="submit" class="steam-btn steam-btn-danger">Удалить заказ</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
        <!-- Footer -->
        <?php include 'footer.php'; ?>
    </div>
</body>
</html>
