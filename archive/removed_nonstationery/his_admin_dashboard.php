<?php
// Archived: his_admin_dashboard.php — moved to archive/removed_nonstationery on 2025-12-22
// Original file preserved for reference.

session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
check_login();

$uid = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';
$full_name = $_SESSION['full_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
    
    <!--Head Code-->

<?php include("assets/inc/head.php"); ?>
<body>
<?php include("assets/inc/nav.php"); ?>
<?php include("assets/inc/sidebar_admin.php"); ?>

        <!-- Begin page -->
        <div id="wrapper">

            <!-- ========== Left Sidebar Start ========== -->
            <?php include('assets/inc/sidebar_admin.php');?>
            <!-- Left Sidebar End -->

            <!-- ============================================================== -->
            <!-- Start Page Content here -->
            <!-- ============================================================== -->

            <div class="content-page">
                <div class="content">

                    <!-- Start Content-->
                    <div class="container-fluid">
                        
                        <!-- start page title -->
                        <div class="row">
                            <div class="col-12">
                                <div class="page-title-box">
                                    <h4 class="page-title">Directorate of Works Inventory Dashboard</h4>
                                    <p class="mb-0 text-muted">Welcome, <?php echo htmlspecialchars($full_name); ?> — role: <?php echo htmlspecialchars(ucfirst($role)); ?></p>
                                </div>
                            </div>
                        </div>     
                        <!-- end page title --> 

                        <?php
                        // Get inventory statistics
                        $total_items = 0;
                        $res = $mysqli->query("SELECT COUNT(*) AS cnt FROM items");
                        if($res){
                            $r = $res->fetch_assoc();
                            $total_items = $r['cnt'];
                        }

                        $total_stock = 0;
                        $res = $mysqli->query("SELECT COALESCE(SUM(quantity), 0) AS total FROM stock_balance");
                        if($res){
                            $r = $res->fetch_assoc();
                            $total_stock = $r['total'];
                        }

                        $low_stock_count = 0;
                        $res = $mysqli->query("SELECT COUNT(*) AS cnt FROM stock_balance WHERE quantity < 20 AND quantity > 0");
                        if($res){
                            $r = $res->fetch_assoc();
                            $low_stock_count = $r['cnt'];
                        }

                        $out_of_stock = 0;
                        $res = $mysqli->query("SELECT COUNT(*) AS cnt FROM stock_balance WHERE quantity <= 0");
                        if($res){
                            $r = $res->fetch_assoc();
                            $out_of_stock = $r['cnt'];
                        }

                        $total_issued = 0;
                        $res = $mysqli->query("SELECT COALESCE(SUM(quantity), 0) AS total FROM stock_issues");
                        if($res){
                            $r = $res->fetch_assoc();
                            $total_issued = $r['total'];
                        }
                        ?>

                        <!-- Summary Cards -->
                        <div class="row">
                            <!-- Total Items Card -->
                            <div class="col-12 col-sm-6 col-xl-3 mb-3">
                                <div class="widget-rounded-circle card-box">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="avatar-lg rounded-circle bg-soft-primary border-primary border">
                                                <i class="fas fa-warehouse font-22 avatar-title text-primary"></i>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-right">
                                                <h3 class="text-dark mt-1"><span data-plugin="counterup"><?php echo (int)$total_items;?></span></h3>
                                                <p class="text-muted mb-1 text-truncate">Total Items</p>
                                                <small class="text-muted">In inventory</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Total Stock Card -->
                            <div class="col-12 col-sm-6 col-xl-3 mb-3">
                                <div class="widget-rounded-circle card-box">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="avatar-lg rounded-circle bg-soft-success border-success border">
                                                <i class="fas fa-boxes font-22 avatar-title text-success"></i>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-right">
                                                <h3 class="text-dark mt-1"><span data-plugin="counterup"><?php echo (int)$total_stock;?></span></h3>
                                                <p class="text-muted mb-1 text-truncate">Total Stock</p>
                                                <small class="text-muted">Units in stock</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Low Stock Card -->
                            <div class="col-12 col-sm-6 col-xl-3 mb-3">
                                <div class="widget-rounded-circle card-box">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="avatar-lg rounded-circle bg-soft-warning border-warning border">
                                                <i class="fas fa-exclamation-triangle font-22 avatar-title text-warning"></i>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-right">
                                                <h3 class="text-dark mt-1"><span data-plugin="counterup"><?php echo (int)$low_stock_count;?></span></h3>
                                                <p class="text-muted mb-1 text-truncate">Low Stock</p>
                                                <small class="text-muted">Below reorder level</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Out of Stock Card -->
                            <div class="col-12 col-sm-6 col-xl-3 mb-3">
                                <div class="widget-rounded-circle card-box">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="avatar-lg rounded-circle bg-soft-danger border-danger border">
                                                <i class="fas fa-times-circle font-22 avatar-title text-danger"></i>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-right">
                                                <h3 class="text-dark mt-1"><span data-plugin="counterup"><?php echo (int)$out_of_stock;?></span></h3>
                                                <p class="text-muted mb-1 text-truncate">Out of Stock</p>
                                                <small class="text-muted">Empty items</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
<?php
// Archived: his_admin_dashboard.php — moved to archive/removed_nonstationery on 2025-12-22
// Original file preserved for reference.

?>

<?php
// Original content preserved below

