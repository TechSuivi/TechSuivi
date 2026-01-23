<?php
// Force correct path relative to this file's location (in src/)
require_once __DIR__ . '/config/database.php';
$pdo = getDatabaseConnection();

echo "<pre>";

// 1. Drop stock_categories table
try {
    $pdo->exec("DROP TABLE IF EXISTS stock_categories");
    echo "Table stock_categories dropped.\n";
} catch (PDOException $e) {
    echo "Error dropping table: " . $e->getMessage() . "\n";
}

// 2. Remove category_id from Stock table
try {
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM Stock LIKE 'category_id'");
    if ($stmt->rowCount() > 0) {
        $pdo->exec("ALTER TABLE Stock DROP COLUMN category_id");
        echo "Column category_id removed from Stock.\n";
    } else {
        echo "Column category_id does not exist in Stock.\n";
    }
} catch (PDOException $e) {
    echo "Error dropping column: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
