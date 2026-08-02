<?php
session_start();
include("assets/inc/config.php");
include("assets/inc/checklogins.php");
check_login();

$page_title = "Store Items";
$err = $success = "";

// Load categories
$categories = [];
$c = $mysqli->query("SELECT category_id, name FROM categories ORDER BY name");
if($c){ while($cat = $c->fetch_assoc()) $categories[] = $cat; }

// Check whether item_categories junction table exists (item hasMany categories)
$has_item_categories = $mysqli->query("SHOW TABLES LIKE 'item_categories'")->num_rows > 0;

// Detect which columns exist in `items` table so the page works with either schema
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

// Find (or create) a category row by name, returns its category_id
function ensure_category($mysqli, $name){
	$name = trim($name);
	$stmt = $mysqli->prepare('SELECT category_id FROM categories WHERE name = ?');
	$stmt->bind_param('s', $name);
	$stmt->execute();
	$stmt->bind_result($cid);
	if($stmt->fetch()){
		$stmt->close();
		return (int)$cid;
	}
	$stmt->close();
	$ins = $mysqli->prepare('INSERT INTO categories (name) VALUES (?)');
	$ins->bind_param('s', $name);
	$ins->execute();
	$cid = $ins->insert_id;
	$ins->close();
	return (int)$cid;
}

// Find (or create) an items row by its exact display name, using whichever
// columns exist on this installation's `items` table.
function find_or_create_item($mysqli, $name, $category_name, $unit, $col_item_name, $col_category, $col_unit){
	$stmt = $mysqli->prepare("SELECT item_id FROM items WHERE `$col_item_name` = ?");
	$stmt->bind_param('s', $name);
	$stmt->execute();
	$stmt->bind_result($item_id);
	if($stmt->fetch()){
		$stmt->close();
		return (int)$item_id;
	}
	$stmt->close();

	if($col_category === 'category'){
		$ins = $mysqli->prepare("INSERT INTO items (`$col_item_name`, `$col_category`, `$col_unit`) VALUES (?, ?, ?)");
		if(!$ins) throw new Exception('Prepare failed: ' . $mysqli->error);
		$ins->bind_param('sss', $name, $category_name, $unit);
	} else {
		// category_id (or no legacy category column): the item<->category link
		// is tracked via the item_categories junction table instead.
		$ins = $mysqli->prepare("INSERT INTO items (`$col_item_name`, `$col_unit`) VALUES (?, ?)");
		if(!$ins) throw new Exception('Prepare failed: ' . $mysqli->error);
		$ins->bind_param('ss', $name, $unit);
	}
	if(!$ins->execute()) throw new Exception('Insert failed: ' . $mysqli->error);
	$item_id = $mysqli->insert_id;
	$ins->close();
	return (int)$item_id;
}

// Register an Item as a base entity (e.g. "Paper", "Biro"). Selected/typed
// Categories (e.g. "A4", "Blue") are linked to that single item via the
// item_categories junction table; stock is tracked per (item, category) pair.
if(isset($_POST['save_item'])){
	$name = trim($_POST['item_name'] ?? '');
	$unit = trim($_POST['unit'] ?? '');
	$existing_category_ids = isset($_POST['category_ids']) ? array_map('intval', $_POST['category_ids']) : [];
	$new_category_names = array_filter(array_map('trim', explode(',', $_POST['new_categories'] ?? '')));

	if($name === ''){
		$err = 'Item name is required.';
	} else {
		$mysqli->begin_transaction();
		try {
			$category_ids = $existing_category_ids;
			foreach($new_category_names as $cat_name){
				if($cat_name !== '') $category_ids[] = ensure_category($mysqli, $cat_name);
			}
			$category_ids = array_unique(array_filter($category_ids, function($id){ return $id > 0; }));

			// First linked category (if any) is also stored in the legacy `category`
			// text column so older reports that read it directly still show something.
			$first_cat_name = null;
			if(!empty($category_ids)){
				$cat_lookup = $mysqli->query('SELECT category_id, name FROM categories');
				$cat_names = [];
				while($row = $cat_lookup->fetch_assoc()) $cat_names[(int)$row['category_id']] = $row['name'];
				$first_cid = reset($category_ids);
				$first_cat_name = $cat_names[$first_cid] ?? null;
			}

			$item_id = find_or_create_item($mysqli, $name, $first_cat_name, $unit, $col_item_name, $col_category, $col_unit);

			if($has_item_categories){
				foreach($category_ids as $cid){
					$link = $mysqli->prepare('INSERT IGNORE INTO item_categories (item_id, category_id) VALUES (?, ?)');
					$link->bind_param('ii', $item_id, $cid);
					$link->execute();
					$link->close();
				}
			}

			$mysqli->commit();
			$success = 'Registered: ' . $name;
		} catch (Exception $e) {
			$mysqli->rollback();
			$err = "Error: " . $e->getMessage();
		}
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
		<p class="text-muted">
			An <strong>Item</strong> is a base entity (e.g. <em>Paper</em>, <em>Biro</em>). Pick or type one or more
			<strong>Categories</strong> to link the sizes/types under it (e.g. Paper &rarr; A4, A5, Legal; Biro &rarr; Blue, Black, Red).
			The item itself is never renamed &mdash; stock is tracked per item and category together.
		</p>

		<form method="POST">
			<div class="row">
				<div class="col-12 col-md-6 mb-3">
					<label>Item Name</label>
					<input name="item_name" class="form-control" placeholder="e.g. Paper, Biro" required>
				</div>

				<div class="col-12 col-md-3 mb-3">
					<label>Existing Categories</label>
					<select name="category_ids[]" class="form-control" multiple size="5" style="height:auto;">
						<?php foreach($categories as $cat): ?>
							<option value="<?= intval($cat['category_id']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
						<?php endforeach; ?>
					</select>
					<small class="form-text text-muted">Hold Ctrl/Cmd to select multiple categories. Leave empty if not needed.</small>
				</div>

				<div class="col-12 col-md-3 mb-3">
					<label>New Categories</label>
					<input name="new_categories" class="form-control" placeholder="e.g. A4, A5, Legal">
					<small class="form-text text-muted">Comma-separated. Created automatically if they don't exist yet.</small>

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
			</div>

			<div class="row">
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
		<p class="text-muted small">Grouped by base item; each row shows the categories/variants registered under it.</p>

		<table class="table table-bordered">
			<thead>
				<tr>
					<th>Item</th>
					<th>Categories</th>
					<th>Unit</th>
				</tr>
			</thead>
			<tbody>
				<?php
				// Build listing query according to detected columns, then group
				// each variant (e.g. "Paper (A4)") under its base name ("Paper").
				if ($has_item_categories) {
					$q = $mysqli->query("
						SELECT it.*, GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS category
						FROM items it
						LEFT JOIN item_categories ic ON it.item_id = ic.item_id
						LEFT JOIN categories c ON ic.category_id = c.category_id
						GROUP BY it.item_id
						ORDER BY it.`$col_item_name`
					");
				} elseif($col_category === 'category'){
					$q = $mysqli->query("SELECT it.*, it.`category` AS category FROM items it ORDER BY it.`$col_item_name`");
				} elseif($col_category === 'category_id'){
					$q = $mysqli->query("SELECT it.*, c.name AS category FROM items it LEFT JOIN categories c ON it.category_id = c.category_id ORDER BY it.`$col_item_name`");
				} else {
					$q = $mysqli->query("SELECT it.*, NULL AS category FROM items it ORDER BY it.`$col_item_name`");
				}

				if($q === false){
					echo '<tr><td colspan="3" class="text-danger">Query error: ' . htmlspecialchars($mysqli->error) . '</td></tr>';
				} else if($q->num_rows === 0){
					echo '<tr><td colspan="3">No items found.</td></tr>';
				} else {
					// Group variants (e.g. "Paper (A4)", "Paper (Legal)") under their base name ("Paper")
					$groups = [];
					while($row = $q->fetch_assoc()){
						$display_name = $row[$col_item_name] ?? $row['name'] ?? '';
						$base_name = preg_replace('/\s*\(.*\)\s*$/', '', $display_name);
						$category = trim($row['category'] ?? '');
						$unit = $row[$col_unit] ?? $row['unit_measure'] ?? $row['unit'] ?? '';

						if(!isset($groups[$base_name])) $groups[$base_name] = ['categories' => [], 'unit' => $unit];
						if($category !== '') $groups[$base_name]['categories'][$category] = true;
					}
					foreach($groups as $base_name => $info){
						$cats = !empty($info['categories']) ? implode(', ', array_keys($info['categories'])) : '<span class="text-muted">&mdash;</span>';
						echo '<tr>';
						echo '<td>' . htmlspecialchars($base_name) . '</td>';
						echo '<td>' . $cats . '</td>';
						echo '<td>' . htmlspecialchars($info['unit']) . '</td>';
						echo '</tr>';
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
