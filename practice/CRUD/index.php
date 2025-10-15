<?php
require_once "functions.php";

$message = "";

// CREATE
if (isset($_POST['create'])) {
    $message = createRecord($_POST['name'], $_POST['email']);
}

// UPDATE
if (isset($_POST['update'])) {
    $message = updateRecord($_POST['id'], $_POST['name'], $_POST['email']);
}

// DELETE
if (isset($_GET['delete'])) {
    $message = deleteRecord($_GET['delete']);
}

// READ (fetch all users)
$users = getAllRecords();
?>
<!DOCTYPE html>
<html>
<head>
  <title>PHP CRUD Demo</title>
  <style>
    body { font-family: Arial; margin: 20px; }
    table { border-collapse: collapse; width: 60%; margin-top: 20px; }
    th, td { border: 1px solid #999; padding: 8px; text-align: center; }
    .msg { margin: 10px 0; font-weight: bold; color: blue; }
  </style>
</head>
<body>
  <h2>PHP CRUD Application</h2>

  <?php if ($message) echo "<div class='msg'>$message</div>"; ?>

  <!-- CREATE / UPDATE FORM -->
  <form method="POST">
    <input type="hidden" name="id" value="<?= $_GET['edit_id'] ?? '' ?>">
    <input type="text" name="name" placeholder="Enter Name" 
           value="<?= $_GET['edit_name'] ?? '' ?>" required>
    <input type="email" name="email" placeholder="Enter Email"
           value="<?= $_GET['edit_email'] ?? '' ?>" required>
    <button type="submit" name="<?= isset($_GET['edit_id']) ? 'update' : 'create' ?>">
      <?= isset($_GET['edit_id']) ? 'Update' : 'Create' ?>
    </button>
  </form>

  <!-- READ: Display Records -->
  <table>
    <tr><th>ID</th><th>Name</th><th>Email</th><th>Actions</th></tr>
    <?php foreach ($users as $row): ?>
      <tr>
        <td><?= $row['id'] ?></td>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= htmlspecialchars($row['email']) ?></td>
        <td>
          <a href="?edit_id=<?= $row['id'] ?>&edit_name=<?= urlencode($row['name']) ?>&edit_email=<?= urlencode($row['email']) ?>">✏️ Edit</a> |
          <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this record?')">🗑️ Delete</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>
