<?php
session_start();
include("assets/inc/config.php");
include("assets/inc/checklogins.php");
check_login();

$page_title = "Store Items";
$err = $success = "";

// Check whether categories table exists and load categories
$categories = [];
$has_categories = false;
$check = $mysqli->query("SHOW TABLES LIKE 'categories'");
if($check && $check->num_rows > 0){
	$has_categories = true;
	$c = $mysqli->query("SELECT category_id, name FROM categories ORDER BY name");
	if($c){ while($cat = $c->fetch_assoc()) $categories[] = $cat; }
}

// Detect which columns exist in `items` table so the page works with either migration
$col_item_name = 'item_name';
$col_category = 'category_id';
$col_unit = 'unit';
$chk = $mysqli->query("SHOW COLUMNS FROM items LIKE 'item_name'");
if(!$chk || $chk->num_rows === 0) $col_item_name = 'name';
$chk = $mysqli->query("SHOW COLUMNS FROM items LIKE 'category_id'");
if(!$chk || $chk->num_rows === 0){
    $chk2 = $mysqli->query("SHOW COLUMNS FROM items LIKE 'category'");
    if($chk2 && $chk2->num_rows > 0) $col_category = 'category'; else $col_category = null;
}
$chk = $mysqli->query("SHOW COLUMNS FROM items LIKE 'unit'");
if(!$chk || $chk->num_rows === 0) $col_unit = 'unit_measure';

// Handle item registration
if(isset($_POST['save_item'])){
	$name = trim($_POST['item_name'] ?? '');
	$unit = trim($_POST['unit'] ?? '');

	// detect items table column names to support either schema
	$col_item_name = 'item_name';
	$col_category = 'category_id';
	$col_unit = 'unit';
	// check which columns exist
	$check1 = $mysqli->query("SHOW COLUMNS FROM items LIKE 'item_name'");
	if(!$check1 || $check1->num_rows === 0){
		$col_item_name = 'name';
	}
	$check2 = $mysqli->query("SHOW COLUMNS FROM items LIKE 'category_id'");
	if(!$check2 || $check2->num_rows === 0){
		// fallback to varchar 'category' column if present
		$check2b = $mysqli->query("SHOW COLUMNS FROM items LIKE 'category'");
		if($check2b && $check2b->num_rows > 0){
			$col_category = 'category';
		} else {
			$col_category = null;
		}
	}
	$check3 = $mysqli->query("SHOW COLUMNS FROM items LIKE 'unit'");
	if(!$check3 || $check3->num_rows === 0){
		$col_unit = 'unit_measure';
	}

	// build insert depending on detected columns
	$params = [];
	$types = '';
	$cols = [];
	// name column
	$cols[] = "`".$col_item_name."`";
	$types .= 's'; $params[] = $name;
	// category: if categories table exists and items has category_id use it, else if items has 'category' use that
	if($col_category === 'category_id' && $has_categories){
		$category = (int) ($_POST['category_id'] ?? 0);
		$cols[] = '`category_id`'; $types .= 'i'; $params[] = $category;
	} elseif($col_category === 'category'){
		$catName = '';
		if($has_categories){
			// map selected category id to its name if available
			foreach($categories as $c){ if(intval($c['category_id']) === (int)($_POST['category_id'] ?? 0)){ $catName = $c['name']; break; } }
		}
		$cols[] = '`category`'; $types .= 's'; $params[] = $catName;
	}
	// unit
	if($col_unit){
		$cols[] = '`'.$col_unit.'`'; $types .= 's'; $params[] = $unit;
	}

	$placeholders = implode(', ', array_fill(0, count($cols), '?'));
	$colList = implode(', ', $cols);
	$stmt = $mysqli->prepare("INSERT INTO items ($colList) VALUES ($placeholders)");
	if($stmt){
		// bind dynamically
		$bind_names[] = $types;
		for ($i=0;$i<count($params);$i++){
			$bind_name = 'bind'.$i;
			$$bind_name = $params[$i];
			$bind_names[] = &$$bind_name;
		}
		call_user_func_array(array($stmt,'bind_param'), $bind_names);
	} else {
		$err = 'Prepare failed: '.$mysqli->error;
	}

	if($stmt->execute()){
		$last_id = $mysqli->insert_id;
		$success = "Item registered successfully! (ID: " . $last_id . ")";
	} else {
		$err = "Error inserting item: " . $mysqli->error;
	}
}
?>

<?php include("assets/inc/head.php"); ?>
<body>
<?php include("assets/inc/nav.php"); ?>
<?php include("assets/inc/sidebar_admin.php"); ?>

<!-- Page fixes: ensure category dropdowns render above sidebar/header -->
<style>
	.content.container, .container { position: relative; z-index: 1100; }
	.left-side-menu { z-index: 1000; }
	.card-box, .form-group, .row { overflow: visible; }
	select, .bootstrap-select .dropdown-menu, .select2-container { z-index: 99999 !important; }
	.content.container { margin-top: 90px; }
</style>

<div class="content-page">
<div class="content container">

	<!-- Alerts -->
	<?php if($success): ?>
		<div class="alert alert-success"><?= $success ?></div>
	<?php endif; ?>

	<?php if($err): ?>
		<div class="alert alert-danger"><?= $err ?></div>
	<?php endif; ?>

	<!-- Register Item Form -->
	<div class="card-box p-4">
		<h5 class="mb-3">Register New Item</h5>

		<form method="POST">
			<div class="row">
				<div class="col-12 col-md-6 mb-3">
					<label>Item Name</label>
					<input name="item_name" class="form-control" required>
				</div>

				<div class="col-12 col-md-3 mb-3">
					<label>Category</label>
					<?php // use $categories loaded earlier and $has_categories flag ?>
					<select name="category_id" class="form-control" <?= $has_categories ? 'required' : '' ?>>
						<?php if(empty($categories)): ?>
							<option value="">No categories found</option>
						<?php else: ?>
							<?php foreach($categories as $cat): ?>
								<option value="<?= intval($cat['category_id']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
							<?php endforeach; ?>
						<?php endif; ?>
						</select>

						<!-- Manage categories navigation box -->
						<div class="mt-2">
							<div class="card p-2" style="background:#f8f9fb;border:1px solid #e9edf0;">
								<div class="d-flex justify-content-between align-items-center">
									<div>
										<strong>Manage Categories</strong>
										<div class="text-muted small">Add, edit or remove categories used for items.</div>
									</div>
									<div>
										<a href="store_management.php" class="btn btn-sm btn-outline-primary">Open</a>
									</div>
								</div>
							</div>
						</div>
				</div>

				<div class="col-12 col-md-3 mb-3">
					<label>Unit</label>
					<select name="unit" class="form-control" required>
						<option value="pcs">pcs</option>
						<option value="ream">ream</option>
						<option value="pack">pack</option>
						<option value="sets">sets</option>
						<option value="box">box</option>
					</select>
					<small class="form-text text-muted">For paper: 1 pack = 5 reams. For Biro: 50pcs = 1 pack.</small>
				</div>
			</div>

			<!-- Save Button -->
			<button type="submit" name="save_item" class="btn btn-primary mt-2">Save Item</button>

		</form>
	</div>

	<!-- Items Table -->
	<div class="table-wrapper mt-4">
		<h5>Existing Items</h5>

		<table class="table table-bordered">
			<thead>
				<tr>
					<th>Item</th>
					<th>Category</th>
					<th>Unit</th>
				</tr>
			</thead>
			<tbody>
				<?php
				// build listing query according to detected columns
				if($col_category === 'category'){
					$q = $mysqli->query("SELECT it.*, it.`category` AS category FROM items it ORDER BY it.`$col_item_name`");
				} elseif($has_categories && $col_category === 'category_id'){
					$q = $mysqli->query("SELECT it.*, c.name AS category FROM items it LEFT JOIN categories c ON it.category_id = c.category_id ORDER BY it.`$col_item_name`");
				} else {
					$q = $mysqli->query("SELECT it.*, NULL AS category FROM items it ORDER BY it.`$col_item_name`");
				}

				if($q === false){
					echo '<tr><td colspan="3" class="text-danger">Query error: ' . htmlspecialchars($mysqli->error) . '</td></tr>';
				} else {
					if($q->num_rows === 0){
						echo '<tr><td colspan="3">No items found.</td></tr>';
					} else {
						while($row = $q->fetch_assoc()){
							echo '<tr>';
							echo '<td>' . htmlspecialchars($row[$col_item_name] ?? $row['name'] ?? '') . '</td>';
							echo '<td>' . htmlspecialchars($row['category'] ?? ($row['category'] ?? '')) . '</td>';
							echo '<td>' . htmlspecialchars($row[$col_unit] ?? $row['unit_measure'] ?? $row['unit'] ?? '') . '</td>';
							echo '</tr>';
						}
					}
				}
				?>
			</tbody>
		</table>
	</div>

	<?php
	// Optional debug panel: show last 20 rows and counts when ?debug=1 is set
	if(isset($_GET['debug']) && $_GET['debug'] == '1'){
		echo '<div class="container mt-4"><div class="card"><div class="card-body"><h5>Debug: Last 20 items</h5>';

		$dq = $mysqli->query("SELECT * FROM items ORDER BY item_id DESC LIMIT 20");
	if($dq === false){
			echo '<div class="text-danger">Debug query error: ' . htmlspecialchars($mysqli->error) . '</div>';
		} else {
			echo '<div>Items in DB (last 20): <strong>' . intval($dq->num_rows) . '</strong></div>';
					echo '<table class="table table-sm"><thead><tr><th>item_id</th><th>'.$col_item_name.'</th><th>'.($col_category ?? 'category').'</th><th>'.$col_unit.'</th></tr></thead><tbody>';
					while($r = $dq->fetch_assoc()){
						echo '<tr>';
						echo '<td>' . intval($r['item_id']) . '</td>';
						echo '<td>' . htmlspecialchars($r[$col_item_name] ?? $r['name'] ?? '') . '</td>';
						echo '<td>' . htmlspecialchars($r[$col_category] ?? $r['category'] ?? '') . '</td>';
						echo '<td>' . htmlspecialchars($r[$col_unit] ?? $r['unit_measure'] ?? $r['unit'] ?? '') . '</td>';
						echo '</tr>';
					}
			echo '</tbody></table>';
		}

		echo '</div></div></div>';
	}
	?>

<?php include("assets/inc/footer.php"); ?>
</div>
</div>

</body>
</html>
