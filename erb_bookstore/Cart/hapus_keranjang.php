<?php

session_start();
require_once "../config/database.php";

// Memastikan customer sudah login
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "customer") {
    header("Location: ../Auth/login.php");
    exit;
}

// Hanya menerima POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../dashboard/keranjang.php");
    exit;
}

// Validasi CSRF Token
if (
    !isset($_POST["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"] ?? "", $_POST["csrf_token"])
) {
    die("Token keamanan tidak valid.");
}

// Mengambil ID item
$cartItemId = filter_var(
    $_POST["cart_item_id"] ?? "",
    FILTER_VALIDATE_INT
);

if ($cartItemId === false || $cartItemId === null) {
    die("ID buku tidak valid.");
}

try {

    // Menghapus hanya item milik customer
    $delete = $conn->prepare("
        DELETE cart_items
        FROM cart_items
        INNER JOIN carts
            ON cart_items.cart_id = carts.id
        WHERE cart_items.id = :item_id
        AND carts.user_id = :user_id
    ");

    $delete->execute([
        ":item_id" => $cartItemId,
        ":user_id" => $_SESSION["user_id"]
    ]);

    header("Location: ../dashboard/keranjang.php?deleted=1");
    exit;

} catch (PDOException $e) {

    die("Gagal menghapus buku: " . $e->getMessage());

}

?>