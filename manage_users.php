<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
check_login();

// Only allow admin, director or superadmin to manage users
if(!in_array($_SESSION['role'] ?? '', ['admin','director','superadmin'])){
    header('Location: admin_dashboard.php');
    exit;
}

// Determine which roles the current user may assign
$current_user_role = $_SESSION['role'] ?? '';
$available_roles = [];
if($current_user_role === 'superadmin'){
    $available_roles = ['superadmin','admin','director','supervisor','storekeeper'];
} elseif($current_user_role === 'admin'){
    $available_roles = ['admin','director','supervisor','storekeeper'];
} elseif($current_user_role === 'director'){
    $available_roles = ['supervisor','storekeeper'];
} else {
    $available_roles = ['storekeeper'];
}

// Role hierarchy for permission checks (higher number = higher privilege)
$role_hierarchy = [
    'storekeeper' => 1,
    'supervisor'  => 2,
    'director'    => 3,
    'admin'       => 4,
    'superadmin'  => 5,
];
$current_user_level = $role_hierarchy[$current_user_role] ?? 0;

$err = $success = '';
$edit_user = null; // ensure variable exists before use to avoid notices
$users = []; // ensure users array exists to avoid undefined variable warnings

// Add user
if(isset($_POST['add_user'])){
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role = trim($_POST['role'] ?? 'storekeeper');
    $password = trim($_POST['password'] ?? '');

    if($username === '' || $password === '' || $full_name === ''){
        $err = 'Username, full name and password are required.';
    } else {
        // enforce allowed roles
        if(!in_array($role, $available_roles)){
            $err = 'You are not permitted to assign that role.';
        }
        
        if($err === ''){
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
        // enforce allowed roles
        if(!in_array($role, $available_roles)){
            $err = 'You are not permitted to assign that role.';
        }
        
        // ensure target user is not higher than current user
        if($err === ''){
            $t = $mysqli->prepare('SELECT role FROM users WHERE user_id = ?');
            $t->bind_param('i', $uid);
            $t->execute();
            $res = $t->get_result();
            $target = $res->fetch_assoc();
            $t->close();
            if($target){
                $target_level = $role_hierarchy[$target['role']] ?? 0;
                if($target_level > $current_user_level){
                    $err = 'You are not permitted to modify a user with a higher role.';
                }
            }
        }
        
        if($err === ''){
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
        // ensure target user is not higher than current user
        $t = $mysqli->prepare('SELECT role FROM users WHERE user_id = ?');
        $t->bind_param('i', $uid);
        $t->execute();
        $res = $t->get_result();
        $target = $res->fetch_assoc();
        $t->close();
        if($target){
            $target_level = $role_hierarchy[$target['role']] ?? 0;
            if($target_level > $current_user_level){
                $err = 'You are not permitted to delete a user with a higher role.';
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
        } else {
            $err = 'User not found.';
        }
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

// prevent editing users with higher roles
if($edit_user){
    $target_level = $role_hierarchy[$edit_user['role']] ?? 0;
    if($target_level > $current_user_level){
        $err = 'You are not permitted to edit a user with a higher role.';
        $edit_user = null;
    }
}

// fetch all users
$users = [];
$r = $mysqli->query('SELECT user_id, username, role, full_name, created_at FROM users ORDER BY username');
if($r){
    while($row = $r->fetch_assoc()) $users[] = $row;
}
}
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
                                <?php foreach($available_roles as $rl): ?>
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
                                <?php foreach($available_roles as $rl): ?>
                                    <option value="<?= $rl ?>"><?= ucfirst($rl) ?></option>
                                <?php endforeach; ?>
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
                            <?php
                                $target_level = $role_hierarchy[$u['role']] ?? 0;
                                // skip users with higher roles than current user
                                if($target_level > $current_user_level) continue;
                            ?>
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
