
<?php

session_start();
require_once "../config/database.php";

$error = "";

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
        $user["role"] === "customer"
    ) {

        session_regenerate_id(true);

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_name"] = $user["name"];
        $_SESSION["user_role"] = $user["role"];

        header("Location: ../index.php");
        exit();

    } else {

        $error = "Email atau password salah.";

    }
}

$pageTitle = "Login - ERB Bookstore";
require_once "../includes/header.php";

?>

<section class="login-page">

    <div class="login-container">

        <!-- LEFT SIDE -->
        <div class="login-decoration">

            <span class="login-label">ERB BOOKSTORE</span>

            <div class="login-brand">
                <h1>Welcome<br>Back.</h1>

                <p>
                    Setiap halaman membawa cerita.
                    Mulailah perjalanan membaca dan
                    temukan pengetahuan baru bersama kami.
                </p>
            </div>

            <div class="login-book-decoration">
                <div class="login-book">
                    <span>ERB</span>
                    <strong>BOOKSTORE</strong>
                    <small>Enjoy Reading</small>
                </div>
            </div>

            <span class="login-tagline">
                ENJOY READING, DISCOVER ENDLESS KNOWLEDGE.
            </span>

        </div>

        <!-- RIGHT SIDE -->
        <div class="login-form-area">

            <div class="login-form-header">
                <span class="form-small-label">YOUR READING JOURNEY</span>

                <h2>Sign In</h2>

                <p>Masuk ke akun ERB Bookstore untuk melanjutkan.</p>
            </div>

            <?php if ($error !== ""): ?>

                <div class="login-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>

            <?php endif; ?>

            <form method="POST" action="">

                <div class="login-input-group">
                    <label for="email">Email Address</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Masukkan email lu"
                        autocomplete="email"
                        required
                    >
                </div>

                <div class="login-input-group">
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

                <button type="submit" class="login-submit">
                    Login to Account
                    <span>→</span>
                </button>

            </form>

            <div class="login-links">

                <p>
                    Belum punya akun?
                    <a href="register.php">Daftar sekarang</a>
                </p>

                <div class="login-divider"></div>

                <p>
                    Administrator?
                    <a href="admin_login.php">Login sebagai Admin</a>
                </p>

            </div>

        </div>

    </div>

</section>

<?php require_once "../includes/footer.php"; ?>
