
<?php

// Memulai session
session_start();

// Menghubungkan database
require_once "../config/database.php";

// Memeriksa akses admin
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["user_role"] ?? "") !== "admin"
) {
    header("Location: ../index.php");
    exit();
}

// Membuat token keamanan
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$error = "";

// Memproses form
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Memeriksa token
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        $error = "Permintaan tidak valid.";
    } else {

        // Mengambil data form
        $categoryName = trim($_POST["category_name"] ?? "");
        $description = trim($_POST["description"] ?? "");

        // Memvalidasi nama kategori
        if ($categoryName === "") {

            $error = "Nama kategori wajib diisi.";

        } else {

            // Memeriksa kategori yang sudah ada
            $checkQuery = "
                SELECT id
                FROM categories
                WHERE category_name = :category_name
            ";

            $checkStmt = $conn->prepare($checkQuery);

            $checkStmt->execute([
                ":category_name" => $categoryName
            ]);

            if ($checkStmt->fetch()) {

                $error = "Kategori tersebut sudah tersedia.";

            } else {

                // Menambahkan kategori
                $insertQuery = "
                    INSERT INTO categories
                    (category_name, description)
                    VALUES
                    (:category_name, :description)
                ";

                $insertStmt = $conn->prepare($insertQuery);

                $insertStmt->execute([
                    ":category_name" => $categoryName,
                    ":description" => $description !== ""
                        ? $description
                        : null
                ]);

                // Kembali ke halaman kategori
                header("Location: kategori.php?added=1");
                exit();
            }
        }
    }
}

// Judul halaman
$pageTitle = "Tambah Kategori";

// Memanggil header admin
require_once __DIR__ . "/includes/header.php";

?>

<section class="admin-content">

    <h1>Tambah Kategori</h1>

    <p>Tambahkan kategori baru ke ERB Bookstore.</p>

    <?php if ($error !== ""): ?>

        <p class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </p>

    <?php endif; ?>

    <form method="POST">

        <!-- Token keamanan -->
        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
        >

        <!-- Nama Kategori -->
        <div>
            <label for="category_name">Nama Kategori</label>

            <input
                type="text"
                id="category_name"
                name="category_name"
                placeholder="Contoh: Fiksi"
                value="<?php echo htmlspecialchars($_POST["category_name"] ?? ""); ?>"
                maxlength="100"
                required
            >
        </div>

        <!-- Deskripsi -->
        <div>
            <label for="description">Deskripsi Kategori</label>

            <textarea
                id="description"
                name="description"
                placeholder="Masukkan deskripsi kategori..."
            ><?php echo htmlspecialchars($_POST["description"] ?? ""); ?></textarea>
        </div>

        <!-- Tombol -->
        <button type="submit" class="btn">
            Simpan Kategori
        </button>

        <a href="kategori.php" class="btn">
            Kembali
        </a>

    </form>

</section>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
