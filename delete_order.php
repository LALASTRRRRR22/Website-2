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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cabinet.php');
    exit;
}

$userId = resolveUserId($conn);

if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

if ($orderId <= 0) {
    header('Location: cabinet.php');
    exit;
}

$check = $conn->prepare('SELECT id FROM orders WHERE id = ? AND client_id = ? LIMIT 1');

if ($check) {
    $check->bind_param('ii', $orderId, $userId);
    $check->execute();
    $check->bind_result($foundOrder);

    if ($check->fetch()) {
        $check->close();

        $delete = $conn->prepare('DELETE FROM orders WHERE id = ? AND client_id = ?');
        if ($delete) {
            $delete->bind_param('ii', $orderId, $userId);
            $delete->execute();
            $delete->close();
        }

        header('Location: cabinet.php?success=order_deleted');
        exit;
    }

    $check->close();
}

header('Location: cabinet.php?error=delete_forbidden');
exit;