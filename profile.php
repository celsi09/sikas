<?php
require_once 'config/database.php';
require_once 'config/auth.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// Get user data
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user statistics
$stats_query = "SELECT 
    COUNT(*) as total_transaksi,
    SUM(CASE WHEN jenis = 'masuk' THEN 1 ELSE 0 END) as transaksi_masuk,
    SUM(CASE WHEN jenis = 'keluar' THEN 1 ELSE 0 END) as transaksi_keluar
    FROM transaksi_kas 
    WHERE dibuat_oleh = ?";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$_SESSION['user_id']]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get login history
$login_query = "SELECT * FROM log_login WHERE user_id = ? ORDER BY waktu_login DESC LIMIT 10";
$login_stmt = $db->prepare($login_query);
$login_stmt->execute([$_SESSION['user_id']]);
$login_history = $login_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $email = trim($_POST['email']);
                
                $query = "UPDATE users SET email = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$email, $_SESSION['user_id']])) {
                    $message = 'Profile berhasil diupdate!';
                    $message_type = 'success';
                    // Refresh user data
                    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $message = 'Gagal mengupdate profile!';
                    $message_type = 'danger';
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                if ($current_password !== $user['password']) {
                    $message = 'Password saat ini salah!';
                    $message_type = 'danger';
                } elseif ($new_password !== $confirm_password) {
                    $message = 'Konfirmasi password tidak cocok!';
                    $message_type = 'danger';
                } elseif (strlen($new_password) < 6) {
                    $message = 'Password minimal 6 karakter!';
                    $message_type = 'danger';
                } else {
                    $query = "UPDATE users SET password = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$new_password, $_SESSION['user_id']])) {
                        $message = 'Password berhasil diubah!';
                        $message_type = 'success';
                    } else {
                        $message = 'Gagal mengubah password!';
                        $message_type = 'danger';
                    }
                }
                break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - SI-KAS</title>
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
        
        .profile-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--primary-color);
            margin: 0 auto 20px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--info-color) 0%, #00f2fe 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .login-history-item {
            padding: 15px;
            border-left: 3px solid;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .login-history-item.berhasil {
            border-left-color: var(--success-color);
        }
        
        .login-history-item.gagal {
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
                    
                    <a class="nav-link" href="kelola_kategori.php">
                        <i class="fas fa-tags me-2"></i>
                        Kategori
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
                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Profile Card -->
                <div class="card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <h2 class="text-center mb-2"><?php echo htmlspecialchars($user['username']); ?></h2>
                        <p class="text-center mb-0">
                            <span class="badge bg-light text-dark px-3 py-2">
                                <i class="fas fa-shield-alt me-1"></i>
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </p>
                        <p class="text-center mt-2 mb-0">
                            <small>Bergabung sejak <?php echo date('d F Y', strtotime($user['created_at'])); ?></small>
                        </p>
                    </div>
                    <div class="card-body p-0">
                        <div class="row p-4">
                            <div class="col-md-4 mb-3">
                                <div class="stat-card">
                                    <h3 class="mb-1"><?php echo $stats['total_transaksi']; ?></h3>
                                    <p class="mb-0"><i class="fas fa-receipt me-1"></i> Total Transaksi</p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="stat-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                                    <h3 class="mb-1"><?php echo $stats['transaksi_masuk']; ?></h3>
                                    <p class="mb-0"><i class="fas fa-arrow-up me-1"></i> Kas Masuk</p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="stat-card" style="background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%);">
                                    <h3 class="mb-1"><?php echo $stats['transaksi_keluar']; ?></h3>
                                    <p class="mb-0"><i class="fas fa-arrow-down me-1"></i> Kas Keluar</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Edit Profile -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-user-edit me-2"></i>
                                    Edit Profile
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_profile">
                                    <div class="mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                        <small class="text-muted">Username tidak dapat diubah</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Role</label>
                                        <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" disabled>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-gradient">
                                        <i class="fas fa-save me-1"></i> Simpan Perubahan
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-key me-2"></i>
                                    Ubah Password
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="change_password">
                                    <div class="mb-3">
                                        <label class="form-label">Password Saat Ini</label>
                                        <input type="password" class="form-control" name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Password Baru</label>
                                        <input type="password" class="form-control" name="new_password" required minlength="6">
                                        <small class="text-muted">Minimal 6 karakter</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Konfirmasi Password Baru</label>
                                        <input type="password" class="form-control" name="confirm_password" required>
                                    </div>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-lock me-1"></i> Ubah Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Login History -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>
                            Riwayat Login (10 Terakhir)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($login_history)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Belum ada riwayat login</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($login_history as $log): ?>
                            <div class="login-history-item <?php echo $log['status']; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <?php if ($log['status'] == 'berhasil'): ?>
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            <strong>Login Berhasil</strong>
                                        <?php else: ?>
                                            <i class="fas fa-times-circle text-danger me-2"></i>
                                            <strong>Login Gagal</strong>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('d/m/Y H:i:s', strtotime($log['waktu_login'])); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
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