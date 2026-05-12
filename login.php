<?php
ob_start();
session_start();
require_once 'db.php';

$error = '';
$success = '';
$redirect = isset($_GET['redirect']) ? trim($_GET['redirect']) : '';

if (isset($_GET['registered']) && $_GET['registered'] == '1') {
    $success = 'Регистрация прошла успешно. Теперь войди.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = isset($_POST['login']) ? trim($_POST['login']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if ($login === '' || $password === '') {
        $error = 'Заполни логин и пароль';
    } else {
        $stmt = $conn->prepare('SELECT Id_U, login, password FROM Users WHERE login = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $login);
            $stmt->execute();
            $stmt->bind_result($userId, $dbLogin, $dbPassword);

            if ($stmt->fetch()) {
                // Проверяем пароль. Используем password_verify для хэшированных паролей,
                // а также поддерживаем старые записи, где пароль сохранялся в открытом виде.
                $isValid = false;
                if (!empty($dbPassword)) {
                    // Если пароль выглядит как хэш ($2y$/$2a$), проверяем через password_verify
                    if (preg_match('/^\$2[ayb]\$/', $dbPassword) === 1) {
                        $isValid = password_verify($password, $dbPassword);
                    } else {
                        // Иначе сравниваем напрямую (для старых записей)
                        $isValid = ($password === $dbPassword);
                    }
                }
                if ($isValid) {
                    $_SESSION['user_id'] = (int)$userId;
                    $_SESSION['Id_U'] = (int)$userId;
                    $_SESSION['login'] = $dbLogin;
                    $_SESSION['username'] = $dbLogin;

                    $stmt->close();
                    if ($redirect !== '' && strpos($redirect, '://') === false && strpos($redirect, "\n") === false) {
                        header('Location: ' . $redirect);
                    } else {
                        header('Location: cabinet.php');
                    }
                    exit;
                } else {
                    $error = 'Неверный пароль';
                }
            } else {
                $error = 'Пользователь не найден';
            }

            $stmt->close();
        } else {
            $error = 'Ошибка запроса к базе';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ДомУслуг — Вход</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-page">
    <div id="progress-bar"></div>
    <div id="cursor-glow"></div>
    <canvas id="particles-canvas"></canvas>
    <script src="js/main.js" defer></script>
    <div class="auth-container<?php echo (isset($_GET['mode']) && $_GET['mode'] === 'register') ? ' right-panel-active' : ''; ?>">
        <!-- Форма входа -->
        <div class="auth-form auth-form-sign-in">
            <h2>Вход</h2>
            <p>Войдите в аккаунт, чтобы размещать заказы и откликаться</p>
            <?php if ($error !== ''): ?>
                <div class="auth-alert auth-alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success !== ''): ?>
                <div class="auth-alert auth-alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <form action="login.php<?php echo $redirect !== '' ? '?redirect=' . urlencode($redirect) : ''; ?>" method="post">
                <input type="text" name="login" class="auth-input" placeholder="Логин" value="<?php echo isset($_POST['login']) ? htmlspecialchars($_POST['login']) : ''; ?>">
                <input type="password" name="password" class="auth-input" placeholder="Пароль">
                <button type="submit" class="auth-btn-submit">Войти</button>
            </form>
        </div>
        <!-- Форма регистрации -->
        <div class="auth-form auth-form-sign-up">
            <h2>Регистрация</h2>
            <p>Создайте аккаунт, чтобы публиковать и находить заказы</p>
            <form action="register.php" method="post">
                <input type="text" name="login" class="auth-input" placeholder="Логин" value="<?php echo isset($_POST['login']) ? htmlspecialchars($_POST['login']) : ''; ?>">
                <input type="password" name="password" class="auth-input" placeholder="Пароль">
                <input type="password" name="password2" class="auth-input" placeholder="Повторите пароль">
                <button type="submit" class="auth-btn-submit">Зарегистрироваться</button>
            </form>
        </div>
        <!-- Оверлей -->
        <div class="auth-overlay-container">
            <div class="auth-overlay">
                <div class="auth-overlay-panel auth-overlay-left">
                    <h2>Уже есть аккаунт?</h2>
                    <p>Войдите и продолжайте пользоваться сервисом бытовых услуг</p>
                    <button id="signInBtn" class="auth-switch-btn">Войти</button>
                </div>
                <div class="auth-overlay-panel auth-overlay-right">
                    <h2>Новый пользователь?</h2>
                    <p>Зарегистрируйтесь, чтобы публиковать заказы и оставлять отклики</p>
                    <button id="signUpBtn" class="auth-switch-btn">Регистрация</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>