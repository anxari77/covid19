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

// Handle appointment cancellation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    
    // Only allow cancellation of pending appointments
    $query = "UPDATE appointments SET status = 'cancelled' WHERE id = ? AND patient_id = ? AND status = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $appointment_id);
    $stmt->bindParam(2, $patient_id);
    
    if ($stmt->execute() && $stmt->rowCount() > 0) {
        $message = "Appointment cancelled successfully.";
        $message_type = 'success';
    } else {
        $message = "Unable to cancel appointment. Only pending appointments can be cancelled.";
        $message_type = 'danger';
    }
}

// Get all appointments for the patient
$query = "SELECT a.*, h.name as hospital_name, h.address as hospital_address, h.contact as hospital_contact
          FROM appointments a 
          JOIN hospitals h ON a.hospital_id = h.id 
          WHERE a.patient_id = ? 
          ORDER BY a.date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $patient_id);
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get appointment statistics
$stats = [];
$query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
FROM appointments WHERE patient_id = ?";
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
    <title>My Appointments - COVID-19 Management System</title>
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
            padding: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid;
        }
        .stat-card.primary { border-left-color: #28a745; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.success { border-left-color: #20c997; }
        .stat-card.danger { border-left-color: #dc3545; }
        .stat-card.secondary { border-left-color: #6c757d; }
        .appointment-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: white;
        }
        .appointment-card.approved { border-left: 4px solid #28a745; }
        .appointment-card.pending { border-left: 4px solid #ffc107; }
        .appointment-card.rejected { border-left: 4px solid #dc3545; }
        .appointment-card.cancelled { border-left: 4px solid #6c757d; }
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
                <a class="nav-link active" href="my_appointments.php">
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-calendar-alt me-2"></i>My Appointments</h2>
                <p class="text-muted">View and manage your COVID-19 appointments</p>
            </div>
            <a href="book_appointment.php" class="btn btn-success">
                <i class="fas fa-plus me-2"></i>Book New Appointment
            </a>
        </div>

        <?php if (isset($message)): ?>
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
                    <h4 class="text-success"><?php echo $stats['total']; ?></h4>
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
                <div class="stat-card secondary">
                    <h4 class="text-secondary"><?php echo $stats['cancelled']; ?></h4>
                    <p class="mb-0 small">Cancelled</p>
                </div>
            </div>
        </div>

        <!-- Appointments List -->
        <?php if (count($appointments) > 0): ?>
            <div class="row">
                <?php foreach ($appointments as $appointment): ?>
                    <div class="col-md-6 mb-3">
                        <div class="appointment-card <?php echo $appointment['status']; ?>">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="mb-1">
                                        <span class="badge bg-<?php echo $appointment['type'] == 'test' ? 'info' : 'success'; ?> me-2">
                                            <i class="fas fa-<?php echo $appointment['type'] == 'test' ? 'vial' : 'syringe'; ?> me-1"></i>
                                            <?php echo ucfirst($appointment['type']); ?>
                                        </span>
                                    </h5>
                                    <h6 class="text-primary"><?php echo htmlspecialchars($appointment['hospital_name']); ?></h6>
                                </div>
                                <span class="badge bg-<?php 
                                    echo $appointment['status'] == 'approved' ? 'success' : 
                                        ($appointment['status'] == 'rejected' ? 'danger' : 
                                        ($appointment['status'] == 'cancelled' ? 'secondary' : 'warning')); 
                                ?>">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Date & Time</small>
                                    <p class="mb-0">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('M d, Y', strtotime($appointment['date'])); ?>
                                    </p>
                                    <p class="mb-0">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('h:i A', strtotime($appointment['date'])); ?>
                                    </p>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Hospital Contact</small>
                                    <p class="mb-0">
                                        <i class="fas fa-phone me-1"></i>
                                        <?php echo htmlspecialchars($appointment['hospital_contact']); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted">Address</small>
                                <p class="mb-0">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?php echo htmlspecialchars($appointment['hospital_address']); ?>
                                </p>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    Booked: <?php echo date('M d, Y', strtotime($appointment['created_at'])); ?>
                                </small>
                                
                                <?php if ($appointment['status'] == 'pending'): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this appointment?')">
                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                        <button type="submit" name="cancel_appointment" class="btn btn-outline-danger btn-sm">
                                            <i class="fas fa-times me-1"></i>Cancel
                                        </button>
                                    </form>
                                <?php elseif ($appointment['status'] == 'approved'): ?>
                                    <span class="text-success small">
                                        <i class="fas fa-check-circle me-1"></i>Confirmed
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No appointments found</h5>
                <p class="text-muted">You haven't booked any appointments yet.</p>
                <a href="book_appointment.php" class="btn btn-success">
                    <i class="fas fa-plus me-2"></i>Book Your First Appointment
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
