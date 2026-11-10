
<?php

session_start();

require_once "../config/database.php";

// Memastikan customer sudah login
if (!isset($_SESSION["user_id"])) {
    header("Location: ../Auth/login.php");
    exit;
}

if ($_SESSION["user_role"] !== "customer") {
    header("Location: ../Admin/dashboard.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

// Mengambil seluruh riwayat pesanan customer
$orderQuery = $conn->prepare(
    "SELECT
        id,
        order_number,
        total_price,
        order_status,
        payment_status,
        created_at
     FROM orders
     WHERE user_id = :user_id
     ORDER BY created_at DESC"
);

$orderQuery->execute([
    ":user_id" => $userId
]);

$orders = $orderQuery->fetchAll(PDO::FETCH_ASSOC);

require_once "../includes/header.php";

?>

<section class="order-history-page">

    <h1>Riwayat Pesanan</h1>

    <p>
        Berikut adalah daftar transaksi yang pernah kamu lakukan.
    </p>

    <?php if (count($orders) > 0): ?>

        <div class="order-history-container">

            <?php foreach ($orders as $order): ?>

                <div class="order-card">

                    <h3>
                        <?php echo htmlspecialchars(
                            $order["order_number"]
                        ); ?>
                    </h3>

                    <p>
                        Tanggal:
                        <?php echo htmlspecialchars(
                            $order["created_at"]
                        ); ?>
                    </p>

                    <p>
                        Total Pembelian:
                        <strong>
                            Rp
                            <?php echo number_format(
                                $order["total_price"],
                                0,
                                ",",
                                "."
                            ); ?>
                        </strong>
                    </p>

                    <p>
                        Status Pesanan:
                        <?php echo htmlspecialchars(
                            $order["order_status"]
                        ); ?>
                    </p>

                    <p>
                        Status Pembayaran:
                        <?php echo htmlspecialchars(
                            $order["payment_status"]
                        ); ?>
                    </p>

                    <a href="detail_pesanan.php?id=<?php echo (int) $order["id"]; ?>">
                        Lihat Detail Pesanan
                    </a>

                </div>

                <hr>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="empty-orders">

            <h2>Belum Ada Pesanan</h2>

            <p>
                Kamu belum melakukan pembelian di ERB Bookstore.
            </p>

            <a href="buku.php">
                Mulai Belanja
            </a>

        </div>

    <?php endif; ?>

</section>

<?php require_once "../includes/footer.php"; ?>
