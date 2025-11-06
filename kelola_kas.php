<?php
require_once 'config/database.php';
require_once 'config/auth.php';

requireBendahara();

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $tanggal = $_POST['tanggal'];
                $jenis = $_POST['jenis'];
                $kategori_id = $_POST['kategori_id'];
                $deskripsi = $_POST['deskripsi'];
                $jumlah = $_POST['jumlah'];
                
                $query = "INSERT INTO transaksi_kas (tanggal, jenis, kategori_id, deskripsi, jumlah, dibuat_oleh) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$tanggal, $jenis, $kategori_id, $deskripsi, $jumlah, $_SESSION['user_id']])) {
                    $message = 'Transaksi berhasil ditambahkan!';
                    $message_type = 'success';
                } else {
                    $message = 'Gagal menambahkan transaksi!';
                    $message_type = 'danger';
                }
                break;
                
            case 'edit':
                $id = $_POST['id'];
                $tanggal = $_POST['tanggal'];
                $jenis = $_POST['jenis'];
                $kategori_id = $_POST['kategori_id'];
                $deskripsi = $_POST['deskripsi'];
                $jumlah = $_POST['jumlah'];
                
                $query = "UPDATE transaksi_kas SET tanggal = ?, jenis = ?, kategori_id = ?, deskripsi = ?, jumlah = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$tanggal, $jenis, $kategori_id, $deskripsi, $jumlah, $id])) {
                    $message = 'Transaksi berhasil diupdate!';
                    $message_type = 'success';
                } else {
                    $message = 'Gagal mengupdate transaksi!';
                    $message_type = 'danger';
                }
                break;
                
            case 'delete':
                $id = $_POST['id'];
                $query = "DELETE FROM transaksi_kas WHERE id = ?";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$id])) {
                    $message = 'Transaksi berhasil dihapus!';
                    $message_type = 'success';
                } else {
                    $message = 'Gagal menghapus transaksi!';
                    $message_type = 'danger';
                }
                break;
        }
    }
}

// Get all transactions
$query = "SELECT t.*, k.nama_kategori, u.username 
          FROM transaksi_kas t 
          LEFT JOIN kategori_kas k ON t.kategori_id = k.id
          LEFT JOIN users u ON t.dibuat_oleh = u.id
          ORDER BY t.tanggal DESC, t.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories
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
    <title>Kelola Kas - SI-KAS</title>
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
                    
                    <a class="nav-link active" href="kelola_kas.php">
                        <i class="fas fa-coins me-2"></i>
                        Kelola Kas
                    </a>
                    
                    <a class="nav-link" href="kelola_kategori.php">
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
                </nav>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <div class="row mb-4">
                    <div class="col-12">
                        <h2 class="fw-bold">Kelola Kas</h2>
                        <p class="text-muted">Kelola transaksi kas masuk dan keluar</p>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Add Transaction Form -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-plus me-2"></i>
                            Tambah Transaksi
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add">
                            <div class="row">
                                <div class="col-md-3">
                                    <label for="tanggal" class="form-label">Tanggal</label>
                                    <input type="date" class="form-control" name="tanggal" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="jenis" class="form-label">Jenis</label>
                                    <select class="form-select" name="jenis" required>
                                        <option value="">Pilih</option>
                                        <option value="masuk">Kas Masuk</option>
                                        <option value="keluar">Kas Keluar</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="kategori_id" class="form-label">Kategori</label>
                                    <select class="form-select" name="kategori_id" required>
                                        <option value="">Pilih</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo ucfirst($cat['nama_kategori']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="jumlah" class="form-label">Jumlah</label>
                                    <input type="number" class="form-control" name="jumlah" required min="0" step="0.01">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary btn-gradient d-block w-100">
                                        <i class="fas fa-plus"></i> Tambah
                                    </button>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <label for="deskripsi" class="form-label">Deskripsi</label>
                                    <textarea class="form-control" name="deskripsi" rows="2" placeholder="Deskripsi transaksi..."></textarea>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Transactions Table -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Daftar Transaksi
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($transactions)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Belum ada transaksi</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Jenis</th>
                                            <th>Kategori</th>
                                            <th>Deskripsi</th>
                                            <th>Jumlah</th>
                                            <th>Dibuat Oleh</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $trans): ?>
                                        <tr>
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
                                            <td><?php echo $trans['username']; ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-warning me-1" onclick="editTransaction(<?php echo htmlspecialchars(json_encode($trans)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteTransaction(<?php echo $trans['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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
                <form method="POST" id="editForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Transaksi</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_tanggal" class="form-label">Tanggal</label>
                            <input type="date" class="form-control" name="tanggal" id="edit_tanggal" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_jenis" class="form-label">Jenis</label>
                            <select class="form-select" name="jenis" id="edit_jenis" required>
                                <option value="masuk">Kas Masuk</option>
                                <option value="keluar">Kas Keluar</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_kategori_id" class="form-label">Kategori</label>
                            <select class="form-select" name="kategori_id" id="edit_kategori_id" required>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo ucfirst($cat['nama_kategori']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_jumlah" class="form-label">Jumlah</label>
                            <input type="number" class="form-control" name="jumlah" id="edit_jumlah" required min="0" step="0.01">
                        </div>
                        <div class="mb-3">
                            <label for="edit_deskripsi" class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="deskripsi" id="edit_deskripsi" rows="3"></textarea>
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
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">Hapus Transaksi</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Apakah Anda yakin ingin menghapus transaksi ini?</p>
                        <p class="text-danger"><small>Data yang sudah dihapus tidak dapat dikembalikan.</small></p>
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
        function editTransaction(trans) {
            document.getElementById('edit_id').value = trans.id;
            document.getElementById('edit_tanggal').value = trans.tanggal;
            document.getElementById('edit_jenis').value = trans.jenis;
            document.getElementById('edit_kategori_id').value = trans.kategori_id;
            document.getElementById('edit_jumlah').value = trans.jumlah;
            document.getElementById('edit_deskripsi').value = trans.deskripsi;
            
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        function deleteTransaction(id) {
            document.getElementById('delete_id').value = id;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>