<?php
require_once 'config/database.php';
requireRole('patient');

$database = new Database();
$db = $database->getConnection();
$user_id = getUserId();

$message = '';
$message_type = '';

// Get patient information
$query = "SELECT p.*, u.name, u.email FROM patients p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $dob = $_POST['dob'];
    
    if (!empty($name) && !empty($email) && !empty($phone) && !empty($address) && !empty($dob)) {
        try {
            $db->beginTransaction();
            
            // Update user table
            $query = "UPDATE users SET name = ?, email = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $name);
            $stmt->bindParam(2, $email);
            $stmt->bindParam(3, $user_id);
            $stmt->execute();
            
            // Update patient table
            $query = "UPDATE patients SET phone = ?, address = ?, dob = ? WHERE user_id = ?";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $phone);
            $stmt->bindParam(2, $address);
            $stmt->bindParam(3, $dob);
            $stmt->bindParam(4, $user_id);
            $stmt->execute();
            
            $db->commit();
            
            // Update session
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            
            $message = "Profile updated successfully!";
            $message_type = 'success';
            
            // Refresh patient data
            $query = "SELECT p.*, u.name, u.email FROM patients p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $user_id);
            $stmt->execute();
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $db->rollback();
            $message = "Error updating profile. Please try again.";
            $message_type = 'danger';
        }
    } else {
        $message = "Please fill in all fields.";
        $message_type = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - COVID-19 Management System</title>
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
        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            margin: 0 auto 1rem;
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
                <a class="nav-link" href="vaccination_history.php">
                    <i class="fas fa-syringe me-2"></i>Vaccination History
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="profile.php">
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
                <h2><i class="fas fa-user-edit me-2"></i>My Profile</h2>
                <p class="text-muted">Manage your personal information</p>
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
            <div class="col-md-4">
                <div class="profile-card text-center">
                    <div class="profile-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <h4><?php echo htmlspecialchars($patient['name']); ?></h4>
                    <p class="text-muted"><?php echo htmlspecialchars($patient['email']); ?></p>
                    <p class="text-muted">
                        <i class="fas fa-calendar me-1"></i>
                        Member since <?php echo date('M Y', strtotime($patient['created_at'])); ?>
                    </p>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="profile-card">
                    <h5 class="mb-4"><i class="fas fa-edit me-2"></i>Edit Profile Information</h5>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="name" 
                                       value="<?php echo htmlspecialchars($patient['name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo htmlspecialchars($patient['email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number *</label>
                                <input type="tel" class="form-control" name="phone" 
                                       value="<?php echo htmlspecialchars($patient['phone']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="dob" class="form-label">Date of Birth *</label>
                                <input type="date" class="form-control" name="dob" 
                                       value="<?php echo $patient['dob']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="address" class="form-label">Address *</label>
                            <textarea class="form-control" name="address" rows="3" required><?php echo htmlspecialchars($patient['address']); ?></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                <i class="fas fa-undo me-2"></i>Reset Changes
                            </button>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Account Information -->
                <div class="profile-card mt-4">
                    <h5 class="mb-4"><i class="fas fa-info-circle me-2"></i>Account Information</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Patient ID:</strong> #<?php echo str_pad($patient['id'], 6, '0', STR_PAD_LEFT); ?></p>
                            <p><strong>Account Created:</strong> <?php echo date('M d, Y', strtotime($patient['created_at'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Account Type:</strong> Patient</p>
                            <p><strong>Status:</strong> <span class="badge bg-success">Active</span></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-shield-alt me-2"></i>
                        <strong>Privacy Notice:</strong> Your personal information is securely stored and only shared with authorized healthcare providers for your appointments and medical records.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function resetForm() {
            if (confirm('Are you sure you want to reset all changes?')) {
                location.reload();
            }
        }
    </script>
</body>
</html>
