
<?php

// Memulai session
session_start();

// Menghubungkan database
require_once "../config/database.php";

// Memeriksa login
if (!isset($_SESSION["user_id"])) {
    header("Location: ../Auth/login.php");
    exit;
}

// Memastikan halaman hanya untuk customer
if ($_SESSION["user_role"] !== "customer") {
    header("Location: ../Admin/dashboard.php");
    exit;
}

// Mengambil ID customer
$userId = (int) $_SESSION["user_id"];

// Mencari keranjang milik customer
$cartQuery = $conn->prepare("
    SELECT id
    FROM carts
    WHERE user_id = :user_id
    ORDER BY id DESC
    LIMIT 1
");

$cartQuery->execute([
    ":user_id" => $userId
]);

$cart = $cartQuery->fetch(PDO::FETCH_ASSOC);

// Menyiapkan data keranjang
$cartItems = [];
$totalHarga = 0;

// Jika keranjang tersedia
if ($cart) {

    // Mengambil daftar buku dalam keranjang
    $itemQuery = $conn->prepare("
        SELECT
            cart_items.id AS cart_item_id,
            cart_items.quantity,
            books.id AS book_id,
            books.title,
            books.author,
            books.price,
            books.cover,
            books.stock
        FROM cart_items
        INNER JOIN books
            ON cart_items.book_id = books.id
        WHERE cart_items.cart_id = :cart_id
        ORDER BY cart_items.id DESC
    ");

    $itemQuery->execute([
        ":cart_id" => $cart["id"]
    ]);

    $cartItems = $itemQuery->fetchAll(PDO::FETCH_ASSOC);

    // Menghitung total harga
    foreach ($cartItems as $item) {

        $subtotal = (float) $item["price"] * (int) $item["quantity"];

        $totalHarga += $subtotal;
    }
}

// Judul halaman
$pageTitle = "Keranjang Belanja - ERB Bookstore";

// Memanggil header
require_once "../includes/header.php";

?>

<div class="cart-container">

    <div class="cart-header">
        <h1>Keranjang Belanja</h1>
        <p>Periksa kembali buku yang ingin lu beli.</p>
    </div>

    <?php if (count($cartItems) > 0): ?>

        <div class="cart-content">

            <div class="cart-list">

                <?php foreach ($cartItems as $item): ?>

                    <?php
                    $subtotal = (float) $item["price"] * (int) $item["quantity"];
                    ?>

                    <div class="cart-item">

                        <div class="cart-book-cover">

                            <?php if (!empty($item["cover"])): ?>

                                <img
                                    src="/erb_bookstore/<?php echo htmlspecialchars($item["cover"]); ?>"
                                    alt="<?php echo htmlspecialchars($item["title"]); ?>"
                                >

                            <?php else: ?>

                                <div class="no-cover">
                                    No Cover
                                </div>

                            <?php endif; ?>

                        </div>

                        <div class="cart-book-info">

                            <h3>
                                <?php echo htmlspecialchars($item["title"]); ?>
                            </h3>

                            <p>
                                Penulis:
                                <?php echo htmlspecialchars($item["author"]); ?>
                            </p>

                            <p>
                                Harga:
                                Rp <?php echo number_format($item["price"], 0, ",", "."); ?>
                            </p>

                            <form action="../Cart/update_keranjang.php" method="POST">

    <input
        type="hidden"
        name="csrf_token"
        value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
    >

    <input
        type="hidden"
        name="cart_item_id"
        value="<?php echo (int) $item["cart_item_id"]; ?>"
    >

    <label>Jumlah:</label>

    <input
        type="number"
        name="quantity"
        value="<?php echo (int) $item["quantity"]; ?>"
        min="1"
        max="<?php echo (int) $item["stock"]; ?>"
        required
    >

    <button type="submit" class="btn">
        Update Quantity
    </button>

</form>

<form action="../Cart/hapus_keranjang.php" method="POST"
      onsubmit="return confirm('Yakin ingin menghapus buku ini?');">

    <input
        type="hidden"
        name="csrf_token"
        value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
    >

    <input
        type="hidden"
        name="cart_item_id"
        value="<?php echo (int) $item["cart_item_id"]; ?>"
    >

    <button type="submit" class="btn">
        Remove Book
    </button>

</form>

                            <p>
                                Subtotal:
                                <strong>
                                    Rp <?php echo number_format($subtotal, 0, ",", "."); ?>
                                </strong>
                            </p>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

            <div class="cart-summary">

                <h2>Ringkasan Belanja</h2>

                <p>
                    Total Buku:
                    <strong><?php echo count($cartItems); ?> jenis</strong>
                </p>

                <p>
                    Total Harga:
                    <strong>
                        Rp <?php echo number_format($totalHarga, 0, ",", "."); ?>
                    </strong>
                </p>

                <a href="checkout.php" class="btn">
    Checkout
</a>

            </div>

        </div>

    <?php else: ?>

        <div class="empty-cart">

            <h2>Keranjang Masih Kosong</h2>

            <p>
                Lu belum menambahkan buku ke keranjang.
            </p>

            <a href="buku.php" class="btn">
                Jelajahi Buku
            </a>

        </div>

    <?php endif; ?>

</div>

<?php

// Memanggil footer
require_once "../includes/footer.php";

?>
