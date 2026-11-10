
<?php

session_start();
require_once "../config/database.php";

$error = "";

if (isset($_SESSION["user_id"])) {

    if (($_SESSION["user_role"] ?? "") === "admin") {
        header("Location: ../Admin/dashboard.php");
    } else {
        header("Location: ../index.php");
    }

    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $query = "SELECT * FROM users WHERE email = :email";
    $stmt = $conn->prepare($query);

    $stmt->execute([
        ":email" => $email
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        $user &&
        password_verify($password, $user["password"]) &&
        $user["role"] === "admin"
    ) {

        session_regenerate_id(true);

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_name"] = $user["name"];
        $_SESSION["user_role"] = $user["role"];

        header("Location: ../Admin/dashboard.php");
        exit();

    } else {

        $error = "Akun admin tidak ditemukan atau password salah.";

    }
}

$pageTitle = "Admin Login - ERB Bookstore";
require_once "../includes/header.php";

?>

<section class="admin-login-page">

    <div class="admin-login-container">

        <!-- LEFT PANEL -->
        <div class="admin-login-decoration">

            <div class="admin-login-brand">
                <span class="admin-brand-label">ERB BOOKSTORE</span>

                <h1>Manage.<br>Organize.<br>Grow.</h1>

                <p>
                    Kelola koleksi buku, pesanan pelanggan,
                    dan aktivitas ERB Bookstore melalui
                    satu ruang pengelolaan.
                </p>
            </div>

            <div class="admin-illustration">

                <div class="admin-panel-card">
                    <div class="admin-panel-top">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>

                    <div class="admin-panel-content">
                        <div class="admin-panel-line"></div>
                        <div class="admin-panel-line short"></div>

                        <div class="admin-panel-boxes">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>

                        <div class="admin-panel-line"></div>
                        <div class="admin-panel-line medium"></div>
                    </div>
                </div>

            </div>

            <span class="admin-login-tagline">
                ADMINISTRATION PORTAL
            </span>

        </div>

        <!-- RIGHT PANEL -->
        <div class="admin-login-form-area">

            <div class="admin-login-heading">

                <span class="admin-form-label">
                    RESTRICTED ACCESS
                </span>

                <h2>Admin Portal</h2>

                <p>
                    Selamat datang kembali.
                    Silakan masuk menggunakan akun administrator.
                </p>

            </div>

            <?php if ($error !== ""): ?>

                <div class="admin-login-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>

            <?php endif; ?>

            <form method="POST" action="">

                <div class="admin-login-input">
                    <label for="email">Admin Email</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Masukkan email administrator"
                        autocomplete="username"
                        required
                    >
                </div>

                <div class="admin-login-input">
                    <label for="password">Password</label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Masukkan password"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button type="submit" class="admin-login-submit">
                    Access Dashboard
                    <span>→</span>
                </button>

            </form>

            <div class="admin-login-bottom">

                <div class="admin-login-divider"></div>

                <a href="login.php">
                    ← Kembali ke Customer Login
                </a>

                <p>
                    ERB Bookstore Administration
                </p>

            </div>

        </div>

    </div>

</section>

<?php require_once "../includes/footer.php"; ?>
