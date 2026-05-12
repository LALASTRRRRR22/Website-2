<?php
/**
 * Страница просмотра отдельного заказа.
 *
 * На этой странице отображаются все детали заказа: название, описание,
 * цена, категория, город, срочность, статус и дата публикации. Здесь же
 * можно откликнуться на заказ, если пользователь не является владельцем,
 * и просмотреть список откликов, если заказ принадлежит текущему
 * пользователю. Для каждого отклика доступна кнопка «Открыть чат».
 */

session_start();
require_once 'db.php';
require_once __DIR__ . '/includes/functions.php';

// Получаем ID заказа из параметра query string
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($orderId <= 0) {
    header('Location: index.php');
    exit;
}

// Загружаем данные заказа
$order = null;
$stmtOrder = $conn->prepare(
    'SELECT o.id, o.client_id, o.worker_id, o.status_id, o.service_name, o.client_comment, o.category, o.city, o.urgency, o.total_price, o.created_at, os.name
     FROM orders o
     LEFT JOIN order_statuses os ON os.id = o.status_id
     WHERE o.id = ? LIMIT 1'
);
if ($stmtOrder) {
    $stmtOrder->bind_param('i', $orderId);
    $stmtOrder->execute();
    $stmtOrder->bind_result($id, $clientId, $workerId, $statusId, $serviceName, $clientComment, $category, $city, $urgency, $totalPrice, $createdAt, $statusName);
    if ($stmtOrder->fetch()) {
        $order = array(
            'id' => $id,
            'client_id' => $clientId,
            'worker_id' => $workerId,
            'status_id' => $statusId,
            'service_name' => $serviceName,
            'client_comment' => $clientComment,
            'category' => $category,
            'city' => $city,
            'urgency' => $urgency,
            'total_price' => $totalPrice,
            'created_at' => $createdAt,
            'status_name' => $statusName
        );
    }
    $stmtOrder->close();
}

// Если заказ не найден — перенаправляем на главную
if (!$order) {
    header('Location: index.php');
    exit;
}

// Загружаем список откликов для заказа
$responses = array();
$stmtResp = $conn->prepare(
    'SELECT r.id, r.user_id, r.name, r.phone, r.comment, r.proposed_price, r.status, r.created_at, u.login
     FROM responses r
     LEFT JOIN Users u ON u.Id_U = r.user_id
     WHERE r.order_id = ?
     ORDER BY r.id DESC'
);
if ($stmtResp) {
    $stmtResp->bind_param('i', $orderId);
    $stmtResp->execute();
    $stmtResp->bind_result($respId, $respUserId, $respName, $respPhone, $respComment, $respPrice, $respStatus, $respCreatedAt, $respLogin);
    while ($stmtResp->fetch()) {
        $responses[] = array(
            'id' => $respId,
            'user_id' => $respUserId,
            'name' => $respName,
            'phone' => $respPhone,
            'comment' => $respComment,
            'proposed_price' => $respPrice,
            'status' => $respStatus,
            'created_at' => $respCreatedAt,
            'login' => $respLogin
        );
    }
    $stmtResp->close();
}

// Получаем текущего пользователя
$currentUserId = getCurrentUserId($conn);
// Флаг, принадлежит ли заказ текущему пользователю
$isOwner = ($currentUserId > 0 && (int)$order['client_id'] === (int)$currentUserId);
// Проверяем, откликался ли текущий пользователь
$hasAlreadyResponded = ($currentUserId > 0) ? hasResponded($conn, $orderId, $currentUserId) : false;

// Подсчёт количества откликов
$responsesCount = count($responses);

// Определяем, открыт ли заказ для откликов (status_id 1 — new / open, 3 — in_progress, 4 — done, 5 — canceled)
$isOpenForResponses = ($order['status_id'] == 1);

// Отображаем страницу
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заказ №<?php echo (int)$order['id']; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'header.php'; ?>

<div class="page-shell">
    <!-- Заголовок -->
<div class="page-header" data-animate="fade-up">
        <div>
            <h1>Заказ №<?php echo (int)$order['id']; ?></h1>
            <p><?php echo htmlspecialchars($order['service_name']); ?></p>
        </div>
        <a href="index.php" class="steam-action-link">Вернуться на главную</a>
    </div>

    <!-- Карточка заказа -->
    <div class="steam-panel" data-animate="fade-up">
        <div class="order-detail">
            <div class="order-price" style="font-size:24px;font-weight:bold;color:var(--primary-color);margin-bottom:8px;">
                <?php echo number_format((float)$order['total_price'], 0, '.', ' '); ?> ₽
            </div>
            <?php if (!empty($order['service_name'])): ?>
                <div style="font-size:20px;font-weight:600;margin-bottom:8px;">
                    <?php echo htmlspecialchars($order['service_name']); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($order['client_comment'])): ?>
                <div style="font-size:14px;color:var(--muted-text-color);margin-bottom:12px;">
                    <?php echo nl2br(htmlspecialchars($order['client_comment'])); ?>
                </div>
            <?php endif; ?>
            <div style="font-size:14px;color:var(--muted-text-color);margin-bottom:6px;">
                Категория: <?php echo htmlspecialchars($order['category']); ?>
            </div>
            <?php if (!empty($order['city'])): ?>
                <div style="font-size:14px;color:var(--muted-text-color);margin-bottom:6px;">
                    Город: <?php echo htmlspecialchars($order['city']); ?>
                </div>
            <?php endif; ?>
            <div style="font-size:14px;color:var(--muted-text-color);margin-bottom:6px;">
                Статус: <?php echo htmlspecialchars($order['status_name']); ?>
            </div>
            <div style="font-size:14px;color:var(--muted-text-color);margin-bottom:6px;">
                Дата публикации: <?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($order['created_at']))); ?>
            </div>
            <div style="font-size:14px;color:var(--muted-text-color);margin-bottom:6px;">
                Откликов: <?php echo $responsesCount; ?>
            </div>

            <!-- Блок действий -->
            <div style="margin-top:20px;">
                <?php if ($isOwner): ?>
                    <!-- Для владельца: показать список откликов -->
                    <h3 class="response-section-title">Отклики исполнителей (<?php echo $responsesCount; ?>)</h3>
                    <?php if ($responsesCount === 0): ?>
                        <div class="empty-box">Пока нет откликов</div>
                    <?php else: ?>
                        <?php foreach ($responses as $resp): ?>
                            <div class="response-card <?php echo ($resp['status'] === 'accepted') ? 'response-card-selected' : ''; ?>">
                                <div class="response-card-head">
                                    <span class="response-name">
                                        <?php echo htmlspecialchars($resp['name'] !== '' ? $resp['name'] : $resp['login']); ?>
                                    </span>
                                    <span class="response-meta"><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($resp['created_at']))); ?></span>
                                </div>
                                <div class="response-meta">
                                    <?php if ($resp['proposed_price'] !== null): ?>
                                        Предложенная цена: <?php echo number_format((float)$resp['proposed_price'], 0, '.', ' '); ?> ₽
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($resp['comment'])): ?>
                                    <div class="response-message">Комментарий: <?php echo htmlspecialchars($resp['comment']); ?></div>
                                <?php endif; ?>
                                <div class="response-actions">
                                    <form method="get" action="cabinet.php" style="display:inline;">
                                        <input type="hidden" name="open_chat" value="1">
                                        <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                        <input type="hidden" name="response_id" value="<?php echo (int)$resp['id']; ?>">
                                        <button type="submit" class="steam-btn steam-btn-dark" style="font-size:13px;padding:6px 10px;">Открыть чат</button>
                                    </form>
                                    <?php if ($order['status_id'] == 1): ?>
                                        <form method="post" action="cabinet.php" style="display:inline;">
                                            <input type="hidden" name="choose_response" value="1">
                                            <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                            <input type="hidden" name="response_id" value="<?php echo (int)$resp['id']; ?>">
                                            <button type="submit" class="steam-btn steam-btn-primary" style="font-size:13px;padding:6px 10px;">Выбрать исполнителя</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Для исполнителя или гостя -->
                    <?php if ($currentUserId <= 0): ?>
                        <div class="empty-box">Чтобы откликнуться, <a href="login.php?redirect=<?php echo urlencode('order.php?id=' . $order['id']); ?>">войдите</a> или <a href="register.php">зарегистрируйтесь</a>.</div>
                    <?php elseif (!$isOpenForResponses): ?>
                        <div class="empty-box">Отклики на этот заказ закрыты.</div>
                    <?php elseif ($hasAlreadyResponded): ?>
                        <!-- Пользователь уже откликался. Предложить перейти в чат -->
                        <?php
                        // Найдем существующий чат
                        $chatId = 0;
                        if ($order['client_id'] > 0) {
                            $chatId = getOrCreateChat($conn, $order['id'], $order['client_id'], $currentUserId);
                        }
                        ?>
                        <div class="empty-box">Вы уже откликались на этот заказ. <a href="chat.php?chat_id=<?php echo $chatId; ?>">Перейти в чат</a>.</div>
                    <?php else: ?>
                        <!-- Кнопка отклика -->
                        <a href="respond.php?id=<?php echo (int)$order['id']; ?>" class="steam-btn steam-btn-primary">Откликнуться на заказ</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <!-- Footer -->
        <?php include 'footer.php'; ?>
    </div>
</body>
</html>