<?php
require_once __DIR__ . '/src/includes/db.php';
$db = getDB();
try {
    $db->exec("ALTER TABLE tickets ADD COLUMN image_url VARCHAR(255) DEFAULT NULL AFTER problem_description");
    echo "Column image_url added successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
unlink(__FILE__);
