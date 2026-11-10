
<?php

// Memastikan session sudah aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Judul halaman
$pageTitle = $pageTitle ?? "Admin Panel - ERB Bookstore";

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?php echo htmlspecialchars($pageTitle); ?>
    </title>

    <!-- ADMIN CSS -->
    <link rel="stylesheet" href="/erb_bookstore/assets/css/admin.css">

</head>

<body>

    <div class="admin-layout">

        <?php require_once __DIR__ . "/sidebar.php"; ?>

        <main class="admin-main">

            <header class="admin-topbar">

                <h2>
                    <?php echo htmlspecialchars($pageTitle); ?>
                </h2>

                <p>
                    Welcome, <?php echo htmlspecialchars($_SESSION["user_name"] ?? "Admin"); ?>
                </p>

            </header>

            


