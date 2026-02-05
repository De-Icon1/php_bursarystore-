<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
check_login();

// prepare last 12 months labels
$labels = [];
$issued_data = [];
$diesel_data = [];

for($i = 11; $i >= 0; $i--){
    $ym = date('Y-m', strtotime("-{$i} months"));
    $labels[] = $ym;

    // issued
    $stmt = $mysqli->prepare("SELECT COALESCE(SUM(quantity),0) FROM stock_issues WHERE DATE_FORMAT(issued_at, '%Y-%m') = ?");
    $stmt->bind_param("s", $ym);
    $stmt->execute();
    $stmt->bind_result($issued_total);
    $stmt->fetch();
    $stmt->close();
    $issued_data[] = (float)$issued_total;

    // diesel (skip if diesel_log table missing)
    $has_diesel = $mysqli->query("SHOW TABLES LIKE 'diesel_log'")->num_rows;
    if($has_diesel){
      $stmt = $mysqli->prepare("SELECT COALESCE(SUM(quantity),0) FROM diesel_log WHERE DATE_FORMAT(logged_at, '%Y-%m') = ?");
      $stmt->bind_param("s", $ym);
      $stmt->execute();
      $stmt->bind_result($diesel_total);
      $stmt->fetch();
      $stmt->close();
      $diesel_data[] = (float)$diesel_total;
    } else {
      $diesel_data[] = 0.0;
    }
}
?>
<?php include("assets/inc/head.php"); ?>
<body><?php include("assets/inc/nav.php"); ?>
<div class="container mt-4">
<?php include("assets/inc/sidebar_admin.php"); ?>
<div class="content-page">
<div class="content container">
  <h3>Usage Charts</h3>

  <div class="row">
    <div class="col-12 col-lg-6 mb-3">
      <div class="card-box">
        <h5>Items Issued (last 12 months)</h5>
        <canvas id="issuedChart" height="200"></canvas>
      </div>
    </div>
    <!-- Diesel chart removed when running stationery-only mode -->
  </div>
</div>
</div>

<?php include("assets/inc/footer.php"); ?>

</body>
</html>
<script>
const labels = <?= json_encode($labels) ?>;
const issued = <?= json_encode($issued_data) ?>;
const diesel = <?= json_encode($diesel_data) ?>;

new Chart(document.getElementById('issuedChart'), {
  type: 'line',
  data: { labels: labels, datasets: [{ label: 'Issued', data: issued, fill:false, tension:0.2 }] }
});
// Diesel chart intentionally omitted in stationery-only mode
</script>
</body>
</html>