// =================================
// 1. MEMASTIKAN JAVASCRIPT BERJALAN
// =================================

document.addEventListener("DOMContentLoaded", function () {
  console.log("ERB Bookstore berhasil dimuat!");

  // =============================
  // 2. INTERAKSI NAVIGASI
  // =============================

  const navLinks = document.querySelectorAll(".nav-links a");

  navLinks.forEach(function (link) {
    link.addEventListener("click", function () {
      console.log("Menu diklik:", link.textContent);
    });
  });
});
