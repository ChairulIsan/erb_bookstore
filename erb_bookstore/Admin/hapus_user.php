
<?php

// Memulai session
session_start();

// Menghubungkan database
require_once "../config/database.php";

// Memastikan admin sudah login
if (!isset($_SESSION["user_id"])) {
    header("Location: ../Auth/admin_login.php");
    exit;
}

// Memastikan hanya admin yang bisa mengakses
if ($_SESSION["user_role"] !== "admin") {
    header("Location: ../index.php");
    exit;
}

// Hanya menerima metode POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: user.php");
    exit;
}

// Memeriksa token CSRF
$csrfToken = $_POST["csrf_token"] ?? "";

if (
    empty($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $csrfToken)
) {
    die("Permintaan tidak valid. Silakan kembali dan coba lagi.");
}

// Mengambil ID user
$userId = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

if (!$userId || $userId < 1) {
    header("Location: user.php");
    exit;
}

// Melarang admin menghapus akunnya sendiri
if ((int) $_SESSION["user_id"] === $userId) {
    die("Anda tidak dapat menghapus akun yang sedang digunakan.");
}

try {

    // Mengambil data pengguna
    $checkUser = $conn->prepare("
        SELECT id, role
        FROM users
        WHERE id = :id
    ");

    $checkUser->execute([":id" => $userId]);

    $user = $checkUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("Pengguna tidak ditemukan.");
    }

    // Melarang penghapusan akun admin
    if ($user["role"] === "admin") {
        die("Akun administrator tidak dapat dihapus melalui fitur ini.");
    }

    // Memeriksa riwayat pesanan
    $checkOrder = $conn->prepare("
        SELECT id
        FROM orders
        WHERE user_id = :user_id
        LIMIT 1
    ");

    $checkOrder->execute([":user_id" => $userId]);

    if ($checkOrder->fetch()) {
        die("Pengguna memiliki riwayat pesanan sehingga tidak dapat dihapus.");
    }

    // Memulai transaksi database
    $conn->beginTransaction();

    // Menghapus item keranjang milik pengguna
    $deleteCartItems = $conn->prepare("
        DELETE FROM cart_items
        WHERE cart_id IN (
            SELECT id
            FROM carts
            WHERE user_id = :user_id
        )
    ");

    $deleteCartItems->execute([":user_id" => $userId]);

    // Menghapus keranjang pengguna
    $deleteCarts = $conn->prepare("
        DELETE FROM carts
        WHERE user_id = :user_id
    ");

    $deleteCarts->execute([":user_id" => $userId]);

    // Menghapus akun pengguna
    $deleteUser = $conn->prepare("
        DELETE FROM users
        WHERE id = :id
        AND role = 'customer'
    ");

    $deleteUser->execute([":id" => $userId]);

    // Memastikan akun benar-benar terhapus
    if ($deleteUser->rowCount() !== 1) {
        throw new Exception("Akun gagal dihapus.");
    }

    // Menyimpan perubahan
    $conn->commit();

    // Mengarahkan kembali ke halaman user
    header("Location: user.php?deleted=1");
    exit;

} catch (Exception $e) {

    // Membatalkan transaksi jika terjadi kesalahan
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log($e->getMessage());

    die("Terjadi kesalahan saat menghapus pengguna. Silakan periksa konfigurasi database.");
}
