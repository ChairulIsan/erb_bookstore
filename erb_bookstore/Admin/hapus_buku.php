<?php

session_start();

require_once "../config/database.php";

// Memeriksa akses admin
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["user_role"] ?? "") !== "admin"
) {
    header("Location: ../index.php");
    exit();
}

// Memastikan penghapusan menggunakan POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: buku.php");
    exit();
}

// Memeriksa token keamanan
if (
    !isset($_SESSION["csrf_token"]) ||
    !isset($_POST["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
) {
    $_SESSION["flash_message"] = "Permintaan tidak valid.";
    $_SESSION["flash_type"] = "error";

    header("Location: buku.php");
    exit();
}

// Mengambil ID buku
$id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

if ($id === false || $id === null || $id <= 0) {
    $_SESSION["flash_message"] = "ID buku tidak valid.";
    $_SESSION["flash_type"] = "error";

    header("Location: buku.php");
    exit();
}

// Memeriksa apakah buku tersedia
$checkBook = $conn->prepare(
    "SELECT id, cover FROM books WHERE id = :id"
);

$checkBook->execute([":id" => $id]);

$book = $checkBook->fetch(PDO::FETCH_ASSOC);

if (!$book) {
    $_SESSION["flash_message"] = "Buku tidak ditemukan.";
    $_SESSION["flash_type"] = "error";

    header("Location: buku.php");
    exit();
}

// Memeriksa riwayat pesanan
$checkOrder = $conn->prepare(
    "SELECT COUNT(*) FROM order_items WHERE book_id = :id"
);

$checkOrder->execute([":id" => $id]);

if ($checkOrder->fetchColumn() > 0) {
    $_SESSION["flash_message"] =
        "Buku tidak bisa dihapus karena sudah memiliki riwayat pesanan.";

    $_SESSION["flash_type"] = "error";

    header("Location: buku.php");
    exit();
}

try {

    $conn->beginTransaction();

    // Menghapus buku dari keranjang
    $deleteCart = $conn->prepare(
        "DELETE FROM cart_items WHERE book_id = :id"
    );

    $deleteCart->execute([":id" => $id]);

    // Menghapus data buku
    $deleteBook = $conn->prepare(
        "DELETE FROM books WHERE id = :id"
    );

    $deleteBook->execute([":id" => $id]);

    $conn->commit();

    // Menghapus file cover jika tersimpan di folder cover buku
    if (
        !empty($book["cover"]) &&
        strpos($book["cover"], "assets/images/books/") === 0
    ) {
        $coverFile = basename($book["cover"]);

        $coverPath = __DIR__ .
            "/../assets/images/books/" . $coverFile;

        if (is_file($coverPath)) {
            unlink($coverPath);
        }
    }

    $_SESSION["flash_message"] = "Buku berhasil dihapus.";
    $_SESSION["flash_type"] = "success";

} catch (PDOException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    $_SESSION["flash_message"] =
        "Gagal menghapus buku. Silakan periksa relasi database.";

    $_SESSION["flash_type"] = "error";
}

header("Location: buku.php");
exit();

?>