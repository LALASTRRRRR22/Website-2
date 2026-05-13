<?php
ob_start();
session_start();
require_once 'db.php';

$loginError    = '';
$registerError = '';
$registerOk    = '';
$mode          = 'login'; // current active panel

$redirect = isset($_GET['redirect']) ? trim($_GET['redirect']) : '';

/* ── Flash: registered successfully ─────────────────────── */
if (isset($_GET['registered']) && $_GET['registered'] == '1') {
    $registerOk = 'Регистрация прошла успешно. Теперь войди.';
}

/* ── Open register panel via GET ─────────────────────────── */
if (isset($_GET['mode']) && $_GET['mode'] === 'register') {
    $mode = 'register';
}

/* ── Handle POST ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authAction = isset($_POST['auth_action']) ? $_POST['auth_action'] : 'login';

    /* ---------- Login ---------- */
    if ($authAction === 'login') {
        $login    = isset($_POST['login'])    ? trim($_POST['login'])    : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';

        if ($login === '' || $password === '') {
            $loginError = 'Заполни логин и пароль';
        } else {
            $stmt = $conn->prepare('SELECT Id_U, login, password FROM Users WHERE login = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $login);
                $stmt->execute();
                $stmt->bind_result($userId, $dbLogin, $dbPassword);
                $found = $stmt->fetch();
                $stmt->close();

                if ($found) {
                    $isValid = false;
                    if (!empty($dbPassword)) {
                        if (preg_match('/^\$2[ayb]\$/', $dbPassword) === 1) {
                            $isValid = password_verify($password, $dbPassword);
                        } else {
                            $isValid = ($password === $dbPassword);
                        }
                    }
                    if ($isValid) {
                        $_SESSION['user_id'] = (int)$userId;
                        $_SESSION['Id_U']    = (int)$userId;
                        $_SESSION['login']   = $dbLogin;
                        $_SESSION['username']= $dbLogin;

                        // Load avatar into session
                        $stmtA = $conn->prepare('SELECT avatar FROM Users WHERE Id_U = ? LIMIT 1');
                        if ($stmtA) {
                            $stmtA->bind_param('i', $userId);
                            $stmtA->execute();
                            $stmtA->bind_result($dbAvatar);
                            if ($stmtA->fetch() && $dbAvatar !== null && $dbAvatar !== '') {
                                $_SESSION['avatar'] = $dbAvatar;
                            }
                            $stmtA->close();
                        }

                        if ($redirect !== '' && strpos($redirect, '://') === false && strpos($redirect, "\n") === false) {
                            header('Location: ' . $redirect);
                        } else {
                            header('Location: cabinet.php');
                        }
                        exit;
                    } else {
                        $loginError = 'Неверный пароль';
                    }
                } else {
                    $loginError = 'Пользователь не найден';
                }
            } else {
                $loginError = 'Ошибка запроса к базе';
            }
        }

    /* ---------- Register ---------- */
    } elseif ($authAction === 'register') {
        $mode      = 'register';
        $login     = isset($_POST['login'])     ? trim($_POST['login'])     : '';
        $password  = isset($_POST['password'])  ? trim($_POST['password'])  : '';
        $password2 = isset($_POST['password2']) ? trim($_POST['password2']) : '';

        if ($login === '' || $password === '' || $password2 === '') {
            $registerError = 'Заполни все поля';
        } elseif ($password !== $password2) {
            $registerError = 'Пароли не совпадают';
        } elseif (mb_strlen($password) < 4) {
            $registerError = 'Пароль должен быть не менее 4 символов';
        } else {
            $check = $conn->prepare('SELECT Id_U FROM Users WHERE login = ? LIMIT 1');
            if ($check) {
                $check->bind_param('s', $login);
                $check->execute();
                $check->bind_result($existsId);
                $alreadyExists = $check->fetch();
                $check->close();
                if ($alreadyExists) {
                    $registerError = 'Такой логин уже занят';
                }
            }

            if ($registerError === '') {
                $email        = '';
                $phone        = '';
                $role         = 'customer';
                $status       = 'active';
                $createdAt    = date('Y-m-d H:i:s');
                $avatar       = 'images/icon-account.png';
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);

                $stmt = $conn->prepare(
                    'INSERT INTO Users (login, password, email, phone, role, status, created_at, avatar) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                if ($stmt) {
                    $stmt->bind_param('ssssssss', $login, $passwordHash, $email, $phone, $role, $status, $createdAt, $avatar);
                    if ($stmt->execute()) {
                        header('Location: login.php?registered=1');
                        exit;
                    } else {
                        $registerError = 'Не удалось зарегистрироваться';
                    }
                    $stmt->close();
                } else {
                    $registerError = 'Ошибка запроса к базе';
                }
            }
        }
    }
}

$loginVal    = (isset($_POST['login']) && isset($_POST['auth_action']) && $_POST['auth_action'] === 'login')
    ? htmlspecialchars($_POST['login']) : '';
$regLoginVal = (isset($_POST['login']) && isset($_POST['auth_action']) && $_POST['auth_action'] === 'register')
    ? htmlspecialchars($_POST['login']) : '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ДомУслуг — Вход / Регистрация</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-page">
    <div id="progress-bar"></div>
    <div id="cursor-glow"></div>
    <canvas id="particles-canvas"></canvas>

    <div class="auth-container<?php echo $mode === 'register' ? ' right-panel-active' : ''; ?>" id="authContainer">

        <!-- ── Форма входа (левая половина) ───────────────── -->
        <div class="auth-form auth-form-sign-in">
            <h2>Вход</h2>
            <p>Войдите в аккаунт, чтобы размещать заказы и откликаться</p>
            <?php if ($loginError !== ''): ?>
                <div class="auth-alert auth-alert-error"><?php echo htmlspecialchars($loginError); ?></div>
            <?php endif; ?>
            <?php if ($registerOk !== ''): ?>
                <div class="auth-alert auth-alert-success"><?php echo htmlspecialchars($registerOk); ?></div>
            <?php endif; ?>
            <form action="login.php<?php echo $redirect !== '' ? '?redirect=' . urlencode($redirect) : ''; ?>" method="post" autocomplete="on">
                <input type="hidden" name="auth_action" value="login">
                <input type="text"     name="login"    class="auth-input" placeholder="Логин"  value="<?php echo $loginVal; ?>" autocomplete="username">
                <input type="password" name="password" class="auth-input" placeholder="Пароль" autocomplete="current-password">
                <button type="submit" class="auth-btn-submit">Войти</button>
            </form>
        </div>

        <!-- ── Форма регистрации (правая половина) ────────── -->
        <div class="auth-form auth-form-sign-up">
            <h2>Регистрация</h2>
            <p>Создайте аккаунт, чтобы публиковать и находить заказы</p>
            <?php if ($registerError !== ''): ?>
                <div class="auth-alert auth-alert-error"><?php echo htmlspecialchars($registerError); ?></div>
            <?php endif; ?>
            <form action="login.php" method="post" autocomplete="on">
                <input type="hidden" name="auth_action" value="register">
                <input type="text"     name="login"     class="auth-input" placeholder="Логин"              value="<?php echo $regLoginVal; ?>" autocomplete="username">
                <input type="password" name="password"  class="auth-input" placeholder="Пароль"             autocomplete="new-password">
                <input type="password" name="password2" class="auth-input" placeholder="Повторите пароль"   autocomplete="new-password">
                <button type="submit" class="auth-btn-submit">Зарегистрироваться</button>
            </form>
        </div>

        <!-- ── Скользящий оверлей (desktop) ──────────────── -->
        <div class="auth-overlay-container">
            <div class="auth-overlay">
                <!-- Показывается когда активна регистрация (оверлей слева) -->
                <div class="auth-overlay-panel auth-overlay-left">
                    <h2>Уже есть аккаунт?</h2>
                    <p>Войдите и продолжайте пользоваться сервисом бытовых услуг</p>
                    <button id="signInBtn" class="auth-switch-btn">Войти</button>
                </div>
                <!-- Показывается по умолчанию (оверлей справа) -->
                <div class="auth-overlay-panel auth-overlay-right">
                    <h2>Новый пользователь?</h2>
                    <p>Зарегистрируйтесь, чтобы публиковать заказы и откликаться</p>
                    <button id="signUpBtn" class="auth-switch-btn">Регистрация</button>
                </div>
            </div>
        </div>

        <!-- ── Мобильный переключатель (скрыт на desktop через CSS) -->
        <div class="auth-mobile-switch" style="display:none;position:absolute;bottom:20px;left:0;right:0;justify-content:center;gap:12px;z-index:10;">
            <button id="signUpBtnMobile"  class="auth-switch-btn-mobile">Регистрация</button>
            <button id="signInBtnMobile"  class="auth-switch-btn-mobile">Уже есть аккаунт</button>
        </div>

    </div><!-- /.auth-container -->

    <script src="js/main.js" defer></script>
</body>
</html>
