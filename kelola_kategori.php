<?php
require_once 'config/database.php';
require_once 'config/auth.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $nama_kategori = trim(strtolower($_POST['nama_kategori']));
                
                // Check if category already exists
                $check_query = "SELECT id FROM kategori_kas WHERE nama_kategori = ?";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->execute([$nama_kategori]);
                
                if ($check_stmt->fetch()) {
                    $message = 'Kategori sudah ada!';
                    $message_type = 'danger';
                } else {
                    $query = "INSERT INTO kategori_kas (nama_kategori) VALUES (?)";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$nama_kategori])) {
                        $message = 'Kategori berhasil ditambahkan!';
                        $message_type = 'success';
                    } else {
                        $message = 'Gagal menambahkan kategori!';
                        $message_type = 'danger';
                    }
                }
                break;
                
            case 'edit':
                $id = $_POST['id'];
                $nama_kategori = trim(strtolower($_POST['nama_kategori']));
                
                // Check if category name already exists (except current)
                $check_query = "SELECT id FROM kategori_kas WHERE nama_kategori = ? AND id != ?";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->execute([$nama_kategori, $id]);
                
                if ($check_stmt->fetch()) {
                    $message = 'Nama kategori sudah digunakan!';
                    $message_type = 'danger';
                } else {
                    $query = "UPDATE kategori_kas SET nama_kategori = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$nama_kategori, $id])) {
                        $message = 'Kategori berhasil diupdate!';
                        $message_type = 'success';
                    } else {
                        $message = 'Gagal mengupdate kategori!';
                        $message_type = 'danger';
                    }
                }
                break;
                
            case 'delete':
                $id = $_POST['id'];
                
                // Check if category is being used
                $check_usage = "SELECT COUNT(*) as count FROM transaksi_kas WHERE kategori_id = ?";
                $check_stmt = $db->prepare($check_usage);
                $check_stmt->execute([$id]);
                $usage = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($usage['count'] > 0) {
                    $message = 'Kategori tidak dapat dihapus karena sedang digunakan pada ' . $usage['count'] . ' transaksi!';
                    $message_type = 'danger';
                } else {
                    $query = "DELETE FROM kategori_kas WHERE id = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$id])) {
                        $message = 'Kategori berhasil dihapus!';
                        $message_type = 'success';
                    } else {
                        $message = 'Gagal menghapus kategori!';
                        $message_type = 'danger';
                    }
                }
                break;
        }
    }
}

// Get all categories with transaction count
$query = "SELECT k.*, COUNT(t.id) as jumlah_transaksi 
          FROM kategori_kas k 
          LEFT JOIN transaksi_kas t ON k.id = t.kategori_id 
          GROUP BY k.id 
          ORDER BY k.nama_kategori";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori - SI-KAS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
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
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
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
        
        .category-badge {
            font-size: 1rem;
            padding: 10px 20px;
            border-radius: 20px;
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
                    <a class="nav-link active" href="kelola_kategori.php">
                        <i class="fas fa-tags me-2"></i>
                        Kategori
                    </a>
                    
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
                        <h2 class="fw-bold">Kelola Kategori</h2>
                        <p class="text-muted">Kelola kategori transaksi kas</p>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Add Category Form -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-plus-circle me-2"></i>
                            Tambah Kategori Baru
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add">
                            <div class="row align-items-end">
                                <div class="col-md-10">
                                    <label for="nama_kategori" class="form-label">Nama Kategori</label>
                                    <input type="text" class="form-control" name="nama_kategori" required placeholder="Contoh: event, sosial, operasional, dll">
                                    <small class="text-muted">Nama kategori akan otomatis diubah menjadi huruf kecil</small>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary btn-gradient d-block w-100">
                                        <i class="fas fa-plus"></i> Tambah
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Categories List -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Daftar Kategori <span class="badge bg-primary"><?php echo count($categories); ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($categories)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Belum ada kategori</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($categories as $cat): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body d-flex justify-content-between align-items-center">
                                            <div>
                                                <h5 class="mb-1">
                                                    <span class="badge category-badge bg-primary">
                                                        <i class="fas fa-tag me-2"></i><?php echo ucfirst($cat['nama_kategori']); ?>
                                                    </span>
                                                </h5>
                                                <small class="text-muted">
                                                    <i class="fas fa-receipt me-1"></i>
                                                    Digunakan pada <?php echo $cat['jumlah_transaksi']; ?> transaksi
                                                </small>
                                            </div>
                                            <div>
                                                <button class="btn btn-sm btn-warning me-1" onclick="editCategory(<?php echo htmlspecialchars(json_encode($cat)); ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteCategory(<?php echo $cat['id']; ?>, '<?php echo $cat['nama_kategori']; ?>', <?php echo $cat['jumlah_transaksi']; ?>)" title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
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

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Kategori</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_nama_kategori" class="form-label">Nama Kategori</label>
                            <input type="text" class="form-control" name="nama_kategori" id="edit_nama_kategori" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-gradient">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">Hapus Kategori</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Apakah Anda yakin ingin menghapus kategori <strong id="delete_nama"></strong>?</p>
                        <p class="text-danger"><small id="delete_warning"></small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCategory(cat) {
            document.getElementById('edit_id').value = cat.id;
            document.getElementById('edit_nama_kategori').value = cat.nama_kategori;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        function deleteCategory(id, nama, jumlahTransaksi) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_nama').textContent = nama;
            
            if (jumlahTransaksi > 0) {
                document.getElementById('delete_warning').textContent = 
                    'Kategori ini digunakan pada ' + jumlahTransaksi + ' transaksi. Anda tidak dapat menghapusnya.';
                document.querySelector('#deleteModal button[type="submit"]').disabled = true;
            } else {
                document.getElementById('delete_warning').textContent = 
                    'Data yang sudah dihapus tidak dapat dikembalikan.';
                document.querySelector('#deleteModal button[type="submit"]').disabled = false;
            }
            
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>