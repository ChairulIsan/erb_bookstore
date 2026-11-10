<?php

session_start();

require_once "../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

$search = trim($_GET["search"] ?? "");

$query = "
    SELECT
        books.id,
        books.title,
        books.author,
        books.price,
        books.stock,
        books.cover,
        categories.category_name AS category_name
    FROM books
    LEFT JOIN categories
        ON books.category_id = categories.id
";

if ($search !== "") {
    $query .= "
        WHERE
            books.title LIKE :title
            OR books.author LIKE :author
            OR categories.category_name LIKE :category
    ";
}

$query .= " ORDER BY books.id DESC";

try {
    $stmt = $conn->prepare($query);

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

    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($books);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "error" => "Gagal mengambil data buku."
    ]);

}

?>