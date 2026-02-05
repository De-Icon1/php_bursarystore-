<?php
// layout.php – unified layout wrapper

if (!isset($page_title)) { $page_title = "OOU Bursary Store"; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>

    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/icons.min.css" rel="stylesheet">
    <link href="assets/css/app.min.css" rel="stylesheet">

    <!-- CUSTOM UI STYLES -->
    <style>

/* -------------------- GENERAL LAYOUT -------------------- */
body {
    background: #f5f8fc;
    font-family: "Segoe UI", sans-serif;
}

.main-wrapper {
    display: flex;
    min-height: 100vh;
}

/* -------------------- SIDEBAR -------------------- */
.sidebar {
    width: 260px;
    background: #1b2a47;
    color: #fff;
    position: fixed;
    top: 0;
    bottom: 0;
    padding: 20px 0;
    overflow-y: auto;
}

.sidebar h4 {
    text-align: center;
    margin-bottom: 20px;
    font-size: 18px;
}

.sidebar a {
    color: #d6d8de;
    display: block;
    padding: 12px 20px;
    text-decoration: none;
    transition: 0.2s;
    font-size: 15px;
}

.sidebar a:hover,
.sidebar a.active {
    background: #0d6efd;
    color: #fff;
}

/* -------------------- TOP NAV -------------------- */
.topbar {
    height: 68px;
    background: #fff;
    border-bottom: 1px solid #e3e6ef;
    padding: 0 25px;
    position: fixed;
    left: 260px;
    right: 0;
    top: 0;

    display: flex;
    align-items: center;
    justify-content: space-between;
    z-index: 100;
}

/* -------------------- CONTENT WRAPPER -------------------- */
.page-content {
    margin-left: 260px;
    padding: 90px 35px 40px;
}

/* -------------------- SEARCH BAR -------------------- */
.search-bar {
    display: flex;
    align-items: center;
    max-width: 400px;
    margin-bottom: 20px;
}

.search-bar input {
    flex: 1;
    border-radius: 30px;
    padding: 10px 18px;
    border: 1px solid #ccd2da;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    transition: 0.2s;
}

.search-bar input:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 4px rgba(13,110,253,0.18);
}

/* -------------------- PAGE FORMS -------------------- */
.form-control {
    border-radius: 10px !important;
    padding: 10px 15px;
    border: 1px solid #cdd3dd;
}

.form-control:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 3px rgba(13,110,253,.2);
}

.btn-primary,
.btn-success,
.btn-warning,
.btn-danger {
    border-radius: 10px !important;
    padding: 10px 18px;
    font-weight: 500;
}

/* -------------------- TABLE STYLING -------------------- */
.table-wrapper {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.05);
    margin-top: 20px;
}

.table th {
    font-size: 14px;
    text-transform: uppercase;
    color: #4b5563;
    border-bottom-width: 1px !important;
}

.table td {
    font-size: 15px;
}

/* -------------------- PAGINATION -------------------- */
.pagination {
    display: flex;
    justify-content: center;
    margin-top: 25px;
}

.pagination .page-link {
    border-radius: 12px;
    border: none;
    padding: 10px 16px;
    margin: 0 4px;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.07);
}

.pagination .active .page-link {
    background: #0d6efd;
    color: #fff;
}
    </style>

</head>

<body>

<div class="main-wrapper">

    <!-- SIDEBAR -->
    <div class="sidebar">
        <h4>Bursary Store Inventory</h4>
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="store_items.php">Store Items</a>
        <a href="stationery_store.php">Stationery Store</a>
        <a href="stock_management.php">Stock Management</a>
        <a href="inventory_report.php">Reports</a>
        <a href="logout.php" style="color: #ffdddd;">Logout</a>
    </div>

    <!-- TOP BAR -->
    <div class="topbar">
        <div class="search-bar">
            <input type="text" placeholder="Search…">
        </div>

        <div class="profile">
            <strong><?= $_SESSION['username'] ?? '' ?></strong>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="page-content">
        <?php include($content); ?>
    </div>

</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
