<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
check_login();

$err = $success = '';

if (isset($_POST['receive'])) {
    $item = (int)$_POST['item_id'];
    $qty = (int)$_POST['quantity'];
    $supplier = trim($_POST['supplier']);
    $reference = trim($_POST['reference']);
    $cost_per_unit = (float)$_POST['cost_per_unit'];
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;

    $received_by = $_SESSION['doc_number']
        ?? $_SESSION['admin_id']
        ?? $_SESSION['user_id']
        ?? $_SESSION['username']
        ?? 'system';


    if ($qty <= 0) {
        $err = "Quantity must be greater than zero.";
    } elseif (empty($supplier)) {
        $err = "Supplier name is required.";
        } else {
        $mysqli->begin_transaction();

        try {
            // Use migration schema: stock_receipts, receipt_items, stock_transactions
            $note = "Received from " . $supplier;

            // 1) stock_receipts
            $has_receipt_category = false;
            $col_check = $mysqli->query("SHOW COLUMNS FROM stock_receipts LIKE 'category_id'");
            $has_receipt_category = $col_check && $col_check->num_rows > 0;

            if ($has_receipt_category && $category_id > 0) {
                $stmt = $mysqli->prepare("INSERT INTO stock_receipts (supplier, received_by, note, category_id) VALUES (?, ?, ?, ?)");
                if (!$stmt) throw new Exception('DB prepare failed (stock_receipts): ' . $mysqli->error);
                $stmt->bind_param('sisi', $supplier, $received_by, $note, $category_id);
            } else {
                $stmt = $mysqli->prepare("INSERT INTO stock_receipts (supplier, received_by, note) VALUES (?, ?, ?)");
                if (!$stmt) throw new Exception('DB prepare failed (stock_receipts): ' . $mysqli->error);
                $stmt->bind_param('sis', $supplier, $received_by, $note);
            }
            $stmt->execute();
            $receipt_id = $mysqli->insert_id;
            $stmt->close();

            // 2) receipt_items
            $stmt = $mysqli->prepare("INSERT INTO receipt_items (receipt_id, item_id, category_id, quantity, unit_cost) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) throw new Exception('DB prepare failed (receipt_items): ' . $mysqli->error);
            $unit_cost = $cost_per_unit;
            $receipt_category_id = $category_id > 0 ? $category_id : null;
            $stmt->bind_param('iiiid', $receipt_id, $item, $receipt_category_id, $qty, $unit_cost);
            $stmt->execute();
            $stmt->close();

            // 3) stock_transactions
            $stmt = $mysqli->prepare("INSERT INTO stock_transactions (item_id, category_id, qty_change, tx_type, reference_id, user_id, note) VALUES (?, ?, ?, 'receive', ?, ?, ?)");
            if (!$stmt) throw new Exception('DB prepare failed (stock_transactions): ' . $mysqli->error);
            $stmt->bind_param('iiiiis', $item, $receipt_category_id, $qty, $receipt_id, $received_by, $note);
            $stmt->execute();
            $stmt->close();

            $mysqli->commit();
            $success = "Stock received successfully. Quantity: " . number_format($qty);
        } catch (Exception $e) {
            $mysqli->rollback();
            $err = "Error receiving stock: " . $e->getMessage() . " (DB: " . $mysqli->error . ")";
            error_log("[stock_receive] Error: " . $e->getMessage() . " | mysqli: " . $mysqli->error . "\n" . $e->getTraceAsString());
        }
    }
}
?>

<?php include("assets/inc/head.php"); ?>
<body>
<?php include("assets/inc/nav.php"); ?>
<?php include("assets/inc/sidebar_admin.php"); ?>

<div class="content-page">
<div class="content container">

<h3>Receive New Stock</h3>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= $success ?>
    <button type="button" class="close" data-dismiss="alert">&times;</button>
</div>
<?php endif; ?>

<?php if ($err): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <?= $err ?>
    <button type="button" class="close" data-dismiss="alert">&times;</button>
</div>
<?php endif; ?>

<div class="card-box">
<form method="POST" onsubmit="return validateForm();">

<?php
$items_map = [];
$items = $mysqli->query("SELECT * FROM items ORDER BY item_id");
while ($row = $items->fetch_assoc()) {
    $items_map[$row['item_id']] = $row;
}
$categories = $mysqli->query("SELECT category_id, name FROM categories ORDER BY name");
?>

<div class="form-row">
    <div class="form-group col-md-5">
        <label>Item *</label>
        <select name="item_id" id="item_id" class="form-control" required>
            <option value="">-- Select Item --</option>
            <?php foreach ($items_map as $id => $it): ?>
                <option value="<?= $id ?>"><?= htmlspecialchars($it['item_name'] ?? $it['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group col-md-3">
        <label>Category</label>
        <select id="category" name="category_id" class="form-control">
            <option value="">-- Select Category --</option>
            <?php 
            $categories->data_seek(0);
            while ($c = $categories->fetch_assoc()): 
            ?>
                <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="form-group col-md-4">
        <label>Current Stock</label>
        <input type="text" id="current_stock" class="form-control" readonly>
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-3">
        <label>Quantity *</label>
        <input type="number" id="quantity" name="quantity" class="form-control" value="1" min="1" required>
    </div>

    <div class="form-group col-md-3">
        <label>Cost per Unit</label>
        <input type="number" id="cost_per_unit" name="cost_per_unit" class="form-control" step="0.01" value="0">
    </div>

    <div class="form-group col-md-3">
        <label>Total Cost</label>
        <input type="text" id="total_cost" class="form-control" readonly>
    </div>

    <div class="form-group col-md-3">
        <label>&nbsp;</label>
        <button type="button" id="calc_btn" class="btn btn-secondary btn-block">
            Calculate
        </button>
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-6">
        <label>Supplier *</label>
        <input type="text" name="supplier" class="form-control" placeholder="Supplier name or company" required>
    </div>

    <div class="form-group col-md-6">
        <label>Reference/PO Number</label>
        <input type="text" name="reference" class="form-control" placeholder="Purchase Order or Invoice number">
    </div>
</div>

<button type="submit" name="receive" class="btn btn-success">
    Receive Stock
</button>

</form>
</div>
</div>
</div>

<?php include("assets/inc/footer.php"); ?>

<?php
// Load item-category mappings for the JS
$item_cat_map = [];
$has_item_categories = $mysqli->query("SHOW TABLES LIKE 'item_categories'")->num_rows > 0;
if ($has_item_categories) {
    $junc_res = $mysqli->query("SELECT item_id, category_id FROM item_categories");
    if ($junc_res) {
        while ($jr = $junc_res->fetch_assoc()) {
            $iid = (int)$jr['item_id'];
            $cid = (int)$jr['category_id'];
            if (!isset($item_cat_map[$iid])) $item_cat_map[$iid] = [];
            $item_cat_map[$iid][] = $cid;
        }
    }
}
?>

<script>
var ITEMS_MAP = <?= json_encode($items_map) ?>;
var ITEM_CAT_MAP = <?= json_encode($item_cat_map) ?>;

/* Get all category IDs for a given item from the junction map */
function getItemCategoryIds(itemId) {
    return ITEM_CAT_MAP[itemId] || [];
}

/* ITEM DETAILS */
function getItemDetails() {
    var id = document.getElementById('item_id').value;
    if (!id) {
        document.getElementById('current_stock').value = '';
        return;
    }

    var catSelect = document.getElementById('category');
    var allowedCats = getItemCategoryIds(id);

    // Store current selection
    var currentVal = catSelect.value;
    
    // Filter category dropdown to only show categories linked to this item
    catSelect.innerHTML = '<option value="">-- Select Category --</option>';
    
    // Add only the categories linked to this item, using the options we already have
    var allOptions = catSelect.options;
    var catOptions = <?php 
    $categories->data_seek(0);
    $cat_opts = [];
    while ($c = $categories->fetch_assoc()) {
        $cat_opts[] = ['id' => (int)$c['category_id'], 'name' => $c['name']];
    }
    echo json_encode($cat_opts);
    ?>;
    
    catOptions.forEach(function(c) {
        if (allowedCats.indexOf(c.id) !== -1) {
            var opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.name;
            catSelect.appendChild(opt);
        }
    });

    // Re-select previous value if still valid
    if (currentVal && allowedCats.indexOf(parseInt(currentVal)) !== -1) {
        catSelect.value = currentVal;
    }

    refreshCurrentStock();
}

/* Refetch current stock whenever the selected item or category changes */
function refreshCurrentStock() {
    var id = document.getElementById('item_id').value;
    if (!id) return;
    var catId = document.getElementById('category').value;
    var url = 'get_item_stock.php?item_id=' + encodeURIComponent(id);
    if (catId) url += '&category_id=' + encodeURIComponent(catId);
    fetch(url)
        .then(r => r.json())
        .then(d => {
            document.getElementById('current_stock').value =
                d.success ? d.current_stock : 'Error';
        });
}

/* COST CALCULATION */
function calculateTotalCost() {
    var q = Number(document.getElementById('quantity').value);
    var c = Number(document.getElementById('cost_per_unit').value);
    if (isNaN(q) || q < 0) q = 0;
    if (isNaN(c) || c < 0) c = 0;
    document.getElementById('total_cost').value = (q * c).toFixed(2);
}

/* VALIDATION */
function validateForm() {
    if (!document.getElementById('item_id').value) {
        alert('Select an item');
        return false;
    }
    if (Number(document.getElementById('quantity').value) <= 0) {
        alert('Quantity must be greater than zero');
        return false;
    }
    return true;
}

/* EVENTS */
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('item_id').addEventListener('change', getItemDetails);
    document.getElementById('category').addEventListener('change', refreshCurrentStock);
    document.getElementById('quantity').addEventListener('input', calculateTotalCost);
    document.getElementById('cost_per_unit').addEventListener('input', calculateTotalCost);
    document.getElementById('calc_btn').addEventListener('click', calculateTotalCost);
    calculateTotalCost();
});
</script>

</body>
</html>
