
<?php

session_start();

require_once "../config/database.php";

// Memeriksa apakah pengguna sudah login
if (!isset($_SESSION["user_id"])) {
    header("Location: ../Auth/login.php");
    exit();
}

// Memeriksa hak akses admin
if (($_SESSION["user_role"] ?? "") !== "admin") {
    header("Location: ../index.php");
    exit();
}

$pageTitle = "Admin Dashboard";

// Menghitung total buku
$totalBooks = $conn->query(
    "SELECT COUNT(*) FROM books"
)->fetchColumn();

// Menghitung total kategori
$totalCategories = $conn->query(
    "SELECT COUNT(*) FROM categories"
)->fetchColumn();

// Menghitung total pengguna
$totalUsers = $conn->query(
    "SELECT COUNT(*) FROM users"
)->fetchColumn();

// Menghitung total pesanan
$totalOrders = $conn->query(
    "SELECT COUNT(*) FROM orders"
)->fetchColumn();

// Menghitung pesanan yang menunggu konfirmasi
$pendingOrders = $conn->query(
    "SELECT COUNT(*) FROM orders
     WHERE order_status = 'pending'"
)->fetchColumn();

// Menghitung pembayaran yang belum lunas
$unpaidOrders = $conn->query(
    "SELECT COUNT(*) FROM orders
     WHERE payment_status = 'unpaid'"
)->fetchColumn();

require_once __DIR__ . "/includes/header.php";

?>

<section class="admin-dashboard">

    <!-- WELCOME BANNER -->
    <div class="dashboard-welcome">

        <div class="welcome-content">
            <span class="welcome-label">ERB BOOKSTORE / ADMINISTRATION</span>

            <h1>
                Welcome back,
                <?php echo htmlspecialchars(
                    $_SESSION["user_name"] ?? "Admin"
                ); ?>!
            </h1>

            <p>
                Kelola toko buku ERB dan pantau seluruh aktivitas
                operasional melalui dashboard ini.
            </p>
        </div>

        <div class="welcome-decoration">
            <span>ERB</span>
            <strong>BOOKSTORE</strong>
            <small>ADMIN PANEL</small>
        </div>

    </div>

    <!-- STATISTICS -->
    <div class="dashboard-section-heading">
        <div>
            <span class="dashboard-eyebrow">OVERVIEW</span>
            <h2>Store Overview</h2>
            <p>Ringkasan data ERB Bookstore saat ini.</p>
        </div>
    </div>

    <div class="dashboard-stats">

        <div class="stat-card stat-books">
            <div class="stat-card-top">
                <span class="stat-icon">B</span>
                <span class="stat-caption">COLLECTION</span>
            </div>
            <h3>Total Books</h3>
            <p><?php echo (int) $totalBooks; ?></p>
            <small>Jumlah buku dalam database</small>
        </div>

        <div class="stat-card stat-categories">
            <div class="stat-card-top">
                <span class="stat-icon">C</span>
                <span class="stat-caption">CLASSIFICATION</span>
            </div>
            <h3>Total Categories</h3>
            <p><?php echo (int) $totalCategories; ?></p>
            <small>Kategori buku tersedia</small>
        </div>

        <div class="stat-card stat-users">
            <div class="stat-card-top">
                <span class="stat-icon">U</span>
                <span class="stat-caption">COMMUNITY</span>
            </div>
            <h3>Total Users</h3>
            <p><?php echo (int) $totalUsers; ?></p>
            <small>Seluruh akun terdaftar</small>
        </div>

        <div class="stat-card stat-orders">
            <div class="stat-card-top">
                <span class="stat-icon">O</span>
                <span class="stat-caption">TRANSACTIONS</span>
            </div>
            <h3>Total Orders</h3>
            <p><?php echo (int) $totalOrders; ?></p>
            <small>Seluruh pesanan pelanggan</small>
        </div>

        <div class="stat-card stat-pending">
            <div class="stat-card-top">
                <span class="stat-icon">!</span>
                <span class="stat-caption">ATTENTION</span>
            </div>
            <h3>Pending Orders</h3>
            <p><?php echo (int) $pendingOrders; ?></p>
            <small>Pesanan menunggu diproses</small>
        </div>

        <div class="stat-card stat-unpaid">
            <div class="stat-card-top">
                <span class="stat-icon">₨</span>
                <span class="stat-caption">PAYMENT</span>
            </div>
            <h3>Unpaid Orders</h3>
            <p><?php echo (int) $unpaidOrders; ?></p>
            <small>Pesanan belum lunas</small>
        </div>

    </div>

    <!-- ORDER MANAGEMENT -->
    <div class="dashboard-order-panel">

        <div class="order-panel-content">
            <span class="dashboard-eyebrow">STORE OPERATIONS</span>

            <h2>Order Management</h2>

            <p>
                Pantau pesanan pelanggan, verifikasi pembayaran,
                dan perbarui status pengiriman melalui halaman
                pengelolaan pesanan.
            </p>

            <a href="pesanan.php" class="admin-order-button">
                Manage Orders <span>→</span>
            </a>
        </div>

        <div class="order-panel-decoration">
            <span>ERB</span>
            <small>ORDER CENTER</small>
        </div>

    </div>

    <!-- ADMIN INFORMATION -->
    <div class="dashboard-admin-profile">

        <div class="admin-profile-heading">
            <span class="dashboard-eyebrow">ACCOUNT DETAILS</span>
            <h2>Admin Information</h2>
        </div>

        <div class="admin-profile-content">

            <div class="admin-profile-avatar">
                <?php
                $adminName = $_SESSION["user_name"] ?? "Admin";
                echo htmlspecialchars(
                    strtoupper(substr($adminName, 0, 1))
                );
                ?>
            </div>

            <div class="admin-profile-details">
                <h3>
                    <?php echo htmlspecialchars($adminName); ?>
                </h3>

                <p>Administrator ERB Bookstore</p>
            </div>

            <span class="admin-role-badge">
                ADMINISTRATOR
            </span>

        </div>

    </div>

</section>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
