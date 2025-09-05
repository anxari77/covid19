<?php
require_once 'config/database.php';
requireRole('patient');

$database = new Database();
$db = $database->getConnection();
$user_id = getUserId();

// Get patient information
$query = "SELECT p.*, u.name, u.email FROM patients p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    logout();
}

$patient_id = $patient['id'];

// Get patient statistics
$stats = [];

// Total appointments
$query = "SELECT COUNT(*) as count FROM appointments WHERE patient_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $patient_id);
$stmt->execute();
$stats['appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Pending appointments
$query = "SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND status = 'pending'";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $patient_id);
$stmt->execute();
$stats['pending_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Vaccinations
$query = "SELECT COUNT(*) as count FROM vaccinations WHERE patient_id = ? AND status = 'done'";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $patient_id);
$stmt->execute();
$stats['vaccinations'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Test results
$query = "SELECT COUNT(*) as count FROM test_results tr 
          JOIN appointments a ON tr.appointment_id = a.id 
          WHERE a.patient_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $patient_id);
$stmt->execute();
$stats['test_results'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Recent appointments
$query = "SELECT a.*, h.name as hospital_name, h.address as hospital_address 
          FROM appointments a 
          JOIN hospitals h ON a.hospital_id = h.id 
          WHERE a.patient_id = ? 
          ORDER BY a.date DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $patient_id);
$stmt->execute();
$recent_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent test results
$query = "SELECT tr.*, a.date as test_date, h.name as hospital_name 
          FROM test_results tr 
          JOIN appointments a ON tr.appointment_id = a.id 
          JOIN hospitals h ON a.hospital_id = h.id 
          WHERE a.patient_id = ? 
          ORDER BY tr.updated_at DESC LIMIT 3";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $patient_id);
$stmt->execute();
$recent_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - COVID-19 Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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
        .stat-card.primary { border-left-color: #28a745; }
        .stat-card.success { border-left-color: #20c997; }
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
            <h4><i class="fas fa-user-circle me-2"></i>Patient Portal</h4>
            <hr class="text-white-50">
            <p class="small mb-0">Welcome, <?php echo htmlspecialchars($patient['name']); ?>!</p>
        </div>
        <ul class="nav flex-column px-3">
            <li class="nav-item">
                <a class="nav-link active" href="patient_dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="book_appointment.php">
                    <i class="fas fa-calendar-plus me-2"></i>Book Appointment
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="my_appointments.php">
                    <i class="fas fa-calendar-alt me-2"></i>My Appointments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="test_results.php">
                    <i class="fas fa-vial me-2"></i>Test Results
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="vaccination_history.php">
                    <i class="fas fa-syringe me-2"></i>Vaccination History
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="profile.php">
                    <i class="fas fa-user-edit me-2"></i>My Profile
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
                <h2>Patient Dashboard</h2>
                <p class="text-muted">Welcome back, <?php echo htmlspecialchars($patient['name']); ?>!</p>
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
                            <h3 class="text-success"><?php echo $stats['appointments']; ?></h3>
                            <p class="mb-0">Total Appointments</p>
                            <small class="text-warning"><?php echo $stats['pending_appointments']; ?> pending</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-calendar-alt fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card success">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-info"><?php echo $stats['vaccinations']; ?></h3>
                            <p class="mb-0">Vaccinations Done</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-syringe fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card info">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-primary"><?php echo $stats['test_results']; ?></h3>
                            <p class="mb-0">Test Results</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-vial fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card warning">
                    <div class="text-center">
                        <a href="book_appointment.php" class="btn btn-success btn-lg">
                            <i class="fas fa-plus me-2"></i>Book New Appointment
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="row">
            <div class="col-md-8">
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
                                                <?php echo htmlspecialchars($appointment['hospital_name']); ?>
                                            </h6>
                                            <p class="mb-1"><?php echo date('M d, Y H:i A', strtotime($appointment['date'])); ?></p>
                                            <small class="text-muted"><?php echo htmlspecialchars($appointment['hospital_address']); ?></small>
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
                            <div class="text-center mt-3">
                                <a href="my_appointments.php" class="btn btn-outline-primary">View All Appointments</a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No appointments yet</h6>
                                <a href="book_appointment.php" class="btn btn-primary">Book Your First Appointment</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-vial me-2"></i>Recent Test Results</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_tests) > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_tests as $test): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong><?php echo htmlspecialchars($test['hospital_name']); ?></strong>
                                            <span class="badge bg-<?php echo $test['result'] == 'negative' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($test['result']); ?>
                                            </span>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y', strtotime($test['test_date'])); ?>
                                        </small>
                                        <?php if ($test['remarks']): ?>
                                            <p class="small mb-0 mt-1"><?php echo htmlspecialchars($test['remarks']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="test_results.php" class="btn btn-outline-primary btn-sm">View All Results</a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-vial fa-2x text-muted mb-2"></i>
                                <p class="text-muted small mb-0">No test results available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
