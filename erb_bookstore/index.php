
<?php

// Memulai session
session_start();

// Menghubungkan database
require_once "config/database.php";

// Mengambil 4 buku terbaru
$query = "
    SELECT
        books.*,
        categories.category_name
    FROM books
    LEFT JOIN categories
        ON books.category_id = categories.id
    ORDER BY books.id DESC
    LIMIT 4
";

$stmt = $conn->prepare($query);
$stmt->execute();

$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Judul halaman
$pageTitle = "Home - ERB Bookstore";

// Memanggil header
require_once "includes/header.php";

?>


<!-- HERO / WELCOME SECTION -->
<section class="hero">

    <div class="hero-container">

        <!-- HERO CONTENT -->
        <div class="hero-content">

            <span class="hero-label">
                SELAMAT DATANG DI ERB BOOKSTORE
            </span>

            <h1>
                Temukan Cerita,
                <span>Perluas Wawasan.</span>
            </h1>

            <p>
                Setiap buku menyimpan cerita, setiap halaman
                menghadirkan pengetahuan baru. Temukan berbagai
                pilihan bacaan yang dapat menemani perjalanan
                belajar, mencari inspirasi, dan mengembangkan diri
                bersama ERB Bookstore.
            </p>

            <div class="hero-buttons">

                <a href="dashboard/buku.php" class="btn">
                    Jelajahi Koleksi Buku
                </a>

                <a href="dashboard/About.php" class="btn-secondary">
                    Tentang Kami
                </a>

            </div>

        </div>

        <!-- HERO VISUAL -->
        <div class="hero-visual">

            <div class="hero-book hero-book-one">
                <span>ERB</span>
                <strong>BOOKSTORE</strong>
                <small>Enjoy Reading</small>
            </div>

            <div class="hero-book hero-book-two">
                <span>READ</span>
                <strong>LEARN</strong>
                <small>GROW</small>
            </div>

            <div class="hero-decoration">
                ENJOY READING,
                DISCOVER ENDLESS KNOWLEDGE.
            </div>

        </div>

    </div>

</section>



<!-- FEATURED BOOKS -->
<section class="featured-books">

    <div class="featured-heading">

        <h2>Featured Books</h2>

        <p>
            Discover our latest book collection.
        </p>

    </div>

    <div class="book-grid">

        <?php if (count($books) > 0): ?>

            <?php foreach ($books as $book): ?>

                <div class="book-card">

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

                    <div class="book-info">

                        <h3>
                            <?php echo htmlspecialchars($book["title"]); ?>
                        </h3>

                        <p>
                            <?php echo htmlspecialchars($book["author"]); ?>
                        </p>

                        <span>
                            <?php echo htmlspecialchars($book["category_name"] ?? "Uncategorized"); ?>
                        </span>

                        <h4>
                            Rp <?php echo number_format((float) $book["price"], 0, ",", "."); ?>
                        </h4>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <p>Belum ada buku yang tersedia.</p>

        <?php endif; ?>

    </div>

</section>






<!-- GET TO KNOW US -->
<section class="get-to-know">

    <div class="get-to-know-container">

        <div class="get-to-know-image">
            <div class="book-decoration">
                <span>ERB</span>
                <strong>BOOKSTORE</strong>
                <small>Enjoy Reading</small>
            </div>
        </div>

        <div class="get-to-know-content">

            <span class="section-label">
                TENTANG ERB BOOKSTORE
            </span>

            <h2>
                Mengenal ERB Bookstore
                <span>Lebih Dekat</span>
            </h2>

            <p>
                ERB Bookstore hadir sebagai ruang bagi siapa saja
                yang ingin menemukan lebih banyak melalui buku.
                Kami percaya bahwa membaca bukan sekadar
                mengumpulkan informasi, melainkan sebuah langkah
                untuk memperluas wawasan, mengenal berbagai
                perspektif, dan mengembangkan potensi diri.
            </p>

            <p>
                Melalui beragam pilihan bacaan dan pengalaman
                berbelanja yang praktis, kami ingin menemani
                setiap perjalanan belajar dan berkembang.
            </p>

            <div class="get-to-know-values">

                <div>
                    <strong>Pengetahuan</strong>
                    <span>Membuka wawasan.</span>
                </div>

                <div>
                    <strong>Inspirasi</strong>
                    <span>Menemukan perspektif baru.</span>
                </div>

                <div>
                    <strong>Pengembangan Diri</strong>
                    <span>Bertumbuh melalui membaca.</span>
                </div>

            </div>

            <a href="dashboard/About.php" class="btn">
                Pelajari Lebih Lanjut
            </a>

        </div>

    </div>

</section>

    </div>
</section>

<!-- FOOTER -->
<?php
require_once "includes/footer.php";
?>