<?php
require_once "Database.php";

// CREATE
function createRecord($name, $email) {
    global $conn;
    if (empty($name) || empty($email)) {
        return "⚠️ Name and Email required!";
    }
    $stmt = $conn->prepare("INSERT INTO users (name, email) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $email);
    return $stmt->execute() ? "✅ Record added!" : "❌ " . $stmt->error;
}

// READ
function getAllRecords() {
    global $conn;
    $result = $conn->query("SELECT * FROM users");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// UPDATE
function updateRecord($id, $name, $email) {
    global $conn;
    $stmt = $conn->prepare("UPDATE users SET name=?, email=? WHERE id=?");
    $stmt->bind_param("ssi", $name, $email, $id);
    return $stmt->execute() ? "✅ Updated!" : "❌ " . $stmt->error;
}

// DELETE
function deleteRecord($id) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param("i", $id);
    return $stmt->execute() ? "✅ Deleted!" : "❌ " . $stmt->error;
}
?>
