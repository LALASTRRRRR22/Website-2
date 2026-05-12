<?php
session_start();
require_once 'db.php';

// Определяем, авторизован ли пользователь, чтобы показать кнопку "Разместить заказ"
$isAuth = false;
if (isset($_SESSION['user_id']) || isset($_SESSION['Id_U']) || (isset($_SESSION['login']) && trim($_SESSION['login']) !== '')) {
    $isAuth = true;
}

// Список категорий для отображения на главной
$categories = array(
    'Ремонт',
    'Сантехника',
    'Электрика',
    'Уборка',
    'Доставка',
    'Сборка мебели',
    'Компьютерная помощь',
    'Сад и участок',
    'Другое'
);

// Статистика для раздела доверия (количество заказов, пользователей и откликов)
$ordersCount = 0;
$usersCount = 0;
$responsesCount = 0;
// Подсчет количества заказов
$resultCountOrders = $conn->query('SELECT COUNT(*) AS cnt FROM orders');
if ($resultCountOrders) {
    $row = $resultCountOrders->fetch_assoc();
    $ordersCount = (int)$row['cnt'];
}
// Подсчет количества пользователей
$resultCountUsers = $conn->query('SELECT COUNT(*) AS cnt FROM Users');
if ($resultCountUsers) {
    $row = $resultCountUsers->fetch_assoc();
    $usersCount = (int)$row['cnt'];
}
// Подсчет количества откликов
$resultCountResponses = $conn->query('SELECT COUNT(*) AS cnt FROM responses');
if ($resultCountResponses) {
    $row = $resultCountResponses->fetch_assoc();
    $responsesCount = (int)$row['cnt'];
}

// Фильтрация по запросу и категории
$orders = array();
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterCategory = isset($_GET['category']) ? trim($_GET['category']) : '';

// Массив для хранения условий и параметров
$conditions = array();
$paramTypes = '';
$params = array();

if ($q !== '') {
    $conditions[] = '(service_name LIKE CONCAT("%", ?, "%") OR client_comment LIKE CONCAT("%", ?, "%"))';
    $paramTypes .= 'ss';
    $params[] = $q;
    $params[] = $q;
}

if ($filterCategory !== '' && in_array($filterCategory, $categories, true)) {
    $conditions[] = 'category = ?';
    $paramTypes .= 's';
    $params[] = $filterCategory;
}

// Составляем запрос
$sql = 'SELECT id, total_price, service_name, client_comment, category, city, urgency, created_at FROM orders';
if (!empty($conditions)) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY id DESC';

// Выполняем запрос
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // Подготавливаем параметры для bind_param
        $bindValues = array();
        $bindValues[] = $paramTypes;
        foreach ($params as $idx => $value) {
            $bindValues[] = &$params[$idx];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bindValues);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        $stmt->close();
    }
} else {
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ДомУслуг — Главная</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'header.php'; ?>

<div class="page-shell">
    <!-- Hero -->
    <div class="page-header hero" data-animate="fade-up">
        <div class="hero-orb hero-orb-1"></div>
        <div class="hero-orb hero-orb-2"></div>
        <div class="hero-orb hero-orb-3"></div>
        <div style="position:relative;z-index:1;">
            <h1>Найдите исполнителя для бытовой задачи</h1>
            <p>Создайте заказ, получите отклики и выберите подходящего исполнителя</p>
        </div>
    </div>
    <div class="steam-panel hero-search-panel" data-animate="fade-up">
        <form method="get" action="index.php" class="steam-form hero-search" style="display:flex; flex-wrap: wrap; gap: 12px; align-items: center;">
            <input type="text" name="q" class="steam-input hero-input" placeholder="Поиск услуг или заказов" value="<?php echo htmlspecialchars($q); ?>" style="flex: 1;">
            <button type="submit" class="steam-btn steam-btn-primary hero-search-btn">Найти</button>
            <?php if ($isAuth): ?>
                <a href="create_order.php" class="steam-btn steam-btn-dark hero-create-btn">Разместить заказ</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Категории услуг -->
    <div id="categories" class="steam-panel categories-section" data-animate="fade-up">
        <h2 class="section-title">Категории услуг</h2>
        <div class="public-order-grid categories-grid">
            <?php foreach ($categories as $idx => $cat): ?>
                <?php
                    // Формируем ссылку на фильтрацию по категории
                    $catUrl = 'index.php?category=' . urlencode($cat);
                    if ($q !== '') {
                        $catUrl .= '&q=' . urlencode($q);
                    }
                    $delay = ($idx % 3) + 1;
                ?>
                <a href="<?php echo $catUrl; ?>" class="public-order-card category-card" data-animate="fade-up" data-animate-delay="<?php echo $delay; ?>">
                    <div class="public-order-title category-name">
                        <?php echo htmlspecialchars($cat); ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Список заказов -->
    <div class="steam-panel orders-section" data-animate="fade-up">
        <h2 class="section-title">Свежие заказы</h2>
        <?php if (count($orders) === 0): ?>
            <div class="empty-box">Пока нет заказов. Станьте первым, кто разместит задачу.</div>
        <?php else: ?>
            <div class="public-order-grid orders-grid">
            <?php foreach ($orders as $idx => $order): ?>
                <?php $delay = ($idx % 3) + 1; ?>
                <div class="public-order-card" data-animate="fade-up" data-animate-delay="<?php echo $delay; ?>">
                    <!-- Цена -->
                    <div class="public-order-price"><?php echo number_format((float)$order['total_price'], 0, '.', ' '); ?> ₽</div>
                    <!-- Название -->
                    <?php if (!empty($order['service_name'])): ?>
                        <div class="public-order-title"><?php echo htmlspecialchars($order['service_name']); ?></div>
                    <?php endif; ?>
                    <!-- Категория -->
                    <?php if (!empty($order['category'])): ?>
                        <div class="public-order-text" style="font-weight:600;color:var(--primary-color);">
                            <?php echo htmlspecialchars($order['category']); ?>
                        </div>
                    <?php endif; ?>
                    <!-- Описание -->
                    <?php if (!empty($order['client_comment'])): ?>
                        <div class="public-order-text"><?php echo htmlspecialchars($order['client_comment']); ?></div>
                    <?php endif; ?>
                    <!-- Город -->
                    <?php if (!empty($order['city'])): ?>
                        <div class="public-order-date"><?php echo htmlspecialchars($order['city']); ?></div>
                    <?php endif; ?>
                    <!-- Дата создания -->
                    <div class="public-order-date"><?php echo htmlspecialchars(date('d.m.Y', strtotime($order['created_at']))); ?></div>
                    <!-- Кнопка подробнее -->
                    <a href="order.php?id=<?php echo (int)$order['id']; ?>" class="steam-btn steam-btn-primary public-order-btn">Подробнее</a>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Как это работает -->
    <div class="steam-panel how-section" data-animate="fade-up">
        <h2 class="section-title">Как это работает</h2>
        <div class="how-grid">
            <div class="how-step" data-animate="fade-up" data-animate-delay="1">
                <h3 class="how-step-title">1. Зарегистрируйтесь</h3>
                <p class="how-step-text">Создайте аккаунт, чтобы разместить заказ или откликнуться на задачу.</p>
            </div>
            <div class="how-step" data-animate="fade-up" data-animate-delay="2">
                <h3 class="how-step-title">2. Разместите заказ</h3>
                <p class="how-step-text">Опишите задачу, укажите бюджет, город и срочность.</p>
            </div>
            <div class="how-step" data-animate="fade-up" data-animate-delay="3">
                <h3 class="how-step-title">3. Получите отклики</h3>
                <p class="how-step-text">Исполнители отправят вам свои предложения и цену.</p>
            </div>
            <div class="how-step" data-animate="fade-up" data-animate-delay="1">
                <h3 class="how-step-title">4. Выберите исполнителя</h3>
                <p class="how-step-text">Сравните отклики и выберите лучшее предложение.</p>
            </div>
            <div class="how-step" data-animate="fade-up" data-animate-delay="2">
                <h3 class="how-step-title">5. Общайтесь в чате</h3>
                <p class="how-step-text">Уточните детали и договоритесь о встрече прямо в чате.</p>
            </div>
            <div class="how-step" data-animate="fade-up" data-animate-delay="3">
                <h3 class="how-step-title">6. Завершите заказ</h3>
                <p class="how-step-text">Закройте заказ и оставьте отзыв о выполненной работе.</p>
            </div>
        </div>
    </div>

    <!-- Преимущества сервиса -->
    <div class="steam-panel advantages-section" data-animate="fade-up">
        <h2 class="section-title">Преимущества сервиса</h2>
        <div class="adv-list">
            <div class="adv-card" data-animate="fade-up" data-animate-delay="1">
                <h3 class="adv-title">Быстрый поиск исполнителей</h3>
                <p class="adv-text">Мгновенно находите подходящих специалистов в вашем городе.</p>
            </div>
            <div class="adv-card" data-animate="fade-up" data-animate-delay="2">
                <h3 class="adv-title">Прямой чат</h3>
                <p class="adv-text">Общайтесь напрямую с исполнителями и обсуждайте детали заказа.</p>
            </div>
            <div class="adv-card" data-animate="fade-up" data-animate-delay="3">
                <h3 class="adv-title">Удобные отклики</h3>
                <p class="adv-text">Получайте несколько предложений и выбирайте лучшее по цене и срокам.</p>
            </div>
            <div class="adv-card" data-animate="fade-up" data-animate-delay="1">
                <h3 class="adv-title">Рейтинги и отзывы</h3>
                <p class="adv-text">Смотрите рейтинг исполнителей и отзывы клиентов.</p>
            </div>
            <div class="adv-card" data-animate="fade-up" data-animate-delay="2">
                <h3 class="adv-title">Простое создание заказа</h3>
                <p class="adv-text">Заполните понятную форму и опубликуйте заказ в пару кликов.</p>
            </div>
            <div class="adv-card" data-animate="fade-up" data-animate-delay="3">
                <h3 class="adv-title">Адаптивный интерфейс</h3>
                <p class="adv-text">Наш сайт удобно использовать на компьютере, планшете и телефоне.</p>
            </div>
        </div>
    </div>

    <!-- Блок статистики -->
    <div class="steam-panel stats-section" data-animate="fade-up">
        <h2 class="section-title">Нам доверяют</h2>
        <div class="stats-grid">
            <div class="stat-item" data-animate="fade-up" data-animate-delay="1">
                <div class="stat-value"><?php echo number_format($ordersCount, 0, '.', ' '); ?>+</div>
                <div class="stat-label">размещенных заказов</div>
            </div>
            <div class="stat-item" data-animate="fade-up" data-animate-delay="2">
                <div class="stat-value"><?php echo number_format($usersCount, 0, '.', ' '); ?>+</div>
                <div class="stat-label">зарегистрированных пользователей</div>
            </div>
            <div class="stat-item" data-animate="fade-up" data-animate-delay="3">
                <div class="stat-value"><?php echo number_format($responsesCount, 0, '.', ' '); ?>+</div>
                <div class="stat-label">откликов</div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'footer.php'; ?>
</div>
</body>
</html>