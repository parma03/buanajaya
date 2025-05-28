<?php

namespace Midtrans;

session_start();
include '../config/koneksi.php';

$id_peserta = $_SESSION['id_user'];
require_once dirname(__FILE__) . '/Midtrans.php';

// Set Your server key
Config::$serverKey = 'SB-Mid-server-yZbU_u1NCKEyGDsZs_UVEmzn';

// non-relevant function only used for demo/example purpose
printExampleWarningMessage();

// Uncomment for production environment
// Config::$isProduction = true;
Config::$isProduction = false;
Config::$isSanitized = Config::$is3ds = true;
Config::$is3ds = true;

// Validate order_id parameter
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    die("Error: Order ID is required");
}

$order_id = $_GET['order_id'];

// Ambil detail transaksi dengan error handling
$query_transaksi = "SELECT * FROM tb_pelatihan WHERE id_pelatihan = ?";
$stmt_transaksi = $conn->prepare($query_transaksi);
$stmt_transaksi->bind_param("i", $order_id);
$stmt_transaksi->execute();
$result_transaksi = $stmt_transaksi->get_result();

if ($result_transaksi->num_rows === 0) {
    die("Error: Order not found with ID: " . $order_id);
}

$row_transaksi = $result_transaksi->fetch_assoc();
$id_jenis_pelatihan = $row_transaksi['id_jenis_pelatihan'];
$id_mobil = $row_transaksi['id_mobil'];
$tanggal_bo = $row_transaksi['tanggal_bo'];

// Validate required fields
if (empty($id_jenis_pelatihan)) {
    die("Error: Invalid training type ID");
}

// Ambil detail paket dan harga
$query_paket = "SELECT * FROM tb_jenis_pelatihan WHERE id_jenis_pelatihan = ?";
$stmt_paket = $conn->prepare($query_paket);
$stmt_paket->bind_param("i", $id_jenis_pelatihan);
$stmt_paket->execute();
$result_paket = $stmt_paket->get_result();

if ($result_paket->num_rows === 0) {
    die("Error: Training package not found");
}

$row_paket = $result_paket->fetch_assoc();
$harga = $row_paket['harga'];
$nama_jenis = $row_paket['nama_jenis'];

// Validate price
if (empty($harga) || $harga <= 0) {
    die("Error: Invalid price: " . $harga);
}

// Ambil detail customer
$query_konsumen = "SELECT name_konsumen, nohp FROM tb_konsumen WHERE id_konsumen = ?";
$stmt_konsumen = $conn->prepare($query_konsumen);
$stmt_konsumen->bind_param("i", $id_peserta);
$stmt_konsumen->execute();
$result_konsumen = $stmt_konsumen->get_result();

if ($result_konsumen->num_rows === 0) {
    die("Error: Customer not found");
}

$row_konsumen = $result_konsumen->fetch_assoc();
$name_konsumen = $row_konsumen['name_konsumen'];
$nohp = $row_konsumen['nohp'];

// Ensure we have valid data before proceeding
if (empty($name_konsumen)) {
    $name_konsumen = "Customer";
}
if (empty($nohp)) {
    $nohp = "0000000000";
}
if (empty($nama_jenis)) {
    $nama_jenis = "Training Package";
}

// Required
$transaction_details = array(
    'order_id' => (string) $order_id,
    'gross_amount' => (int) $harga, // no decimal allowed for creditcard
);

// Optional
$item_details = array(
    array(
        'id' => (string) $id_jenis_pelatihan,
        'price' => (int) $harga,
        'quantity' => 1,
        'name' => $nama_jenis
    ),
);

// Optional
$customer_details = array(
    'first_name' => $name_konsumen,
    'last_name' => "",
    'email' => "customer@buanajaya.com",
    'phone' => $nohp,
);

// Fill transaction details
$transaction = array(
    'transaction_details' => $transaction_details,
    'customer_details' => $customer_details,
    'item_details' => $item_details,
);

$snap_token = '';
try {
    $snap_token = Snap::getSnapToken($transaction);
} catch (\Exception $e) {
    echo "Error generating payment token: " . $e->getMessage();
    die();
}

function printExampleWarningMessage()
{
    if (strpos(Config::$serverKey, 'your ') != false) {
        echo "<code>";
        echo "<h4>Please set your server key from sandbox</h4>";
        echo "In file: " . __FILE__;
        echo "<br>";
        echo "<br>";
        echo htmlspecialchars('Config::$serverKey = \'<your server key>\';');
        die();
    }
}

?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Buana Jaya</title>
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.0.0-beta/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
        integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto">
</head>

<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Payment Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Order ID:</strong> #<?php echo $order_id; ?><br>
                            <strong>Package:</strong> <?php echo $nama_jenis; ?><br>
                            <strong>Price:</strong> Rp <?php echo number_format($harga, 0, ',', '.'); ?><br>
                            <strong>Customer:</strong> <?php echo $name_konsumen; ?>
                        </div>
                        <p class="text-success">Pendaftaran Berhasil, Silahkan selesaikan pembayaran anda!</p>
                        <button id="pay-button" class="btn btn-primary btn-block">
                            <i class="fas fa-credit-card"></i> Pilih Metode Pembayaran
                        </button>
                        <div class="mt-3 text-center">
                            <a href="index.php" class="btn btn-secondary">Kembali ke Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://app.sandbox.midtrans.com/snap/snap.js"
        data-client-key="<?php echo Config::$clientKey; ?>"></script>
    <script type="text/javascript">
        document.getElementById('pay-button').onclick = function () {
            // SnapToken acquired from previous step
            snap.pay('<?php echo $snap_token ?>', {
                onSuccess: function (result) {
                    // Payment success
                    alert("Payment success!");
                    console.log(result);
                    window.location.href = 'index.php';
                },
                onPending: function (result) {
                    // Payment pending
                    alert("Waiting for payment!");
                    console.log(result);
                },
                onError: function (result) {
                    // Payment error
                    alert("Payment failed!");
                    console.log(result);
                }
            });
        };
    </script>
</body>

</html>