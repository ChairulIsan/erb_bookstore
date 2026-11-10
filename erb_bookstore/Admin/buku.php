<?php

// Memulai session
session_start();

// Menghubungkan database
require_once "../config/database.php";

// Membuat token keamanan
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Memeriksa login
if (!isset($_SESSION["user_id"])) {
    header("Location: ../Auth/login.php");
    exit();
}

// Memeriksa hak akses admin
if (($_SESSION["user_role"] ?? "") !== "admin") {
    header("Location: ../index.php");
    exit();
}

// Judul halaman
$pageTitle = "Data Buku";

// Mengambil data buku dan kategori
$query = "
    SELECT
        books.*,
        categories.category_name
    FROM books
    LEFT JOIN categories
        ON books.category_id = categories.id
    ORDER BY books.id DESC
";

$stmt = $conn->prepare($query);
$stmt->execute();

$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Memanggil header admin
require_once __DIR__ . "/includes/header.php";

?>

<section class="admin-books">

    <!-- PAGE HEADING -->
    <div class="books-heading">
        <div>
            <span class="books-eyebrow">BOOK COLLECTION</span>
            <h1>Data Buku</h1>
            <p>Kelola seluruh koleksi buku ERB Bookstore.</p>
        </div>

        <a href="tambah_buku.php" class="btn-add-book">
            <span>+</span> Tambah Buku
        </a>
    </div>

    <!-- BOOK TABLE -->
    <div class="books-table-container">

        <div class="books-table-heading">
            <div>
                <h2>Koleksi Buku</h2>
                <p>Daftar buku yang tersedia di ERB Bookstore.</p>
            </div>

            <span class="books-count">
                <?php echo count($books); ?> Buku
            </span>
        </div>

        <div class="books-table-scroll">

            <table class="books-table">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cover</th>
                        <th>Judul Buku</th>
                        <th>Penulis</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (count($books) > 0): ?>

                        <?php foreach ($books as $book): ?>

                            <tr>

                                <!-- ID -->
                                <td class="book-id">
                                    #<?php echo (int) $book["id"]; ?>
                                </td>

                                <!-- COVER -->
                                <td>
                                    <div class="book-cover">
                                        <?php if (!empty($book["cover"])): ?>

                                            <img
                                                src="/erb_bookstore/<?php echo htmlspecialchars($book["cover"]); ?>"
                                                alt="Cover <?php echo htmlspecialchars($book["title"]); ?>"
                                            >

                                        <?php else: ?>

                                            <span>No Cover</span>

                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- JUDUL -->
                                <td class="book-title-cell">
                                    <?php echo htmlspecialchars($book["title"]); ?>
                                </td>

                                <!-- PENULIS -->
                                <td>
                                    <?php echo htmlspecialchars($book["author"]); ?>
                                </td>

                                <!-- KATEGORI -->
                                <td>
                                    <span class="book-category">
                                        <?php echo htmlspecialchars($book["category_name"] ?? "Uncategorized"); ?>
                                    </span>
                                </td>

                                <!-- HARGA -->
                                <td class="book-price">
                                    Rp <?php echo number_format((float) $book["price"], 0, ",", "."); ?>
                                </td>

                                <!-- STOK -->
                                <td>
                                    <span class="book-stock">
                                        <?php echo (int) ($book["stock"] ?? 0); ?>
                                    </span>
                                </td>

                                <!-- AKSI -->
                                <td>
                                    <div class="book-actions">

                                        <a
                                            href="edit_buku.php?id=<?php echo (int) $book['id']; ?>"
                                            class="btn-edit-book"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            action="hapus_buku.php"
                                            method="POST"
                                            class="delete-form"
                                            onsubmit="return confirm('Yakin ingin menghapus buku ini?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?php echo (int) $book['id']; ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
                                            >

                                            <button type="submit" class="btn-delete-book">
                                                Hapus
                                            </button>

                                        </form>

                                    </div>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8" class="empty-books">
                                Belum ada data buku yang tersedia.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>
    </div>

</section>

<?php require_once __DIR__ . "/includes/footer.php"; ?>