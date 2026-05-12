<?php
ob_start();
session_start();
require_once 'db.php';
// Общие функции: получение пользователя, проверка откликов, создание чата
require_once __DIR__ . '/includes/functions.php';



$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$order = null;
$success = '';
$error = '';
$userId = getCurrentUserId($conn);

if ($orderId > 0) {
    $stmt = $conn->prepare('SELECT id, total_price, service_name, client_comment, created_at FROM orders WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $stmt->bind_result($id, $totalPrice, $serviceName, $clientComment, $createdAt);

        if ($stmt->fetch()) {
            $order = array(
                'id' => $id,
                'total_price' => $totalPrice,
                'service_name' => $serviceName,
                'client_comment' => $clientComment,
                'created_at' => $createdAt
            );
        }

        $stmt->close();
    }
}

if (!$order) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($userId <= 0) {
        // Гостя перенаправляем на форму входа с возвратом на эту страницу
        header('Location: login.php?redirect=' . urlencode('respond.php?id=' . $orderId));
        exit;
    }
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    // Предложенная цена может быть пустой или числом
    $proposedPrice = null;
    if (isset($_POST['proposed_price']) && $_POST['proposed_price'] !== '') {
        $proposedPrice = floatval($_POST['proposed_price']);
        if ($proposedPrice < 0) {
            $error = 'Предложенная цена должна быть положительным числом';
        }
    }

    if ($name === '' || $phone === '') {
        $error = 'Заполни имя и телефон';
    } elseif (!canRespondToOrder($conn, $orderId, $userId)) {
        // Проверяем, что пользователь не владелец заказа и ещё не откликался
        $error = 'Вы уже откликались на этот заказ или вы являетесь его владельцем';
    } else {
        $stmt = $conn->prepare('INSERT INTO responses (order_id, user_id, name, phone, comment, proposed_price, status) VALUES (?, ?, ?, ?, ?, ?, "pending")');
        if ($stmt) {
            // Если предложенная цена отсутствует, передаем NULL
            if ($proposedPrice === null) {
                // bind_param требует указать параметр типа double, поэтому используем null
                $stmt->bind_param('iisssd', $orderId, $userId, $name, $phone, $comment, $proposedPrice);
            } else {
                $stmt->bind_param('iisssd', $orderId, $userId, $name, $phone, $comment, $proposedPrice);
            }
            if ($stmt->execute()) {
                // После сохранения отклика создаём чат между заказчиком и исполнителем
                // Узнаём client_id (владелец заказа)
                $clientId = 0;
                $stOwner = $conn->prepare('SELECT client_id FROM orders WHERE id = ? LIMIT 1');
                if ($stOwner) {
                    $stOwner->bind_param('i', $orderId);
                    $stOwner->execute();
                    $stOwner->bind_result($foundClientId);
                    if ($stOwner->fetch()) {
                        $clientId = (int)$foundClientId;
                    }
                    $stOwner->close();
                }
                if ($clientId > 0) {
                    getOrCreateChat($conn, $orderId, $clientId, $userId);
                }
                $success = 'Отклик отправлен и записан в базу данных';
            } else {
                $error = 'Не удалось записать отклик в базу';
            }
            $stmt->close();
        } else {
            $error = 'Ошибка подготовки запроса';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отклик на заказ</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="general-bg">
<?php include 'header.php'; ?>

<div class="page-shell">
    <div class="page-header" data-animate="fade-up">
        <div>
            <h1>Отклик на заказ #<?php echo (int)$order['id']; ?></h1>
            <p>Заполни данные, и отклик сохранится в базу</p>
        </div>
    </div>

    <?php if ($success !== ''): ?>
        <div class="steam-alert steam-alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="steam-alert steam-alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="steam-panel respond-layout" data-animate="fade-up">
        <div class="respond-order-box">
            <div class="respond-order-price"><?php echo number_format((float)$order['total_price'], 0, '.', ' '); ?> ₽</div>
            <?php if (!empty($order['service_name'])): ?>
                <div class="respond-order-title"><?php echo htmlspecialchars($order['service_name']); ?></div>
            <?php endif; ?>
            <?php if (!empty($order['client_comment'])): ?>
                <div class="respond-order-text"><?php echo htmlspecialchars($order['client_comment']); ?></div>
            <?php endif; ?>
            <div class="respond-order-date"><?php echo htmlspecialchars(date('d.m.Y', strtotime($order['created_at']))); ?></div>
        </div>

        <?php if ($userId <= 0): ?>
            <div class="guest-lock-box">
                <h3>Чтобы откликнуться, сначала войди в аккаунт</h3>
                <p>Только зарегистрированные пользователи могут отправлять отклики и участвовать в чате с заказчиком.</p>
                <div class="guest-lock-actions">
                    <a href="login.php?redirect=<?php echo urlencode('respond.php?id=' . (int)$order['id']); ?>" class="steam-btn steam-btn-primary">Войти</a>
                    <a href="register.php" class="steam-btn steam-btn-dark">Регистрация</a>
                </div>
            </div>
        <?php else: ?>
        <form action="respond.php?id=<?php echo (int)$order['id']; ?>" method="post" class="respond-form">
            <div class="respond-form-grid">
                <div>
                    <label class="steam-label">Ваше имя</label>
                    <input type="text" name="name" class="steam-input" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : (isset($_SESSION['login']) ? htmlspecialchars($_SESSION['login']) : ''); ?>">
                </div>

                <div>
                    <label class="steam-label">Телефон</label>
                    <input type="text" name="phone" class="steam-input" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                </div>

                <div>
                    <label class="steam-label">Предложенная цена (₽)</label>
                    <input type="number" name="proposed_price" step="0.01" min="0" class="steam-input" value="<?php echo isset($_POST['proposed_price']) ? htmlspecialchars($_POST['proposed_price']) : ''; ?>" placeholder="Например, 2000">
                </div>

                <div class="respond-form-full">
                    <label class="steam-label">Комментарий</label>
                    <textarea name="comment" rows="6" class="steam-input"><?php echo isset($_POST['comment']) ? htmlspecialchars($_POST['comment']) : ''; ?></textarea>
                </div>
            </div>

            <button type="submit" class="steam-btn steam-btn-primary">Отправить отклик</button>
        </form>
        <?php endif; ?>
        <!-- Footer -->
        <?php include 'footer.php'; ?>
    </div>
</body>
</html>