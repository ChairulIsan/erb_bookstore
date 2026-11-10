
<?php

session_start();

require_once "../config/database.php";

// 1. Memastikan customer sudah login
if (!isset($_SESSION["user_id"])) {
    header("Location: ../Auth/login.php");
    exit;
}

if (($_SESSION["user_role"] ?? "") !== "customer") {
    header("Location: ../Admin/dashboard.php");
    exit;
}

// 2. Membuat token keamanan
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$userId = (int) $_SESSION["user_id"];

// 3. Mengambil alamat customer
$userQuery = $conn->prepare(
    "SELECT name, address FROM users WHERE id = :id"
);

$userQuery->execute([
    ":id" => $userId
]);

$user = $userQuery->fetch(PDO::FETCH_ASSOC);

// 4. Mengambil zona pengiriman aktif
$zoneQuery = $conn->query(
    "SELECT id, zone_name, shipping_fee
     FROM shipping_zones
     WHERE is_active = 1
     ORDER BY shipping_fee ASC"
);

$shippingZones = $zoneQuery->fetchAll(PDO::FETCH_ASSOC);

// 5. Mengambil keranjang customer
$cartQuery = $conn->prepare(
    "SELECT id FROM carts
     WHERE user_id = :user_id
     ORDER BY id DESC
     LIMIT 1"
);

$cartQuery->execute([
    ":user_id" => $userId
]);

$cart = $cartQuery->fetch(PDO::FETCH_ASSOC);

$cartItems = [];
$totalPrice = 0;

if ($cart) {

    $itemQuery = $conn->prepare(
        "SELECT
            ci.id AS cart_item_id,
            ci.quantity,
            b.id AS book_id,
            b.title,
            b.author,
            b.price,
            b.stock
         FROM cart_items ci
         JOIN books b ON ci.book_id = b.id
         WHERE ci.cart_id = :cart_id"
    );

    $itemQuery->execute([
        ":cart_id" => $cart["id"]
    ]);

    $cartItems = $itemQuery->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cartItems as $item) {
        $totalPrice += $item["price"] * $item["quantity"];
    }
}

// 6. Memastikan keranjang tidak kosong
if (empty($cartItems)) {
    header("Location: ../Cart/keranjang.php?empty=1");
    exit;
}

// 7. Memeriksa stok buku
$stockError = false;

foreach ($cartItems as $item) {

    if (
        $item["stock"] <= 0 ||
        $item["quantity"] > $item["stock"]
    ) {
        $stockError = true;
        break;
    }
}

require_once "../includes/header.php";

?>

<section class="checkout-page">

    <h1>Checkout</h1>

    <p>Periksa pesanan sebelum menyelesaikan pembelian.</p>

    <?php if ($stockError): ?>

        <p class="error-message">
            Stok salah satu buku tidak mencukupi.
            Silakan periksa kembali keranjang.
        </p>

        <a href="../Cart/keranjang.php">
            Kembali ke Keranjang
        </a>

    <?php elseif (empty($shippingZones)): ?>

        <p class="error-message">
            Zona pengiriman belum tersedia.
            Silakan hubungi administrator.
        </p>

    <?php else: ?>

        <div class="checkout-container">

            <!-- FORM CHECKOUT -->
            <div class="checkout-form">

                <form
                    action="../Checkout/proses_checkout.php"
                    method="POST"
                    enctype="multipart/form-data"
                >

                    <!-- CSRF TOKEN -->
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo htmlspecialchars(
                            $_SESSION["csrf_token"]
                        ); ?>"
                    >

                    <!-- INFORMASI PENGIRIMAN -->
                    <h2>Informasi Pengiriman</h2>

                    <label for="shipping_address">
                        Alamat Pengiriman
                    </label>

                    <textarea
                        id="shipping_address"
                        name="shipping_address"
                        rows="5"
                        required
                    ><?php echo htmlspecialchars(
                        $user["address"] ?? ""
                    ); ?></textarea>

                    <!-- ZONA PENGIRIMAN -->
                    <label for="shipping_zone_id">
                        Zona Pengiriman
                    </label>

                    <select
                        id="shipping_zone_id"
                        name="shipping_zone_id"
                        required
                    >

                        <option value="">
                            Pilih Zona Pengiriman
                        </option>

                        <?php foreach ($shippingZones as $zone): ?>

                            <option
                                value="<?php echo (int) $zone["id"]; ?>"
                                data-fee="<?php echo (float) $zone["shipping_fee"]; ?>"
                            >

                                <?php echo htmlspecialchars(
                                    $zone["zone_name"]
                                ); ?>

                                - Rp

                                <?php echo number_format(
                                    $zone["shipping_fee"],
                                    0,
                                    ",",
                                    "."
                                ); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <!-- METODE PEMBAYARAN -->
                    <h2>Metode Pembayaran</h2>

                    <div class="payment-method-options">

                        <label>
                            <input
                                type="radio"
                                name="payment_method"
                                value="transfer"
                                required
                            >
                            Transfer Bank
                        </label>

                        <label>
                            <input
                                type="radio"
                                name="payment_method"
                                value="qris"
                            >
                            QRIS
                        </label>

                        <label>
                            <input
                                type="radio"
                                name="payment_method"
                                value="cod"
                            >
                            Cash on Delivery (COD)
                        </label>

                    </div>

                    <!-- INFORMASI TRANSFER BANK -->
                    <div
                        id="transfer-info"
                        class="payment-info"
                        hidden
                    >

                        <h3>Informasi Transfer Bank</h3>

                        <p>
                            Bank:
                            <strong>Bank Isann</strong>
                        </p>

                        <p>
                            Nomor Rekening:
                            <strong>081586890225</strong>
                        </p>

                        <p>
                            Atas Nama:
                            <strong>ERB Bookstore</strong>
                        </p>

                        <p class="payment-note">
                            Silakan transfer sesuai total pembayaran
                            yang tertera.
                        </p>

                    </div>

                    <!-- INFORMASI QRIS -->
                    <div
                        id="qris-info"
                        class="payment-info"
                        hidden
                    >

                        <h3>Scan QRIS untuk Membayar</h3>

                        <img
                            src="/erb_bookstore/assets/images/qris-erb.png"
                            alt="QRIS ERB Bookstore"
                            class="qris-image"
                        >

                        <p class="payment-note">
                            Scan QRIS menggunakan aplikasi pembayaran
                            yang mendukung QRIS.
                        </p>

                    </div>

                    <!-- UPLOAD BUKTI PEMBAYARAN -->
                    <div id="proof-section" hidden>

                        <h2>Bukti Pembayaran</h2>

                        <label for="payment_proof">
                            Upload Foto Bukti Pembayaran
                        </label>

                        <input
                            type="file"
                            id="payment_proof"
                            name="payment_proof"
                            accept="image/jpeg,image/png,image/webp"
                        >

                        <small>
                            Format JPG, PNG, atau WEBP.
                            Maksimal 5 MB.
                            Bukti pembayaran wajib untuk Transfer
                            dan QRIS.
                        </small>

                    </div>

                    <!-- TOMBOL CHECKOUT -->
                    <button
                        type="submit"
                        name="checkout"
                    >
                        Buat Pesanan
                    </button>

                </form>

            </div>

            <!-- RINGKASAN PESANAN -->
            <div class="checkout-summary">

                <h2>Ringkasan Pesanan</h2>

                <?php foreach ($cartItems as $item): ?>

                    <div class="checkout-item">

                        <h3>
                            <?php echo htmlspecialchars(
                                $item["title"]
                            ); ?>
                        </h3>

                        <p>
                            <?php echo htmlspecialchars(
                                $item["author"]
                            ); ?>
                        </p>

                        <p>
                            <?php echo (int) $item["quantity"]; ?>
                            x Rp

                            <?php echo number_format(
                                $item["price"],
                                0,
                                ",",
                                "."
                            ); ?>
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

                <!-- SUBTOTAL -->
                <p>
                    Subtotal Buku:

                    <strong>
                        Rp <?php echo number_format(
                            $totalPrice,
                            0,
                            ",",
                            "."
                        ); ?>
                    </strong>
                </p>

                <!-- ONGKIR -->
                <p>
                    Ongkir:

                    <strong id="shipping-display">
                        Rp 0
                    </strong>
                </p>

                <hr>

                <!-- TOTAL -->
                <h3>
                    Total Pembayaran:

                    <span id="grand-total">
                        Rp <?php echo number_format(
                            $totalPrice,
                            0,
                            ",",
                            "."
                        ); ?>
                    </span>
                </h3>

            </div>

        </div>

    <?php endif; ?>

</section>

<!-- JAVASCRIPT CHECKOUT -->
<script>

// 1. Mengambil elemen checkout
const zoneSelect = document.getElementById("shipping_zone_id");

const shippingDisplay = document.getElementById(
    "shipping-display"
);

const grandTotal = document.getElementById("grand-total");

const paymentRadios = document.querySelectorAll(
    'input[name="payment_method"]'
);

const proofSection = document.getElementById("proof-section");

const proofInput = document.getElementById("payment_proof");

const transferInfo = document.getElementById("transfer-info");

const qrisInfo = document.getElementById("qris-info");

// 2. Mengambil subtotal dari PHP
const subtotal = <?php echo json_encode((float) $totalPrice); ?>;

// 3. Format Rupiah
function formatRupiah(value) {

    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0
    }).format(value);

}

// 4. Menghitung ongkir dan total pembayaran
if (zoneSelect) {

    zoneSelect.addEventListener("change", function () {

        const selectedOption = this.options[this.selectedIndex];

        const shippingFee = Number(
            selectedOption.dataset.fee || 0
        );

        shippingDisplay.textContent = formatRupiah(shippingFee);

        grandTotal.textContent = formatRupiah(
            subtotal + shippingFee
        );

    });

}

// 5. Mengatur informasi berdasarkan metode pembayaran
function updatePaymentMethod() {

    const selected = document.querySelector(
        'input[name="payment_method"]:checked'
    );

    const method = selected ? selected.value : "";

    // Transfer dan QRIS membutuhkan bukti pembayaran
    const needsProof =
        method === "transfer" || method === "qris";

    // Mengatur tampilan upload bukti
    proofSection.hidden = !needsProof;

    proofInput.required = needsProof;

    // Mengatur informasi pembayaran
    transferInfo.hidden = method !== "transfer";

    qrisInfo.hidden = method !== "qris";

    // Menghapus file jika memilih COD
    if (!needsProof) {
        proofInput.value = "";
    }

}

// 6. Memantau perubahan pilihan pembayaran
paymentRadios.forEach(function (radio) {

    radio.addEventListener(
        "change",
        updatePaymentMethod
    );

});

// 7. Memeriksa pilihan awal saat halaman dibuka
updatePaymentMethod();

</script>

<?php require_once "../includes/footer.php"; ?>
