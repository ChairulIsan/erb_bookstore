
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

// Mengambil seluruh pesanan customer
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

// Mengambil jumlah buku setiap pesanan
$countQuery = $conn->prepare(
    "SELECT
        order_id,
        SUM(quantity) AS total_books
     FROM order_items
     GROUP BY order_id"
);

$countQuery->execute();

$bookCounts = [];

foreach ($countQuery->fetchAll(PDO::FETCH_ASSOC) as $count) {
    $bookCounts[$count["order_id"]] = $count["total_books"];
}

// Label status pesanan
$statusLabels = [
    "pending" => "Menunggu Konfirmasi",
    "processing" => "Sedang Diproses",
    "shipped" => "Sedang Dikirim",
    "completed" => "Selesai",
    "cancelled" => "Dibatalkan"
];

$paymentLabels = [
    "unpaid" => "Belum Dibayar",
    "paid" => "Sudah Dibayar",
    "refunded" => "Dikembalikan"
];

require_once "../includes/header.php";

?>

<section class="order-history-page">

    <div class="order-history-heading">

        <p class="section-label">MY ACCOUNT</p>

        <h1>Riwayat Pesanan</h1>

        <p>
            Pantau semua transaksi dan perjalanan
            belanja buku lu di ERB Bookstore.
        </p>

    </div>

    <div class="order-history-summary">

        <div class="history-summary-card">
            <span>Total Pesanan</span>
            <h2><?php echo count($orders); ?></h2>
        </div>

        <div class="history-summary-card">
            <span>Pesanan Selesai</span>
            <h2>
                <?php
                echo count(array_filter(
                    $orders,
                    fn($order) =>
                        $order["order_status"] === "completed"
                ));
                ?>
            </h2>
        </div>

        <div class="history-summary-card">
            <span>Dalam Proses</span>
            <h2>
                <?php
                echo count(array_filter(
                    $orders,
                    fn($order) =>
                        in_array(
                            $order["order_status"],
                            ["pending", "processing", "shipped"]
                        )
                ));
                ?>
            </h2>
        </div>

    </div>

    <div class="order-history-list">

        <h2>Semua Transaksi</h2>

        <?php if (empty($orders)): ?>

            <div class="empty-orders">

                <h3>Belum Ada Pesanan</h3>

                <p>
                    Lu belum memiliki transaksi di ERB Bookstore.
                </p>

                <a href="buku.php" class="btn">
                    Mulai Belanja
                </a>

            </div>

        <?php else: ?>

            <?php foreach ($orders as $order): ?>

                <?php
                $status = $order["order_status"];
                $payment = $order["payment_status"];

                $statusClass = in_array(
                    $status,
                    ["pending", "processing", "shipped",
                     "completed", "cancelled"]
                ) ? $status : "pending";
                ?>

                <article class="order-history-card">

                    <div class="order-card-top">

                        <div>
                            <span class="order-date">
                                <?php echo date(
                                    "d M Y, H:i",
                                    strtotime($order["created_at"])
                                ); ?>
                            </span>

                            <h3>
                                <?php echo htmlspecialchars(
                                    $order["order_number"]
                                ); ?>
                            </h3>
                        </div>

                        <span class="order-status <?php
                            echo $statusClass;
                        ?>">
                            <?php echo htmlspecialchars(
                                $statusLabels[$status] ?? $status
                            ); ?>
                        </span>

                    </div>

                    <div class="order-card-information">

                        <div>
                            <span>Jumlah Buku</span>
                            <strong>
                                <?php echo (int) (
                                    $bookCounts[$order["id"]] ?? 0
                                ); ?> Buku
                            </strong>
                        </div>

                        <div>
                            <span>Total Pembayaran</span>
                            <strong>
                                Rp <?php echo number_format(
                                    $order["total_price"],
                                    0,
                                    ",",
                                    "."
                                ); ?>
                            </strong>
                        </div>

                        <div>
                            <span>Pembayaran</span>
                            <strong>
                                <?php echo htmlspecialchars(
                                    $paymentLabels[$payment] ?? $payment
                                ); ?>
                            </strong>
                        </div>

                    </div>

                    <div class="order-card-bottom">

                        <span>ERB Bookstore</span>

                        <a
                            href="detail_pesanan.php?id=<?php
                                echo (int) $order["id"];
                            ?>"
                            class="btn"
                        >
                            Lihat Detail
                        </a>

                    </div>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</section>

<?php require_once "../includes/footer.php"; ?>
