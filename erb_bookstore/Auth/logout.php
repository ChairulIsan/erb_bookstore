<?php

// Memulai session
session_start();

// Memeriksa metode request
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../index.php");
    exit();
}

// Menghapus seluruh data session
$_SESSION = [];

// Menghapus cookie session
if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );

}

// Menghancurkan session
session_destroy();

// Kembali ke halaman utama
header("Location: ../index.php");
exit();

?>