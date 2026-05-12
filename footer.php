<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentUserIdForFooter = 0;
if (isset($_SESSION['user_id'])) {
    $currentUserIdForFooter = (int)$_SESSION['user_id'];
} elseif (isset($_SESSION['Id_U'])) {
    $currentUserIdForFooter = (int)$_SESSION['Id_U'];
} elseif (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    if (isset($_SESSION['user']['Id_U'])) {
        $currentUserIdForFooter = (int)$_SESSION['user']['Id_U'];
    } elseif (isset($_SESSION['user']['id'])) {
        $currentUserIdForFooter = (int)$_SESSION['user']['id'];
    }
}
?>
<footer class="site-footer" data-animate="fade-up">
    <div class="footer-inner">
        <div class="footer-col footer-col-brand">
            <div class="footer-logo">ДомУслуг</div>
            <p class="footer-desc">Площадка для поиска исполнителей бытовых задач и создания заказов. Быстро, удобно, надёжно.</p>
        </div>
        <div class="footer-col">
            <h3 class="footer-heading">Разделы</h3>
            <ul class="footer-links">
                <li><a href="index.php">Заказы</a></li>
                <li><a href="create_order.php">Разместить заказ</a></li>
                <li><a href="cabinet.php">Кабинет</a></li>
                <li><a href="chat.php">Чаты</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h3 class="footer-heading">Аккаунт</h3>
            <ul class="footer-links">
                <li><a href="login.php">Войти</a></li>
                <li><a href="register.php">Регистрация</a></li>
                <?php if ($currentUserIdForFooter > 0): ?>
                    <li><a href="profile.php?id=<?php echo $currentUserIdForFooter; ?>">Профиль</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="footer-col">
            <h3 class="footer-heading">О проекте</h3>
            <p class="footer-desc">Учебный проект: разработка сайта по предмету.</p>
        </div>
    </div>
    <div style="border-top:1px solid rgba(255,255,255,0.08); padding-top:24px; text-align:center; font-size:0.8rem; color:rgba(241,245,249,0.3);">
        &copy; <?php echo date('Y'); ?> ДомУслуг. Все права защищены.
    </div>
</footer>
