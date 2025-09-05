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

$message = '';
$message_type = '';

// Handle appointment booking
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $hospital_id = $_POST['hospital_id'];
    $type = $_POST['type'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    
    $appointment_datetime = $date . ' ' . $time;
    
    if (!empty($hospital_id) && !empty($type) && !empty($date) && !empty($time)) {
        // Check if appointment time is in the future
        if (strtotime($appointment_datetime) > time()) {
            $query = "INSERT INTO appointments (patient_id, hospital_id, type, date) VALUES (?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $patient_id);
            $stmt->bindParam(2, $hospital_id);
            $stmt->bindParam(3, $type);
            $stmt->bindParam(4, $appointment_datetime);
            
            if ($stmt->execute()) {
                $message = "Appointment booked successfully! You will be notified once it's approved.";
                $message_type = 'success';
            } else {
                $message = "Error booking appointment. Please try again.";
                $message_type = 'danger';
            }
        } else {
            $message = "Please select a future date and time.";
            $message_type = 'danger';
        }
    } else {
        $message = "Please fill in all fields.";
        $message_type = 'danger';
    }
}

// Get approved hospitals
$query = "SELECT * FROM hospitals WHERE status = 'approved' ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - COVID-19 Management System</title>
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
        .hospital-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .hospital-card:hover {
            border-color: #28a745;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .hospital-card.selected {
            border-color: #28a745;
            background-color: #f8fff9;
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
                <a class="nav-link active" href="book_appointment.php">
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-calendar-plus me-2"></i>Book Appointment</h2>
                <p class="text-muted">Schedule your COVID-19 test or vaccination appointment</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-hospital me-2"></i>Select Hospital</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($hospitals) > 0): ?>
                            <form method="POST" id="appointmentForm">
                                <div class="row">
                                    <?php foreach ($hospitals as $hospital): ?>
                                        <div class="col-md-6">
                                            <div class="hospital-card" onclick="selectHospital(<?php echo $hospital['id']; ?>)">
                                                <input type="radio" name="hospital_id" value="<?php echo $hospital['id']; ?>" 
                                                       id="hospital_<?php echo $hospital['id']; ?>" style="display: none;">
                                                <h6><i class="fas fa-hospital me-2"></i><?php echo htmlspecialchars($hospital['name']); ?></h6>
                                                <p class="text-muted small mb-2">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?php echo htmlspecialchars($hospital['address']); ?>
                                                </p>
                                                <p class="text-muted small mb-0">
                                                    <i class="fas fa-phone me-1"></i>
                                                    <?php echo htmlspecialchars($hospital['contact']); ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <hr>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="type" class="form-label">Appointment Type</label>
                                        <select class="form-select" name="type" required>
                                            <option value="">Select type</option>
                                            <option value="test">COVID-19 Test</option>
                                            <option value="vaccination">Vaccination</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="date" class="form-label">Date</label>
                                        <input type="date" class="form-control" name="date" 
                                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="time" class="form-label">Time</label>
                                        <select class="form-select" name="time" required>
                                            <option value="">Select time</option>
                                            <option value="09:00">09:00 AM</option>
                                            <option value="10:00">10:00 AM</option>
                                            <option value="11:00">11:00 AM</option>
                                            <option value="12:00">12:00 PM</option>
                                            <option value="14:00">02:00 PM</option>
                                            <option value="15:00">03:00 PM</option>
                                            <option value="16:00">04:00 PM</option>
                                            <option value="17:00">05:00 PM</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-success btn-lg" id="bookBtn" disabled>
                                    <i class="fas fa-calendar-check me-2"></i>Book Appointment
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-hospital fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No hospitals available</h5>
                                <p class="text-muted">No approved hospitals found. Please check back later.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle me-2"></i>Appointment Guidelines</h5>
                    </div>
                    <div class="card-body">
                        <h6><i class="fas fa-vial me-2"></i>COVID-19 Testing</h6>
                        <ul class="small">
                            <li>Arrive 15 minutes before your appointment</li>
                            <li>Bring a valid ID</li>
                            <li>Wear a mask at all times</li>
                            <li>Results available within 24-48 hours</li>
                        </ul>
                        
                        <h6><i class="fas fa-syringe me-2"></i>Vaccination</h6>
                        <ul class="small">
                            <li>Bring vaccination card (if any)</li>
                            <li>Stay for 15-30 minutes after vaccination</li>
                            <li>Inform about any allergies</li>
                            <li>Wear comfortable clothing</li>
                        </ul>
                        
                        <div class="alert alert-warning small">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Note:</strong> All appointments require approval from the hospital. 
                            You will be notified via email once approved.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectHospital(hospitalId) {
            // Remove selected class from all cards
            document.querySelectorAll('.hospital-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            event.currentTarget.classList.add('selected');
            
            // Check the radio button
            document.getElementById('hospital_' + hospitalId).checked = true;
            
            // Enable book button if hospital is selected
            checkFormValidity();
        }
        
        function checkFormValidity() {
            const hospitalSelected = document.querySelector('input[name="hospital_id"]:checked');
            const bookBtn = document.getElementById('bookBtn');
            
            if (hospitalSelected) {
                bookBtn.disabled = false;
            } else {
                bookBtn.disabled = true;
            }
        }
        
        // Check form validity on input changes
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('appointmentForm');
            if (form) {
                form.addEventListener('change', checkFormValidity);
            }
        });
    </script>
</body>
</html>
