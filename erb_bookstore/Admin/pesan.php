
<?php

session_start();

require_once "../config/database.php";

// Memastikan hanya admin yang dapat mengakses
if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["user_role"] !== "admin"
) {
    header("Location: ../Auth/login.php");
    exit;
}

$pageTitle = "Pesan Customer";

try {
    // Mengambil seluruh pesan customer
    $query = $conn->prepare("
        SELECT
            m.id,
            m.subject,
            m.message,
            m.status,
            m.created_at,
            u.name AS customer_name
        FROM messages m
        JOIN users u ON m.user_id = u.id
        ORDER BY
            CASE
                WHEN m.status = 'unread' THEN 0
                ELSE 1
            END,
            m.created_at DESC
    ");

    $query->execute();
    $messages = $query->fetchAll(PDO::FETCH_ASSOC);

    // Menghitung pesan yang belum dibaca
    $unreadQuery = $conn->query("
        SELECT COUNT(*)
        FROM messages
        WHERE status = 'unread'
    ");

    $unreadCount = $unreadQuery->fetchColumn();

} catch (PDOException $e) {
    error_log($e->getMessage());
    $messages = [];
    $unreadCount = 0;
    $error = "Gagal mengambil data pesan.";
}

require_once "includes/header.php";

?>

<section class="admin-orders">

    <div class="order-detail-heading">
        <div>
            <h1>Pesan Customer</h1>
            <p>Kelola pertanyaan dan pesan dari customer ERB Bookstore.</p>
        </div>
    </div>

    <?php if (isset($error)): ?>

        <div class="order-alert error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>

    <div class="message-stat">
        <div>
            <span>Total Pesan</span>
            <h2><?php echo count($messages); ?></h2>
        </div>

        <div>
            <span>Belum Dibaca</span>
            <h2><?php echo (int) $unreadCount; ?></h2>
        </div>
    </div>

    <div class="orders-table-container">

        <table class="orders-table">

            <thead>
                <tr>
                    <th>No.</th>
                    <th>Customer</th>
                    <th>Judul Pesan</th>
                    <th>Isi Pesan</th>
                    <th>Tanggal</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>

                <?php if (count($messages) > 0): ?>

                    <?php foreach ($messages as $index => $message): ?>

                        <tr>

                            <td>
                                <?php echo $index + 1; ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($message["customer_name"]); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($message["subject"]); ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    mb_substr($message["message"], 0, 60)
                                );

                                if (mb_strlen($message["message"]) > 60) {
                                    echo "...";
                                }
                                ?>
                            </td>

                            <td>
                                <?php
                                echo date(
                                    "d-m-Y H:i",
                                    strtotime($message["created_at"])
                                );
                                ?>
                            </td>

                            <td>
                                <?php if ($message["status"] === "unread"): ?>

                                    <span class="message-status unread">
                                        Belum Dibaca
                                    </span>

                                <?php elseif ($message["status"] === "read"): ?>

                                    <span class="message-status read">
                                        Sudah Dibaca
                                    </span>

                                <?php else: ?>

                                    <span class="message-status replied">
                                        Sudah Dibalas
                                    </span>

                                <?php endif; ?>
                            </td>

                            <td>
                                <a
                                    href="detail_pesan.php?id=<?php echo (int) $message["id"]; ?>"
                                    class="btn-detail-order"
                                >
                                    Buka Pesan
                                </a>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7" class="empty-message">
                            Belum ada pesan dari customer.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>

<?php require_once "includes/footer.php"; ?>

