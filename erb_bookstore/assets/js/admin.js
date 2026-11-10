// Mengambil seluruh link navigasi admin
const adminLinks = document.querySelectorAll(".admin-nav-link");

// Mengambil alamat halaman saat ini
const currentPage = window.location.pathname;

// Memeriksa setiap link
adminLinks.forEach(function (link) {
  const linkPage = new URL(link.href).pathname;

  if (currentPage === linkPage) {
    link.classList.add("active");
  }
});
