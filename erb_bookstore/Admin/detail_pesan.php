
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

// Mengambil ID pesan
$messageId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$messageId) {
    header("Location: pesan.php");
    exit;
}

// Membuat CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$error = "";
$success = "";

// Memproses balasan admin
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";
    $reply = trim($_POST["admin_reply"] ?? "");

    // Validasi CSRF
    if (!hash_equals($_SESSION["csrf_token"], $token)) {

        $error = "Permintaan tidak valid.";

    } elseif ($reply === "") {

        $error = "Balasan tidak boleh kosong.";

    } else {

        try {

            // Memastikan pesan tersedia dan belum dibalas
            $check = $conn->prepare("
                SELECT id, status
                FROM messages
                WHERE id = :id
            ");

            $check->execute([
                ":id" => $messageId
            ]);

            $existingMessage = $check->fetch(PDO::FETCH_ASSOC);

            if (!$existingMessage) {

                $error = "Pesan tidak ditemukan.";

            } elseif ($existingMessage["status"] === "replied") {

                $error = "Pesan ini sudah dibalas.";

            } else {

                // Menyimpan balasan admin
                $update = $conn->prepare("
                    UPDATE messages
                    SET
                        admin_reply = :reply,
                        status = 'replied',
                        replied_at = NOW()
                    WHERE id = :id
                ");

                $update->execute([
                    ":reply" => $reply,
                    ":id" => $messageId
                ]);

                header("Location: detail_pesan.php?id=" . $messageId . "&success=1");
                exit;
            }

        } catch (PDOException $e) {

            error_log($e->getMessage());
            $error = "Gagal mengirim balasan.";
        }
    }
}

// Menampilkan notifikasi berhasil
if (isset($_GET["success"])) {
    $success = "Balasan berhasil dikirim.";
}

try {

    // Mengubah status unread menjadi read ketika dibuka
    $readQuery = $conn->prepare("
        UPDATE messages
        SET status = 'read'
        WHERE id = :id AND status = 'unread'
    ");

    $readQuery->execute([
        ":id" => $messageId
    ]);

    // Mengambil detail pesan
    $query = $conn->prepare("
        SELECT
            m.*,
            u.name AS customer_name,
            u.email AS customer_email
        FROM messages m
        JOIN users u ON m.user_id = u.id
        WHERE m.id = :id
    ");

    $query->execute([
        ":id" => $messageId
    ]);

    $message = $query->fetch(PDO::FETCH_ASSOC);

    if (!$message) {
        header("Location: pesan.php");
        exit;
    }

} catch (PDOException $e) {

    error_log($e->getMessage());
    die("Gagal mengambil detail pesan.");
}

$pageTitle = "Detail Pesan Customer";

require_once "includes/header.php";

?>

<section class="admin-message-detail">

    <div class="message-detail-heading">

        <div>
            <h1>Detail Pesan Customer</h1>
            <p>Baca pesan dan kirim balasan kepada customer.</p>
        </div>

        <a href="pesan.php" class="btn-back-message">
            Kembali
        </a>

    </div>

    <?php if ($success): ?>
        <div class="message-alert success">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="message-alert error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="message-detail-card">

        <div class="message-customer-detail">

            <h2>
                <?php echo htmlspecialchars($message["subject"]); ?>
            </h2>

            <p>
                <strong>Nama Customer:</strong>
                <?php echo htmlspecialchars($message["customer_name"]); ?>
            </p>

            <p>
                <strong>Email:</strong>
                <?php echo htmlspecialchars($message["customer_email"] ?? "-"); ?>
            </p>

            <p>
                <strong>Tanggal:</strong>
                <?php echo date("d-m-Y H:i", strtotime($message["created_at"])); ?>
            </p>

            <p>
                <strong>Status:</strong>
                <?php echo htmlspecialchars($message["status"]); ?>
            </p>

        </div>

        <div class="customer-message-content">

            <h3>Pesan Customer</h3>

            <p>
                <?php
                echo nl2br(htmlspecialchars($message["message"]));
                ?>
            </p>

        </div>

        <?php if (!empty($message["admin_reply"])): ?>

            <div class="admin-reply-content">

                <h3>Balasan Admin</h3>

                <p>
                    <?php
                    echo nl2br(htmlspecialchars($message["admin_reply"]));
                    ?>
                </p>

                <small>
                    Dibalas pada:
                    <?php
                    echo date("d-m-Y H:i", strtotime($message["replied_at"]));
                    ?>
                </small>

            </div>

        <?php else: ?>

            <div class="admin-reply-form">

                <h3>Balas Pesan</h3>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
                    >

                    <label for="admin_reply">
                        Isi Balasan
                    </label>

                    <textarea
                        name="admin_reply"
                        id="admin_reply"
                        rows="6"
                        placeholder="Tulis balasan untuk customer..."
                        required
                    ></textarea>

                    <button type="submit" class="btn-send-reply">
                        Kirim Balasan
                    </button>

                </form>

            </div>

        <?php endif; ?>

    </div>

</section>

<?php require_once "includes/footer.php"; ?>
