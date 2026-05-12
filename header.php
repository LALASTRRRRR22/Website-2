<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAuth = false;
$topbarLogin = 'Гость';
$topbarAvatar = 'images/icon-account.png';
$currentPage = basename($_SERVER['PHP_SELF']);

if (isset($_SESSION['user_id']) || isset($_SESSION['Id_U']) || (isset($_SESSION['login']) && trim($_SESSION['login']) !== '')) {
    $isAuth = true;
}
if (isset($_SESSION['login']) && trim($_SESSION['login']) !== '') {
    $topbarLogin = trim($_SESSION['login']);
}
if ($topbarLogin === 'Гость' && isset($_SESSION['username']) && trim($_SESSION['username']) !== '') {
    $topbarLogin = trim($_SESSION['username']);
}
if (isset($_SESSION['avatar']) && trim($_SESSION['avatar']) !== '') {
    $topbarAvatar = trim($_SESSION['avatar']);
}
?>
<div id="progress-bar"></div>
<div id="cursor-glow"></div>
<canvas id="particles-canvas"></canvas>

<div class="steam-topbar">
    <div class="steam-topbar-inner">
        <div class="steam-left">
            <a href="index.php" class="steam-logo">ДомУслуг</a>
            <a href="index.php" class="steam-link<?php echo $currentPage === 'index.php' ? ' steam-link-active' : ''; ?>">Заказы</a>
            <a href="index.php#categories" class="steam-link">Категории</a>
            <?php if ($isAuth): ?>
                <a href="cabinet.php" class="steam-link<?php echo ($currentPage === 'cabinet.php' || $currentPage === 'chat.php') ? ' steam-link-active' : ''; ?>">Кабинет</a>
            <?php endif; ?>
        </div>
        <div class="steam-right">
            <?php if ($isAuth): ?>
                <a href="create_order.php" class="steam-btn steam-btn-primary">Разместить заказ</a>
                <a href="cabinet.php" class="steam-userbox">
                    <img src="<?php echo htmlspecialchars($topbarAvatar); ?>" alt="" onerror="this.src='images/icon-account.png'">
                    <span><?php echo htmlspecialchars($topbarLogin); ?></span>
                </a>
                <a href="logout.php" class="steam-link">Выйти</a>
            <?php else: ?>
                <a href="login.php" class="steam-link<?php echo $currentPage === 'login.php' ? ' steam-link-active' : ''; ?>">Войти</a>
                <a href="register.php" class="steam-btn steam-btn-primary">Регистрация</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="js/main.js" defer></script>
