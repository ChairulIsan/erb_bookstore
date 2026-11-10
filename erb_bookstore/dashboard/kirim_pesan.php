
<?php

session_start();

require_once "../config/database.php";

// Memastikan hanya customer yang dapat mengakses
if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["user_role"] !== "customer"
) {
    header("Location: ../Auth/login.php");
    exit;
}

// Membuat CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$userId = $_SESSION["user_id"];
$error = "";

// Memproses pengiriman pesan
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";
    $subject = trim($_POST["subject"] ?? "");
    $message = trim($_POST["message"] ?? "");

    if (!hash_equals($_SESSION["csrf_token"], $token)) {

        $error = "Permintaan tidak valid.";

    } elseif ($subject === "" || $message === "") {

        $error = "Judul dan isi pesan wajib diisi.";

    } elseif (mb_strlen($subject) > 150) {

        $error = "Judul pesan maksimal 150 karakter.";

    } else {

        try {

            $insert = $conn->prepare("
                INSERT INTO messages
                    (user_id, subject, message)
                VALUES
                    (:user_id, :subject, :message)
            ");

            $insert->execute([
                ":user_id" => $userId,
                ":subject" => $subject,
                ":message" => $message
            ]);

            header("Location: kirim_pesan.php?success=1");
            exit;

        } catch (PDOException $e) {

            error_log($e->getMessage());
            $error = "Pesan gagal dikirim. Silakan coba kembali.";
        }
    }
}

// Mengambil seluruh pesan milik customer
try {

    $query = $conn->prepare("
        SELECT
            id,
            subject,
            message,
            admin_reply,
            status,
            created_at,
            replied_at
        FROM messages
        WHERE user_id = :user_id
        ORDER BY created_at DESC
    ");

    $query->execute([
        ":user_id" => $userId
    ]);

    $messages = $query->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log($e->getMessage());
    $messages = [];
    $error = "Gagal mengambil riwayat pesan.";
}

$pageTitle = "Pusat Pesan - ERB Bookstore";

require_once "../includes/header.php";

?>

<section class="customer-message-page">

    <div class="customer-message-heading">
        <h1>Pusat Pesan</h1>
        <p>
            Hubungi admin ERB Bookstore dan lihat balasan pesanmu.
        </p>
    </div>

    <!-- NOTIFIKASI -->
    <?php if (isset($_GET["success"])): ?>

        <div class="message-alert success">
            Pesan berhasil dikirim kepada admin.
        </div>

    <?php endif; ?>

    <?php if ($error !== ""): ?>

        <div class="message-alert error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>


    <!-- FORM KIRIM PESAN -->
    <div class="customer-message-card">

        <h2>Kirim Pesan</h2>

        <p>
            Sampaikan pertanyaan atau kendala yang ingin kamu tanyakan.
        </p>

        <form method="POST" class="customer-message-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
            >

            <label for="subject">Judul Pesan</label>

            <input
                type="text"
                id="subject"
                name="subject"
                maxlength="150"
                placeholder="Contoh: Pertanyaan mengenai pesanan"
                required
            >

            <label for="message">Isi Pesan</label>

            <textarea
                id="message"
                name="message"
                rows="5"
                placeholder="Tuliskan pesanmu di sini..."
                required
            ></textarea>

            <button type="submit" class="btn-send-message">
                Kirim Pesan
            </button>

        </form>

    </div>


    <!-- RIWAYAT PESAN -->
    <div class="customer-message-history">

        <div class="message-history-heading">
            <h2>Pesan Saya</h2>
            <span>
                <?php echo count($messages); ?> Pesan
            </span>
        </div>

        <?php if (count($messages) > 0): ?>

            <?php foreach ($messages as $item): ?>

                <div class="customer-message-item">

                    <div class="customer-message-item-heading">

                        <h3>
                            <?php echo htmlspecialchars($item["subject"]); ?>
                        </h3>

                        <?php if ($item["status"] === "replied"): ?>

                            <span class="message-status replied">
                                Sudah Dibalas
                            </span>

                        <?php elseif ($item["status"] === "read"): ?>

                            <span class="message-status read">
                                Sudah Dibaca
                            </span>

                        <?php else: ?>

                            <span class="message-status unread">
                                Menunggu Admin
                            </span>

                        <?php endif; ?>

                    </div>

                    <small class="message-date">
                        Dikirim:
                        <?php echo date("d-m-Y H:i", strtotime($item["created_at"])); ?>
                    </small>

                    <div class="customer-original-message">

                        <strong>Pesan Saya</strong>

                        <p>
                            <?php echo nl2br(htmlspecialchars($item["message"])); ?>
                        </p>

                    </div>


                    <!-- BALASAN ADMIN -->
                    <?php if (!empty($item["admin_reply"])): ?>

                        <div class="customer-admin-reply">

                            <strong>Balasan Admin ERB Bookstore</strong>

                            <p>
                                <?php echo nl2br(htmlspecialchars($item["admin_reply"])); ?>
                            </p>

                            <?php if (!empty($item["replied_at"])): ?>

                                <small>
                                    Dibalas:
                                    <?php echo date("d-m-Y H:i", strtotime($item["replied_at"])); ?>
                                </small>

                            <?php endif; ?>

                        </div>

                    <?php else: ?>

                        <div class="waiting-admin-reply">
                            Pesanmu sedang menunggu balasan dari admin.
                        </div>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="empty-customer-message">
                <h3>Belum Ada Pesan</h3>
                <p>
                    Pesan yang kamu kirim kepada admin akan muncul di sini.
                </p>
            </div>

        <?php endif; ?>

    </div>

</section>

<?php require_once "../includes/footer.php"; ?>
