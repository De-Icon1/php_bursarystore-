<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
check_login();

// Only allow admin or director to manage users
if(!in_array($_SESSION['role'] ?? '', ['admin','director'])){
    header('Location: admin_dashboard.php');
    exit;
}

$err = $success = '';

// Add user
if(isset($_POST['add_user'])){
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role = trim($_POST['role'] ?? 'storekeeper');
    $password = trim($_POST['password'] ?? '');

    if($username === '' || $password === '' || $full_name === ''){
        $err = 'Username, full name and password are required.';
    } else {
        // check unique username
        $stmt = $mysqli->prepare('SELECT user_id FROM users WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();
        if($stmt->num_rows > 0){
            $err = 'Username already exists.';
            $stmt->close();
        } else {
            $stmt->close();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $mysqli->prepare('INSERT INTO users (username, password, role, full_name, created_at) VALUES (?, ?, ?, ?, NOW())');
            $ins->bind_param('ssss', $username, $hash, $role, $full_name);
            if($ins->execute()){
                $success = 'User added successfully.';
            } else {
                $err = 'Error adding user: ' . $mysqli->error;
            }
            $ins->close();
        }
    }
}

// Update user
if(isset($_POST['update_user'])){
    $uid = (int)($_POST['user_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role = trim($_POST['role'] ?? 'storekeeper');
    $password = trim($_POST['password'] ?? '');

    if($uid <= 0 || $username === '' || $full_name === ''){
        $err = 'Invalid input for update.';
    } else {
        // ensure username is unique for other users
        $stmt = $mysqli->prepare('SELECT user_id FROM users WHERE username = ? AND user_id != ?');
        $stmt->bind_param('si', $username, $uid);
        $stmt->execute();
        $stmt->store_result();
        if($stmt->num_rows > 0){
            $err = 'Username already taken by another user.';
            $stmt->close();
        } else {
            $stmt->close();
            if($password !== ''){
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $upd = $mysqli->prepare('UPDATE users SET username = ?, password = ?, role = ?, full_name = ? WHERE user_id = ?');
                $upd->bind_param('ssssi', $username, $hash, $role, $full_name, $uid);
            } else {
                $upd = $mysqli->prepare('UPDATE users SET username = ?, role = ?, full_name = ? WHERE user_id = ?');
                $upd->bind_param('sssi', $username, $role, $full_name, $uid);
            }
            if($upd->execute()){
                $success = 'User updated successfully.';
            } else {
                $err = 'Error updating user: ' . $mysqli->error;
            }
            $upd->close();
        }
    }
}

// Delete user
if(isset($_POST['delete_user'])){
    $uid = (int)($_POST['user_id'] ?? 0);
    if($uid <= 0){
        $err = 'Invalid user id for deletion.';
    } else if($uid == ($_SESSION['user_id'] ?? 0)){
        $err = 'You cannot delete your own account while logged in.';
    } else {
        $del = $mysqli->prepare('DELETE FROM users WHERE user_id = ?');
        $del->bind_param('i', $uid);
        if($del->execute()){
            $success = 'User deleted.';
        } else {
            $err = 'Error deleting user: ' . $mysqli->error;
        }
        $del->close();
    }
}

// load user for edit if requested
$edit_user = null;
if(isset($_GET['edit_id'])){
    $eid = (int)$_GET['edit_id'];
    if($eid > 0){
        $s = $mysqli->prepare('SELECT user_id, username, role, full_name FROM users WHERE user_id = ?');
        $s->bind_param('i', $eid);
        $s->execute();
        $res = $s->get_result();
        $edit_user = $res->fetch_assoc();
        $s->close();
    }
}

// fetch all users
$users = [];
$r = $mysqli->query('SELECT user_id, username, role, full_name, created_at FROM users ORDER BY username');
if($r){
    while($row = $r->fetch_assoc()) $users[] = $row;
}
?>
<?php include('assets/inc/head.php'); ?>
<body>
<?php include('assets/inc/nav.php'); ?>
<?php include('assets/inc/sidebar_admin.php'); ?>

<div class="content-page">
<div class="content container">

    <h4 class="mb-3">Manage Users</h4>

    <?php if($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if($err): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12 col-lg-5 mb-3">
            <div class="card-box">
                <?php if($edit_user): ?>
                    <h5>Edit User: <?= htmlspecialchars($edit_user['username']) ?></h5>
                    <form method="POST">
                        <input type="hidden" name="user_id" value="<?= intval($edit_user['user_id']) ?>">
                        <div class="form-group">
                            <label>Username</label>
                            <input name="username" class="form-control" value="<?= htmlspecialchars($edit_user['username']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Full Name</label>
                            <input name="full_name" class="form-control" value="<?= htmlspecialchars($edit_user['full_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <select name="role" class="form-control">
                                <?php foreach(['admin','director','supervisor','storekeeper'] as $rl): ?>
                                    <option value="<?= $rl ?>" <?= $edit_user['role'] === $rl ? 'selected' : '' ?>><?= ucfirst($rl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>New Password (leave blank to keep current)</label>
                            <input name="password" type="password" class="form-control">
                        </div>
                        <button name="update_user" class="btn btn-primary">Update User</button>
                        <a href="manage_users.php" class="btn btn-light">Cancel</a>
                    </form>
                <?php else: ?>
                    <h5>Add New User</h5>
                    <form method="POST">
                        <div class="form-group">
                            <label>Username</label>
                            <input name="username" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Full Name</label>
                            <input name="full_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <select name="role" class="form-control">
                                <option value="storekeeper">Storekeeper</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="director">Director</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input name="password" type="password" class="form-control" required>
                        </div>
                        <button name="add_user" class="btn btn-success">Create User</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card-box">
                <h5>Users</h5>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead><tr><th>Username</th><th>Full Name</th><th>Role</th><th>Joined</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach($users as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['username']) ?></td>
                                <td><?= htmlspecialchars($u['full_name']) ?></td>
                                <td><?= htmlspecialchars(ucfirst($u['role'])) ?></td>
                                <td><?= htmlspecialchars($u['created_at']) ?></td>
                                <td>
                                    <a href="manage_users.php?edit_id=<?= intval($u['user_id']) ?>" class="btn btn-sm btn-primary">Edit</a>
                                    <?php if(intval($u['user_id']) !== intval($_SESSION['user_id'] ?? 0)): ?>
                                    <form method="POST" style="display:inline-block" onsubmit="return confirm('Delete this user?');">
                                        <input type="hidden" name="user_id" value="<?= intval($u['user_id']) ?>">
                                        <button name="delete_user" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
</div>

<?php include('assets/inc/footer.php'); ?>
</body>
</html>
        </body>
        </html>
