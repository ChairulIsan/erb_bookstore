
<?php

session_start();
require_once "../config/database.php";

// 1. Memastikan hanya admin yang dapat mengakses
if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["user_role"] !== "admin"
) {
    header("Location: ../Auth/login.php");
    exit;
}

// 2. CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// 3. Mengambil ID pesanan
$orderId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$orderId) {
    die("ID pesanan tidak valid.");
}

// 4. Memproses konfirmasi atau penolakan pembayaran
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        die("Permintaan tidak valid.");
    }

    $action = $_POST["action"] ?? "";

    try {

        // Mengambil data pembayaran terbaru
        $check = $conn->prepare(
            "SELECT payment_method, payment_status,
                    payment_verification_status, payment_proof
             FROM orders
             WHERE id = :id"
        );

        $check->execute([":id" => $orderId]);
        $payment = $check->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            die("Pesanan tidak ditemukan.");
        }

        // Memastikan hanya transfer dan QRIS yang dapat diverifikasi
        if (
            !in_array($payment["payment_method"], ["transfer", "qris"], true) ||
            $payment["payment_status"] === "paid" ||
            $payment["payment_verification_status"] !== "pending" ||
            empty($payment["payment_proof"])
        ) {
            die("Pembayaran tidak dapat diverifikasi.");
        }

        // SETUJUI PEMBAYARAN
        if ($action === "approve") {

            $update = $conn->prepare(
                "UPDATE orders
                 SET payment_status = 'paid',
                     payment_verification_status = 'approved'
                 WHERE id = :id"
            );

            $update->execute([":id" => $orderId]);

            header("Location: detail_pesanan.php?id=" . $orderId . "&message=approved");
            exit;
        }

        // TOLAK PEMBAYARAN
        elseif ($action === "reject") {

            $update = $conn->prepare(
                "UPDATE orders
                 SET payment_status = 'unpaid',
                     payment_verification_status = 'rejected'
                 WHERE id = :id"
            );

            $update->execute([":id" => $orderId]);

            header("Location: detail_pesanan.php?id=" . $orderId . "&message=rejected");
            exit;
        }

    } catch (PDOException $e) {
        error_log($e->getMessage());
        die("Terjadi kesalahan saat memproses pembayaran.");
    }
}


// 5. Mengambil informasi pesanan
$query = $conn->prepare(
    "SELECT
        o.*,
        u.name AS customer_name,
        sz.zone_name,
        sz.shipping_fee
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN shipping_zones sz
        ON o.shipping_zone_id = sz.id
    WHERE o.id = :id
    LIMIT 1"
);

$query->execute([":id" => $orderId]);
$order = $query->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Pesanan tidak ditemukan.");
}


// 6. Mengambil daftar buku dalam pesanan
$itemQuery = $conn->prepare(
    "SELECT
        book_title,
        price,
        quantity
    FROM order_items
    WHERE order_id = :order_id"
);

$itemQuery->execute([":order_id" => $orderId]);
$items = $itemQuery->fetchAll(PDO::FETCH_ASSOC);


// 7. Label status
$statusLabels = [
    "pending" => "Menunggu",
    "processing" => "Diproses",
    "packed" => "Dikemas",
    "shipped" => "Dikirim",
    "completed" => "Selesai",
    "cancelled" => "Dibatalkan"
];

$paymentLabels = [
    "transfer" => "Transfer Bank",
    "qris" => "QRIS",
    "cod" => "Cash on Delivery (COD)"
];


// 8. Memeriksa lokasi file bukti pembayaran
$proofPath = null;

if (!empty($order["payment_proof"])) {

    $storedPath = str_replace("\\", "/", $order["payment_proof"]);

    if (
        strpos($storedPath, "uploads/payment_proofs/") === 0 &&
        !str_contains($storedPath, "..")
    ) {
        $serverPath = dirname(__DIR__) . "/" . $storedPath;

        if (is_file($serverPath)) {
            $proofPath = "../" . $storedPath;
        }
    }
}

$pageTitle = "Detail Pesanan";

?>

<?php require_once "includes/header.php"; ?>


<section class="order-detail">

    <div class="order-detail-heading">

        <div>
            <h1>Detail Pesanan</h1>

            <p>
                Informasi lengkap transaksi pelanggan ERB Bookstore.
            </p>
        </div>

        <a href="pesanan.php" class="btn-back-order">
            Kembali ke Pesanan
        </a>

    </div>


    <!-- NOTIFIKASI -->
    <?php if (isset($_GET["message"])): ?>

        <?php if ($_GET["message"] === "approved"): ?>

            <div class="order-alert success">
                Pembayaran berhasil disetujui.
            </div>

        <?php elseif ($_GET["message"] === "rejected"): ?>

            <div class="order-alert error">
                Bukti pembayaran berhasil ditolak.
            </div>

        <?php endif; ?>

    <?php endif; ?>


    <!-- INFORMASI PESANAN -->
    <div class="detail-card">

        <h2>Informasi Pesanan</h2>

        <div class="detail-grid">

            <div>
                <span>Nomor Pesanan</span>
                <strong>
                    <?php echo htmlspecialchars($order["order_number"]); ?>
                </strong>
            </div>

            <div>
                <span>Tanggal Pesanan</span>
                <strong>
                    <?php echo htmlspecialchars($order["created_at"]); ?>
                </strong>
            </div>

            <div>
                <span>Nama Pelanggan</span>
                <strong>
                    <?php echo htmlspecialchars($order["customer_name"]); ?>
                </strong>
            </div>

            <div>
                <span>Status Pesanan</span>
                <strong>
                    <?php
                    echo htmlspecialchars(
                        $statusLabels[$order["order_status"]] ?? "Tidak diketahui"
                    );
                    ?>
                </strong>
            </div>

        </div>

    </div>


    <!-- DAFTAR BUKU -->
    <div class="detail-card">

        <h2>Produk yang Dibeli</h2>

        <div class="detail-table-container">

            <table class="detail-table">

                <thead>
                    <tr>
                        <th>Nama Buku</th>
                        <th>Harga</th>
                        <th>Jumlah</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($items as $item): ?>

                        <tr>

                            <td>
                                <?php echo htmlspecialchars($item["book_title"]); ?>
                            </td>

                            <td>
                                Rp <?php echo number_format($item["price"], 0, ",", "."); ?>
                            </td>

                            <td>
                                <?php echo (int) $item["quantity"]; ?>
                            </td>

                            <td>
                                Rp <?php
                                echo number_format(
                                    $item["price"] * $item["quantity"],
                                    0,
                                    ",",
                                    "."
                                );
                                ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- INFORMASI PENGIRIMAN -->
    <div class="detail-card">

        <h2>Informasi Pengiriman</h2>

        <div class="detail-grid">

            <div>
                <span>Alamat Pengiriman</span>
                <strong>
                    <?php echo nl2br(htmlspecialchars($order["shipping_address"])); ?>
                </strong>
            </div>

            <div>
                <span>Zona Pengiriman</span>
                <strong>
                    <?php echo htmlspecialchars($order["zone_name"] ?? "Tidak tersedia"); ?>
                </strong>
            </div>

        </div>

    </div>


    <!-- RINCIAN PEMBAYARAN -->
    <div class="detail-card">

        <h2>Rincian Pembayaran</h2>

        <div class="payment-summary">

            <div>
                <span>Subtotal Buku</span>
                <strong>
                    Rp <?php echo number_format($order["subtotal_price"], 0, ",", "."); ?>
                </strong>
            </div>

            <div>
                <span>Ongkos Kirim</span>
                <strong>
                    Rp <?php echo number_format($order["shipping_cost"], 0, ",", "."); ?>
                </strong>
            </div>

            <div class="payment-total">
                <span>Total Pembayaran</span>
                <strong>
                    Rp <?php echo number_format($order["total_price"], 0, ",", "."); ?>
                </strong>
            </div>

        </div>

        <div class="detail-grid">

            <div>
                <span>Metode Pembayaran</span>
                <strong>
                    <?php
                    echo htmlspecialchars(
                        $paymentLabels[$order["payment_method"] ?? ""] ?? "Tidak tersedia"
                    );
                    ?>
                </strong>
            </div>

            <div>
                <span>Status Pembayaran</span>
                <strong>
                    <?php
                    if ($order["payment_status"] === "paid") {
                        echo "Lunas";
                    } elseif ($order["payment_verification_status"] === "pending") {
                        echo "Menunggu Verifikasi";
                    } elseif ($order["payment_verification_status"] === "rejected") {
                        echo "Bukti Ditolak";
                    } else {
                        echo "Belum Dibayar";
                    }
                    ?>
                </strong>
            </div>

        </div>

    </div>


    <!-- BUKTI PEMBAYARAN -->
    <div class="detail-card">

        <h2>Bukti Pembayaran</h2>

        <?php if ($proofPath): ?>

            <div class="proof-preview">

                <img
                    src="<?php echo htmlspecialchars($proofPath); ?>"
                    alt="Bukti Pembayaran"
                >

                <button
                    type="button"
                    class="btn-view-proof"
                    onclick="openProofModal()"
                >
                    Lihat Bukti Pembayaran
                </button>

            </div>

            <!-- POPUP -->
            <div id="proofModal" class="proof-modal">

                <div class="proof-modal-content">

                    <button
                        type="button"
                        class="proof-close"
                        onclick="closeProofModal()"
                        aria-label="Tutup"
                    >
                        &times;
                    </button>

                    <h2>Bukti Pembayaran</h2>

                    <img
                        src="<?php echo htmlspecialchars($proofPath); ?>"
                        alt="Bukti Pembayaran"
                    >

                </div>

            </div>

        <?php else: ?>

            <p class="no-proof">
                Belum ada bukti pembayaran yang tersedia.
            </p>

        <?php endif; ?>


        <!-- TOMBOL VERIFIKASI -->
        <?php if (
            in_array($order["payment_method"], ["transfer", "qris"], true) &&
            $order["payment_verification_status"] === "pending" &&
            $order["payment_status"] !== "paid" &&
            $proofPath
        ): ?>

            <div class="payment-actions">

                <form method="POST"
                    onsubmit="return confirm('Setujui pembayaran ini?');">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
                    >

                    <input type="hidden" name="action" value="approve">

                    <button type="submit" class="btn-approve">
                        Setujui Pembayaran
                    </button>

                </form>


                <form method="POST"
                    onsubmit="return confirm('Tolak bukti pembayaran ini?');">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
                    >

                    <input type="hidden" name="action" value="reject">

                    <button type="submit" class="btn-reject">
                        Tolak Bukti Pembayaran
                    </button>

                </form>

            </div>

        <?php elseif ($order["payment_status"] === "paid"): ?>

            <p class="payment-result success">
                Pembayaran telah dikonfirmasi.
            </p>

        <?php elseif ($order["payment_verification_status"] === "rejected"): ?>

            <p class="payment-result error">
                Bukti pembayaran telah ditolak.
            </p>

        <?php elseif ($order["payment_method"] === "cod"): ?>

            <p class="payment-result">
                Pembayaran COD dikonfirmasi setelah uang diterima.
            </p>

        <?php endif; ?>

    </div>

</section>


<script>

// Membuka popup bukti pembayaran
function openProofModal() {
    document.getElementById("proofModal").style.display = "flex";
}

// Menutup popup
function closeProofModal() {
    document.getElementById("proofModal").style.display = "none";
}

// Menutup popup ketika area luar diklik
window.addEventListener("click", function(event) {

    const modal = document.getElementById("proofModal");

    if (event.target === modal) {
        closeProofModal();
    }

});

// Menutup popup dengan tombol Escape
window.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {
        closeProofModal();
    }

});

</script>


<?php require_once "includes/footer.php"; ?>
