<?php
require_once 'config/database.php';
require_once 'config/auth.php';

requireAdmin(); // Hanya admin yang bisa akses

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                $role = $_POST['role'];

                // Cek username duplikat
                $check_query = "SELECT id FROM users WHERE username = ?";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->execute([$username]);

                if ($check_stmt->fetch()) {
                    $message = 'Username sudah digunakan!';
                    $message_type = 'danger';
                } else {
                    $query = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$username, $email, $password, $role])) {
                        $message = 'Pengguna berhasil ditambahkan!';
                        $message_type = 'success';
                    } else {
                        $message = 'Gagal menambahkan pengguna!';
                        $message_type = 'danger';
                    }
                }
                break;

            case 'edit':
                $id = $_POST['id'];
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $role = $_POST['role'];

                $check_query = "SELECT id FROM users WHERE username = ? AND id != ?";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->execute([$username, $id]);

                if ($check_stmt->fetch()) {
                    $message = 'Username sudah digunakan!';
                    $message_type = 'danger';
                } else {
                    if (!empty($_POST['password'])) {
                        $query = "UPDATE users SET username = ?, email = ?, password = ?, role = ? WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $params = [$username, $email, $_POST['password'], $role, $id];
                    } else {
                        $query = "UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $params = [$username, $email, $role, $id];
                    }

                    if ($stmt->execute($params)) {
                        $message = 'Pengguna berhasil diupdate!';
                        $message_type = 'success';
                    } else {
                        $message = 'Gagal mengupdate pengguna!';
                        $message_type = 'danger';
                    }
                }
                break;

            case 'delete':
                $id = $_POST['id'];

                if ($id == $_SESSION['user_id']) {
                    $message = 'Tidak dapat menghapus akun sendiri!';
                    $message_type = 'danger';
                } else {
                    $query = "DELETE FROM users WHERE id = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$id])) {
                        $message = 'Pengguna berhasil dihapus!';
                        $message_type = 'success';
                    } else {
                        $message = 'Gagal menghapus pengguna!';
                        $message_type = 'danger';
                    }
                }
                break;
        }
    }
}

// Ambil semua user
$query = "SELECT * FROM users ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna - SI-KAS</title>
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
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
            min-height: calc(100vh - 56px);
        }

        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 15px 20px;
            border-radius: 10px;
            margin: 5px 10px;
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

        .footer {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 30px 0;
            width: 100%;
        }

        @media (max-width: 768px) {
            .content-container { flex-direction: column; }
            .sidebar { width: 100%; }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-wallet me-2"></i> SI-KAS
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-2"></i>
                        <?php echo $_SESSION['username']; ?> 
                        <span class="badge bg-light text-dark ms-1"><?php echo ucfirst($_SESSION['role']); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
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
                    <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                    <a class="nav-link" href="laporan.php"><i class="fas fa-chart-bar me-2"></i>Laporan Keuangan</a>
                    <a class="nav-link" href="search.php"><i class="fas fa-search me-2"></i>Pencarian</a>
                    <a class="nav-link active" href="kelola_pengguna.php"><i class="fas fa-users me-2"></i>Kelola Pengguna</a>
                    
                </nav>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <div class="mb-4">
                    <h2 class="fw-bold">Kelola Pengguna</h2>
                    <p class="text-muted">Kelola akun admin dan bendahara</p>
                </div>

                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i><?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Add User -->
                <div class="card">
                    <div class="card-header bg-white"><h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Tambah Pengguna</h5></div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" name="username" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" name="password" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Role</label>
                                    <select class="form-select" name="role" required>
                                        <option value="">Pilih</option>
                                        <option value="admin">Admin</option>
                                        <option value="bendahara">Bendahara</option>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <button type="submit" class="btn btn-gradient"><i class="fas fa-plus"></i></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- User Table -->
                <div class="card">
                    <div class="card-header bg-white"><h5 class="mb-0"><i class="fas fa-list me-2"></i>Daftar Pengguna</h5></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Terdaftar</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?><?php if ($user['id'] == $_SESSION['user_id']): ?><span class="badge bg-info ms-2">Anda</span><?php endif; ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><span class="badge bg-<?php echo $user['role']=='admin'?'primary':'success'; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning me-1" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)"><i class="fas fa-edit"></i></button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo $user['username']; ?>')"><i class="fas fa-trash"></i></button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer mt-auto">
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
    </div>

    <!-- Modal Edit User -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Pengguna</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">

                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" id="edit_username" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password (opsional)</label>
                            <input type="password" class="form-control" name="password" id="edit_password">
                            <small class="text-muted">Kosongkan jika tidak ingin mengganti password.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" id="edit_role" required>
                                <option value="admin">Admin</option>
                                <option value="bendahara">Bendahara</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(user) {
            document.getElementById('edit_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_password').value = '';

            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();
        }

        function deleteUser(id, username) {
            if (confirm('Hapus pengguna ' + username + '?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="${id}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
