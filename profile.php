<?php
session_start();
require_once 'db.php';

$profileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$profile = null;

if ($profileId > 0) {
    $stmt = $conn->prepare('SELECT Id_U, login, email, phone, role, status, avatar FROM Users WHERE Id_U = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $profileId);
        $stmt->execute();
        $stmt->bind_result($id, $login, $email, $phone, $role, $status, $avatar);

        if ($stmt->fetch()) {
            $profile = array(
                'id' => $id,
                'login' => $login,
                'email' => $email,
                'phone' => $phone,
                'role' => $role,
                'status' => $status,
                'avatar' => ($avatar !== null && $avatar !== '') ? $avatar : 'images/icon-account.png'
            );
        }

        $stmt->close();
    }
}

if (!$profile) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ДомУслуг — Профиль</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'header.php'; ?>

<div class="page-shell">
    <div class="profile-hero">
        <div class="profile-hero-left">
            <div class="profile-avatar-wrap">
                <img src="<?php echo htmlspecialchars($profile['avatar']); ?>" alt="" class="profile-avatar-large" onerror="this.src='images/icon-account.png'">
            </div>
            <div class="profile-meta">
                <h1><?php echo htmlspecialchars($profile['login']); ?></h1>
                <div class="profile-subline">Роль: <?php echo htmlspecialchars($profile['role']); ?></div>
                <div class="profile-subline">Статус: <?php echo htmlspecialchars($profile['status']); ?></div>
                <div class="profile-subline">Email: <?php echo htmlspecialchars($profile['email']); ?></div>
                <div class="profile-subline">Телефон: <?php echo htmlspecialchars($profile['phone']); ?></div>
            </div>
        </div>
    </div>
    <!-- Footer -->
    <?php include 'footer.php'; ?>
</div>
</body>
</html>