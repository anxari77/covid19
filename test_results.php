<?php
require_once 'config/database.php';
requireRole('patient');

$database = new Database();
$db = $database->getConnection();
$user_id = getUserId();

// Get patient ID
$query = "SELECT id FROM patients WHERE user_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
$patient_id = $patient['id'];

// Get all test results for the patient
$query = "SELECT tr.*, a.date as test_date, h.name as hospital_name, h.address as hospital_address
          FROM test_results tr 
          JOIN appointments a ON tr.appointment_id = a.id 
          JOIN hospitals h ON a.hospital_id = h.id 
          WHERE a.patient_id = ? AND a.type = 'test'
          ORDER BY tr.updated_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $patient_id);
$stmt->execute();
$test_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get test statistics
$stats = [];
$query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN tr.result = 'positive' THEN 1 ELSE 0 END) as positive,
    SUM(CASE WHEN tr.result = 'negative' THEN 1 ELSE 0 END) as negative
FROM test_results tr 
JOIN appointments a ON tr.appointment_id = a.id 
WHERE a.patient_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $patient_id);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Results - COVID-19 Management System</title>
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
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid;
        }
        .stat-card.primary { border-left-color: #17a2b8; }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.danger { border-left-color: #dc3545; }
        .result-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: white;
        }
        .result-card.positive { border-left: 4px solid #dc3545; }
        .result-card.negative { border-left: 4px solid #28a745; }
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
            <p class="small mb-0">Welcome, <?php echo htmlspecialchars(getUserName()); ?>!</p>
        </div>
        <ul class="nav flex-column px-3">
            <li class="nav-item">
                <a class="nav-link" href="patient_dashboard.php">
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
                <a class="nav-link active" href="test_results.php">
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-vial me-2"></i>Test Results</h2>
                <p class="text-muted">View your COVID-19 test results history</p>
            </div>
            <a href="book_appointment.php" class="btn btn-info">
                <i class="fas fa-plus me-2"></i>Book Test Appointment
            </a>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card primary">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-info"><?php echo $stats['total']; ?></h3>
                            <p class="mb-0">Total Tests</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-vial fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card success">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-success"><?php echo $stats['negative']; ?></h3>
                            <p class="mb-0">Negative Results</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card danger">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-danger"><?php echo $stats['positive']; ?></h3>
                            <p class="mb-0">Positive Results</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test Results List -->
        <?php if (count($test_results) > 0): ?>
            <div class="row">
                <?php foreach ($test_results as $result): ?>
                    <div class="col-md-6 mb-3">
                        <div class="result-card <?php echo $result['result']; ?>">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="mb-1">
                                        <i class="fas fa-vial me-2"></i>COVID-19 Test Result
                                    </h5>
                                    <h6 class="text-primary"><?php echo htmlspecialchars($result['hospital_name']); ?></h6>
                                </div>
                                <span class="badge bg-<?php echo $result['result'] == 'negative' ? 'success' : 'danger'; ?> fs-6">
                                    <i class="fas fa-<?php echo $result['result'] == 'negative' ? 'check' : 'exclamation-triangle'; ?> me-1"></i>
                                    <?php echo strtoupper($result['result']); ?>
                                </span>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Test Date</small>
                                    <p class="mb-0">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('M d, Y', strtotime($result['test_date'])); ?>
                                    </p>
                                    <p class="mb-0">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('h:i A', strtotime($result['test_date'])); ?>
                                    </p>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Result Updated</small>
                                    <p class="mb-0">
                                        <i class="fas fa-calendar-check me-1"></i>
                                        <?php echo date('M d, Y', strtotime($result['updated_at'])); ?>
                                    </p>
                                    <p class="mb-0">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('h:i A', strtotime($result['updated_at'])); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <?php if ($result['remarks']): ?>
                                <div class="mb-3">
                                    <small class="text-muted">Remarks</small>
                                    <p class="mb-0">
                                        <i class="fas fa-comment me-1"></i>
                                        <?php echo htmlspecialchars($result['remarks']); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <small class="text-muted">Hospital Address</small>
                                <p class="mb-0">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?php echo htmlspecialchars($result['hospital_address']); ?>
                                </p>
                            </div>
                            
                            <?php if ($result['result'] == 'positive'): ?>
                                <div class="alert alert-warning small mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Important:</strong> Please follow isolation guidelines and contact your healthcare provider.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success small mb-0">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>Good news:</strong> Your test result is negative. Continue following safety protocols.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-vial fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No test results found</h5>
                <p class="text-muted">You don't have any COVID-19 test results yet.</p>
                <a href="book_appointment.php" class="btn btn-info">
                    <i class="fas fa-plus me-2"></i>Book a Test Appointment
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
