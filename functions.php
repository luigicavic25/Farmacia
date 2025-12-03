<?php
// functions.php
if (session_status() === PHP_SESSION_NONE) session_start();

// inclui config de BD (use caminho relativo a partir de functions.php)
require_once __DIR__ . '/config.php';

// funções utilitárias
function is_logged_in() {
    return isset($_SESSION['usuario_id']);
}
function require_login() {
    if (!is_logged_in()) {
        header('Location: /Farmacia/login.php');
        exit;
    }
}
function e($s) {
    return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
}
