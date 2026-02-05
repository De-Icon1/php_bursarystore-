<?php
session_start();
include __DIR__ . '/../assets/inc/config.php';
include __DIR__ . '/../assets/inc/checklogins.php';
include __DIR__ . '/../assets/inc/functions.php';

check_login();
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
  header('Location: ../index.php');
  exit;
}

$msg = '';
$errors = [];

$check = $mysqli->query("SHOW TABLES LIKE 'items'");
if($check && $check->num_rows > 0){
    $msg = 'Table `items` already exists.';
} else {
    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])){
        $migration = __DIR__ . '/../sql/migrations/003_create_items.sql';
        if(!file_exists($migration)){
            $errors[] = 'Migration SQL not found: ' . $migration;
        } else {
            $sql = file_get_contents($migration);
            if($sql === false) $errors[] = 'Failed to read migration file.';
            else {
                if($mysqli->multi_query($sql)){
                    while($mysqli->more_results()) { $mysqli->next_result(); }
                    $msg = 'Table `items` created successfully.';
                    if(function_exists('log_action') && isset($_SESSION['user_id'])) log_action($_SESSION['user_id'], 'Created items table via admin tool');
                } else {
                    $errors[] = 'Create failed: ' . $mysqli->error;
                }
            }
        }
    }
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Create items table</title>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css"></head>
<body class="p-4">
<div class="container">
  <h4>Create `items` table</h4>
  <?php if($msg) echo '<div class="alert alert-success">'.htmlspecialchars($msg).'</div>'; ?>
  <?php if($errors) foreach($errors as $e) echo '<div class="alert alert-danger">'.htmlspecialchars($e).'</div>'; ?>

  <?php if(empty($msg)): ?>
    <p>This will create the `items` table. Back up your DB before proceeding.</p>
    <form method="post">
      <button name="create" class="btn btn-danger">Create items table</button>
      <a class="btn btn-secondary" href="../admin_dashboard.php">Cancel</a>
    </form>
  <?php else: ?>
    <a class="btn btn-primary" href="../store_management.php">Open Manage Stock</a>
  <?php endif; ?>

</div>
</body>
</html>
