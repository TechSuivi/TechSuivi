<?php
require_once __DIR__ . '/config/database.php';
$pdo = getDatabaseConnection();

echo "<pre>";

// 1. Create stock_categories table
try {
    $sql = "CREATE TABLE IF NOT EXISTS stock_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            default_margin DECIMAL(5,2) DEFAULT 30.00,
            color VARCHAR(20) DEFAULT '#3498db',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sql);
    echo "Table stock_categories checked/created.\n";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}

// 2. Add category_id to Stock table
try {
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM Stock LIKE 'category_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE Stock ADD COLUMN category_id INT DEFAULT NULL");
        echo "Column category_id added to Stock.\n";
    } else {
        echo "Column category_id already exists in Stock.\n";
    }
} catch (PDOException $e) {
    echo "Error adding column: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
