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

$message = '';
$message_type = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $hospital_name = trim($_POST['hospital_name']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $contact = trim($_POST['contact']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validate required fields
    if (empty($hospital_name)) $errors[] = "Hospital name is required.";
    if (empty($email)) $errors[] = "Email is required.";
    if (empty($address)) $errors[] = "Address is required.";
    if (empty($contact)) $errors[] = "Contact is required.";
    
    // Validate email format
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    // Validate phone number
    if (!empty($contact) && !preg_match('/^[\d\s\-\+\(\)]+$/', $contact)) {
        $errors[] = "Please enter a valid contact number.";
    }
    
    // Check if email is already taken by another user
    if (!empty($email)) {
        $query = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $email);
        $stmt->bindParam(2, $user_id);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $errors[] = "Email address is already registered.";
        }
    }
    
    // Password validation if changing password
    if (!empty($new_password)) {
        if (empty($current_password)) {
            $errors[] = "Current password is required to change password.";
        } else {
            // Verify current password
            $query = "SELECT password FROM users WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $user_id);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!password_verify($current_password, $user['password'])) {
                $errors[] = "Current password is incorrect.";
            }
        }
        
        if (strlen($new_password) < 6) {
            $errors[] = "New password must be at least 6 characters long.";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "New password and confirmation do not match.";
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Update user table
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $query = "UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $hospital_name);
                $stmt->bindParam(2, $email);
                $stmt->bindParam(3, $hashed_password);
                $stmt->bindParam(4, $user_id);
            } else {
                $query = "UPDATE users SET name = ?, email = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $hospital_name);
                $stmt->bindParam(2, $email);
                $stmt->bindParam(3, $user_id);
            }
            $stmt->execute();
            
            // Update hospital table
            $query = "UPDATE hospitals SET name = ?, address = ?, contact = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $hospital_name);
            $stmt->bindParam(2, $address);
            $stmt->bindParam(3, $contact);
            $stmt->bindParam(4, $hospital['id']);
            $stmt->execute();
            
            $db->commit();
            
            $message = "Profile updated successfully!";
            $message_type = 'success';
            
            // Refresh hospital data
            $query = "SELECT h.*, u.name, u.email FROM hospitals h JOIN users u ON u.name = h.name WHERE u.id = ?";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $user_id);
            $stmt->execute();
            $hospital = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $db->rollback();
            $message = "Error updating profile. Please try again.";
            $message_type = 'danger';
        }
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'danger';
    }
}

// Get hospital statistics
$hospital_id = $hospital['id'];

$query = "SELECT 
    COUNT(CASE WHEN a.status = 'pending' THEN 1 END) as pending_appointments,
    COUNT(CASE WHEN a.status = 'approved' THEN 1 END) as approved_appointments,
    COUNT(CASE WHEN a.type = 'test' THEN 1 END) as test_appointments,
    COUNT(CASE WHEN a.type = 'vaccination' THEN 1 END) as vaccination_appointments
FROM appointments a 
WHERE a.hospital_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $hospital_id);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get test results statistics
$query = "SELECT 
    COUNT(*) as total_results,
    COUNT(CASE WHEN tr.result = 'positive' THEN 1 END) as positive_results,
    COUNT(CASE WHEN tr.result = 'negative' THEN 1 END) as negative_results
FROM test_results tr 
JOIN appointments a ON tr.appointment_id = a.id 
WHERE a.hospital_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $hospital_id);
$stmt->execute();
$test_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get vaccination statistics
$query = "SELECT 
    COUNT(DISTINCT v.id) as total_vaccinations,
    COUNT(DISTINCT v.patient_id) as unique_patients
FROM vaccinations v 
JOIN patients p ON v.patient_id = p.id 
JOIN appointments a ON a.patient_id = p.id AND a.type = 'vaccination'
WHERE a.hospital_id = ? AND v.status = 'done'";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $hospital_id);
$stmt->execute();
$vaccination_stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Profile - COVID-19 Management System</title>
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
        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .profile-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid;
            margin-bottom: 1rem;
        }
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
                <a class="nav-link" href="vaccination_management.php">
                    <i class="fas fa-syringe me-2"></i>Vaccinations
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="hospital_profile.php">
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
                <h2><i class="fas fa-building me-2"></i>Hospital Profile</h2>
                <p class="text-muted">Manage your hospital information and settings</p>
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
            <!-- Profile Information -->
            <div class="col-md-8">
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <i class="fas fa-hospital"></i>
                        </div>
                        <h3><?php echo htmlspecialchars($hospital['name']); ?></h3>
                        <p class="mb-0 opacity-75"><?php echo htmlspecialchars($hospital['email']); ?></p>
                        <small class="opacity-75">
                            <i class="fas fa-calendar me-1"></i>
                            Registered: <?php echo date('M d, Y', strtotime($hospital['created_at'])); ?>
                        </small>
                    </div>
                    
                    <div class="p-4">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="hospital_name" class="form-label">Hospital Name *</label>
                                    <input type="text" class="form-control" name="hospital_name" 
                                           value="<?php echo htmlspecialchars($hospital['name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($hospital['email']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address *</label>
                                <textarea class="form-control" name="address" rows="3" required><?php echo htmlspecialchars($hospital['address']); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="contact" class="form-label">Contact Number *</label>
                                <input type="text" class="form-control" name="contact" 
                                       value="<?php echo htmlspecialchars($hospital['contact']); ?>" required>
                            </div>
                            
                            <hr class="my-4">
                            <h5 class="mb-3"><i class="fas fa-lock me-2"></i>Change Password</h5>
                            <p class="text-muted small">Leave password fields empty if you don't want to change your password.</p>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" name="current_password">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" name="new_password">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" name="confirm_password">
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="col-md-4">
                <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Hospital Statistics</h5>
                
                <div class="stat-card primary">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="text-primary"><?php echo $stats['pending_appointments']; ?></h4>
                            <p class="mb-0 small">Pending Appointments</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="text-success"><?php echo $stats['approved_appointments']; ?></h4>
                            <p class="mb-0 small">Approved Appointments</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="text-warning"><?php echo $stats['test_appointments']; ?></h4>
                            <p class="mb-0 small">Test Appointments</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-vial fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card info">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="text-info"><?php echo $stats['vaccination_appointments']; ?></h4>
                            <p class="mb-0 small">Vaccination Appointments</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-syringe fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
                
                <hr class="my-4">
                <h6 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Service Statistics</h6>
                
                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="card-title"><i class="fas fa-vial me-2"></i>Test Results</h6>
                        <div class="row text-center">
                            <div class="col-4">
                                <h5 class="text-primary"><?php echo $test_stats['total_results']; ?></h5>
                                <small class="text-muted">Total</small>
                            </div>
                            <div class="col-4">
                                <h5 class="text-success"><?php echo $test_stats['negative_results']; ?></h5>
                                <small class="text-muted">Negative</small>
                            </div>
                            <div class="col-4">
                                <h5 class="text-danger"><?php echo $test_stats['positive_results']; ?></h5>
                                <small class="text-muted">Positive</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title"><i class="fas fa-syringe me-2"></i>Vaccinations</h6>
                        <div class="row text-center">
                            <div class="col-6">
                                <h5 class="text-success"><?php echo $vaccination_stats['total_vaccinations']; ?></h5>
                                <small class="text-muted">Total Doses</small>
                            </div>
                            <div class="col-6">
                                <h5 class="text-info"><?php echo $vaccination_stats['unique_patients']; ?></h5>
                                <small class="text-muted">Patients</small>
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
