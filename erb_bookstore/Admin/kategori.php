<?php

// Memulai session
session_start();

// Menghubungkan database
require_once "../config/database.php";

// Memeriksa login admin
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["user_role"] ?? "") !== "admin"
) {
    header("Location: ../index.php");
    exit();
}

// Judul halaman
$pageTitle = "Manajemen Kategori";

// Mengambil data kategori dan menghitung jumlah buku
$query = "
    SELECT
        categories.id,
        categories.category_name,
        categories.description,
        COUNT(books.id) AS total_books
    FROM categories
    LEFT JOIN books
        ON categories.id = books.category_id
    GROUP BY
        categories.id,
        categories.category_name,
        categories.description
    ORDER BY categories.id DESC
";

$stmt = $conn->prepare($query);
$stmt->execute();

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Memanggil header admin
require_once __DIR__ . "/includes/header.php";

?>

<section class="admin-categories">

    <!-- PAGE HEADING -->
    <div class="categories-heading">

        <div>
            <span class="categories-eyebrow">BOOK CLASSIFICATION</span>

            <h1>Manajemen Kategori</h1>

            <p>Kelola dan atur kategori buku ERB Bookstore.</p>
        </div>

        <a href="tambah_kategori.php" class="btn-add-category">
            <span>+</span> Tambah Kategori
        </a>

    </div>

    <!-- CATEGORY TABLE -->
    <div class="categories-table-container">

        <div class="categories-table-heading">

            <div>
                <h2>Daftar Kategori</h2>
                <p>Kategori yang digunakan untuk mengelompokkan koleksi buku.</p>
            </div>

            <span class="categories-count">
                <?php echo count($categories); ?> Kategori
            </span>

        </div>

        <div class="categories-table-scroll">

            <table class="categories-table">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Kategori</th>
                        <th>Deskripsi</th>
                        <th>Jumlah Buku</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (count($categories) > 0): ?>

                        <?php foreach ($categories as $category): ?>

                            <tr>

                                <!-- ID -->
                                <td class="category-id">
                                    #<?php echo (int) $category["id"]; ?>
                                </td>

                                <!-- NAMA KATEGORI -->
                                <td>
                                    <span class="category-name">
                                        <?php echo htmlspecialchars(
                                            $category["category_name"]
                                        ); ?>
                                    </span>
                                </td>

                                <!-- DESKRIPSI -->
                                <td class="category-description">
                                    <?php echo htmlspecialchars(
                                        $category["description"] ?? "-"
                                    ); ?>
                                </td>

                                <!-- JUMLAH BUKU -->
                                <td>
                                    <span class="category-book-count">
                                        <?php echo (int) $category["total_books"]; ?>
                                        Buku
                                    </span>
                                </td>

                                <!-- AKSI -->
                                <td>

                                    <a
                                        href="edit_kategori.php?id=<?php echo (int) $category['id']; ?>"
                                        class="btn-edit-category"
                                    >
                                        Edit
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="5" class="empty-categories">
                                Belum ada kategori buku yang tersedia.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</section>

<?php require_once __DIR__ . "/includes/footer.php"; ?>