<?php
require_once 'config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    
    // Additional fields based on role
    $address = trim($_POST['address']);
    $contact = trim($_POST['contact']);
    
    if (!empty($name) && !empty($email) && !empty($password) && !empty($address) && !empty($contact)) {
        if ($password === $confirm_password) {
            if (strlen($password) >= 6) {
                $database = new Database();
                $db = $database->getConnection();
                
                // Check if email already exists
                $query = "SELECT id FROM users WHERE email = ?";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $email);
                $stmt->execute();
                
                if ($stmt->rowCount() == 0) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    try {
                        $db->beginTransaction();
                        
                        // Insert user
                        $query = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(1, $name);
                        $stmt->bindParam(2, $email);
                        $stmt->bindParam(3, $hashed_password);
                        $stmt->bindParam(4, $role);
                        $stmt->execute();
                        
                        $user_id = $db->lastInsertId();
                        
                        if ($role == 'patient') {
                            $dob = $_POST['dob'];
                            $phone = $_POST['phone'];
                            
                            $query = "INSERT INTO patients (user_id, address, dob, phone) VALUES (?, ?, ?, ?)";
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(1, $user_id);
                            $stmt->bindParam(2, $address);
                            $stmt->bindParam(3, $dob);
                            $stmt->bindParam(4, $phone);
                            $stmt->execute();
                        } elseif ($role == 'hospital') {
                            $query = "INSERT INTO hospitals (name, address, contact, status) VALUES (?, ?, ?, 'pending')";
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(1, $name);
                            $stmt->bindParam(2, $address);
                            $stmt->bindParam(3, $contact);
                            $stmt->execute();
                        }
                        
                        $db->commit();
                        $success = "Registration successful! Please login to continue.";
                        
                    } catch (Exception $e) {
                        $db->rollback();
                        $error = "Registration failed. Please try again.";
                    }
                } else {
                    $error = "Email already exists. Please use a different email.";
                }
            } else {
                $error = "Password must be at least 6 characters long.";
            }
        } else {
            $error = "Passwords do not match.";
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - COVID-19 Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .register-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin: 20px 0;
        }
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .register-body {
            padding: 2rem;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
        }
        .role-tabs {
            margin-bottom: 2rem;
        }
        .role-tabs .nav-link {
            color: #667eea;
            border: 2px solid #667eea;
            margin: 0 5px;
            border-radius: 25px;
        }
        .role-tabs .nav-link.active {
            background-color: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="register-card">
                    <div class="register-header">
                        <i class="fas fa-user-plus fa-3x mb-3"></i>
                        <h3>Create Account</h3>
                        <p class="mb-0">Join COVID-19 Management System</p>
                    </div>
                    <div class="register-body">
                        <!-- Role Selection Tabs -->
                        <ul class="nav nav-pills justify-content-center role-tabs" id="roleTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="patient-tab" data-bs-toggle="pill" 
                                        data-bs-target="#patient-form" type="button" role="tab">
                                    <i class="fas fa-user me-2"></i>Patient Registration
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="hospital-tab" data-bs-toggle="pill" 
                                        data-bs-target="#hospital-form" type="button" role="tab">
                                    <i class="fas fa-hospital me-2"></i>Hospital Registration
                                </button>
                            </li>
                        </ul>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                                <br><a href="login.php" class="alert-link">Click here to login</a>
                            </div>
                        <?php endif; ?>

                        <div class="tab-content" id="roleTabContent">
                            <!-- Patient Registration -->
                            <div class="tab-pane fade show active" id="patient-form" role="tabpanel">
                                <form method="POST" id="patientForm">
                                    <input type="hidden" name="role" value="patient">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label">Full Name *</label>
                                            <input type="text" class="form-control" name="name" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Email Address *</label>
                                            <input type="email" class="form-control" name="email" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="password" class="form-label">Password *</label>
                                            <input type="password" class="form-control" name="password" required>
                                            <small class="text-muted">Minimum 6 characters</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="confirm_password" class="form-label">Confirm Password *</label>
                                            <input type="password" class="form-control" name="confirm_password" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="dob" class="form-label">Date of Birth *</label>
                                            <input type="date" class="form-control" name="dob" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="phone" class="form-label">Phone Number *</label>
                                            <input type="tel" class="form-control" name="phone" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address *</label>
                                        <textarea class="form-control" name="address" rows="3" required></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="contact" class="form-label">Emergency Contact *</label>
                                        <input type="tel" class="form-control" name="contact" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary btn-register w-100">
                                        <i class="fas fa-user-plus me-2"></i>Register as Patient
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Hospital Registration -->
                            <div class="tab-pane fade" id="hospital-form" role="tabpanel">
                                <form method="POST" id="hospitalForm">
                                    <input type="hidden" name="role" value="hospital">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label">Hospital Name *</label>
                                            <input type="text" class="form-control" name="name" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Official Email *</label>
                                            <input type="email" class="form-control" name="email" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="password" class="form-label">Password *</label>
                                            <input type="password" class="form-control" name="password" required>
                                            <small class="text-muted">Minimum 6 characters</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="confirm_password" class="form-label">Confirm Password *</label>
                                            <input type="password" class="form-control" name="confirm_password" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Hospital Address *</label>
                                        <textarea class="form-control" name="address" rows="3" required></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="contact" class="form-label">Contact Number *</label>
                                        <input type="tel" class="form-control" name="contact" required>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Note:</strong> Hospital registrations require admin approval before you can access the system.
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary btn-register w-100">
                                        <i class="fas fa-hospital me-2"></i>Register Hospital
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <p class="mb-0">Already have an account? 
                                <a href="login.php" class="text-decoration-none">Login here</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Clear form when switching tabs
        document.querySelectorAll('[data-bs-toggle="pill"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function (event) {
                // Clear all form inputs when switching tabs
                document.querySelectorAll('form input, form textarea').forEach(input => {
                    if (input.type !== 'hidden') {
                        input.value = '';
                    }
                });
            });
        });
    </script>
</body>
</html>
