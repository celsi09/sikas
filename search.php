<?php
require_once 'config/database.php';
require_once 'config/auth.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

$search_keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$search_jenis = isset($_GET['jenis']) ? $_GET['jenis'] : '';
$search_kategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$results = [];
$total_results = 0;

if (!empty($search_keyword) || !empty($search_jenis) || !empty($search_kategori) || !empty($date_from)) {
    // Build query
    $conditions = ["1=1"];
    $params = [];
    
    if (!empty($search_keyword)) {
        $conditions[] = "(t.deskripsi LIKE ? OR k.nama_kategori LIKE ? OR u.username LIKE ?)";
        $search_param = "%$search_keyword%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($search_jenis)) {
        $conditions[] = "t.jenis = ?";
        $params[] = $search_jenis;
    }
    
    if (!empty($search_kategori)) {
        $conditions[] = "t.kategori_id = ?";
        $params[] = $search_kategori;
    }
    
    if (!empty($date_from)) {
        $conditions[] = "t.tanggal >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $conditions[] = "t.tanggal <= ?";
        $params[] = $date_to;
    }
    
    $where_clause = implode(" AND ", $conditions);
    
    $query = "SELECT t.*, k.nama_kategori, u.username 
              FROM transaksi_kas t 
              LEFT JOIN kategori_kas k ON t.kategori_id = k.id
              LEFT JOIN users u ON t.dibuat_oleh = u.id
              WHERE $where_clause
              ORDER BY t.tanggal DESC, t.created_at DESC
              LIMIT 100";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_results = count($results);
}

// Get categories for filter
$query_cat = "SELECT * FROM kategori_kas ORDER BY nama_kategori";
$stmt_cat = $db->prepare($query_cat);
$stmt_cat->execute();
$categories = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pencarian Transaksi - SI-KAS</title>
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
        
        .search-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .search-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .result-item {
            padding: 20px;
            border-left: 4px solid var(--primary-color);
            margin-bottom: 15px;
            background: white;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .result-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .result-item.masuk {
            border-left-color: var(--success-color);
        }
        
        .result-item.keluar {
            border-left-color: var(--danger-color);
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
                    
                    <a class="nav-link" href="laporan.php">
                        <i class="fas fa-chart-bar me-2"></i>
                        Laporan Keuangan
                    </a>
                                        
                    <a class="nav-link active" href="search.php">
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
                <!-- Search Header -->
                <div class="search-header">
                    <h1 class="text-center mb-3">
                        <i class="fas fa-search fa-2x mb-3"></i>
                        <br>Pencarian Transaksi
                    </h1>
                    <p class="text-center mb-0">Cari transaksi berdasarkan kata kunci, jenis, kategori, atau periode</p>
                </div>

                <!-- Search Form -->
                <div class="search-box mb-4">
                    <form method="GET">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-keyboard me-1"></i> Kata Kunci
                                </label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" name="keyword" 
                                           placeholder="Cari deskripsi, kategori, atau nama pengguna..." 
                                           value="<?php echo htmlspecialchars($search_keyword); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-filter me-1"></i> Jenis
                                </label>
                                <select class="form-select" name="jenis">
                                    <option value="">Semua</option>
                                    <option value="masuk" <?php echo $search_jenis == 'masuk' ? 'selected' : ''; ?>>Kas Masuk</option>
                                    <option value="keluar" <?php echo $search_jenis == 'keluar' ? 'selected' : ''; ?>>Kas Keluar</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-tags me-1"></i> Kategori
                                </label>
                                <select class="form-select" name="kategori">
                                    <option value="">Semua</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $search_kategori == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($cat['nama_kategori']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-calendar me-1"></i> Dari Tanggal
                                </label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-calendar me-1"></i> Sampai Tanggal
                                </label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary btn-gradient btn-lg">
                                <i class="fas fa-search me-2"></i> Cari Transaksi
                            </button>
                            <a href="search.php" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-redo me-2"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Search Results -->
                <?php if (!empty($search_keyword) || !empty($search_jenis) || !empty($search_kategori) || !empty($date_from)): ?>
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Hasil Pencarian 
                            <span class="badge bg-primary"><?php echo $total_results; ?> Transaksi</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($results)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-search fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">Tidak ada hasil yang ditemukan</h5>
                                <p class="text-muted">Coba gunakan kata kunci atau filter yang berbeda</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($results as $trans): ?>
                            <div class="result-item <?php echo $trans['jenis']; ?>">
                                <div class="row">
                                    <div class="col-md-1 text-center">
                                        <?php if ($trans['jenis'] == 'masuk'): ?>
                                            <div class="bg-success bg-opacity-10 p-3 rounded-circle d-inline-block">
                                                <i class="fas fa-arrow-up text-success fa-2x"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="bg-danger bg-opacity-10 p-3 rounded-circle d-inline-block">
                                                <i class="fas fa-arrow-down text-danger fa-2x"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-8">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($trans['deskripsi']); ?></h5>
                                        <p class="mb-0 text-muted">
                                            <span class="badge bg-secondary me-1">
                                                <i class="fas fa-tag me-1"></i><?php echo ucfirst($trans['nama_kategori']); ?>
                                            </span>
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo date('d F Y', strtotime($trans['tanggal'])); ?>
                                            •
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo $trans['username']; ?>
                                            •
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('H:i', strtotime($trans['created_at'])); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <h4 class="mb-0 <?php echo $trans['jenis'] == 'masuk' ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $trans['jenis'] == 'masuk' ? '+' : '-'; ?>
                                            Rp <?php echo number_format($trans['jumlah'], 0, ',', '.'); ?>
                                        </h4>
                                        <small class="text-muted">
                                            <?php echo $trans['jenis'] == 'masuk' ? 'Kas Masuk' : 'Kas Keluar'; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if ($total_results >= 100): ?>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Menampilkan 100 hasil pertama. Gunakan filter lebih spesifik untuk hasil yang lebih baik.
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Tips -->
                <div class="card mt-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-lightbulb me-2"></i>
                            Tips Pencarian
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-check-circle text-success me-2"></i>Gunakan kata kunci spesifik</h6>
                                <p class="text-muted">Coba cari nama kegiatan, vendor, atau deskripsi tertentu</p>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-check-circle text-success me-2"></i>Filter berdasarkan periode</h6>
                                <p class="text-muted">Batasi pencarian dengan rentang tanggal tertentu</p>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-check-circle text-success me-2"></i>Kombinasikan filter</h6>
                                <p class="text-muted">Gunakan jenis dan kategori bersama untuk hasil lebih akurat</p>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-check-circle text-success me-2"></i>Maksimal 100 hasil</h6>
                                <p class="text-muted">Sistem akan menampilkan 100 hasil teratas</p>
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