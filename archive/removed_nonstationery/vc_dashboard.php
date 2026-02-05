<?php
  session_start();
  include('assets/inc/config.php');
  include('assets/inc/checklogins.php');
  
  if(!check_login()){
    header("Location: index.php");
    exit();
  }
  
  if(!authorize()){
    header("Location: index.php");
    exit();
  }
  
  $aid=$_SESSION['doc_id'];
  $doc_number = $_SESSION['doc_number'];
?>
<!DOCTYPE html>
<html lang="en">
    
    <!--Head Code-->

<?php include("assets/inc/head.php"); ?>
<body>

        <!-- Begin page -->
        <div id="wrapper">

            <!-- Topbar Start -->
            <?php include("assets/inc/nav.php"); ?>
            <!-- end Topbar -->

            <!-- ========== Left Sidebar Start ========== -->
            <?php include('assets/inc/sidebar_vc.php');?>
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
                                    
                                    <h4 class="page-title">Inventory Management System - Vice Chancellor Dashboard</h4>
                                </div>
                            </div>
                        </div>     
                        <!-- end page title --> 
                        
                        <!-- Reports Section -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card-box">
                                    <h4 class="header-title mb-3"><i class="fas fa-chart-bar"></i> Inventory Reports</h4>
                                    <p class="text-muted">Access comprehensive reports for inventory management and vehicle operations</p>
                                    
                                    <div class="row">
                                        <div class="col-12 col-md-6 col-lg-4 mb-3">
                                            <div class="card bg-primary text-white">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 mr-3">
                                                            <i class="fas fa-boxes font-32"></i>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h5 class="text-white mb-1">Stock Balance Report</h5>
                                                            <p class="mb-2" style="opacity: 0.9;">View current stock levels, low stock alerts, and inventory valuation</p>
                                                            <a href="inventory_report.php" class="btn btn-light btn-sm">
                                                                <i class="fas fa-eye"></i> View Report
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-6 col-lg-4 mb-3">
                                            <div class="card bg-success text-white">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 mr-3">
                                                            <i class="fas fa-gas-pump font-32"></i>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h5 class="text-white mb-1">Diesel Usage Report</h5>
                                                            <p class="mb-2" style="opacity: 0.9;">Track diesel consumption by source type and vehicle</p>
                                                            <a href="diesel_report.php" class="btn btn-light btn-sm">
                                                                <i class="fas fa-eye"></i> View Report
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-6 col-lg-4 mb-3">
                                            <div class="card bg-info text-white">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 mr-3">
                                                            <i class="fas fa-history font-32"></i>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h5 class="text-white mb-1">Inventory History</h5>
                                                            <p class="mb-2" style="opacity: 0.9;">Review stock movements, receipts, and issues</p>
                                                            <a href="inventory_history.php" class="btn btn-light btn-sm">
                                                                <i class="fas fa-eye"></i> View Report
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-6 col-lg-4 mb-3">
                                            <div class="card bg-warning text-white">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 mr-3">
                                                            <i class="fas fa-exclamation-triangle font-32"></i>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h5 class="text-white mb-1">Low Stock Alerts</h5>
                                                            <p class="mb-2" style="opacity: 0.9;">Monitor items below reorder level</p>
                                                            <a href="low_stock_alerts.php" class="btn btn-light btn-sm">
                                                                <i class="fas fa-eye"></i> View Alerts
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-6 col-lg-4 mb-3">
                                            <div class="card bg-secondary text-white">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 mr-3">
                                                            <i class="fas fa-chart-line font-32"></i>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h5 class="text-white mb-1">Usage Charts</h5>
                                                            <p class="mb-2" style="opacity: 0.9;">Visual analysis of inventory trends</p>
                                                            <a href="inventory_charts.php" class="btn btn-light btn-sm">
                                                                <i class="fas fa-eye"></i> View Charts
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-6 col-lg-4 mb-3">
                                            <div class="card bg-dark text-white">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 mr-3">
                                                            <i class="fas fa-car font-32"></i>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h5 class="text-white mb-1">Vehicle History</h5>
                                                            <p class="mb-2" style="opacity: 0.9;">Track vehicle service and maintenance records</p>
                                                            <a href="vehicle_history.php" class="btn btn-light btn-sm">
                                                                <i class="fas fa-eye"></i> View History
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- End Reports Section -->
                        
                    </div> <!-- container -->

                </div> <!-- content -->

                <!-- Footer Start -->
                <?php include('assets/inc/footer.php');?>
                <!-- end Footer -->

            </div>

            <!-- ============================================================== -->
            <!-- End Page content -->
            <!-- ============================================================== -->


        </div>
        <!-- END wrapper -->

        <!-- Right Sidebar -->
        <div class="right-bar">
            <div class="rightbar-title">
                <a href="javascript:void(0);" class="right-bar-toggle float-right">
                    <i class="dripicons-cross noti-icon"></i>
                </a>
                <h5 class="m-0 text-white">Settings</h5>
            </div>
            <div class="slimscroll-menu">
                <!-- User box -->
                <div class="user-box">
                    <div class="user-img">
                        <img src="assets/images/users/user-1.jpg" alt="user-img" title="Mat Helme" class="rounded-circle img-fluid">
                        <a href="javascript:void(0);" class="user-edit"><i class="mdi mdi-pencil"></i></a>
                    </div>
            
                    <h5><a href="javascript: void(0);">Geneva Kennedy</a> </h5>
                    <p class="text-muted mb-0"><small>Admin Head</small></p>
                </div>

                <!-- Settings -->
                <hr class="mt-0" />
                <h5 class="pl-3">Basic Settings</h5>
                <hr class="mb-0" />

                <div class="p-3">
                    <div class="checkbox checkbox-primary mb-2">
                        <input id="Rcheckbox1" type="checkbox" checked>
                        <label for="Rcheckbox1">
                            Notifications
                        </label>
                    </div>
                    <div class="checkbox checkbox-primary mb-2">
                        <input id="Rcheckbox2" type="checkbox" checked>
                        <label for="Rcheckbox2">
                            API Access
                        </label>
                    </div>
                    <div class="checkbox checkbox-primary mb-2">
                        <input id="Rcheckbox3" type="checkbox">
                        <label for="Rcheckbox3">
                            Auto Updates
                        </label>
                    </div>
                    <div class="checkbox checkbox-primary mb-2">
                        <input id="Rcheckbox4" type="checkbox" checked>
                        <label for="Rcheckbox4">
                            Online Status
                        </label>
                    </div>
                    <div class="checkbox checkbox-primary mb-0">
                        <input id="Rcheckbox5" type="checkbox" checked>
                        <label for="Rcheckbox5">
                            Auto Payout
                        </label>
                    </div>
                </div>

                <!-- Timeline -->
                <hr class="mt-0" />
                <h5 class="px-3">Messages <span class="float-right badge badge-pill badge-danger">25</span></h5>
                <hr class="mb-0" />
                <div class="p-3">
                    <div class="inbox-widget">
                        <div class="inbox-item">
                            <div class="inbox-item-img"><img src="assets/images/users/user-2.jpg" class="rounded-circle" alt=""></div>
                            <p class="inbox-item-author"><a href="javascript: void(0);" class="text-dark">Tomaslau</a></p>
                            <p class="inbox-item-text">I've finished it! See you so...</p>
                        </div>
                        <div class="inbox-item">
                            <div class="inbox-item-img"><img src="assets/images/users/user-3.jpg" class="rounded-circle" alt=""></div>
                            <p class="inbox-item-author"><a href="javascript: void(0);" class="text-dark">Stillnotdavid</a></p>
                            <p class="inbox-item-text">This theme is awesome!</p>
                        </div>
                        <div class="inbox-item">
                            <div class="inbox-item-img"><img src="assets/images/users/user-4.jpg" class="rounded-circle" alt=""></div>
                            <p class="inbox-item-author"><a href="javascript: void(0);" class="text-dark">Kurafire</a></p>
                            <p class="inbox-item-text">Nice to meet you</p>
                        </div>

                        <div class="inbox-item">
                            <div class="inbox-item-img"><img src="assets/images/users/user-5.jpg" class="rounded-circle" alt=""></div>
                            <p class="inbox-item-author"><a href="javascript: void(0);" class="text-dark">Shahedk</a></p>
                            <p class="inbox-item-text">Hey! there I'm available...</p>
                        </div>
                        <div class="inbox-item">
                            <div class="inbox-item-img"><img src="assets/images/users/user-6.jpg" class="rounded-circle" alt=""></div>
                            <p class="inbox-item-author"><a href="javascript: void(0);" class="text-dark">Adhamdannaway</a></p>
                            <p class="inbox-item-text">This theme is awesome!</p>
                        </div>
                    </div> <!-- end inbox-widget -->
                </div> <!-- end .p-3-->

            </div> <!-- end slimscroll-menu-->
        </div>
        <!-- /Right-bar -->

        <!-- Right bar overlay-->
        <div class="rightbar-overlay"></div>

        <!-- Vendor js -->
        <script src="assets/js/vendor.min.js"></script>

        <!-- Plugins js-->
        <script src="assets/libs/flatpickr/flatpickr.min.js"></script>
        <script src="assets/libs/jquery-knob/jquery.knob.min.js"></script>
        <script src="assets/libs/jquery-sparkline/jquery.sparkline.min.js"></script>
        <script src="assets/libs/flot-charts/jquery.flot.js"></script>
        <script src="assets/libs/flot-charts/jquery.flot.time.js"></script>
        <script src="assets/libs/flot-charts/jquery.flot.tooltip.min.js"></script>
        <script src="assets/libs/flot-charts/jquery.flot.selection.js"></script>
        <script src="assets/libs/flot-charts/jquery.flot.crosshair.js"></script>

        <!-- Dashboar 1 init js-->
        <script src="assets/js/pages/dashboard-1.init.js"></script>

        <!-- App js-->
        <script src="assets/js/app.min.js"></script>
        
    </body>

</html>
<?php
// Archived: vc_dashboard.php — moved to archive/removed_nonstationery on 2025-12-22
// Original preserved for reference.

?>

<?php
// (original content archived separately)
