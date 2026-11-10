
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = $pageTitle ?? "ERB Bookstore";

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <link rel="stylesheet" href="/erb_bookstore/assets/css/style.css">
</head>

<body>

<header class="navbar">

    <!-- ERB LOGO -->
    <a href="/erb_bookstore/index.php" class="navbar-logo">
        <img
            src="/erb_bookstore/assets/images/erb-logo.png"
            alt="ERB Bookstore"
        >
    </a>

    <!-- NAVIGATION -->
    <nav>
        <ul class="nav-links">

            <li>
                <a href="/erb_bookstore/index.php">Home</a>
            </li>

            <li>
                <a href="/erb_bookstore/dashboard/About.php">About</a>
            </li>

            <li>
                <a href="/erb_bookstore/dashboard/buku.php">Books</a>
            </li>

            <?php if (
                isset($_SESSION["user_id"]) &&
                $_SESSION["user_role"] === "customer"
            ): ?>

                <li>
                    <a href="/erb_bookstore/dashboard/riwayat_pesanan.php">
                        Riwayat Pesanan
                    </a>
                </li>

                <li>
                    <a href="/erb_bookstore/dashboard/kirim_pesan.php">
                        Kirim Pesan
                    </a>
                </li>

            <?php endif; ?>

            <?php if (isset($_SESSION["user_id"])): ?>

                <li>
                    <form
                        action="/erb_bookstore/Auth/logout.php"
                        method="POST"
                        class="logout-form"
                    >
                        <button type="submit" class="logout-btn">
                            Logout
                        </button>
                    </form>
                </li>

            <?php else: ?>

                <li>
                    <a href="/erb_bookstore/Auth/login.php">Login</a>
                </li>

            <?php endif; ?>

        </ul>
    </nav>

</header>

<main class="container">
