<?php
ob_start();
session_start();
require_once 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = isset($_POST['login']) ? trim($_POST['login']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $password2 = isset($_POST['password2']) ? trim($_POST['password2']) : '';

    if ($login === '' || $password === '' || $password2 === '') {
        $error = 'Заполни все поля';
    } elseif ($password !== $password2) {
        $error = 'Пароли не совпадают';
    } else {
        $check = $conn->prepare('SELECT Id_U FROM Users WHERE login = ? LIMIT 1');
        if ($check) {
            $check->bind_param('s', $login);
            $check->execute();
            $check->bind_result($existsId);

            if ($check->fetch()) {
                $error = 'Такой логин уже существует';
            }

            $check->close();
        }

        if ($error === '') {
            $email = '';
            $phone = '';
            $role = 'customer';
            $status = 'active';
            $createdAt = date('Y-m-d H:i:s');
            $avatar = 'images/icon-account.png';

            // При регистрации используем хэширование пароля.
            // Пароли нельзя хранить в открытом виде, поэтому
            // преобразуем его с помощью password_hash().
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $conn->prepare('INSERT INTO Users (login, password, email, phone, role, status, created_at, avatar) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            if ($stmt) {
                $stmt->bind_param('ssssssss', $login, $passwordHash, $email, $phone, $role, $status, $createdAt, $avatar);

                if ($stmt->execute()) {
                    header('Location: login.php?registered=1');
                    exit;
                } else {
                    $error = 'Не удалось зарегистрироваться';
                }

                $stmt->close();
            } else {
                $error = 'Ошибка запроса к базе';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ДомУслуг — Регистрация</title>
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
    <div class="auth-container right-panel-active">
        <!-- Форма входа -->
        <div class="auth-form auth-form-sign-in">
            <h2>Вход</h2>
            <p>Войдите в аккаунт, чтобы размещать заказы и откликаться</p>
            <form action="login.php" method="post">
                <input type="text" name="login" class="auth-input" placeholder="Логин">
                <input type="password" name="password" class="auth-input" placeholder="Пароль">
                <button type="submit" class="auth-btn-submit">Войти</button>
            </form>
        </div>
        <!-- Форма регистрации -->
        <div class="auth-form auth-form-sign-up">
            <h2>Регистрация</h2>
            <p>Создайте аккаунт, чтобы публиковать и находить заказы</p>
            <?php if ($error !== ''): ?>
                <div class="auth-alert auth-alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success !== ''): ?>
                <div class="auth-alert auth-alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
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