<?php
ob_start();
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

$currentUserId = resolveUserId($conn);
if ($currentUserId <= 0) {
    header('Location: login.php');
    exit;
}

$chatId = isset($_GET['chat_id']) ? (int)$_GET['chat_id'] : 0;
if ($chatId <= 0) {
    die('Чат не найден');
}

$chat = null;
$error = '';
$success = '';

$stmt = $conn->prepare('SELECT id, order_id, client_id, worker_id FROM order_chats WHERE id = ? LIMIT 1');
if (!$stmt) {
    die('Ошибка подготовки запроса к order_chats: ' . $conn->error);
}
$stmt->bind_param('i', $chatId);
$stmt->execute();
$stmt->bind_result($id, $orderId, $clientId, $workerId);

if ($stmt->fetch()) {
    $chat = array(
        'id' => $id,
        'order_id' => $orderId,
        'client_id' => $clientId,
        'worker_id' => $workerId
    );
}
$stmt->close();

if (!$chat) {
    die('Чат не найден');
}

if ($currentUserId !== (int)$chat['client_id'] && $currentUserId !== (int)$chat['worker_id']) {
    die('У тебя нет доступа к этому чату');
}

$partnerId = ($currentUserId == (int)$chat['client_id']) ? (int)$chat['worker_id'] : (int)$chat['client_id'];
$partnerLogin = 'Пользователь';

$stmt = $conn->prepare('SELECT login FROM Users WHERE Id_U = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $partnerId);
    $stmt->execute();
    $stmt->bind_result($dbPartnerLogin);
    if ($stmt->fetch()) {
        $partnerLogin = $dbPartnerLogin;
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $messageText = isset($_POST['message_text']) ? trim($_POST['message_text']) : '';

    if ($messageText === '') {
        $error = 'Введите сообщение';
    } else {
        $stmt = $conn->prepare('INSERT INTO chat_messages (chat_id, sender_id, message) VALUES (?, ?, ?)');
        if (!$stmt) {
            $error = 'Ошибка подготовки INSERT: ' . $conn->error;
        } else {
            $stmt->bind_param('iis', $chatId, $currentUserId, $messageText);
            if ($stmt->execute()) {
                header('Location: chat.php?chat_id=' . $chatId);
                exit;
            } else {
                $error = 'Ошибка сохранения сообщения: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

$messages = array();

$stmt = $conn->prepare('
    SELECT cm.id, cm.sender_id, cm.message, cm.created_at, u.login
    FROM chat_messages cm
    LEFT JOIN Users u ON u.Id_U = cm.sender_id
    WHERE cm.chat_id = ?
    ORDER BY cm.created_at ASC, cm.id ASC
');
if (!$stmt) {
    die('Ошибка подготовки выборки сообщений: ' . $conn->error);
}
$stmt->bind_param('i', $chatId);
$stmt->execute();
$stmt->bind_result($msgId, $senderId, $messageText, $createdAt, $senderLogin);

while ($stmt->fetch()) {
    $messages[] = array(
        'id' => $msgId,
        'sender_id' => $senderId,
        'message_text' => $messageText,
        'created_at' => $createdAt,
        'sender_login' => $senderLogin
    );
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Чат</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'header.php'; ?>

<div class="page-shell">
    <div class="page-header">
        <div>
            <h1>Чат по заказу #<?php echo (int)$chat['order_id']; ?></h1>
            <p>Собеседник: <?php echo htmlspecialchars($partnerLogin); ?></p>
        </div>
        <a href="cabinet.php" class="steam-action-link">Назад в кабинет</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="steam-alert steam-alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="steam-alert steam-alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="steam-panel">
        <div class="chat-messages">
            <?php if (count($messages) === 0): ?>
                <div class="empty-box">Сообщений пока нет</div>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <?php $isMine = ((int)$message['sender_id'] === (int)$currentUserId); ?>
                    <div class="chat-message <?php echo $isMine ? 'sent' : 'received'; ?>">
                        <div class="chat-message-author">
                            <?php echo htmlspecialchars($message['sender_login'] ? $message['sender_login'] : 'Пользователь'); ?>
                            <span class="chat-message-time"><?php echo htmlspecialchars($message['created_at']); ?></span>
                        </div>
                        <div class="chat-message-text"><?php echo nl2br(htmlspecialchars($message['message_text'])); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <form action="chat.php?chat_id=<?php echo (int)$chatId; ?>" method="post" class="chat-input">
            <textarea name="message_text" class="steam-input" placeholder="Введите сообщение..."></textarea>
            <button type="submit" name="send_message" value="1" class="steam-btn steam-btn-primary">Отправить</button>
        </form>
        <!-- Footer -->
        <?php include 'footer.php'; ?>
    </div>
</body>
</html>