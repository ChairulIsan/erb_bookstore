
<?php

session_start();

require_once "../config/database.php";

// Memeriksa hak akses admin
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["user_role"] ?? "") !== "admin"
) {
    header("Location: ../index.php");
    exit();
}

// Mengambil ID buku dari URL
$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header("Location: buku.php");
    exit();
}

// Mengambil data buku berdasarkan ID
$query = "SELECT * FROM books WHERE id = :id";
$stmt = $conn->prepare($query);
$stmt->execute([":id" => $id]);

$book = $stmt->fetch(PDO::FETCH_ASSOC);

// Jika buku tidak ditemukan
if (!$book) {
    header("Location: buku.php");
    exit();
}

// Mengambil kategori
$categoryQuery = "
    SELECT * FROM categories
    ORDER BY category_name ASC
";

$categoryStmt = $conn->prepare($categoryQuery);
$categoryStmt->execute();

$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

$error = "";

// Memproses form edit
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $author = trim($_POST["author"] ?? "");
    $publisher = trim($_POST["publisher"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $categoryId = $_POST["category_id"] ?? "";
    $price = $_POST["price"] ?? "";
    $stock = $_POST["stock"] ?? "";

    // Validasi input
    if (
        $title === "" ||
        $author === "" ||
        $price === "" ||
        !is_numeric($price) ||
        (float) $price < 0 ||
        filter_var($stock, FILTER_VALIDATE_INT) === false ||
        (int) $stock < 0
    ) {
        $error = "Periksa kembali data buku yang dimasukkan.";
    }

    // Memeriksa kategori
    if ($error === "") {

        $categoryId = $categoryId === ""
            ? null
            : filter_var($categoryId, FILTER_VALIDATE_INT);

        if ($categoryId === false) {
            $error = "Kategori tidak valid.";
        }

        if ($error === "" && $categoryId !== null) {

            $checkCategory = $conn->prepare(
                "SELECT id FROM categories WHERE id = :id"
            );

            $checkCategory->execute([
                ":id" => $categoryId
            ]);

            if (!$checkCategory->fetch()) {
                $error = "Kategori tidak ditemukan.";
            }
        }
    }

    // Mempertahankan cover lama
    $coverPath = $book["cover"];

    // Memeriksa apakah admin mengunggah cover baru
    if (
        $error === "" &&
        isset($_FILES["cover"]) &&
        $_FILES["cover"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES["cover"]["error"] !== UPLOAD_ERR_OK) {

            $error = "Upload gambar gagal.";

        } elseif ($_FILES["cover"]["size"] > 2 * 1024 * 1024) {

            $error = "Ukuran gambar maksimal 2 MB.";

        } else {

            // Memeriksa tipe gambar
            $fileInfo = new finfo(FILEINFO_MIME_TYPE);

            $mimeType = $fileInfo->file(
                $_FILES["cover"]["tmp_name"]
            );

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

                if (
                    move_uploaded_file(
                        $_FILES["cover"]["tmp_name"],
                        $targetPath
                    )
                ) {

                    $coverPath = "assets/images/books/" . $fileName;

                } else {

                    $error = "Gagal menyimpan gambar.";

                }
            }
        }
    }

    // Memperbarui data buku
    if ($error === "") {

        $updateQuery = "
            UPDATE books SET
                category_id = :category_id,
                title = :title,
                author = :author,
                publisher = :publisher,
                description = :description,
                price = :price,
                stock = :stock,
                cover = :cover
            WHERE id = :id
        ";

        $updateStmt = $conn->prepare($updateQuery);

        $updateStmt->execute([
            ":category_id" => $categoryId,
            ":title" => $title,
            ":author" => $author,
            ":publisher" => $publisher,
            ":description" => $description,
            ":price" => $price,
            ":stock" => $stock,
            ":cover" => $coverPath,
            ":id" => $id
        ]);

        header("Location: buku.php?updated=1");
        exit();
    }

    // Memperbarui data tampilan form jika validasi gagal
    $book["title"] = $title;
    $book["author"] = $author;
    $book["publisher"] = $publisher;
    $book["description"] = $description;
    $book["category_id"] = $categoryId;
    $book["price"] = $price;
    $book["stock"] = $stock;
    $book["cover"] = $coverPath;
}

$pageTitle = "Edit Buku";

require_once __DIR__ . "/includes/header.php";

?>

<section class="admin-content">

    <h1>Edit Buku</h1>

    <p>Perbarui informasi buku ERB Bookstore.</p>

    <?php if ($error !== ""): ?>
        <p class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </p>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <div>
            <label for="title">Judul Buku</label>

            <input
                type="text"
                id="title"
                name="title"
                value="<?php echo htmlspecialchars($book["title"]); ?>"
                required
            >
        </div>

        <div>
            <label for="author">Penulis</label>

            <input
                type="text"
                id="author"
                name="author"
                value="<?php echo htmlspecialchars($book["author"]); ?>"
                required
            >
        </div>

        <div>
            <label for="publisher">Penerbit</label>

            <input
                type="text"
                id="publisher"
                name="publisher"
                value="<?php echo htmlspecialchars($book["publisher"] ?? ""); ?>"
            >
        </div>

        <div>
            <label for="category_id">Kategori</label>

            <select id="category_id" name="category_id">

                <option value="">Pilih Kategori</option>

                <?php foreach ($categories as $category): ?>

                    <option
                        value="<?php echo (int) $category["id"]; ?>"
                        <?php echo (string) $book["category_id"] === (string) $category["id"] ? "selected" : ""; ?>
                    >
                        <?php echo htmlspecialchars($category["category_name"]); ?>
                    </option>

                <?php endforeach; ?>

            </select>
        </div>

        <div>
            <label for="description">Deskripsi</label>

            <textarea
                id="description"
                name="description"
            ><?php echo htmlspecialchars($book["description"] ?? ""); ?></textarea>
        </div>

        <div>
            <label for="price">Harga</label>

            <input
                type="number"
                id="price"
                name="price"
                min="0"
                step="0.01"
                value="<?php echo htmlspecialchars((string) $book["price"]); ?>"
                required
            >
        </div>

        <div>
            <label for="stock">Stok</label>

            <input
                type="number"
                id="stock"
                name="stock"
                min="0"
                step="1"
                value="<?php echo (int) $book["stock"]; ?>"
                required
            >
        </div>

        <div>
            <label>Cover Saat Ini</label>

            <?php if (!empty($book["cover"])): ?>

                <img
                    src="/erb_bookstore/<?php echo htmlspecialchars($book["cover"]); ?>"
                    alt="Cover buku"
                    width="100"
                >

            <?php else: ?>

                <p>Belum ada cover.</p>

            <?php endif; ?>
        </div>

        <div>
            <label for="cover">Ganti Cover Buku</label>

            <input
                type="file"
                id="cover"
                name="cover"
                accept=".jpg,.jpeg,.png,.webp"
            >

            <small>
                Kosongkan jika tidak ingin mengganti cover.
                Maksimal 2 MB.
            </small>
        </div>

        <button type="submit" class="btn">
            Simpan Perubahan
        </button>

        <a href="buku.php" class="btn">
            Kembali
        </a>

    </form>

</section>

<?php require_once __DIR__ . "/includes/footer.php"; ?>

