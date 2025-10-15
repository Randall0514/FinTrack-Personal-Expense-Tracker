<?php
include "config/db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $category = $_POST['category'];
    $amount = $_POST['amount'];
    $date = $_POST['date'];
    $description = $_POST['description'];
    $payment_method = $_POST['payment_method'];

    $sql = "INSERT INTO expenses (category, amount, date, description, payment_method) 
            VALUES ('$category', '$amount', '$date', '$description', '$payment_method')";

    if ($conn->query($sql) === TRUE) {
        header("Location: index.php?success=1"); // redirect back to dashboard
        exit;
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
