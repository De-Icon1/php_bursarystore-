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
            // Ensure we post into a category-specific item.
            // For each (base item, category) pair we use or create a variant
            // like "Paper (A5)" so A5/Legal stock is not merged into A4.
            if ($item > 0 && $category_id > 0) {
                $item_name = null;
                $unit = null;

                // Load the selected base item (schema-agnostic)
                $stmt = $mysqli->prepare("SELECT * FROM items WHERE item_id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('i', $item);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res && ($row = $res->fetch_assoc())) {
                        $item_name = $row['item_name'] ?? ($row['name'] ?? null);
                        $unit = $row['unit'] ?? ($row['unit_measure'] ?? null);
                    }
                    $stmt->close();
                }

                // Look up the chosen category name (e.g. A4, Legal, A5)
                $cat_name = null;
                $stmt = $mysqli->prepare("SELECT name FROM categories WHERE category_id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('i', $category_id);
                    $stmt->execute();
                    $stmt->bind_result($cat_name_val);
                    if ($stmt->fetch()) {
                        $cat_name = $cat_name_val;
                    }
                    $stmt->close();
                }

                if ($item_name && $cat_name) {
                    // If the item name already ends with "(Category)", just reuse it
                    $suffix = ' (' . $cat_name . ')';
                    $need_variant = true;
                    if (strlen($item_name) >= strlen($suffix)) {
                        $end = substr($item_name, -strlen($suffix));
                        if (strcasecmp($end, $suffix) === 0) {
                            $need_variant = false;
                        }
                    }

                    if ($need_variant) {
                        $variant_name = $item_name . ' (' . $cat_name . ')';

                        // Detect schema (item_name vs name)
                        $has_item_name_res = $mysqli->query("SHOW COLUMNS FROM items LIKE 'item_name'");
                        $has_item_name = $has_item_name_res && $has_item_name_res->num_rows > 0;

                        // Check if variant already exists
                        if ($has_item_name) {
                            $stmt = $mysqli->prepare("SELECT item_id FROM items WHERE item_name = ? LIMIT 1");
                        } else {
                            $stmt = $mysqli->prepare("SELECT item_id FROM items WHERE name = ? LIMIT 1");
                        }

                        $variant_id = null;
                        if ($stmt) {
                            $stmt->bind_param('s', $variant_name);
                            $stmt->execute();
                            $stmt->bind_result($found_id);
                            if ($stmt->fetch()) {
                                $variant_id = (int)$found_id;
                            }
                            $stmt->close();
                        }

                        if ($variant_id) {
                            $item = $variant_id;
                        } else {
                            // Create a new variant item with same unit but specific category
                            $unit_to_use = $unit ?: 'pcs';

                            $has_catid_res = $mysqli->query("SHOW COLUMNS FROM items LIKE 'category_id'");
                            $has_catid = $has_catid_res && $has_catid_res->num_rows > 0;

                            if ($has_item_name) {
                                if ($has_catid) {
                                    $stmt = $mysqli->prepare("INSERT INTO items (item_name, category_id, unit) VALUES (?, ?, ?)");
                                    if ($stmt) {
                                        $stmt->bind_param('sis', $variant_name, $category_id, $unit_to_use);
                                        $stmt->execute();
                                        $item = $stmt->insert_id;
                                        $stmt->close();
                                    }
                                } else {
                                    $stmt = $mysqli->prepare("INSERT INTO items (item_name, unit) VALUES (?, ?)");
                                    if ($stmt) {
                                        $stmt->bind_param('ss', $variant_name, $unit_to_use);
                                        $stmt->execute();
                                        $item = $stmt->insert_id;
                                        $stmt->close();
                                    }
                                }
                            } else {
                                // Legacy schema: name/category/unit_measure
                                $cat_label = $cat_name;
                                $stmt = $mysqli->prepare("INSERT INTO items (name, category, unit_measure) VALUES (?, ?, ?)");
                                if ($stmt) {
                                    $stmt->bind_param('sss', $variant_name, $cat_label, $unit_to_use);
                                    $stmt->execute();
                                    $item = $stmt->insert_id;
                                    $stmt->close();
                                }
                            }
                        }
                    }
                }
            }

            // Use migration schema: stock_receipts, receipt_items, stock_transactions
            $note = "Received from " . $supplier;

            // 1) stock_receipts
            $stmt = $mysqli->prepare("INSERT INTO stock_receipts (supplier, received_by, note) VALUES (?, ?, ?)");
            if (!$stmt) {
                throw new Exception('DB prepare failed (stock_receipts): ' . $mysqli->error);
            }
            $stmt->bind_param('sis', $supplier, $received_by, $note);
            $stmt->execute();
            $receipt_id = $mysqli->insert_id;
            $stmt->close();

            // 2) receipt_items
            $stmt = $mysqli->prepare("INSERT INTO receipt_items (receipt_id, item_id, quantity, unit_cost) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception('DB prepare failed (receipt_items): ' . $mysqli->error);
            }
            $unit_cost = $cost_per_unit;
            $stmt->bind_param('iiid', $receipt_id, $item, $qty, $unit_cost);
            $stmt->execute();
            $stmt->close();

            // 3) stock_transactions
            $stmt = $mysqli->prepare("INSERT INTO stock_transactions (item_id, qty_change, tx_type, reference_id, user_id, note) VALUES (?, ?, 'receive', ?, ?, ?)");
            if (!$stmt) {
                throw new Exception('DB prepare failed (stock_transactions): ' . $mysqli->error);
            }
            $stmt->bind_param('iiiis', $item, $qty, $receipt_id, $received_by, $note);
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
            <?php while ($c = $categories->fetch_assoc()): ?>
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

<script>
var ITEMS_MAP = <?= json_encode($items_map) ?>;

/* ITEM DETAILS */
function getItemDetails() {
    var id = document.getElementById('item_id').value;
    if (!id) return;

    var catSelect = document.getElementById('category');
    var baseCatId = (ITEMS_MAP[id] && ITEMS_MAP[id].category_id) ? String(ITEMS_MAP[id].category_id) : '';

    // If no category chosen yet and the item has a stored category, default to it
    if (catSelect && !catSelect.value && baseCatId) {
        catSelect.value = baseCatId;
    }

    // If a category is selected but the item has NO stored category,
    // treat this as a new variant (e.g. Legal/A5) with no existing stock.
    if (catSelect && catSelect.value && !baseCatId) {
        document.getElementById('current_stock').value = 0;
        return;
    }

    // If both are set and they don't match, also treat as new variant with 0 stock
    if (catSelect && catSelect.value && baseCatId && catSelect.value !== baseCatId) {
        document.getElementById('current_stock').value = 0;
        return;
    }

    // Otherwise, same category or none selected: fetch real current stock
    fetch('get_item_stock.php?item_id=' + id)
        .then(r => r.json())
        .then(d => {
            document.getElementById('current_stock').value =
                d.success ? d.current_stock : 'Error';
        });
}

/* CATEGORY CHANGE HANDLER */
function onCategoryChange() {
    var id = document.getElementById('item_id').value;
    var catSelect = document.getElementById('category');
    if (!id || !catSelect) return;

    var baseCatId = (ITEMS_MAP[id] && ITEMS_MAP[id].category_id) ? String(ITEMS_MAP[id].category_id) : '';

    if (!catSelect.value) {
        // No category selected: refresh based on item only
        getItemDetails();
        return;
    }

    if (!baseCatId) {
        // Item has no stored category but user picked one: this variant has no stock yet
        document.getElementById('current_stock').value = 0;
        return;
    }

    if (catSelect.value !== baseCatId) {
        // Different category from item's stored category: show 0
        document.getElementById('current_stock').value = 0;
    } else {
        // Same category: show real stock
        getItemDetails();
    }
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
    var catEl = document.getElementById('category');
    if (catEl) catEl.addEventListener('change', onCategoryChange);
    document.getElementById('quantity').addEventListener('input', calculateTotalCost);
    document.getElementById('cost_per_unit').addEventListener('input', calculateTotalCost);
    document.getElementById('calc_btn').addEventListener('click', calculateTotalCost);
    calculateTotalCost();
});
</script>

</body>
</html>
