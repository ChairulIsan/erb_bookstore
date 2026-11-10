<?php

session_start();
require_once "../config/database.php";

// Memastikan hanya admin yang dapat mengakses
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
    header("Location: ../Auth/login.php");
    exit;
}

// Membuat CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Memproses tombol
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        die("Permintaan tidak valid.");
    }

    $orderId = filter_input(INPUT_POST, "order_id", FILTER_VALIDATE_INT);
    $action = $_POST["action"] ?? "";

    if (!$orderId) {
        die("ID pesanan tidak valid.");
    }

    try {

        // 1. Konfirmasi pembayaran
        if ($action === "confirm_payment") {

            $check = $conn->prepare(
                "SELECT payment_method, payment_status,
                        payment_verification_status
                 FROM orders
                 WHERE id = :id"
            );

            $check->execute([":id" => $orderId]);
            $order = $check->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                die("Pesanan tidak ditemukan.");
            }

            if ($order["payment_status"] === "paid") {
                die("Pembayaran sudah dikonfirmasi.");
            }

            if ($order["payment_method"] === "cod") {

                // COD hanya dikonfirmasi setelah uang diterima
                $update = $conn->prepare(
                    "UPDATE orders
                     SET payment_status = 'paid'
                     WHERE id = :id"
                );

            } elseif (
                in_array($order["payment_method"], ["transfer", "qris"]) &&
                $order["payment_verification_status"] === "pending"
            ) {

                // Menyetujui bukti transfer atau QRIS
                $update = $conn->prepare(
                    "UPDATE orders
                     SET payment_status = 'paid',
                         payment_verification_status = 'approved'
                     WHERE id = :id"
                );

            } else {
                die("Pembayaran belum memenuhi syarat konfirmasi.");
            }

            $update->execute([":id" => $orderId]);

            header("Location: pesanan.php?message=payment_success");
            exit;
        }

        // 2. Update status pesanan
        if ($action === "update_status") {

            $allowedStatuses = [
                "pending",
                "processing",
                "packed",
                "shipped",
                "completed",
                "cancelled"
            ];

            $newStatus = $_POST["order_status"] ?? "";

            if (!in_array($newStatus, $allowedStatuses, true)) {
                die("Status pesanan tidak valid.");
            }

            $update = $conn->prepare(
                "UPDATE orders
                 SET order_status = :status
                 WHERE id = :id"
            );

            $update->execute([
                ":status" => $newStatus,
                ":id" => $orderId
            ]);

            header("Location: pesanan.php?message=status_success");
            exit;
        }

    } catch (PDOException $e) {
        error_log($e->getMessage());
        die("Terjadi kesalahan saat memproses pesanan.");
    }
}


// Mengambil data pesanan
$pageTitle = "Manage Orders";

$query = $conn->prepare(
    "SELECT
        o.id,
        o.order_number,
        o.total_price,
        o.order_status,
        o.payment_status,
        o.payment_method,
        o.payment_verification_status,
        o.created_at,
        o.payment_proof,
        u.name AS customer_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC"
);

$query->execute();
$orders = $query->fetchAll(PDO::FETCH_ASSOC);


// Label status
$statusLabels = [
    "pending" => "Menunggu",
    "processing" => "Diproses",
    "packed" => "Dikemas",
    "shipped" => "Dikirim",
    "completed" => "Selesai",
    "cancelled" => "Dibatalkan"
];

$paymentLabels = [
    "transfer" => "Transfer",
    "qris" => "QRIS",
    "cod" => "COD"
];

?>

<?php require_once "includes/header.php"; ?>


<section class="admin-orders">

    <h1>Manajemen Pesanan</h1>

    <p>Kelola pembayaran dan proses pesanan pelanggan.</p>

    <?php if (isset($_GET["message"])): ?>

        <?php if ($_GET["message"] === "payment_success"): ?>
            <p class="success-message">
                Pembayaran berhasil dikonfirmasi.
            </p>
        <?php elseif ($_GET["message"] === "status_success"): ?>
            <p class="success-message">
                Status pesanan berhasil diperbarui.
            </p>
        <?php endif; ?>

    <?php endif; ?>


    <?php if (empty($orders)): ?>

        <p class="empty-message">Belum ada pesanan.</p>

    <?php else: ?>

        <div class="orders-table-container">

            <table class="orders-table">

                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Pesanan</th>
                        <th>Pelanggan</th>
                        <th>Total</th>
                        <th>Pembayaran</th>
                        <th>Status Pesanan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($orders as $index => $order): ?>

                        <tr>

                            <td>
                                <?php echo $index + 1; ?>
                            </td>

                            <td>
                                <strong>
                                    <?php echo htmlspecialchars($order["order_number"]); ?>
                                </strong>

                                <br>

                                <small>
                                    <?php echo htmlspecialchars($order["created_at"]); ?>
                                </small>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($order["customer_name"]); ?>
                            </td>

                            <td>
                                Rp <?php echo number_format($order["total_price"], 0, ",", "."); ?>
                            </td>

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $paymentLabels[$order["payment_method"] ?? ""] ?? "Belum tersedia"
                                );
                                ?>

                                <br>

                                <?php if ($order["payment_status"] === "paid"): ?>

                                    <span class="payment-paid">Lunas</span>

                                <?php elseif (
                                    $order["payment_verification_status"] === "pending"
                                ): ?>

                                    <span class="payment-pending">
                                        Menunggu Verifikasi
                                    </span>

                                <?php elseif (
                                    $order["payment_verification_status"] === "rejected"
                                ): ?>

                                    <span class="payment-rejected">
                                        Bukti Ditolak
                                    </span>

                                <?php else: ?>

                                    <span class="payment-unpaid">
                                        Belum Dibayar
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- STATUS PESANAN -->
                            <td>

                                <form method="POST" class="status-form">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="order_id"
                                        value="<?php echo (int) $order["id"]; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="update_status"
                                    >

                                    <select name="order_status">

                                        <?php foreach ($statusLabels as $value => $label): ?>

                                            <option
                                                value="<?php echo $value; ?>"
                                                <?php echo $order["order_status"] === $value ? "selected" : ""; ?>
                                            >
                                                <?php echo $label; ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                    <button type="submit" class="btn-update-status">
                                        Update
                                    </button>

                                </form>

                            </td>


                            <!-- KONFIRMASI PEMBAYARAN -->
                            <td>

                                <?php if ($order["payment_status"] !== "paid"): ?>

                                    <?php
                                    $canConfirm =
                                        (
                                            in_array($order["payment_method"], ["transfer", "qris"]) &&
                                            $order["payment_verification_status"] === "pending" &&
                                            !empty($order["payment_proof"])
                                        )
                                        ||
                                        $order["payment_method"] === "cod";
                                    ?>

                                    <?php if ($canConfirm): ?>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Konfirmasi pembayaran pesanan ini?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="order_id"
                                                value="<?php echo (int) $order["id"]; ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="confirm_payment"
                                            >

                                            <button
                                                type="submit"
                                                class="btn-confirm-payment"
                                            >
                                                <?php echo $order["payment_method"] === "cod"
                                                    ? "Konfirmasi COD"
                                                    : "Konfirmasi Pembayaran"; ?>
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <span>Belum dapat dikonfirmasi</span>

                                    <?php endif; ?>

                                <?php else: ?>

                                    <span class="payment-paid">
                                        Sudah Dikonfirmasi
                                    </span>

                                <?php endif; ?>

                                <br>

                                <a
                                    href="detail_pesanan.php?id=<?php echo (int) $order["id"]; ?>"
                                    class="btn-detail-order"
                                >
                                    Detail
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>


<?php require_once "includes/footer.php"; ?>