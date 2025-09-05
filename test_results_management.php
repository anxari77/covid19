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

// Handle test result submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_result'])) {
    $appointment_id = $_POST['appointment_id'];
    $result = $_POST['result'];
    $remarks = trim($_POST['remarks']);
    
    if (!empty($appointment_id) && !empty($result)) {
        // Check if result already exists
        $query = "SELECT id FROM test_results WHERE appointment_id = ?";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $appointment_id);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            $query = "INSERT INTO test_results (appointment_id, result, remarks) VALUES (?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $appointment_id);
            $stmt->bindParam(2, $result);
            $stmt->bindParam(3, $remarks);
            
            if ($stmt->execute()) {
                $message = "Test result added successfully!";
                $message_type = 'success';
            } else {
                $message = "Error adding test result.";
                $message_type = 'danger';
            }
        } else {
            $message = "Test result already exists for this appointment.";
            $message_type = 'warning';
        }
    } else {
        $message = "Please fill in all required fields.";
        $message_type = 'danger';
    }
}

// Get approved test appointments without results
$query = "SELECT a.*, u.name as patient_name, u.email as patient_email, p.phone as patient_phone
          FROM appointments a 
          JOIN patients p ON a.patient_id = p.id 
          JOIN users u ON p.user_id = u.id 
          LEFT JOIN test_results tr ON a.id = tr.appointment_id
          WHERE a.hospital_id = ? AND a.type = 'test' AND a.status = 'approved' AND tr.id IS NULL
          ORDER BY a.date ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $hospital_id);
$stmt->execute();
$pending_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all test results for this hospital
$query = "SELECT tr.*, a.date as test_date, u.name as patient_name, u.email as patient_email, p.phone as patient_phone
          FROM test_results tr 
          JOIN appointments a ON tr.appointment_id = a.id 
          JOIN patients p ON a.patient_id = p.id 
          JOIN users u ON p.user_id = u.id 
          WHERE a.hospital_id = ?
          ORDER BY tr.updated_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $hospital_id);
$stmt->execute();
$test_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$query = "SELECT 
    COUNT(*) as total_results,
    SUM(CASE WHEN tr.result = 'positive' THEN 1 ELSE 0 END) as positive,
    SUM(CASE WHEN tr.result = 'negative' THEN 1 ELSE 0 END) as negative
FROM test_results tr 
JOIN appointments a ON tr.appointment_id = a.id 
WHERE a.hospital_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $hospital_id);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Results Management - COVID-19 Management System</title>
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
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid;
        }
        .stat-card.primary { border-left-color: #17a2b8; }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.danger { border-left-color: #dc3545; }
        .test-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
        }
        .result-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 1rem;
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
                <a class="nav-link" href="manage_appointments.php">
                    <i class="fas fa-calendar-check me-2"></i>Manage Appointments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="test_results_management.php">
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
                <h2><i class="fas fa-vial me-2"></i>Test Results Management</h2>
                <p class="text-muted">Add and manage COVID-19 test results</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card primary">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-info"><?php echo $stats['total_results']; ?></h3>
                            <p class="mb-0">Total Results</p>
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

        <div class="row">
            <!-- Pending Test Results -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-clock me-2"></i>Pending Test Results</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($pending_tests) > 0): ?>
                            <?php foreach ($pending_tests as $test): ?>
                                <div class="test-card">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h6 class="mb-1">
                                                <i class="fas fa-user me-2"></i>
                                                <?php echo htmlspecialchars($test['patient_name']); ?>
                                            </h6>
                                            <p class="mb-1 text-muted"><?php echo htmlspecialchars($test['patient_email']); ?></p>
                                            <p class="mb-0">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('M d, Y H:i A', strtotime($test['date'])); ?>
                                            </p>
                                        </div>
                                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" 
                                                data-bs-target="#addResultModal" 
                                                onclick="setAppointmentData(<?php echo $test['id']; ?>, '<?php echo htmlspecialchars($test['patient_name']); ?>')">
                                            <i class="fas fa-plus me-1"></i>Add Result
                                        </button>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($test['patient_phone']); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h6 class="text-muted">No pending test results</h6>
                                <p class="text-muted small">All test results are up to date!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Test Results -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history me-2"></i>Recent Test Results</h5>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <?php if (count($test_results) > 0): ?>
                            <?php foreach ($test_results as $result): ?>
                                <div class="result-card <?php echo $result['result']; ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-1">
                                                <i class="fas fa-user me-2"></i>
                                                <?php echo htmlspecialchars($result['patient_name']); ?>
                                            </h6>
                                            <p class="mb-1 text-muted small"><?php echo htmlspecialchars($result['patient_email']); ?></p>
                                        </div>
                                        <span class="badge bg-<?php echo $result['result'] == 'negative' ? 'success' : 'danger'; ?> fs-6">
                                            <?php echo strtoupper($result['result']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="row mb-2">
                                        <div class="col-6">
                                            <small class="text-muted">Test Date</small>
                                            <p class="mb-0 small">
                                                <?php echo date('M d, Y', strtotime($result['test_date'])); ?>
                                            </p>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Result Added</small>
                                            <p class="mb-0 small">
                                                <?php echo date('M d, Y', strtotime($result['updated_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <?php if ($result['remarks']): ?>
                                        <div class="mb-0">
                                            <small class="text-muted">Remarks</small>
                                            <p class="mb-0 small">
                                                <?php echo htmlspecialchars($result['remarks']); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-vial fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No test results yet</h6>
                                <p class="text-muted small">Test results will appear here once added.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Result Modal -->
    <div class="modal fade" id="addResultModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Test Result</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="appointment_id" id="modalAppointmentId">
                        
                        <div class="mb-3">
                            <label class="form-label">Patient</label>
                            <p class="form-control-plaintext" id="modalPatientName"></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="result" class="form-label">Test Result *</label>
                            <select class="form-select" name="result" required>
                                <option value="">Select result</option>
                                <option value="negative">Negative</option>
                                <option value="positive">Positive</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="3" 
                                      placeholder="Additional notes or observations..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_result" class="btn btn-primary">Add Result</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function setAppointmentData(appointmentId, patientName) {
            document.getElementById('modalAppointmentId').value = appointmentId;
            document.getElementById('modalPatientName').textContent = patientName;
        }
    </script>
</body>
</html>
