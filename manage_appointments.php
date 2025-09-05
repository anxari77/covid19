<?php
require_once 'config/database.php';
requireRole('hospital');

$database = new Database();
$db = $database->getConnection();
$user_id = getUserId();

// Get hospital information
$query = "SELECT h.*, u.name, u.email FROM hospitals h JOIN users u ON u.name = h.name WHERE u.id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$hospital = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hospital || $hospital['status'] !== 'approved') {
    header("Location: hospital_pending.php");
    exit();
}

$hospital_id = $hospital['id'];
$message = '';
$message_type = '';

// Handle appointment status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && isset($_POST['appointment_id'])) {
        $appointment_id = $_POST['appointment_id'];
        $action = $_POST['action'];
        
        if ($action == 'approve' || $action == 'reject') {
            $status = ($action == 'approve') ? 'approved' : 'rejected';
            
            $query = "UPDATE appointments SET status = ? WHERE id = ? AND hospital_id = ?";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $status);
            $stmt->bindParam(2, $appointment_id);
            $stmt->bindParam(3, $hospital_id);
            
            if ($stmt->execute()) {
                $message = "Appointment " . ucfirst($action) . "d successfully!";
                $message_type = 'success';
            } else {
                $message = "Error updating appointment status.";
                $message_type = 'danger';
            }
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Build query with filters
$where_conditions = ["a.hospital_id = ?"];
$params = [$hospital_id];

if ($status_filter != 'all') {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
}

if ($type_filter != 'all') {
    $where_conditions[] = "a.type = ?";
    $params[] = $type_filter;
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(a.date) = ?";
    $params[] = $date_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get appointments
$query = "SELECT a.*, u.name as patient_name, u.email as patient_email, p.phone as patient_phone, p.address as patient_address
          FROM appointments a 
          JOIN patients p ON a.patient_id = p.id 
          JOIN users u ON p.user_id = u.id 
          WHERE $where_clause
          ORDER BY a.date DESC";

$stmt = $db->prepare($query);
foreach ($params as $index => $param) {
    $stmt->bindValue($index + 1, $param);
}
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN type = 'test' THEN 1 ELSE 0 END) as tests,
    SUM(CASE WHEN type = 'vaccination' THEN 1 ELSE 0 END) as vaccinations
FROM appointments WHERE hospital_id = ?";

$stmt = $db->prepare($stats_query);
$stmt->bindParam(1, $hospital_id);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - COVID-19 Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            min-height: 100vh;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 0;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: rgba(255,255,255,0.1);
            color: white;
        }
        .main-content { margin-left: 250px; padding: 20px; }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid;
        }
        .stat-card.primary { border-left-color: #007bff; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.danger { border-left-color: #dc3545; }
        .stat-card.info { border-left-color: #17a2b8; }
        .filter-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
            .sidebar { position: fixed; z-index: 1000; width: 250px; transform: translateX(-100%); }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar position-fixed">
        <div class="p-4">
            <h4><i class="fas fa-hospital me-2"></i>Hospital Portal</h4>
            <hr class="text-white-50">
            <p class="small mb-0"><?php echo htmlspecialchars($hospital['name']); ?></p>
        </div>
        <ul class="nav flex-column px-3">
            <li class="nav-item">
                <a class="nav-link" href="hospital_dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="manage_appointments.php">
                    <i class="fas fa-calendar-check me-2"></i>Manage Appointments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="test_results_management.php">
                    <i class="fas fa-vial me-2"></i>Test Results
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="vaccination_management.php">
                    <i class="fas fa-syringe me-2"></i>Vaccinations
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="hospital_profile.php">
                    <i class="fas fa-building me-2"></i>Hospital Profile
                </a>
            </li>
            <hr class="text-white-50">
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-calendar-check me-2"></i>Manage Appointments</h2>
                <p class="text-muted">Review and manage patient appointments</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Card -->
        <div class="filter-card">
            <h5><i class="fas fa-filter me-2"></i>Filter Appointments</h5>
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="type" class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="all" <?php echo $type_filter == 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="test" <?php echo $type_filter == 'test' ? 'selected' : ''; ?>>Test</option>
                        <option value="vaccination" <?php echo $type_filter == 'vaccination' ? 'selected' : ''; ?>>Vaccination</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date" class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?php echo $date_filter; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>Filter
                        </button>
                        <a href="manage_appointments.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="stat-card primary">
                    <h4 class="text-primary"><?php echo $stats['total']; ?></h4>
                    <p class="mb-0 small">Total</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card warning">
                    <h4 class="text-warning"><?php echo $stats['pending']; ?></h4>
                    <p class="mb-0 small">Pending</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card success">
                    <h4 class="text-success"><?php echo $stats['approved']; ?></h4>
                    <p class="mb-0 small">Approved</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card danger">
                    <h4 class="text-danger"><?php echo $stats['rejected']; ?></h4>
                    <p class="mb-0 small">Rejected</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card info">
                    <h4 class="text-info"><?php echo $stats['tests']; ?></h4>
                    <p class="mb-0 small">Tests</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card success">
                    <h4 class="text-success"><?php echo $stats['vaccinations']; ?></h4>
                    <p class="mb-0 small">Vaccinations</p>
                </div>
            </div>
        </div>

        <!-- Appointments List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Appointments List</h5>
                <small class="text-muted">Showing <?php echo count($appointments); ?> appointments</small>
            </div>
            <div class="card-body">
                <?php if (count($appointments) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Patient</th>
                                    <th>Type</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Contact</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appointment): ?>
                                    <tr>
                                        <td>#<?php echo str_pad($appointment['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($appointment['patient_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($appointment['patient_email']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $appointment['type'] == 'test' ? 'info' : 'success'; ?>">
                                                <i class="fas fa-<?php echo $appointment['type'] == 'test' ? 'vial' : 'syringe'; ?> me-1"></i>
                                                <?php echo ucfirst($appointment['type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo date('M d, Y', strtotime($appointment['date'])); ?></strong><br>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($appointment['date'])); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $appointment['status'] == 'approved' ? 'success' : 
                                                    ($appointment['status'] == 'rejected' ? 'danger' : 'warning'); 
                                            ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($appointment['patient_phone']); ?>
                                        </td>
                                        <td>
                                            <?php if ($appointment['status'] == 'pending'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                    <button type="submit" name="action" value="approve" 
                                                            class="btn btn-success btn-sm me-1"
                                                            onclick="return confirm('Approve this appointment?')">
                                                        <i class="fas fa-check me-1"></i>Approve
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                    <button type="submit" name="action" value="reject" 
                                                            class="btn btn-danger btn-sm"
                                                            onclick="return confirm('Reject this appointment?')">
                                                        <i class="fas fa-times me-1"></i>Reject
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted small">
                                                    <i class="fas fa-<?php echo $appointment['status'] == 'approved' ? 'check' : 'times'; ?>"></i>
                                                    <?php echo ucfirst($appointment['status']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No appointments found</h5>
                        <p class="text-muted">
                            <?php if ($status_filter != 'all' || $type_filter != 'all' || !empty($date_filter)): ?>
                                No appointments match your current filters. <a href="manage_appointments.php">Clear filters</a>
                            <?php else: ?>
                                No appointments have been scheduled yet.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
