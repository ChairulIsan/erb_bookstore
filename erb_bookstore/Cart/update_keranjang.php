<?php

session_start();
require_once "../config/database.php";

// Hanya customer yang boleh mengakses
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "customer") {
    header("Location: ../Auth/login.php");
    exit;
}

// Hanya menerima POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../dashboard/keranjang.php");
    exit;
}

// Memeriksa CSRF Token
if (
    !isset($_POST["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"] ?? "", $_POST["csrf_token"])
) {
    die("Token keamanan tidak valid.");
}

// Mengambil data
$cartItemId = filter_var($_POST["cart_item_id"] ?? "", FILTER_VALIDATE_INT);
$quantity = filter_var($_POST["quantity"] ?? "", FILTER_VALIDATE_INT);

if ($cartItemId === false || $cartItemId === null ||
    $quantity === false || $quantity === null || $quantity < 1) {
    die("Data jumlah buku tidak valid.");
}

try {

    // Memastikan item milik keranjang customer
    $query = $conn->prepare("
        SELECT
            cart_items.id,
            books.stock
        FROM cart_items
        INNER JOIN carts ON cart_items.cart_id = carts.id
        INNER JOIN books ON cart_items.book_id = books.id
        WHERE cart_items.id = :item_id
        AND carts.user_id = :user_id
    ");

    $query->execute([
        ":item_id" => $cartItemId,
        ":user_id" => $_SESSION["user_id"]
    ]);

    $item = $query->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        die("Buku tidak ditemukan di keranjang.");
    }

    if ($quantity > (int) $item["stock"]) {
        die("Jumlah melebihi stok buku.");
    }

    // Memperbarui jumlah
    $update = $conn->prepare("
        UPDATE cart_items
        SET quantity = :quantity
        WHERE id = :item_id
    ");

    $update->execute([
        ":quantity" => $quantity,
        ":item_id" => $cartItemId
    ]);

    header("Location: ../dashboard/keranjang.php?updated=1");
    exit;

} catch (PDOException $e) {
    die("Gagal memperbarui keranjang: " . $e->getMessage());
}