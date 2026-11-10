
<?php

// Memulai session
session_start();

// Menghubungkan database
require_once "../config/database.php";

// Memeriksa login
if (!isset($_SESSION["user_id"])) {
    header("Location: ../Auth/admin_login.php");
    exit;
}

// Memastikan hanya admin yang bisa mengakses
if ($_SESSION["user_role"] !== "admin") {
    header("Location: ../index.php");
    exit;
}

// Membuat token CSRF
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Mengambil ID user
$userId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

// Jika form dikirim, gunakan ID dari POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $userId = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
}

// Memastikan ID valid
if (!$userId || $userId < 1) {
    header("Location: user.php");
    exit;
}

// Mengambil data user
$query = $conn->prepare("
    SELECT id, name, email, phone, address
    FROM users
    WHERE id = :id
");

$query->execute([":id" => $userId]);

$user = $query->fetch(PDO::FETCH_ASSOC);

// Jika user tidak ditemukan
if (!$user) {
    header("Location: user.php");
    exit;
}

// Menyiapkan pesan error
$error = "";

// Memproses form
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Memeriksa token keamanan
    $csrfToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $csrfToken)) {
        $error = "Permintaan tidak valid. Silakan coba lagi.";
    }

    // Mengambil data form
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");

    // Validasi nama
    if ($error === "" && $name === "") {
        $error = "Nama pengguna wajib diisi.";
    }

    if ($error === "" && strlen($name) > 100) {
        $error = "Nama maksimal 100 karakter.";
    }

    // Validasi email
    if ($error === "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    }

    if ($error === "" && strlen($email) > 100) {
        $error = "Email maksimal 100 karakter.";
    }

    // Validasi nomor telepon
    if ($error === "" && strlen($phone) > 20) {
        $error = "Nomor telepon maksimal 20 karakter.";
    }

    // Memeriksa email yang sudah digunakan
    if ($error === "") {

        $checkEmail = $conn->prepare("
            SELECT id
            FROM users
            WHERE email = :email
            AND id != :id
        ");

        $checkEmail->execute([
            ":email" => $email,
            ":id" => $userId
        ]);

        if ($checkEmail->fetch()) {
            $error = "Email sudah digunakan oleh pengguna lain.";
        }
    }

    // Memperbarui data user
    if ($error === "") {

        $update = $conn->prepare("
            UPDATE users
            SET
                name = :name,
                email = :email,
                phone = :phone,
                address = :address
            WHERE id = :id
        ");

        $update->execute([
            ":name" => $name,
            ":email" => $email,
            ":phone" => $phone !== "" ? $phone : null,
            ":address" => $address !== "" ? $address : null,
            ":id" => $userId
        ]);

        // Jika admin mengedit akun sendiri,
        // perbarui nama pada session
        if ((int) $_SESSION["user_id"] === $userId) {
            $_SESSION["user_name"] = $name;
        }

        header("Location: user.php?updated=1");
        exit;
    }

    // Mempertahankan input jika terjadi error
    $user["name"] = $name;
    $user["email"] = $email;
    $user["phone"] = $phone;
    $user["address"] = $address;
}

// Judul halaman
$pageTitle = "Edit User - ERB Bookstore";

// Memanggil header admin
require_once "includes/header.php";

?>

<div class="admin-content">

    <h2>Edit Pengguna</h2>
    <p>Perbarui informasi akun pengguna ERB Bookstore.</p>

    <?php if ($error !== ""): ?>
        <p style="color: red;">
            <?php echo htmlspecialchars($error); ?>
        </p>
    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="id"
            value="<?php echo (int) $user["id"]; ?>"
        >

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
        >

        <div>
            <label for="name">Nama Pengguna</label>
            <input
                type="text"
                id="name"
                name="name"
                maxlength="100"
                value="<?php echo htmlspecialchars($user["name"]); ?>"
                required
            >
        </div>

        <div>
            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                maxlength="100"
                value="<?php echo htmlspecialchars($user["email"]); ?>"
                required
            >
        </div>

        <div>
            <label for="phone">Nomor Telepon</label>
            <input
                type="text"
                id="phone"
                name="phone"
                maxlength="20"
                value="<?php echo htmlspecialchars($user["phone"] ?? ""); ?>"
            >
        </div>

        <div>
            <label for="address">Alamat</label>
            <textarea
                id="address"
                name="address"
                rows="4"
            ><?php echo htmlspecialchars($user["address"] ?? ""); ?></textarea>
        </div>

        <button type="submit" class="btn-primary">
            Simpan Perubahan
        </button>

        <a href="user.php">
            Kembali
        </a>

    </form>

</div>

<?php

// Memanggil footer admin
require_once "includes/footer.php";

?>
