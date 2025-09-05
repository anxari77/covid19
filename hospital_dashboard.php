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
    // Hospital not approved yet
    include 'hospital_pending.php';
    exit();
}

$hospital_id = $hospital['id'];

// Get hospital statistics
$stats = [];

// Total appointments
$query = "SELECT COUNT(*) as count FROM appointments WHERE hospital_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $hospital_id);
$stmt->execute();
$stats['appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Pending appointments
$query = "SELECT COUNT(*) as count FROM appointments WHERE hospital_id = ? AND status = 'pending'";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $hospital_id);
$stmt->execute();
$stats['pending_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Today's appointments
$query = "SELECT COUNT(*) as count FROM appointments WHERE hospital_id = ? AND DATE(date) = CURDATE()";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $hospital_id);
$stmt->execute();
$stats['today_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Test results pending
$query = "SELECT COUNT(*) as count FROM appointments a 
          LEFT JOIN test_results tr ON a.id = tr.appointment_id 
          WHERE a.hospital_id = ? AND a.type = 'test' AND a.status = 'approved' AND tr.id IS NULL";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $hospital_id);
$stmt->execute();
$stats['pending_results'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Recent appointments
$query = "SELECT a.*, u.name as patient_name, u.email as patient_email, p.phone as patient_phone
          FROM appointments a 
          JOIN patients p ON a.patient_id = p.id 
          JOIN users u ON p.user_id = u.id 
          WHERE a.hospital_id = ? 
          ORDER BY a.date DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $hospital_id);
$stmt->execute();
$recent_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Appointments needing attention (pending)
$query = "SELECT a.*, u.name as patient_name, u.email as patient_email, p.phone as patient_phone
          FROM appointments a 
          JOIN patients p ON a.patient_id = p.id 
          JOIN users u ON p.user_id = u.id 
          WHERE a.hospital_id = ? AND a.status = 'pending'
          ORDER BY a.date ASC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $hospital_id);
$stmt->execute();
$pending_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Dashboard - COVID-19 Management System</title>
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
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 4px solid;
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card.primary { border-left-color: #007bff; }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.info { border-left-color: #17a2b8; }
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
                <a class="nav-link active" href="hospital_dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="manage_appointments.php">
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
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Hospital Dashboard</h2>
                <p class="text-muted">Welcome to <?php echo htmlspecialchars($hospital['name']); ?> management portal</p>
            </div>
            <div class="text-end">
                <small class="text-muted">Last updated: <?php echo date('Y-m-d H:i:s'); ?></small>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card primary">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-primary"><?php echo $stats['appointments']; ?></h3>
                            <p class="mb-0">Total Appointments</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-calendar-alt fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card warning">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-warning"><?php echo $stats['pending_appointments']; ?></h3>
                            <p class="mb-0">Pending Approval</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card success">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-success"><?php echo $stats['today_appointments']; ?></h3>
                            <p class="mb-0">Today's Appointments</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-calendar-day fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card info">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-info"><?php echo $stats['pending_results']; ?></h3>
                            <p class="mb-0">Pending Results</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-vial fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Row -->
        <div class="row">
            <!-- Pending Appointments -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-clock me-2"></i>Pending Appointments</h5>
                        <a href="manage_appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($pending_appointments) > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($pending_appointments as $appointment): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">
                                                <span class="badge bg-<?php echo $appointment['type'] == 'test' ? 'info' : 'success'; ?> me-2">
                                                    <?php echo ucfirst($appointment['type']); ?>
                                                </span>
                                                <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                            </h6>
                                            <p class="mb-1"><?php echo date('M d, Y H:i A', strtotime($appointment['date'])); ?></p>
                                            <small class="text-muted"><?php echo htmlspecialchars($appointment['patient_phone']); ?></small>
                                        </div>
                                        <span class="badge bg-warning">Pending</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h6 class="text-muted">No pending appointments</h6>
                                <p class="text-muted small">All appointments are up to date!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Appointments -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar me-2"></i>Recent Appointments</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_appointments) > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_appointments as $appointment): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">
                                                <span class="badge bg-<?php echo $appointment['type'] == 'test' ? 'info' : 'success'; ?> me-2">
                                                    <?php echo ucfirst($appointment['type']); ?>
                                                </span>
                                                <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                            </h6>
                                            <p class="mb-1"><?php echo date('M d, Y H:i A', strtotime($appointment['date'])); ?></p>
                                            <small class="text-muted"><?php echo htmlspecialchars($appointment['patient_email']); ?></small>
                                        </div>
                                        <span class="badge bg-<?php 
                                            echo $appointment['status'] == 'approved' ? 'success' : 
                                                ($appointment['status'] == 'rejected' ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No appointments yet</h6>
                                <p class="text-muted small">Appointments will appear here once patients book them.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="manage_appointments.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-calendar-check fa-2x mb-2"></i><br>
                                    Manage Appointments
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="test_results_management.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-vial fa-2x mb-2"></i><br>
                                    Add Test Results
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="vaccination_management.php" class="btn btn-outline-success w-100">
                                    <i class="fas fa-syringe fa-2x mb-2"></i><br>
                                    Manage Vaccinations
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="hospital_profile.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-building fa-2x mb-2"></i><br>
                                    Hospital Profile
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
