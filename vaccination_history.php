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

// Get all vaccinations for the patient
$query = "SELECT v.*, vac.name as vaccine_name, vac.manufacturer, vac.description
          FROM vaccinations v 
          JOIN vaccines vac ON v.vaccine_id = vac.id 
          WHERE v.patient_id = ? 
          ORDER BY v.date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $patient_id);
$stmt->execute();
$vaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get vaccination statistics
$stats = [];
$query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
FROM vaccinations WHERE patient_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $patient_id);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get available vaccines for booking
$query = "SELECT * FROM vaccines WHERE status = 'available' ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$available_vaccines = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccination History - COVID-19 Management System</title>
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
        .stat-card.warning { border-left-color: #ffc107; }
        .vaccination-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: white;
        }
        .vaccination-card.done { border-left: 4px solid #28a745; }
        .vaccination-card.pending { border-left: 4px solid #ffc107; }
        .vaccine-info-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
            transition: all 0.3s;
        }
        .vaccine-info-card:hover {
            border-color: #28a745;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
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
                <a class="nav-link" href="test_results.php">
                    <i class="fas fa-vial me-2"></i>Test Results
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="vaccination_history.php">
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
                <h2><i class="fas fa-syringe me-2"></i>Vaccination History</h2>
                <p class="text-muted">Track your COVID-19 vaccination progress</p>
            </div>
            <a href="book_appointment.php" class="btn btn-success">
                <i class="fas fa-plus me-2"></i>Book Vaccination
            </a>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card primary">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-info"><?php echo $stats['total']; ?></h3>
                            <p class="mb-0">Total Vaccinations</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-syringe fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card success">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-success"><?php echo $stats['completed']; ?></h3>
                            <p class="mb-0">Completed</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card warning">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-warning"><?php echo $stats['pending']; ?></h3>
                            <p class="mb-0">Pending</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Vaccination History -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history me-2"></i>My Vaccination Records</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($vaccinations) > 0): ?>
                            <?php foreach ($vaccinations as $vaccination): ?>
                                <div class="vaccination-card <?php echo $vaccination['status']; ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="mb-1">
                                                <i class="fas fa-syringe me-2"></i>
                                                <?php echo htmlspecialchars($vaccination['vaccine_name']); ?>
                                            </h5>
                                            <h6 class="text-muted"><?php echo htmlspecialchars($vaccination['manufacturer']); ?></h6>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?php echo $vaccination['status'] == 'done' ? 'success' : 'warning'; ?> mb-2">
                                                <?php echo ucfirst($vaccination['status']); ?>
                                            </span>
                                            <br>
                                            <span class="badge bg-info">Dose <?php echo $vaccination['dose_number']; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">Vaccination Date</small>
                                            <p class="mb-0">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('M d, Y', strtotime($vaccination['date'])); ?>
                                            </p>
                                            <p class="mb-0">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('h:i A', strtotime($vaccination['date'])); ?>
                                            </p>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Recorded</small>
                                            <p class="mb-0">
                                                <i class="fas fa-calendar-plus me-1"></i>
                                                <?php echo date('M d, Y', strtotime($vaccination['created_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <?php if ($vaccination['description']): ?>
                                        <div class="mb-2">
                                            <small class="text-muted">Vaccine Information</small>
                                            <p class="mb-0 small">
                                                <i class="fas fa-info-circle me-1"></i>
                                                <?php echo htmlspecialchars($vaccination['description']); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($vaccination['status'] == 'done'): ?>
                                        <div class="alert alert-success small mb-0">
                                            <i class="fas fa-shield-alt me-2"></i>
                                            <strong>Vaccination completed successfully!</strong> You are now protected with dose <?php echo $vaccination['dose_number']; ?>.
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning small mb-0">
                                            <i class="fas fa-clock me-2"></i>
                                            <strong>Vaccination scheduled.</strong> Please arrive on time for your appointment.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-syringe fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No vaccination records found</h6>
                                <p class="text-muted">You haven't received any COVID-19 vaccinations yet.</p>
                                <a href="book_appointment.php" class="btn btn-success">
                                    <i class="fas fa-plus me-2"></i>Book Vaccination Appointment
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Available Vaccines Info -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle me-2"></i>Available Vaccines</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($available_vaccines) > 0): ?>
                            <?php foreach ($available_vaccines as $vaccine): ?>
                                <div class="vaccine-info-card">
                                    <h6 class="text-success">
                                        <i class="fas fa-syringe me-2"></i>
                                        <?php echo htmlspecialchars($vaccine['name']); ?>
                                    </h6>
                                    <p class="small text-muted mb-2">
                                        <strong>Manufacturer:</strong> <?php echo htmlspecialchars($vaccine['manufacturer']); ?>
                                    </p>
                                    <?php if ($vaccine['description']): ?>
                                        <p class="small mb-0">
                                            <?php echo htmlspecialchars($vaccine['description']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="alert alert-info small">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> Consult with healthcare providers to determine the best vaccination schedule for you.
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-exclamation-triangle fa-2x text-muted mb-2"></i>
                                <p class="text-muted small mb-0">No vaccines currently available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Vaccination Guidelines -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5><i class="fas fa-shield-alt me-2"></i>Vaccination Guidelines</h5>
                    </div>
                    <div class="card-body">
                        <ul class="small mb-0">
                            <li>Follow the recommended dosage schedule</li>
                            <li>Bring your vaccination card to each appointment</li>
                            <li>Stay for observation period after vaccination</li>
                            <li>Report any side effects to your healthcare provider</li>
                            <li>Continue following safety protocols even after vaccination</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
