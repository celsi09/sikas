<?php
require_once 'config/database.php';
require_once 'config/auth.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Hitung total kas masuk
$query_masuk = "SELECT COALESCE(SUM(jumlah), 0) as total_masuk FROM transaksi_kas WHERE jenis = 'masuk'";
$stmt_masuk = $db->prepare($query_masuk);
$stmt_masuk->execute();
$total_masuk = $stmt_masuk->fetch(PDO::FETCH_ASSOC)['total_masuk'];

// Hitung total kas keluar
$query_keluar = "SELECT COALESCE(SUM(jumlah), 0) as total_keluar FROM transaksi_kas WHERE jenis = 'keluar'";
$stmt_keluar = $db->prepare($query_keluar);
$stmt_keluar->execute();
$total_keluar = $stmt_keluar->fetch(PDO::FETCH_ASSOC)['total_keluar'];

$saldo_kas = $total_masuk - $total_keluar;

// Transaksi terbaru
$query_recent = "SELECT t.*, k.nama_kategori, u.username 
                FROM transaksi_kas t 
                LEFT JOIN kategori_kas k ON t.kategori_id = k.id
                LEFT JOIN users u ON t.dibuat_oleh = u.id
                ORDER BY t.created_at DESC LIMIT 5";
$stmt_recent = $db->prepare($query_recent);
$stmt_recent->execute();
$recent_transactions = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);

// Data untuk chart - transaksi 6 bulan terakhir
$query_chart = "SELECT 
    DATE_FORMAT(tanggal, '%Y-%m') as bulan,
    SUM(CASE WHEN jenis = 'masuk' THEN jumlah ELSE 0 END) as masuk,
    SUM(CASE WHEN jenis = 'keluar' THEN jumlah ELSE 0 END) as keluar
    FROM transaksi_kas 
    WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(tanggal, '%Y-%m')
    ORDER BY bulan";
$stmt_chart = $db->prepare($query_chart);
$stmt_chart->execute();
$chart_data = $stmt_chart->fetchAll(PDO::FETCH_ASSOC);

// Data untuk chart kategori
$query_kategori = "SELECT k.nama_kategori, SUM(t.jumlah) as total
    FROM transaksi_kas t
    JOIN kategori_kas k ON t.kategori_id = k.id
    WHERE t.jenis = 'keluar'
    GROUP BY k.nama_kategori";
$stmt_kategori = $db->prepare($query_kategori);
$stmt_kategori->execute();
$kategori_data = $stmt_kategori->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SI-KAS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
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
        
        .navbar-brand {
            font-weight: bold;
        }
        
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
            transition: transform 0.2s;
            margin-bottom: 20px;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 30px rgba(0,0,0,0.15);
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
        
        .recent-transactions {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .footer {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 30px 0;
            margin-top: auto;
            width: 100%;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .stat-icon {
            font-size: 3rem;
            opacity: 0.3;
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .transaction-badge {
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
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
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
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
            <div class="sidebar">
                <nav class="nav flex-column py-3">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>
                        Dashboard
                    </a>
                    
                    <?php if (isBendahara()): ?>
                    <a class="nav-link" href="kelola_kas.php">
                        <i class="fas fa-coins me-2"></i>
                        Kelola Kas
                    </a>
                    <a class="nav-link" href="kelola_kategori.php">
                        <i class="fas fa-tags me-2"></i>
                        Kategori
                    </a>
                    <?php endif; ?>
                    
                    <a class="nav-link" href="laporan.php">
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
                        <h2 class="fw-bold">Dashboard</h2>
                        <p class="text-muted">Selamat datang, <?php echo $_SESSION['username']; ?>! 👋</p>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card stats-card income position-relative">
                            <div class="card-body text-center">
                                <i class="fas fa-arrow-circle-up stat-icon"></i>
                                <i class="fas fa-arrow-up fa-2x mb-3"></i>
                                <h3>Rp <?php echo number_format($total_masuk, 0, ',', '.'); ?></h3>
                                <p class="mb-0">Total Kas Masuk</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card expense position-relative">
                            <div class="card-body text-center">
                                <i class="fas fa-arrow-circle-down stat-icon"></i>
                                <i class="fas fa-arrow-down fa-2x mb-3"></i>
                                <h3>Rp <?php echo number_format($total_keluar, 0, ',', '.'); ?></h3>
                                <p class="mb-0">Total Kas Keluar</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card balance position-relative">
                            <div class="card-body text-center">
                                <i class="fas fa-wallet stat-icon"></i>
                                <i class="fas fa-wallet fa-2x mb-3"></i>
                                <h3>Rp <?php echo number_format($saldo_kas, 0, ',', '.'); ?></h3>
                                <p class="mb-0">Saldo Kas</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-line me-2"></i>
                                    Grafik Kas 6 Bulan Terakhir
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="cashFlowChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-pie me-2"></i>
                                    Pengeluaran per Kategori
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="categoryChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-history me-2"></i>
                                    Transaksi Terbaru
                                </h5>
                                <?php if (isBendahara()): ?>
                                <a href="kelola_kas.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye me-1"></i>Lihat Semua
                                </a>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_transactions)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Belum ada transaksi</p>
                                    </div>
                                <?php else: ?>
                                    <div class="recent-transactions">
                                        <?php foreach ($recent_transactions as $trans): ?>
                                        <div class="d-flex justify-content-between align-items-center border-bottom py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <?php if ($trans['jenis'] == 'masuk'): ?>
                                                        <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                                                            <i class="fas fa-arrow-up text-success fa-lg"></i>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="bg-danger bg-opacity-10 p-3 rounded-circle">
                                                            <i class="fas fa-arrow-down text-danger fa-lg"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($trans['deskripsi']); ?></h6>
                                                    <small class="text-muted">
                                                        <span class="badge bg-secondary"><?php echo ucfirst($trans['nama_kategori']); ?></span>
                                                        • <?php echo date('d/m/Y', strtotime($trans['tanggal'])); ?> 
                                                        • <?php echo $trans['username']; ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <h6 class="mb-0 <?php echo $trans['jenis'] == 'masuk' ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo $trans['jenis'] == 'masuk' ? '+' : '-'; ?>
                                                    Rp <?php echo number_format($trans['jumlah'], 0, ',', '.'); ?>
                                                </h6>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
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
                    <div class="mt-3">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-instagram fa-lg"></i></a>
                    </div>
                </div>
            </div>
            <hr class="bg-white my-3">
            <div class="text-center">
                <small>&copy; <?php echo date('Y'); ?> SI-KAS. All rights reserved. Made with <i class="fas fa-heart text-danger"></i></small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Cash Flow Chart
        const cashFlowCtx = document.getElementById('cashFlowChart').getContext('2d');
        const cashFlowChart = new Chart(cashFlowCtx, {
            type: 'line',
            data: {
                labels: [<?php echo implode(',', array_map(function($d) { return "'".$d['bulan']."'"; }, $chart_data)); ?>],
                datasets: [{
                    label: 'Kas Masuk',
                    data: [<?php echo implode(',', array_map(function($d) { return $d['masuk']; }, $chart_data)); ?>],
                    borderColor: '#38ef7d',
                    backgroundColor: 'rgba(56, 239, 125, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Kas Keluar',
                    data: [<?php echo implode(',', array_map(function($d) { return $d['keluar']; }, $chart_data)); ?>],
                    borderColor: '#fc4a1a',
                    backgroundColor: 'rgba(252, 74, 26, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php echo implode(',', array_map(function($d) { return "'".ucfirst($d['nama_kategori'])."'"; }, $kategori_data)); ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_map(function($d) { return $d['total']; }, $kategori_data)); ?>],
                    backgroundColor: [
                        '#667eea',
                        '#fc4a1a',
                        '#38ef7d',
                        '#4facfe',
                        '#f7b733'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
    </script>
</body>
</html>
