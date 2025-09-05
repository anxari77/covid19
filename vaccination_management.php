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

// Handle vaccination record submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_vaccination'])) {
    $appointment_id = $_POST['appointment_id'];
    $vaccine_id = $_POST['vaccine_id'];
    $dose_number = $_POST['dose_number'];
    $vaccination_date = $_POST['vaccination_date'];
    $remarks = trim($_POST['remarks']);
    
    if (!empty($appointment_id) && !empty($vaccine_id) && !empty($dose_number) && !empty($vaccination_date)) {
        // Get patient ID from appointment
        $query = "SELECT patient_id FROM appointments WHERE id = ? AND hospital_id = ?";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $appointment_id);
        $stmt->bindParam(2, $hospital_id);
        $stmt->execute();
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($appointment) {
            $patient_id = $appointment['patient_id'];
            
            // Check if vaccination record already exists for this appointment
            $query = "SELECT id FROM vaccinations WHERE patient_id = ? AND vaccine_id = ? AND dose_number = ? AND date = ?";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $patient_id);
            $stmt->bindParam(2, $vaccine_id);
            $stmt->bindParam(3, $dose_number);
            $stmt->bindParam(4, $vaccination_date);
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                $query = "INSERT INTO vaccinations (patient_id, vaccine_id, dose_number, status, date) VALUES (?, ?, ?, 'done', ?)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $patient_id);
                $stmt->bindParam(2, $vaccine_id);
                $stmt->bindParam(3, $dose_number);
                $stmt->bindParam(4, $vaccination_date);
                
                if ($stmt->execute()) {
                    $message = "Vaccination record added successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error adding vaccination record.";
                    $message_type = 'danger';
                }
            } else {
                $message = "Vaccination record already exists for this patient and dose.";
                $message_type = 'warning';
            }
        } else {
            $message = "Invalid appointment.";
            $message_type = 'danger';
        }
    } else {
        $message = "Please fill in all required fields.";
        $message_type = 'danger';
    }
}

// Get approved vaccination appointments
$query = "SELECT a.*, u.name as patient_name, u.email as patient_email, p.phone as patient_phone
          FROM appointments a 
          JOIN patients p ON a.patient_id = p.id 
          JOIN users u ON p.user_id = u.id 
          WHERE a.hospital_id = ? AND a.type = 'vaccination' AND a.status = 'approved'
          ORDER BY a.date ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $hospital_id);
$stmt->execute();
$vaccination_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available vaccines
$query = "SELECT * FROM vaccines WHERE status = 'available' ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$vaccines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get vaccination records for this hospital
$query = "SELECT v.*, vac.name as vaccine_name, vac.manufacturer, u.name as patient_name, u.email as patient_email, p.phone as patient_phone
          FROM vaccinations v 
          JOIN vaccines vac ON v.vaccine_id = vac.id
          JOIN patients p ON v.patient_id = p.id 
          JOIN users u ON p.user_id = u.id 
          JOIN appointments a ON a.patient_id = p.id AND a.type = 'vaccination'
          WHERE a.hospital_id = ? AND v.status = 'done'
          GROUP BY v.id
          ORDER BY v.date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $hospital_id);
$stmt->execute();
$vaccination_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$query = "SELECT 
    COUNT(DISTINCT v.id) as total_vaccinations,
    COUNT(DISTINCT v.patient_id) as unique_patients,
    COUNT(CASE WHEN v.dose_number = 1 THEN 1 END) as first_doses,
    COUNT(CASE WHEN v.dose_number = 2 THEN 1 END) as second_doses,
    COUNT(CASE WHEN v.dose_number >= 3 THEN 1 END) as boosters
FROM vaccinations v 
JOIN patients p ON v.patient_id = p.id 
JOIN appointments a ON a.patient_id = p.id AND a.type = 'vaccination'
WHERE a.hospital_id = ? AND v.status = 'done'";
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
    <title>Vaccination Management - COVID-19 Management System</title>
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
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.info { border-left-color: #17a2b8; }
        .stat-card.purple { border-left-color: #6f42c1; }
        .appointment-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
        }
        .vaccination-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
            border-left: 4px solid #28a745;
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
                <a class="nav-link active" href="vaccination_management.php">
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
                <h2><i class="fas fa-syringe me-2"></i>Vaccination Management</h2>
                <p class="text-muted">Manage COVID-19 vaccinations and records</p>
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
            <div class="col-md-2">
                <div class="stat-card primary">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="text-info"><?php echo $stats['total_vaccinations']; ?></h4>
                            <p class="mb-0 small">Total Vaccinations</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-syringe fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card success">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="text-success"><?php echo $stats['unique_patients']; ?></h4>
                            <p class="mb-0 small">Patients Vaccinated</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-users fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card warning">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="text-warning"><?php echo $stats['first_doses']; ?></h4>
                            <p class="mb-0 small">First Doses</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-play fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card info">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="text-info"><?php echo $stats['second_doses']; ?></h4>
                            <p class="mb-0 small">Second Doses</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-forward fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card purple">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 style="color: #6f42c1;"><?php echo $stats['boosters']; ?></h4>
                            <p class="mb-0 small">Boosters</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-plus fa-2x" style="color: #6f42c1;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Vaccination Appointments -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-plus me-2"></i>Vaccination Appointments</h5>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <?php if (count($vaccination_appointments) > 0): ?>
                            <?php foreach ($vaccination_appointments as $appointment): ?>
                                <div class="appointment-card">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h6 class="mb-1">
                                                <i class="fas fa-user me-2"></i>
                                                <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                            </h6>
                                            <p class="mb-1 text-muted small"><?php echo htmlspecialchars($appointment['patient_email']); ?></p>
                                            <p class="mb-0">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('M d, Y H:i A', strtotime($appointment['date'])); ?>
                                            </p>
                                        </div>
                                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" 
                                                data-bs-target="#addVaccinationModal" 
                                                onclick="setAppointmentData(<?php echo $appointment['id']; ?>, '<?php echo htmlspecialchars($appointment['patient_name']); ?>')">
                                            <i class="fas fa-syringe me-1"></i>Record Vaccination
                                        </button>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($appointment['patient_phone']); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No vaccination appointments</h6>
                                <p class="text-muted small">Vaccination appointments will appear here.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Vaccination Records -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history me-2"></i>Vaccination Records</h5>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <?php if (count($vaccination_records) > 0): ?>
                            <?php foreach ($vaccination_records as $record): ?>
                                <div class="vaccination-card">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-1">
                                                <i class="fas fa-user me-2"></i>
                                                <?php echo htmlspecialchars($record['patient_name']); ?>
                                            </h6>
                                            <p class="mb-1 text-muted small"><?php echo htmlspecialchars($record['patient_email']); ?></p>
                                        </div>
                                        <span class="badge bg-success">
                                            Dose <?php echo $record['dose_number']; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="row mb-2">
                                        <div class="col-6">
                                            <small class="text-muted">Vaccine</small>
                                            <p class="mb-0 small fw-bold">
                                                <?php echo htmlspecialchars($record['vaccine_name']); ?>
                                            </p>
                                            <p class="mb-0 small text-muted">
                                                <?php echo htmlspecialchars($record['manufacturer']); ?>
                                            </p>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Date</small>
                                            <p class="mb-0 small">
                                                <?php echo date('M d, Y', strtotime($record['date'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-syringe fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No vaccination records yet</h6>
                                <p class="text-muted small">Vaccination records will appear here once added.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Vaccination Modal -->
    <div class="modal fade" id="addVaccinationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Record Vaccination</h5>
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
                            <label for="vaccine_id" class="form-label">Vaccine *</label>
                            <select class="form-select" name="vaccine_id" required>
                                <option value="">Select vaccine</option>
                                <?php foreach ($vaccines as $vaccine): ?>
                                    <option value="<?php echo $vaccine['id']; ?>">
                                        <?php echo htmlspecialchars($vaccine['name']); ?> - <?php echo htmlspecialchars($vaccine['manufacturer']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="dose_number" class="form-label">Dose Number *</label>
                            <select class="form-select" name="dose_number" required>
                                <option value="">Select dose</option>
                                <option value="1">First Dose</option>
                                <option value="2">Second Dose</option>
                                <option value="3">Booster (3rd Dose)</option>
                                <option value="4">Booster (4th Dose)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="vaccination_date" class="form-label">Vaccination Date *</label>
                            <input type="date" class="form-control" name="vaccination_date" 
                                   max="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="3" 
                                      placeholder="Additional notes or observations..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_vaccination" class="btn btn-success">Record Vaccination</button>
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
        
        // Set default vaccination date to today
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.querySelector('input[name="vaccination_date"]');
            if (dateInput) {
                dateInput.value = new Date().toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>
