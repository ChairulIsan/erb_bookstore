
<?php

session_start();

require_once "../config/database.php";

// Membuat CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$pageTitle = "Book Details - ERB Bookstore";

// Mengambil ID buku dari URL
$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

$book = null;

// Memastikan ID valid
if ($id !== false && $id !== null && $id > 0) {

    $query = "
        SELECT
            books.*,
            categories.category_name AS category_name
        FROM books
        LEFT JOIN categories
            ON books.category_id = categories.id
        WHERE books.id = :id
    ";

    $stmt = $conn->prepare($query);

    $stmt->execute([
        ":id" => $id
    ]);

    $book = $stmt->fetch(PDO::FETCH_ASSOC);
}

require_once "../includes/header.php";

?>

<section class="book-detail">

    <?php if ($book): ?>

        <div class="detail-container">

            <!-- BOOK COVER -->
            <div class="detail-cover">

                <?php if (!empty($book["cover"])): ?>

                    <img
                        src="/erb_bookstore/<?php echo htmlspecialchars($book["cover"]); ?>"
                        alt="Cover <?php echo htmlspecialchars($book["title"]); ?>"
                    >

                <?php else: ?>

                    <div class="no-cover">
                        No Cover Available
                    </div>

                <?php endif; ?>

            </div>


            <!-- BOOK INFORMATION -->
            <div class="detail-info">

                <span class="detail-category">
                    <?php echo htmlspecialchars($book["category_name"] ?? "Uncategorized"); ?>
                </span>

                <h1>
                    <?php echo htmlspecialchars($book["title"]); ?>
                </h1>

                <p class="detail-author">
                    By <?php echo htmlspecialchars($book["author"]); ?>
                </p>

                <div class="detail-divider"></div>

                <p>
                    <strong>Publisher:</strong>
                    <?php echo htmlspecialchars($book["publisher"] ?? "-"); ?>
                </p>

                <p>
                    <strong>Category:</strong>
                    <?php echo htmlspecialchars($book["category_name"] ?? "Uncategorized"); ?>
                </p>

                <p>
                    <strong>Stock:</strong>
                    <?php echo (int) ($book["stock"] ?? 0); ?>
                </p>

                <h2 class="detail-price">
                    Rp <?php echo number_format((float) $book["price"], 0, ",", "."); ?>
                </h2>

                <div class="detail-description">

                    <h3>Description</h3>

                    <p>
                        <?php
                        echo nl2br(
                            htmlspecialchars($book["description"] ?? "No description available.")
                        );
                        ?>
                    </p>

                </div>


                <!-- ADD TO CART -->
                <?php if (
                    isset($_SESSION["user_id"]) &&
                    ($_SESSION["user_role"] ?? "") === "customer"
                ): ?>

                    <?php if ((int) $book["stock"] > 0): ?>

                        <form
                            action="/erb_bookstore/Cart/tambah_keranjang.php"
                            method="POST"
                            class="detail-cart-form"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
                            >

                            <input
                                type="hidden"
                                name="book_id"
                                value="<?php echo (int) $book["id"]; ?>"
                            >

                            <label for="quantity">Quantity:</label>

                            <input
                                type="number"
                                id="quantity"
                                name="quantity"
                                value="1"
                                min="1"
                                max="<?php echo (int) $book["stock"]; ?>"
                                required
                            >

                            <button type="submit" class="btn">
                                Add to Cart
                            </button>

                        </form>

                    <?php else: ?>

                        <p class="out-of-stock">
                            Out of Stock
                        </p>

                    <?php endif; ?>

                <?php elseif (!isset($_SESSION["user_id"])): ?>

                    <a href="/erb_bookstore/Auth/login.php" class="btn">
                        Login to Purchase
                    </a>

                <?php endif; ?>

                <a href="buku.php" class="back-button">
                    Back to Books
                </a>

            </div>

        </div>

    <?php else: ?>

        <div class="book-not-found">

            <h1>Book Not Found</h1>

            <p>Buku tidak ditemukan atau ID buku tidak valid.</p>

            <a href="buku.php" class="btn">
                Back to Books
            </a>

        </div>

    <?php endif; ?>

</section>

<?php require_once "../includes/footer.php"; ?>
