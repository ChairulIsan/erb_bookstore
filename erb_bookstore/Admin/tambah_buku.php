
<?php

session_start();

require_once "../config/database.php";

// Memeriksa akses admin
if (!isset($_SESSION["user_id"]) ||
    ($_SESSION["user_role"] ?? "") !== "admin") {

    header("Location: ../index.php");
    exit();
}

$error = "";
$success = "";

// Mengambil kategori dari database
$categoryQuery = "SELECT * FROM categories ORDER BY category_name ASC";
$categoryStmt = $conn->prepare($categoryQuery);
$categoryStmt->execute();

$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

// Memproses form ketika dikirim
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $author = trim($_POST["author"] ?? "");
    $publisher = trim($_POST["publisher"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $categoryId = $_POST["category_id"] ?? "";
    $price = $_POST["price"] ?? "";
    $stock = $_POST["stock"] ?? "";

    // Validasi data
    if ($title === "" || $author === "" ||
        $price === "" || !is_numeric($price) ||
        (float) $price < 0 ||
        filter_var($stock, FILTER_VALIDATE_INT) === false ||
        (int) $stock < 0) {

        $error = "Periksa kembali data buku yang dimasukkan.";

    } else {

        // Memeriksa kategori
        $categoryId = $categoryId === "" ? null : filter_var(
            $categoryId,
            FILTER_VALIDATE_INT
        );

        if ($categoryId !== null && $categoryId === false) {
            $error = "Kategori tidak valid.";
        }

        if ($error === "" && $categoryId !== null) {

            $checkCategory = $conn->prepare(
                "SELECT id FROM categories WHERE id = :id"
            );

            $checkCategory->execute([":id" => $categoryId]);

            if (!$checkCategory->fetch()) {
                $error = "Kategori tidak ditemukan.";
            }
        }

        // Menyiapkan lokasi cover
        $coverPath = null;

        if ($error === "" && isset($_FILES["cover"]) &&
            $_FILES["cover"]["error"] !== UPLOAD_ERR_NO_FILE) {

            if ($_FILES["cover"]["error"] !== UPLOAD_ERR_OK) {

                $error = "Upload gambar gagal.";

            } elseif ($_FILES["cover"]["size"] > 2 * 1024 * 1024) {

                $error = "Ukuran gambar maksimal 2 MB.";

            } else {

                // Memeriksa jenis file berdasarkan MIME
                $fileInfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $fileInfo->file($_FILES["cover"]["tmp_name"]);

                $allowedTypes = [
                    "image/jpeg" => "jpg",
                    "image/png" => "png",
                    "image/webp" => "webp"
                ];

                if (!isset($allowedTypes[$mimeType])) {

                    $error = "Format gambar harus JPG, PNG, atau WEBP.";

                } else {

                    $uploadDirectory = "../assets/images/books/";

                    $fileName = bin2hex(random_bytes(16))
                        . "." . $allowedTypes[$mimeType];

                    $targetPath = $uploadDirectory . $fileName;

                    if (move_uploaded_file(
                        $_FILES["cover"]["tmp_name"],
                        $targetPath
                    )) {

                        $coverPath = "assets/images/books/" . $fileName;

                    } else {

                        $error = "Gagal menyimpan gambar.";

                    }
                }
            }
        }

        // Menyimpan data buku
        if ($error === "") {

            $query = "
                INSERT INTO books
                (
                    category_id,
                    title,
                    author,
                    publisher,
                    description,
                    price,
                    stock,
                    cover
                )
                VALUES
                (
                    :category_id,
                    :title,
                    :author,
                    :publisher,
                    :description,
                    :price,
                    :stock,
                    :cover
                )
            ";

            $stmt = $conn->prepare($query);

            $stmt->execute([
                ":category_id" => $categoryId,
                ":title" => $title,
                ":author" => $author,
                ":publisher" => $publisher,
                ":description" => $description,
                ":price" => $price,
                ":stock" => $stock,
                ":cover" => $coverPath
            ]);

            header("Location: buku.php");
            exit();
        }
    }
}

$pageTitle = "Tambah Buku";

require_once __DIR__ . "/includes/header.php";

?>

<section class="admin-content">

    <h1>Tambah Buku</h1>

    <p>Masukkan informasi buku baru ke ERB Bookstore.</p>

    <?php if ($error !== ""): ?>
        <p class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </p>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <div>
            <label for="title">Judul Buku</label>
            <input type="text" id="title" name="title" required>
        </div>

        <div>
            <label for="author">Penulis</label>
            <input type="text" id="author" name="author" required>
        </div>

        <div>
            <label for="publisher">Penerbit</label>
            <input type="text" id="publisher" name="publisher">
        </div>

        <div>
            <label for="category_id">Kategori</label>

            <select id="category_id" name="category_id">
                <option value="">Pilih Kategori</option>

                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo (int) $category["id"]; ?>">
                        <?php echo htmlspecialchars($category["category_name"]); ?>
                    </option>
                <?php endforeach; ?>

            </select>
        </div>

        <div>
            <label for="description">Deskripsi</label>
            <textarea id="description" name="description"></textarea>
        </div>

        <div>
            <label for="price">Harga</label>
            <input type="number" id="price" name="price"
                   min="0" step="0.01" required>
        </div>

        <div>
            <label for="stock">Stok</label>
            <input type="number" id="stock" name="stock"
                   min="0" step="1" required>
        </div>

        <div>
            <label for="cover">Cover Buku</label>
            <input type="file" id="cover" name="cover"
                   accept=".jpg,.jpeg,.png,.webp">
            <small>Format JPG, PNG, WEBP. Maksimal 2 MB.</small>
        </div>

        <button type="submit" class="btn">
            Simpan Buku
        </button>

        <a href="buku.php" class="btn">
            Kembali
        </a>

    </form>

</section>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
