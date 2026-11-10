
<?php

session_start();

if (isset($_SESSION["user_id"])) {
    header("Location: ../index.php");
    exit();
}

require_once "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if (
        $name === "" ||
        $email === "" ||
        $password === "" ||
        $confirmPassword === ""
    ) {

        $error = "Semua data wajib diisi.";

    } elseif (mb_strlen($name) > 100) {

        $error = "Nama maksimal 100 karakter.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Format email tidak valid.";

    } elseif (strlen($password) < 8) {

        $error = "Password minimal 8 karakter.";

    } elseif ($password !== $confirmPassword) {

        $error = "Konfirmasi password tidak cocok.";

    } else {

        $checkQuery = "SELECT id FROM users WHERE email = :email";
        $checkStmt = $conn->prepare($checkQuery);

        $checkStmt->execute([
            ":email" => $email
        ]);

        if ($checkStmt->fetch()) {

            $error = "Email sudah terdaftar.";

        } else {

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $insertQuery = "
                INSERT INTO users (name, email, password, role)
                VALUES (:name, :email, :password, 'customer')
            ";

            try {

                $insertStmt = $conn->prepare($insertQuery);

                $insertStmt->execute([
                    ":name" => $name,
                    ":email" => $email,
                    ":password" => $passwordHash
                ]);

                header("Location: login.php?registered=1");
                exit();

            } catch (PDOException $e) {

                if ($e->getCode() === "23000") {
                    $error = "Email sudah terdaftar.";
                } else {
                    $error = "Pendaftaran gagal. Silakan coba lagi.";
                }

            }
        }
    }
}

$pageTitle = "Register - ERB Bookstore";
require_once "../includes/header.php";

?>

<section class="register-page">

    <div class="register-container">

        <!-- LEFT PANEL -->
        <div class="register-decoration">

            <span class="register-brand-label">
                ERB BOOKSTORE
            </span>

            <div class="register-brand-content">

                <h1>Begin Your<br>Reading Journey.</h1>

                <p>
                    Satu akun untuk menjelajahi berbagai
                    cerita, menemukan wawasan baru,
                    dan mengembangkan diri melalui membaca.
                </p>

            </div>

            <div class="register-book-decoration">

                <div class="register-book">
                    <span>ERB</span>
                    <strong>BOOKSTORE</strong>
                    <small>Enjoy Reading</small>
                </div>

            </div>

            <span class="register-tagline">
                ENJOY READING, DISCOVER ENDLESS KNOWLEDGE.
            </span>

        </div>

        <!-- RIGHT PANEL -->
        <div class="register-form-area">

            <div class="register-form-header">

                <span class="register-small-label">
                    JOIN OUR COMMUNITY
                </span>

                <h2>Create Account</h2>

                <p>
                    Lengkapi informasi berikut untuk membuat akun ERB.
                </p>

            </div>

            <?php if ($error !== ""): ?>

                <div class="register-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>

            <?php endif; ?>

            <form method="POST" action="">

                <div class="register-input-group">
                    <label for="name">Full Name</label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        placeholder="Masukkan nama lengkap"
                        maxlength="100"
                        autocomplete="name"
                        value="<?php echo htmlspecialchars($_POST["name"] ?? ""); ?>"
                        required
                    >
                </div>

                <div class="register-input-group">
                    <label for="email">Email Address</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Masukkan email"
                        autocomplete="email"
                        value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>"
                        required
                    >
                </div>

                <div class="register-input-group">
                    <label for="password">Password</label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Minimal 8 karakter"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <div class="register-input-group">
                    <label for="confirm_password">
                        Confirm Password
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Ulangi password"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <button type="submit" class="register-submit">
                    Create My Account
                    <span>→</span>
                </button>

            </form>

            <div class="register-login-link">

                <p>
                    Sudah punya akun?
                    <a href="login.php">Login di sini</a>
                </p>

            </div>

        </div>

    </div>

</section>

<?php require_once "../includes/footer.php"; ?>
