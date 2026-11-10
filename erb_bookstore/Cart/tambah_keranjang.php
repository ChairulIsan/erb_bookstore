
<?php

// Memulai session
session_start();

// Menghubungkan database
require_once "../config/database.php";

// Memastikan pengguna sudah login
if (!isset($_SESSION["user_id"])) {
    header("Location: ../Auth/login.php");
    exit;
}

// Memastikan hanya customer yang bisa menggunakan fitur
if ($_SESSION["user_role"] !== "customer") {
    header("Location: ../Admin/dashboard.php");
    exit;
}

// Hanya menerima metode POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../dashboard/buku.php");
    exit;
}

// Memeriksa token CSRF
$csrfToken = $_POST["csrf_token"] ?? "";

if (
    empty($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $csrfToken)
) {
    die("Permintaan tidak valid. Silakan coba lagi.");
}

// Mengambil ID buku
$bookId = filter_input(INPUT_POST, "book_id", FILTER_VALIDATE_INT);

// Mengambil jumlah buku
$quantity = filter_input(INPUT_POST, "quantity", FILTER_VALIDATE_INT);

if (!$bookId || $bookId < 1) {
    die("ID buku tidak valid.");
}

// Jika jumlah tidak dikirim, gunakan 1
$quantity = $quantity === null ? 1 : $quantity;

if ($quantity === false || $quantity < 1) {
    die("Jumlah buku tidak valid.");
}

// Mengambil ID customer
$userId = (int) $_SESSION["user_id"];

try {

    // Memulai transaksi database
    $conn->beginTransaction();

    // Memeriksa buku dan stok
    $bookQuery = $conn->prepare("
        SELECT id, stock
        FROM books
        WHERE id = :id
        FOR UPDATE
    ");

    $bookQuery->execute([
        ":id" => $bookId
    ]);

    $book = $bookQuery->fetch(PDO::FETCH_ASSOC);

    if (!$book) {
        throw new Exception("Buku tidak ditemukan.");
    }

    // Memastikan stok tersedia
    if ($quantity > (int) $book["stock"]) {
        throw new Exception("Jumlah buku melebihi stok yang tersedia.");
    }

    // Mencari keranjang milik customer
    $cartQuery = $conn->prepare("
        SELECT id
        FROM carts
        WHERE user_id = :user_id
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ");

    $cartQuery->execute([
        ":user_id" => $userId
    ]);

    $cart = $cartQuery->fetch(PDO::FETCH_ASSOC);

    // Jika keranjang belum tersedia, buat keranjang baru
    if (!$cart) {

        $createCart = $conn->prepare("
            INSERT INTO carts (user_id)
            VALUES (:user_id)
        ");

        $createCart->execute([
            ":user_id" => $userId
        ]);

        $cartId = (int) $conn->lastInsertId();

    } else {

        $cartId = (int) $cart["id"];

    }

    // Memeriksa apakah buku sudah ada di keranjang
    $itemQuery = $conn->prepare("
        SELECT id, quantity
        FROM cart_items
        WHERE cart_id = :cart_id
        AND book_id = :book_id
        LIMIT 1
        FOR UPDATE
    ");

    $itemQuery->execute([
        ":cart_id" => $cartId,
        ":book_id" => $bookId
    ]);

    $cartItem = $itemQuery->fetch(PDO::FETCH_ASSOC);

    // Menghitung jumlah akhir
    $finalQuantity = $quantity;

    if ($cartItem) {
        $finalQuantity += (int) $cartItem["quantity"];
    }

    // Memastikan jumlah akhir tidak melebihi stok
    if ($finalQuantity > (int) $book["stock"]) {
        throw new Exception("Jumlah buku di keranjang melebihi stok tersedia.");
    }

    // Memperbarui jumlah jika buku sudah ada
    if ($cartItem) {

        $updateItem = $conn->prepare("
            UPDATE cart_items
            SET quantity = :quantity
            WHERE id = :id
        ");

        $updateItem->execute([
            ":quantity" => $finalQuantity,
            ":id" => $cartItem["id"]
        ]);

    } else {

        // Menambahkan buku baru ke keranjang
        $insertItem = $conn->prepare("
            INSERT INTO cart_items (cart_id, book_id, quantity)
            VALUES (:cart_id, :book_id, :quantity)
        ");

        $insertItem->execute([
            ":cart_id" => $cartId,
            ":book_id" => $bookId,
            ":quantity" => $quantity
        ]);

    }

    // Menyimpan perubahan database
    $conn->commit();

    // Mengarahkan ke halaman keranjang
    header("Location: ../dashboard/keranjang.php?added=1");
    exit;

} catch (Exception $e) {

    // Membatalkan perubahan jika terjadi kesalahan
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    // Menampilkan pesan error
    die(htmlspecialchars($e->getMessage()));

}
