<?php
// config/auth.php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isBendahara() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'bendahara';
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit();
    }
}

function requireBendahara() {
    requireLogin();
    if (!isBendahara()) {
        header('Location: dashboard.php');
        exit();
    }
}

function logout() {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}
?>