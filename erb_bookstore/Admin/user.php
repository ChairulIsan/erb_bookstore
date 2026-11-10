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
if (($_SESSION["user_role"] ?? "") !== "admin") {
    header("Location: ../index.php");
    exit;
}

// Membuat token CSRF jika belum tersedia
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Mengambil seluruh data user
$query = $conn->prepare("
    SELECT id, name, email, phone, role, created_at
    FROM users
    ORDER BY id DESC
");

$query->execute();

$users = $query->fetchAll(PDO::FETCH_ASSOC);

// Judul halaman
$pageTitle = "Manajemen User - ERB Bookstore";

// Memanggil header admin
require_once __DIR__ . "/includes/header.php";

?>

<section class="admin-users">

    <!-- PAGE HEADING -->
    <div class="users-heading">

        <div>
            <span class="users-eyebrow">USER MANAGEMENT</span>

            <h1>Manajemen User</h1>

            <p>Kelola dan pantau seluruh pengguna ERB Bookstore.</p>
        </div>

        <div class="users-total">
            <span>Total Pengguna</span>
            <strong><?php echo count($users); ?></strong>
        </div>

    </div>

    <!-- USER TABLE -->
    <div class="users-table-container">

        <div class="users-table-heading">

            <div>
                <h2>Daftar Pengguna</h2>
                <p>Informasi akun yang terdaftar di ERB Bookstore.</p>
            </div>

            <span class="users-count">
                <?php echo count($users); ?> Pengguna
            </span>

        </div>

        <div class="users-table-scroll">

            <table class="users-table">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Pengguna</th>
                        <th>Email</th>
                        <th>No. Telepon</th>
                        <th>Role</th>
                        <th>Tanggal Registrasi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (count($users) > 0): ?>

                        <?php foreach ($users as $user): ?>

                            <tr>

                                <!-- ID -->
                                <td class="user-id">
                                    #<?php echo (int) $user["id"]; ?>
                                </td>

                                <!-- NAMA -->
                                <td class="user-name">
                                    <?php echo htmlspecialchars($user["name"]); ?>
                                </td>

                                <!-- EMAIL -->
                                <td class="user-email">
                                    <?php echo htmlspecialchars($user["email"]); ?>
                                </td>

                                <!-- TELEPON -->
                                <td>
                                    <?php echo htmlspecialchars($user["phone"] ?? "-"); ?>
                                </td>

                                <!-- ROLE -->
                                <td>
                                    <?php if ($user["role"] === "admin"): ?>

                                        <span class="user-role admin-role">
                                            Admin
                                        </span>

                                    <?php else: ?>

                                        <span class="user-role customer-role">
                                            Customer
                                        </span>

                                    <?php endif; ?>
                                </td>

                                <!-- TANGGAL -->
                                <td class="user-date">
                                    <?php
                                    echo date(
                                        "d M Y",
                                        strtotime($user["created_at"])
                                    );
                                    ?>
                                </td>

                                <!-- AKSI -->
                                <td>

                                    <div class="user-actions">

                                        <a
                                            href="edit_user.php?id=<?php echo (int) $user["id"]; ?>"
                                            class="btn-edit-user"
                                        >
                                            Edit
                                        </a>

                                        <?php if ($user["role"] === "customer"): ?>

                                            <form
                                                action="hapus_user.php"
                                                method="POST"
                                                class="user-delete-form"
                                                onsubmit="return confirm('Yakin ingin menghapus akun ini?');"
                                            >

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

                                                <button
                                                    type="submit"
                                                    class="btn-delete-user"
                                                >
                                                    Hapus
                                                </button>

                                            </form>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7" class="empty-users">
                                Belum ada pengguna terdaftar.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</section>

<?php require_once __DIR__ . "/includes/footer.php"; ?>