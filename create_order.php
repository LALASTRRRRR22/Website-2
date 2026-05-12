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

$userId = resolveUserId($conn);

if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

// In this version of the create order page we no longer fetch a list of predefined
// services. Previously the customer selected one or more services from a list and
// optionally provided a date. The client has requested that the order creator
// instead specify the service name, description and price manually. To support
// this behaviour we no longer query the `services` table here.
$services = array();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Разместить заказ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'header.php'; ?>

<div class="page-shell">
    <div class="page-header" data-animate="fade-up">
        <div>
            <h1>Разместить заказ</h1>
            <p>Опишите задачу, чтобы исполнители могли помочь</p>
        </div>
        <a href="cabinet.php" class="steam-action-link">Вернуться в профиль</a>
    </div>

    <?php if (isset($_GET['error']) && $_GET['error'] !== ''): ?>
        <div class="steam-alert steam-alert-error"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <form action="save_order.php" method="post" class="steam-form" data-animate="fade-up">
        <div class="steam-panel steam-panel-large">
            <h2>Детали заказа</h2>

            <label class="steam-label">Название заказа</label>
            <input type="text" name="service_name" class="steam-input" placeholder="Например, починить кран" required>

            <label class="steam-label">Категория</label>
            <select name="category" class="steam-input" required>
                <option value="">Выберите категорию</option>
                <option value="Ремонт">Ремонт</option>
                <option value="Сантехника">Сантехника</option>
                <option value="Электрика">Электрика</option>
                <option value="Уборка">Уборка</option>
                <option value="Доставка">Доставка</option>
                <option value="Сборка мебели">Сборка мебели</option>
                <option value="Компьютерная помощь">Компьютерная помощь</option>
                <option value="Сад и участок">Сад и участок</option>
                <option value="Другое">Другое</option>
            </select>

            <label class="steam-label">Описание</label>
            <textarea name="client_comment" rows="6" class="steam-input" placeholder="Опишите, что нужно сделать, где и какие детали" required></textarea>

            <label class="steam-label">Цена (₽)</label>
            <input type="number" name="price" min="0" step="0.01" class="steam-input" placeholder="Например, 2500" required>

            <label class="steam-label">Город/район</label>
            <input type="text" name="city" class="steam-input" placeholder="Например, Москва, район Южное Бутово">

            <input type="hidden" name="urgency" value="">
            <input type="hidden" name="address_id" value="0">

            <button type="submit" class="steam-btn steam-btn-primary steam-btn-block">Опубликовать заказ</button>
        </div>
    </form>
    <!-- Footer -->
    <?php include 'footer.php'; ?>
</div>
</body>
</html>