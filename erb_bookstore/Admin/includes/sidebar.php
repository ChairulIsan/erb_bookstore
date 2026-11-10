<aside class="admin-sidebar">

    <!-- LOGO -->
<div class="admin-logo">
    <a href="/erb_bookstore/Admin/dashboard.php">
        <img
            src="/erb_bookstore/assets/images/erb-logo.png"
            alt="ERB Bookstore"
            class="admin-logo-image"
        >
    </a>

    <p>ADMIN PANEL</p>
</div>

    <!-- NAVIGATION -->
    <nav class="admin-navigation">

        <p class="nav-heading">MAIN MENU</p>

        <a href="/erb_bookstore/Admin/dashboard.php"
           class="admin-nav-link">
            Dashboard
        </a>

        <a href="/erb_bookstore/Admin/buku.php"
           class="admin-nav-link">
            Buku
        </a>

        <a href="/erb_bookstore/Admin/kategori.php"
           class="admin-nav-link">
            Kategori
        </a>

        <a href="/erb_bookstore/Admin/user.php"
           class="admin-nav-link">
            User
        </a>

        <a href="/erb_bookstore/Admin/pesanan.php"
           class="admin-nav-link">
            Pesanan
        </a>

        <a href="/erb_bookstore/Admin/pesan.php"
           class="admin-nav-link">
            Pesan
        </a>

    </nav>

    <!-- LOGOUT -->
    <div class="admin-sidebar-footer">

        <form action="/erb_bookstore/Auth/logout.php" method="POST">

            <button type="submit" class="admin-logout">
                Logout
            </button>

        </form>

    </div>

</aside>