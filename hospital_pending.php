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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Approval Pending - COVID-19 Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .pending-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 600px;
            margin: 0 auto;
        }
        .pending-header {
            background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .pending-body {
            padding: 2rem;
        }
        .status-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="pending-card">
                    <div class="pending-header">
                        <div class="status-icon">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                        <h3>Hospital Approval Pending</h3>
                        <p class="mb-0">Your registration is under review</p>
                    </div>
                    <div class="pending-body">
                        <div class="text-center mb-4">
                            <h5>Thank you for registering with our COVID-19 Management System!</h5>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Status:</strong> Your hospital registration is currently <strong>pending approval</strong> from our administrators.
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6><i class="fas fa-hospital me-2"></i>Hospital Information</h6>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($hospital['name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($hospital['email']); ?></p>
                                <p><strong>Contact:</strong> <?php echo htmlspecialchars($hospital['contact']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-calendar me-2"></i>Registration Details</h6>
                                <p><strong>Submitted:</strong> <?php echo date('M d, Y', strtotime($hospital['created_at'])); ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-warning">
                                        <?php echo ucfirst($hospital['status']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6><i class="fas fa-list-check me-2"></i>What happens next?</h6>
                                <ol class="mb-0">
                                    <li>Our administrators will review your hospital registration</li>
                                    <li>We may contact you for additional verification if needed</li>
                                    <li>Once approved, you'll receive an email notification</li>
                                    <li>You can then access the full hospital management portal</li>
                                </ol>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <p class="text-muted">
                                <i class="fas fa-envelope me-2"></i>
                                You will be notified via email once your registration is approved.
                            </p>
                            <div class="btn-group">
                                <a href="logout.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a>
                                <button onclick="location.reload()" class="btn btn-primary">
                                    <i class="fas fa-refresh me-2"></i>Check Status
                                </button>
                            </div>
                        </div>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                Need help? Contact our support team at support@covid19system.com
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
