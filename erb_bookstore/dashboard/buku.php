<?php

session_start();

require_once "../config/database.php";

// Membuat token keamanan
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$pageTitle = "Books - ERB Bookstore";

// Mengambil kata pencarian dari URL
$search = trim($_GET["search"] ?? "");

// Query dasar
$query = "
    SELECT
        books.*,
        categories.category_name AS category_name
    FROM books
    LEFT JOIN categories
        ON books.category_id = categories.id
";

// Jika pengunjung melakukan pencarian
if ($search !== "") {

    $query .= "
        WHERE
            books.title LIKE :title
            OR books.author LIKE :author
            OR categories.category_name LIKE :category
    ";
}

// Mengurutkan buku berdasarkan ID terbaru
$query .= " ORDER BY books.id DESC";

$stmt = $conn->prepare($query);

// Mengirim kata pencarian ke SQL
if ($search !== "") {

    $keyword = "%" . $search . "%";

    $stmt->execute([
        ":title" => $keyword,
        ":author" => $keyword,
        ":category" => $keyword
    ]);

} else {

    $stmt->execute();

}

// Mengambil seluruh hasil pencarian
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once "../includes/header.php";

?>

<section class="books-section">

    <h1 class="section-title">Our Books</h1>

    <p>Discover books and expand your knowledge.</p>

    <!-- FORM PENCARIAN -->
    <form method="GET" action="" class="search-form" id="search-form">

    <input
        type="text"
        name="search"
        id="book-search"
        placeholder="Search by title, author, or category..."
        value="<?php echo htmlspecialchars($search); ?>"
        autocomplete="off"
    >

    <button type="submit" class="btn">
        Search
    </button>

    <a href="buku.php" class="btn">
        Reset
    </a>

</form>

    <!-- DAFTAR BUKU -->
   <div class="books-container" id="books-container">

    <?php if (count($books) > 0): ?>

        <?php foreach ($books as $book): ?>

            <div class="book-card">

            <?php if (!empty($book["cover"])): ?>

    <img
        src="/erb_bookstore/<?php echo htmlspecialchars($book["cover"]); ?>"
        alt="Cover <?php echo htmlspecialchars($book["title"]); ?>"
        class="book-cover"
    >

<?php else: ?>

    <div class="no-cover">
        No Cover Available
    </div>

<?php endif; ?>

                <h3>
                    <?php echo htmlspecialchars($book["title"]); ?>
                </h3>

                <p>
                    Author:
                    <?php echo htmlspecialchars($book["author"]); ?>
                </p>

                <p>
                    Category:
                    <?php echo htmlspecialchars($book["category_name"] ?? "Uncategorized"); ?>
                </p>

                <p>
                    Price:
                    Rp <?php echo number_format((float) $book["price"], 0, ",", "."); ?>
                </p>

                <p>
                    Stock:
                    <?php echo (int) ($book["stock"] ?? 0); ?>
                </p>

                <a href="detail_buku.php?id=<?php echo (int) $book["id"]; ?>" class="btn">
                    View Details
                </a>

                <?php if (isset($_SESSION["user_id"]) && $_SESSION["user_role"] === "customer"): ?>

                    <?php if ((int) $book["stock"] > 0): ?>

                        <form action="/erb_bookstore/Cart/tambah_keranjang.php" method="POST">

                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">

                            <input type="hidden" name="book_id"
                                value="<?php echo (int) $book["id"]; ?>">

                            <input type="number" name="quantity"
                                value="1" min="1"
                                max="<?php echo (int) $book["stock"]; ?>" required>

                            <button type="submit" class="btn">
                                Add to Cart
                            </button>

                        </form>

                    <?php else: ?>

                        <p>Out of Stock</p>

                    <?php endif; ?>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <p>No books found.</p>

    <?php endif; ?>

</div>

<script>

const searchInput = document.getElementById("book-search");
const searchForm = document.getElementById("search-form");
const booksContainer = document.getElementById("books-container");

// Informasi customer dan keamanan
const isCustomer = <?php
    echo json_encode(
        isset($_SESSION["user_id"]) &&
        ($_SESSION["user_role"] ?? "") === "customer"
    );
?>;

const csrfToken = <?php echo json_encode($_SESSION["csrf_token"]); ?>;

// Lokasi folder website
const projectBase = <?php
    echo json_encode(
        rtrim(dirname(dirname($_SERVER["SCRIPT_NAME"])), "/") . "/"
    );
?>;

// Mencegah HTML dari data buku menjadi kode aktif
function escapeHTML(value) {

    return String(value ?? "").replace(/[&<>"']/g, function(char) {

        const entities = {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;"
        };

        return entities[char];

    });

}

// Menampilkan buku
function displayBooks(books) {

    if (books.length === 0) {

        booksContainer.innerHTML = "<p>No books found.</p>";
        return;

    }

    let html = "";

    books.forEach(function(book) {

        const id = Number(book.id);
        const stock = Number(book.stock);
        const price = Number(book.price);

        html += `
            <div class="book-card">

                ${
                    book.cover
                    ? `
                        <img
                            src="${escapeHTML(projectBase + book.cover)}"
                            alt="Cover ${escapeHTML(book.title)}"
                            class="book-cover"
                        >
                    `
                    : `
                        <div class="no-cover">
                            No Cover Available
                        </div>
                    `
                }

                <h3>${escapeHTML(book.title)}</h3>

                <p>
                    Author: ${escapeHTML(book.author)}
                </p>

                <p>
                    Category: ${escapeHTML(book.category_name || "Uncategorized")}
                </p>

                <p>
                    Price: Rp ${price.toLocaleString("id-ID")}
                </p>

                <p>
                    Stock: ${stock}
                </p>

                <a
                    href="detail_buku.php?id=${id}"
                    class="btn"
                >
                    View Details
                </a>

                ${
                    isCustomer
                    ? stock > 0
                        ? `
                            <form
                                action="${escapeHTML(projectBase)}Cart/tambah_keranjang.php"
                                method="POST"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="${escapeHTML(csrfToken)}"
                                >

                                <input
                                    type="hidden"
                                    name="book_id"
                                    value="${id}"
                                >

                                <input
                                    type="number"
                                    name="quantity"
                                    value="1"
                                    min="1"
                                    max="${stock}"
                                    required
                                >

                                <button type="submit" class="btn">
                                    Add to Cart
                                </button>

                            </form>
                        `
                        : "<p>Out of Stock</p>"
                    : ""
                }

            </div>
        `;

    });

    booksContainer.innerHTML = html;

}

// Mengambil data dari PHP
let searchTimer;
let currentRequest;

async function searchBooks(keyword) {

    if (currentRequest) {
        currentRequest.abort();
    }

    currentRequest = new AbortController();

    booksContainer.innerHTML = "<p>Searching books...</p>";

    try {

        const response = await fetch(
            "cari_buku.php?search=" + encodeURIComponent(keyword),
            {
                signal: currentRequest.signal
            }
        );

        if (!response.ok) {
            throw new Error("Gagal mengambil data.");
        }

        const books = await response.json();

        displayBooks(books);

    } catch (error) {

        if (error.name === "AbortError") {
            return;
        }

        booksContainer.innerHTML =
            "<p>Terjadi kesalahan saat mencari buku.</p>";

    }

}

// Pencarian langsung saat mengetik
searchInput.addEventListener("input", function() {

    clearTimeout(searchTimer);

    const keyword = searchInput.value.trim();

    searchTimer = setTimeout(function() {

        searchBooks(keyword);

    }, 300);

});

// Mencegah halaman reload saat tombol Search ditekan
searchForm.addEventListener("submit", function(event) {

    event.preventDefault();

    clearTimeout(searchTimer);

    searchBooks(searchInput.value.trim());

});

</script>

<?php
require_once "../includes/footer.php";
?>