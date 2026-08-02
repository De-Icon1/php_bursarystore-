<?php
require_once __DIR__ . '/../assets/inc/config.php';

$mysqli->query("CREATE TABLE IF NOT EXISTS users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'storekeeper',
  full_name VARCHAR(200) NOT NULL,
  email VARCHAR(150) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$mysqli->query("CREATE TABLE IF NOT EXISTS logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  action TEXT,
  mac VARCHAR(100) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$username = 'storekeeper';
$password = 'Store@12345';
$full_name = 'Store Keeper';
$role = 'storekeeper';
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $mysqli->prepare('SELECT user_id FROM users WHERE username = ?');
$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    $upd = $mysqli->prepare('UPDATE users SET password = ?, role = ?, full_name = ? WHERE username = ?');
    $upd->bind_param('ssss', $hash, $role, $full_name, $username);
    $upd->execute();
    echo "Updated existing user\n";
} else {
    $ins = $mysqli->prepare('INSERT INTO users (username, password, role, full_name) VALUES (?, ?, ?, ?)');
    $ins->bind_param('ssss', $username, $hash, $role, $full_name);
    $ins->execute();
    echo "Created new user\n";
}

$sel = $mysqli->prepare('SELECT user_id, username, role, full_name FROM users WHERE username = ?');
$sel->bind_param('s', $username);
$sel->execute();
$sel->bind_result($id, $u, $r, $f);
$sel->fetch();
echo "user_id=$id\nusername=$u\nrole=$r\nfull_name=$f\n";
$sel->close();
