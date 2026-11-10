<?php

session_start();

require_once "../config/database.php";

// 1. Memastikan customer sudah login
if (!isset($_SESSION["user_id"])) {
    header("Location: ../Auth/login.php");
    exit;
}

if ($_SESSION["user_role"] !== "customer") {
    header("Location: ../Admin/dashboard.php");
    exit;
}

// 2. Mengambil ID pesanan
$orderId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$orderId || $orderId <= 0) {
    die("ID pesanan tidak valid.");
}

$userId = (int) $_SESSION["user_id"];

// 3. Mengambil data pesanan milik customer
$orderQuery = $conn->prepare(
    "SELECT
        id,
        order_number,
        total_price,
        subtotal_price,
        shipping_cost,
        payment_method,
        shipping_address,
        order_status,
        payment_status,
        created_at
     FROM orders
     WHERE id = :id
     AND user_id = :user_id"
);

$orderQuery->execute([
    ":id" => $orderId,
    ":user_id" => $userId
]);

$order = $orderQuery->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Pesanan tidak ditemukan.");
}

// 4. Mengambil detail buku
$itemQuery = $conn->prepare(
    "SELECT
        book_title,
        price,
        quantity
     FROM order_items
     WHERE order_id = :order_id"
);

$itemQuery->execute([
    ":order_id" => $orderId
]);

$orderItems = $itemQuery->fetchAll(PDO::FETCH_ASSOC);

// 5. Menyiapkan label
$paymentLabels = [
    "transfer" => "Transfer Bank",
    "qris" => "QRIS",
    "cod" => "Cash on Delivery (COD)"
];

$statusLabels = [
    "pending" => "Menunggu Konfirmasi",
    "processing" => "Sedang Diproses",
    "shipped" => "Sedang Dikirim",
    "completed" => "Selesai",
    "cancelled" => "Dibatalkan"
];

$paymentStatusLabels = [
    "unpaid" => "Belum Dibayar",
    "paid" => "Sudah Dibayar",
    "refunded" => "Dana Dikembalikan"
];

// Fallback untuk transaksi lama
$shippingCost = (float) ($order["shipping_cost"] ?? 0);

$subtotalPrice = (float) ($order["subtotal_price"] ?? 0);

if ($subtotalPrice <= 0) {
    $subtotalPrice = (float) $order["total_price"] - $shippingCost;
}

$paymentMethod = $order["payment_method"] ?? "";

require_once "../includes/header.php";

?>

<section class="order-detail-page">

    <div class="order-detail-heading">
        <p class="section-label">ORDER INFORMATION</p>
        <h1>Detail Pesanan</h1>
        <p>Periksa informasi transaksi dan pembayaran anda.</p>
    </div>

    <div class="order-success">
        <h2>Pesanan Berhasil Dibuat!</h2>
        <p>Terima kasih telah berbelanja di ERB Bookstore.</p>
    </div>

    <div class="order-information">

        <h2>Informasi Pesanan</h2>

        <p>
            Nomor Pesanan:
            <strong>
                <?php echo htmlspecialchars($order["order_number"]); ?>
            </strong>
        </p>

        <p>
            Tanggal:
            <?php echo date("d M Y, H:i", strtotime($order["created_at"])); ?>
        </p>

        <p>
            Status Pesanan:
            <strong>
                <?php echo htmlspecialchars(
                    $statusLabels[$order["order_status"]]
                    ?? $order["order_status"]
                ); ?>
            </strong>
        </p>

        <p>
            Status Pembayaran:
            <strong>
                <?php echo htmlspecialchars(
                    $paymentStatusLabels[$order["payment_status"]]
                    ?? $order["payment_status"]
                ); ?>
            </strong>
        </p>

        <p>
            Metode Pembayaran:
            <strong>
                <?php echo htmlspecialchars(
                    $paymentLabels[$paymentMethod] ?? "Belum tersedia"
                ); ?>
            </strong>
        </p>

        <p>
            Alamat Pengiriman:
            <br>
            <?php echo nl2br(htmlspecialchars($order["shipping_address"])); ?>
        </p>

    </div>

    <div class="order-items">

        <h2>Buku yang Dibeli</h2>

        <?php foreach ($orderItems as $item): ?>

            <div class="order-item">

                <h3>
                    <?php echo htmlspecialchars($item["book_title"]); ?>
                </h3>

                <p>
                    Harga: Rp
                    <?php echo number_format($item["price"], 0, ",", "."); ?>
                </p>

                <p>
                    Jumlah:
                    <?php echo (int) $item["quantity"]; ?>
                </p>

                <p>
                    Subtotal: Rp
                    <?php echo number_format(
                        $item["price"] * $item["quantity"],
                        0,
                        ",",
                        "."
                    ); ?>
                </p>

            </div>

            <hr>

        <?php endforeach; ?>

        <div class="order-total">
            <p>
                Subtotal Buku:
                <strong>
                    Rp <?php echo number_format($subtotalPrice, 0, ",", "."); ?>
                </strong>
            </p>

            <p>
                Ongkos Kirim:
                <strong>
                    Rp <?php echo number_format($shippingCost, 0, ",", "."); ?>
                </strong>
            </p>

            <h2>
                Total Pembayaran:
                Rp <?php echo number_format($order["total_price"], 0, ",", "."); ?>
            </h2>
        </div>

    </div>

    <div class="order-actions">

        <button type="button" class="receipt-button" id="openReceipt">
            Lihat Struk Pembayaran
        </button>

        <a href="buku.php">Kembali Belanja</a>

        <a href="riwayat_pesanan.php">Lihat Riwayat Pesanan</a>

    </div>

</section>

<!-- POP-UP STRUK -->
<div class="receipt-modal" id="receiptModal" hidden>

    <div class="receipt-overlay" id="closeReceiptOverlay"></div>

    <div class="receipt-box" role="dialog" aria-modal="true"
         aria-labelledby="receiptTitle">

        <button type="button" class="receipt-close" id="closeReceipt"
                aria-label="Tutup">
            &times;
        </button>

        <div class="receipt-content" id="receiptContent">

            <div class="receipt-header">
                <h2 id="receiptTitle">ERB Bookstore</h2>
                <p>Enjoy Reading, Discover Endless Knowledge.</p>
                <span>STRUK PEMBAYARAN</span>
            </div>

            <div class="receipt-divider"></div>

            <div class="receipt-information">

                <p>
                    Nomor Pesanan
                    <strong>
                        <?php echo htmlspecialchars($order["order_number"]); ?>
                    </strong>
                </p>

                <p>
                    Tanggal
                    <strong>
                        <?php echo date("d/m/Y H:i", strtotime($order["created_at"])); ?>
                    </strong>
                </p>

                <p>
                    Metode Pembayaran
                    <strong>
                        <?php echo htmlspecialchars(
                            $paymentLabels[$paymentMethod] ?? "Belum tersedia"
                        ); ?>
                    </strong>
                </p>

                <p>
                    Status Pembayaran
                    <strong>
                        <?php echo htmlspecialchars(
                            $paymentStatusLabels[$order["payment_status"]]
                            ?? $order["payment_status"]
                        ); ?>
                    </strong>
                </p>

            </div>

            <div class="receipt-divider"></div>

            <h3>Rincian Pembelian</h3>

            <?php foreach ($orderItems as $item): ?>

                <div class="receipt-item">

                    <div>
                        <strong>
                            <?php echo htmlspecialchars($item["book_title"]); ?>
                        </strong>

                        <small>
                            <?php echo (int) $item["quantity"]; ?> x
                            Rp <?php echo number_format($item["price"], 0, ",", "."); ?>
                        </small>
                    </div>

                    <strong>
                        Rp <?php echo number_format(
                            $item["price"] * $item["quantity"],
                            0,
                            ",",
                            "."
                        ); ?>
                    </strong>

                </div>

            <?php endforeach; ?>

            <div class="receipt-divider"></div>

            <div class="receipt-total">

                <p>
                    Subtotal Buku
                    <strong>
                        Rp <?php echo number_format($subtotalPrice, 0, ",", "."); ?>
                    </strong>
                </p>

                <p>
                    Ongkos Kirim
                    <strong>
                        Rp <?php echo number_format($shippingCost, 0, ",", "."); ?>
                    </strong>
                </p>

                <div class="receipt-grand-total">
                    <span>TOTAL</span>
                    <strong>
                        Rp <?php echo number_format($order["total_price"], 0, ",", "."); ?>
                    </strong>
                </div>

            </div>

            <div class="receipt-divider"></div>

            <div class="receipt-footer">
                <p>Terima kasih telah berbelanja!</p>
                <strong>ERB Bookstore</strong>
            </div>

        </div>

        <button type="button" class="receipt-print-button" id="printReceipt">
            Cetak Struk
        </button>

    </div>

</div>

<script>
const receiptModal = document.getElementById("receiptModal");
const openReceipt = document.getElementById("openReceipt");
const closeReceipt = document.getElementById("closeReceipt");
const closeOverlay = document.getElementById("closeReceiptOverlay");
const printReceipt = document.getElementById("printReceipt");

openReceipt.addEventListener("click", function () {
    receiptModal.hidden = false;
});

function hideReceipt() {
    receiptModal.hidden = true;
}

closeReceipt.addEventListener("click", hideReceipt);
closeOverlay.addEventListener("click", hideReceipt);

document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
        hideReceipt();
    }
});

printReceipt.addEventListener("click", function () {
    const receiptContent = document.getElementById("receiptContent").innerHTML;

    const printWindow = window.open("", "_blank", "width=800,height=900");

    if (!printWindow) {
        alert("Izinkan pop-up browser untuk mencetak struk.");
        return;
    }

    printWindow.document.write(`
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <title>Struk ERB Bookstore</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    color: #30251F;
                    padding: 25px;
                    max-width: 600px;
                    margin: auto;
                }

                .receipt-header {
                    text-align: center;
                    margin-bottom: 20px;
                }

                .receipt-header h2 {
                    color: #4B3025;
                    margin-bottom: 5px;
                }

                .receipt-header p {
                    font-size: 12px;
                    color: #78665A;
                }

                .receipt-header span {
                    display: block;
                    margin-top: 20px;
                    font-weight: bold;
                    letter-spacing: 2px;
                }

                .receipt-divider {
                    border-top: 1px dashed #A99A8D;
                    margin: 18px 0;
                }

                .receipt-information p,
                .receipt-total p {
                    display: flex;
                    justify-content: space-between;
                    gap: 15px;
                    font-size: 13px;
                }

                .receipt-item {
                    display: flex;
                    justify-content: space-between;
                    gap: 15px;
                    margin: 15px 0;
                    font-size: 13px;
                }

                .receipt-item small {
                    display: block;
                    margin-top: 5px;
                }

                .receipt-grand-total {
                    display: flex;
                    justify-content: space-between;
                    font-size: 17px;
                    font-weight: bold;
                    margin-top: 20px;
                }

                .receipt-footer {
                    text-align: center;
                    margin-top: 25px;
                }

                .receipt-footer p {
                    font-size: 13px;
                }

                @media print {
                    body {
                        padding: 0;
                    }
                }
            </style>
        </head>
        <body>
            ${receiptContent}
        </body>
        </html>
    `);

    printWindow.document.close();
    printWindow.focus();

    printWindow.onload = function () {
        printWindow.print();
    };
});
</script>

<?php require_once "../includes/footer.php"; ?>