
<?php

session_start();

require_once "../config/database.php";

// ========================================
// 1. AUTENTIKASI CUSTOMER
// ========================================

if (!isset($_SESSION["user_id"])) {
    header("Location: ../Auth/login.php");
    exit;
}

if (($_SESSION["user_role"] ?? "") !== "customer") {
    header("Location: ../Admin/dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../dashboard/keranjang.php");
    exit;
}

// ========================================
// 2. VALIDASI CSRF
// ========================================

$token = $_POST["csrf_token"] ?? "";

if (
    empty($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $token)
) {
    die("Permintaan tidak valid. Silakan kembali dan coba lagi.");
}

// ========================================
// 3. VALIDASI INPUT CHECKOUT
// ========================================

$userId = (int) $_SESSION["user_id"];

$shippingAddress = trim($_POST["shipping_address"] ?? "");

$shippingZoneId = filter_var(
    $_POST["shipping_zone_id"] ?? null,
    FILTER_VALIDATE_INT
);

$paymentMethod = $_POST["payment_method"] ?? "";

$allowedPaymentMethods = [
    "transfer",
    "qris",
    "cod"
];

if ($shippingAddress === "") {
    die("Alamat pengiriman wajib diisi.");
}

if (strlen($shippingAddress) > 10000) {
    die("Alamat pengiriman terlalu panjang.");
}

if (!$shippingZoneId || $shippingZoneId <= 0) {
    die("Zona pengiriman tidak valid.");
}

if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
    die("Metode pembayaran tidak valid.");
}

// ========================================
// 4. VALIDASI BUKTI PEMBAYARAN
// ========================================

$paymentProofPath = null;
$absoluteProofPath = null;

if (in_array($paymentMethod, ["transfer", "qris"], true)) {

    if (
        !isset($_FILES["payment_proof"]) ||
        $_FILES["payment_proof"]["error"] === UPLOAD_ERR_NO_FILE
    ) {
        die("Bukti pembayaran wajib diunggah.");
    }

    $file = $_FILES["payment_proof"];

    if ($file["error"] !== UPLOAD_ERR_OK) {
        die("Terjadi kesalahan saat mengunggah bukti pembayaran.");
    }

    // Maksimal ukuran file 5 MB
    if ($file["size"] <= 0 || $file["size"] > 5 * 1024 * 1024) {
        die("Ukuran bukti pembayaran maksimal 5 MB.");
    }

    if (!is_uploaded_file($file["tmp_name"])) {
        die("File yang diunggah tidak valid.");
    }

    // Memeriksa MIME asli file
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file["tmp_name"]);

    $allowedMimeTypes = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp"
    ];

    if (!isset($allowedMimeTypes[$mimeType])) {
        die("Format bukti pembayaran harus JPG, PNG, atau WEBP.");
    }

    // Menyiapkan nama file acak
    $extension = $allowedMimeTypes[$mimeType];

    $fileName = "proof_" .
        bin2hex(random_bytes(16)) .
        "." . $extension;

    $uploadDirectory = __DIR__ .
        "/../uploads/payment_proofs/";

    if (
        !is_dir($uploadDirectory) ||
        !is_writable($uploadDirectory)
    ) {
        die("Folder penyimpanan bukti pembayaran belum tersedia.");
    }

    $absoluteProofPath = $uploadDirectory . $fileName;

    $paymentProofPath = "uploads/payment_proofs/" . $fileName;
}

// ========================================
// 5. PROSES TRANSAKSI DATABASE
// ========================================

try {

    $conn->beginTransaction();

    // Mengunci keranjang customer
    $cartQuery = $conn->prepare(
        "SELECT id
         FROM carts
         WHERE user_id = :user_id
         ORDER BY id DESC
         LIMIT 1
         FOR UPDATE"
    );

    $cartQuery->execute([
        ":user_id" => $userId
    ]);

    $cart = $cartQuery->fetch(PDO::FETCH_ASSOC);

    if (!$cart) {
        throw new Exception("Keranjang tidak ditemukan.");
    }

    $cartId = (int) $cart["id"];

    // Mengunci dan mengambil isi keranjang
    $itemQuery = $conn->prepare(
        "SELECT
            ci.id AS cart_item_id,
            ci.quantity,
            b.id AS book_id,
            b.title,
            b.price,
            b.stock
         FROM cart_items ci
         JOIN books b ON ci.book_id = b.id
         WHERE ci.cart_id = :cart_id
         ORDER BY b.id
         FOR UPDATE"
    );

    $itemQuery->execute([
        ":cart_id" => $cartId
    ]);

    $items = $itemQuery->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        throw new Exception("Keranjang masih kosong.");
    }

    // ========================================
    // 6. VALIDASI ZONA PENGIRIMAN
    // ========================================

    $zoneQuery = $conn->prepare(
        "SELECT id, shipping_fee
         FROM shipping_zones
         WHERE id = :id
         AND is_active = 1"
    );

    $zoneQuery->execute([
        ":id" => $shippingZoneId
    ]);

    $zone = $zoneQuery->fetch(PDO::FETCH_ASSOC);

    if (!$zone) {
        throw new Exception("Zona pengiriman tidak tersedia.");
    }

    $shippingCost = (float) $zone["shipping_fee"];

    // ========================================
    // 7. MENGHITUNG SUBTOTAL DAN VALIDASI STOK
    // ========================================

    $subtotalPrice = 0;

    foreach ($items as $item) {

        $quantity = (int) $item["quantity"];
        $stock = (int) $item["stock"];
        $price = (float) $item["price"];

        if ($quantity <= 0 || $quantity > $stock) {
            throw new Exception(
                "Stok buku " . $item["title"] .
                " tidak mencukupi."
            );
        }

        $subtotalPrice += $price * $quantity;
    }

    // Total dihitung oleh server, bukan browser
    $totalPrice = $subtotalPrice + $shippingCost;

    // ========================================
    // 8. STATUS PEMBAYARAN AWAL
    // ========================================

    $paymentStatus = "unpaid";

    if ($paymentMethod === "cod") {
        $verificationStatus = "not_required";
    } else {
        $verificationStatus = "pending";
    }

    // ========================================
    // 9. MEMBUAT NOMOR PESANAN
    // ========================================

    $orderNumber = "ERB-" .
        date("YmdHis") . "-" .
        strtoupper(bin2hex(random_bytes(3)));

    // ========================================
    // 10. MENYIMPAN PESANAN
    // ========================================

    $orderQuery = $conn->prepare(
        "INSERT INTO orders (
            order_number,
            user_id,
            total_price,
            subtotal_price,
            shipping_zone_id,
            shipping_cost,
            payment_method,
            payment_proof,
            payment_verification_status,
            shipping_address,
            order_status,
            payment_status
        )
        VALUES (
            :order_number,
            :user_id,
            :total_price,
            :subtotal_price,
            :shipping_zone_id,
            :shipping_cost,
            :payment_method,
            :payment_proof,
            :verification_status,
            :shipping_address,
            'pending',
            :payment_status
        )"
    );

    $orderQuery->execute([
        ":order_number" => $orderNumber,
        ":user_id" => $userId,
        ":total_price" => $totalPrice,
        ":subtotal_price" => $subtotalPrice,
        ":shipping_zone_id" => $shippingZoneId,
        ":shipping_cost" => $shippingCost,
        ":payment_method" => $paymentMethod,
        ":payment_proof" => $paymentProofPath,
        ":verification_status" => $verificationStatus,
        ":shipping_address" => $shippingAddress,
        ":payment_status" => $paymentStatus
    ]);

    $orderId = (int) $conn->lastInsertId();

    // ========================================
    // 11. MEMINDAHKAN FOTO BUKTI PEMBAYARAN
    // ========================================

    if ($paymentProofPath !== null) {

        if (!move_uploaded_file(
            $_FILES["payment_proof"]["tmp_name"],
            $absoluteProofPath
        )) {
            throw new Exception(
                "Gagal menyimpan foto bukti pembayaran."
            );
        }
    }

    // ========================================
    // 12. MENYIMPAN DETAIL BUKU
    // ========================================

    $detailQuery = $conn->prepare(
        "INSERT INTO order_items (
            order_id,
            book_id,
            book_title,
            price,
            quantity
        )
        VALUES (
            :order_id,
            :book_id,
            :book_title,
            :price,
            :quantity
        )"
    );

    $stockQuery = $conn->prepare(
        "UPDATE books
         SET stock = stock - :quantity
         WHERE id = :book_id
         AND stock >= :quantity"
    );

    foreach ($items as $item) {

        $quantity = (int) $item["quantity"];

        $detailQuery->execute([
            ":order_id" => $orderId,
            ":book_id" => $item["book_id"],
            ":book_title" => $item["title"],
            ":price" => $item["price"],
            ":quantity" => $quantity
        ]);

        // Mengurangi stok buku
        $stockQuery->execute([
            ":quantity" => $quantity,
            ":book_id" => $item["book_id"]
        ]);

        if ($stockQuery->rowCount() !== 1) {
            throw new Exception(
                "Gagal memperbarui stok buku."
            );
        }
    }

    // ========================================
    // 13. MENGOSONGKAN KERANJANG
    // ========================================

    $deleteCart = $conn->prepare(
        "DELETE FROM cart_items
         WHERE cart_id = :cart_id"
    );

    $deleteCart->execute([
        ":cart_id" => $cartId
    ]);

    // ========================================
    // 14. MENYELESAIKAN TRANSAKSI
    // ========================================

    $conn->commit();

    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

    header(
        "Location: ../dashboard/detail_pesanan.php?id=" . $orderId
    );

    exit;

} catch (Throwable $e) {

    // Membatalkan transaksi jika terjadi kesalahan
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    // Menghapus file jika database gagal menyimpan transaksi
    if (
        $absoluteProofPath !== null &&
        file_exists($absoluteProofPath)
    ) {
        unlink($absoluteProofPath);
    }

    error_log("Checkout Error: " . $e->getMessage());

    http_response_code(500);

    echo "Checkout gagal. Silakan periksa data dan coba kembali.";
    exit;
}

?>
```

## 4. Pastikan form checkout sesuai

Buka `dashboard/checkout.php`. Form harus memiliki atribut `enctype` agar foto bisa dikirim ke PHP.

```html
<form
    action="../Checkout/proses_checkout.php"
    method="POST"
    enctype="multipart/form-data"
>
```

Pastikan nama input berikut sama persis:

| Input | `name` |
|---|---|
| Zona pengiriman | `shipping_zone_id` |
| Metode pembayaran | `payment_method` |
| Foto bukti pembayaran | `payment_proof` |
| Alamat | `shipping_address` |
| Token keamanan | `csrf_token` |

Jika nama input berbeda, backend tidak akan menerima datanya dengan benar.

## 5. Cara menguji checkout

Jangan langsung menguji semua metode. Mulai dengan dua skenario:

**Skenario A — Transfer**
1. Login sebagai customer.
2. Masukkan buku ke keranjang.
3. Pilih zona Jakarta.
4. Pilih Transfer.
5. Unggah gambar JPG atau PNG.
6. Checkout.
7. Periksa tabel `orders` di phpMyAdmin.

Data yang diharapkan:

| Kolom | Hasil |
|---|---|
| `payment_method` | transfer |
| `payment_status` | unpaid |
| `payment_verification_status` | pending |
| `payment_proof` | Lokasi foto |
| `shipping_cost` | Sesuai zona |
| `subtotal_price` | Harga buku |
| `total_price` | Subtotal + ongkir |

**Skenario B — COD**

| Kolom | Hasil |
|---|---|
| `payment_method` | cod |
| `payment_status` | unpaid |
| `payment_verification_status` | not_required |
| `payment_proof` | NULL |

---

## 6. Berikutnya: halaman manajemen pesanan admin

Setelah backend checkout ini berhasil diuji, kita akan membuat:

- `Admin/pesanan.php` — daftar seluruh pesanan.
- `Admin/detail_pesanan.php` — detail transaksi dan tampilan foto bukti.
- `Admin/konfirmasi_pembayaran.php` — menerima atau menolak bukti pembayaran.
- `Admin/update_status.php` — mengubah status pengiriman.

**Satu hal yang perlu diperhatikan:** file `dashboard/detail_pesanan.php` yang sebelumnya kita buat sudah mengambil kolom baru dari tabel `orders`. Setelah backend ini digunakan, struk bisa menampilkan nominal dan metode pembayaran yang sebenarnya.

Untuk sekarang, jalankan SQL, siapkan folder upload, lalu uji satu transaksi transfer terlebih dahulu. Jangan lanjut ke halaman admin sebelum penyimpanan foto dan transaksi berhasil.