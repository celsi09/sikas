<?php
require_once 'config/database.php';
require_once 'config/auth.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Filter parameters
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$kategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';

// Build query conditions
$conditions = [];
$params = [];

$conditions[] = "MONTH(tanggal) = ?";
$params[] = $bulan;
$conditions[] = "YEAR(tanggal) = ?";
$params[] = $tahun;

if (!empty($kategori)) {
    $conditions[] = "kategori_id = ?";
    $params[] = $kategori;
}

$where_clause = implode(' AND ', $conditions);

// Get transactions
$query = "SELECT t.*, k.nama_kategori, u.username 
          FROM transaksi_kas t 
          LEFT JOIN kategori_kas k ON t.kategori_id = k.id
          LEFT JOIN users u ON t.dibuat_oleh = u.id
          WHERE $where_clause
          ORDER BY t.tanggal DESC, t.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_masuk = 0;
$total_keluar = 0;
foreach ($transactions as $trans) {
    if ($trans['jenis'] == 'masuk') {
        $total_masuk += $trans['jumlah'];
    } else {
        $total_keluar += $trans['jumlah'];
    }
}
$saldo = $total_masuk - $total_keluar;

// Get categories for filter
$query_cat = "SELECT * FROM kategori_kas ORDER BY nama_kategori";
$stmt_cat = $db->prepare($query_cat);
$stmt_cat->execute();
$categories = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

// Export to CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan_kas_' . $bulan . '_' . $tahun . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // CSV Headers
    fputcsv($output, ['Tanggal', 'Jenis', 'Kategori', 'Deskripsi', 'Jumlah', 'Dibuat Oleh']);
    
    // CSV Data
    foreach ($transactions as $trans) {
        fputcsv($output, [
            date('d/m/Y', strtotime($trans['tanggal'])),
            ucfirst($trans['jenis']),
            ucfirst($trans['nama_kategori']),
            $trans['deskripsi'],
            $trans['jumlah'],
            $trans['username']
        ]);
    }
    
    fclose($output);
    exit();
}

$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan - SI-KAS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #38ef7d;
            --danger-color: #fc4a1a;
            --info-color: #4facfe;
        }
        
        body {
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .navbar-brand { font-weight: bold; }
        
        .page-wrapper {
            display: flex;
            flex: 1;
            flex-direction: column;
        }
        
        .content-container {
            display: flex;
            flex: 1;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            width: 250px;
            flex-shrink: 0;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 15px 20px;
            margin-bottom: 5px;
            border-radius: 10px;
            margin-left: 10px;
            margin-right: 10px;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(5px);
        }
        
        .main-content {
            flex: 1;
            padding: 30px;
            overflow-x: hidden;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .stats-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }
        
        .stats-card.income {
            background: linear-gradient(135deg, #11998e 0%, var(--success-color) 100%);
        }
        
        .stats-card.expense {
            background: linear-gradient(135deg, var(--danger-color) 0%, #f7b733 100%);
        }
        
        .stats-card.balance {
            background: linear-gradient(135deg, var(--info-color) 0%, #00f2fe 100%);
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, var(--info-color) 0%, #00f2fe 100%);
            border: none;
            color: white;
        }
        
        .btn-gradient:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.3);
            color: white;
        }
        
        .footer {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 30px 0;
            margin-top: auto;
            width: 100%;
        }

        @media (max-width: 768px) {
            .content-container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
            }
            .main-content {
                padding: 15px;
            }
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            .sidebar {
                display: none !important;
            }
            .main-content {
                padding: 0;
            }
            .content-container {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark no-print" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-wallet me-2"></i>
                SI-KAS
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-2"></i>
                        <?php echo $_SESSION['username']; ?> 
                        <span class="badge bg-light text-dark ms-1"><?php echo ucfirst($_SESSION['role']); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="page-wrapper">
        <div class="content-container">
            <!-- Sidebar -->
            <div class="sidebar no-print">
                <nav class="nav flex-column py-3">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>
                        Dashboard
                    </a>
                    
                    <?php if (isBendahara()): ?>
                    <a class="nav-link" href="kelola_kas.php">
                        <i class="fas fa-coins me-2"></i>
                        Kelola Kas
                    </a>
                    <?php endif; ?>
                    
                    <a class="nav-link active" href="laporan.php">
                        <i class="fas fa-chart-bar me-2"></i>
                        Laporan Keuangan
                    </a>
                                        
                    <a class="nav-link" href="search.php">
                        <i class="fas fa-search me-2"></i>
                        Pencarian
                    </a>
                    
                    <?php if (isAdmin()): ?>
                    <a class="nav-link" href="kelola_pengguna.php">
                        <i class="fas fa-users me-2"></i>
                        Kelola Pengguna
                    </a>
                    <?php endif; ?>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <div class="row mb-4">
                    <div class="col-12">
                        <h2 class="fw-bold">Laporan Keuangan</h2>
                        <p class="text-muted">Laporan kas periode <?php echo $nama_bulan[$bulan] . ' ' . $tahun; ?></p>
                    </div>
                </div>

                <!-- Filter -->
                <div class="card mb-4 no-print">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-filter me-2"></i>
                            Filter Laporan
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row align-items-end">
                            <div class="col-md-3">
                                <label for="bulan" class="form-label">Bulan</label>
                                <select class="form-select" name="bulan">
                                    <?php foreach ($nama_bulan as $key => $nama): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $bulan == $key ? 'selected' : ''; ?>>
                                        <?php echo $nama; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="tahun" class="form-label">Tahun</label>
                                <select class="form-select" name="tahun">
                                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $tahun == $y ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="kategori" class="form-label">Kategori</label>
                                <select class="form-select" name="kategori">
                                    <option value="">Semua Kategori</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $kategori == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($cat['nama_kategori']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary btn-gradient me-2">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-success me-2">
                                    <i class="fas fa-file-csv"></i> CSV
                                </a>
                                <a href="export_pdf.php?<?php echo http_build_query($_GET); ?>" class="btn btn-danger me-2" target="_blank">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </a>
                                <button type="button" class="btn btn-info" onclick="window.print()">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card stats-card income">
                            <div class="card-body text-center">
                                <i class="fas fa-arrow-up fa-2x mb-3"></i>
                                <h3>Rp <?php echo number_format($total_masuk, 0, ',', '.'); ?></h3>
                                <p class="mb-0">Total Kas Masuk</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card expense">
                            <div class="card-body text-center">
                                <i class="fas fa-arrow-down fa-2x mb-3"></i>
                                <h3>Rp <?php echo number_format($total_keluar, 0, ',', '.'); ?></h3>
                                <p class="mb-0">Total Kas Keluar</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card balance">
                            <div class="card-body text-center">
                                <i class="fas fa-wallet fa-2x mb-3"></i>
                                <h3>Rp <?php echo number_format($saldo, 0, ',', '.'); ?></h3>
                                <p class="mb-0">Saldo Periode</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transactions Table -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Detail Transaksi - <?php echo $nama_bulan[$bulan] . ' ' . $tahun; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($transactions)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Tidak ada transaksi pada periode ini</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="5%">No</th>
                                            <th width="12%">Tanggal</th>
                                            <th width="10%">Jenis</th>
                                            <th width="12%">Kategori</th>
                                            <th width="33%">Deskripsi</th>
                                            <th width="15%">Jumlah</th>
                                            <th width="13%" class="no-print">Dibuat Oleh</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1; foreach ($transactions as $trans): ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($trans['tanggal'])); ?></td>
                                            <td>
                                                <?php if ($trans['jenis'] == 'masuk'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-arrow-up"></i> Masuk
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-arrow-down"></i> Keluar
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo ucfirst($trans['nama_kategori']); ?></td>
                                            <td><?php echo htmlspecialchars($trans['deskripsi']); ?></td>
                                            <td class="fw-bold <?php echo $trans['jenis'] == 'masuk' ? 'text-success' : 'text-danger'; ?>">
                                                Rp <?php echo number_format($trans['jumlah'], 0, ',', '.'); ?>
                                            </td>
                                            <td class="no-print"><?php echo $trans['username']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-secondary">
                                        <tr>
                                            <th colspan="5" class="text-end">TOTAL</th>
                                            <th>
                                                <div class="text-success">+ Rp <?php echo number_format($total_masuk, 0, ',', '.'); ?></div>
                                                <div class="text-danger">- Rp <?php echo number_format($total_keluar, 0, ',', '.'); ?></div>
                                                <hr class="my-1">
                                                <div class="fw-bold <?php echo $saldo >= 0 ? 'text-primary' : 'text-danger'; ?>">
                                                    Rp <?php echo number_format($saldo, 0, ',', '.'); ?>
                                                </div>
                                            </th>
                                            <th class="no-print"></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer no-print">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5><i class="fas fa-wallet me-2"></i>SI-KAS</h5>
                    <p class="mb-0">Sistem Informasi Kas - Mengelola keuangan dengan mudah dan efisien</p>
                </div>
                <div class="col-md-4 mb-3">
                    <h6>Menu Cepat</h6>
                    <ul class="list-unstyled">
                        <li><a href="dashboard.php" class="text-white text-decoration-none"><i class="fas fa-angle-right me-1"></i> Dashboard</a></li>
                        <li><a href="laporan.php" class="text-white text-decoration-none"><i class="fas fa-angle-right me-1"></i> Laporan</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-3">
                    <h6>Kontak</h6>
                    <p class="mb-1"><i class="fas fa-envelope me-2"></i> info@sikas.com</p>
                    <p class="mb-1"><i class="fas fa-phone me-2"></i> (0123) 456-789</p>
                </div>
            </div>
            <hr class="bg-white my-3">
            <div class="text-center">
                <small>&copy; <?php echo date('Y'); ?> SI-KAS. All rights reserved.</small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>