<?php
require_once 'config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Get statistics
$stats = [];

// Total patients
$query = "SELECT COUNT(*) as count FROM patients";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total hospitals
$query = "SELECT COUNT(*) as count FROM hospitals";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['hospitals'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Pending hospitals
$query = "SELECT COUNT(*) as count FROM hospitals WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['pending_hospitals'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total appointments
$query = "SELECT COUNT(*) as count FROM appointments";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Today's appointments
$query = "SELECT COUNT(*) as count FROM appointments WHERE DATE(date) = CURDATE()";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['today_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total vaccines
$query = "SELECT COUNT(*) as count FROM vaccines";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['vaccines'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Available vaccines
$query = "SELECT COUNT(*) as count FROM vaccines WHERE status = 'available'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['available_vaccines'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total vaccinations done
$query = "SELECT COUNT(*) as count FROM vaccinations WHERE status = 'done'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['vaccinations_done'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Recent activities
$query = "SELECT 'appointment' as type, CONCAT(u.name, ' booked ', a.type, ' appointment') as activity, a.created_at 
          FROM appointments a 
          JOIN patients p ON a.patient_id = p.id 
          JOIN users u ON p.user_id = u.id 
          UNION ALL
          SELECT 'hospital' as type, CONCAT(h.name, ' registered') as activity, h.created_at 
          FROM hospitals h 
          ORDER BY created_at DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - COVID-19 Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 4px solid;
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card.primary { border-left-color: #667eea; }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.info { border-left-color: #17a2b8; }
        .stat-card.danger { border-left-color: #dc3545; }
        .main-content { margin-left: 250px; padding: 20px; }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
            .sidebar { position: fixed; z-index: 1000; width: 250px; transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar position-fixed">
        <div class="p-4">
            <h4><i class="fas fa-virus me-2"></i>COVID-19 Admin</h4>
            <hr class="text-white-50">
        </div>
        <ul class="nav flex-column px-3">
            <li class="nav-item">
                <a class="nav-link active" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="hospitals.php">
                    <i class="fas fa-hospital me-2"></i>Manage Hospitals
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="vaccines.php">
                    <i class="fas fa-syringe me-2"></i>Manage Vaccines
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="patients.php">
                    <i class="fas fa-users me-2"></i>View Patients
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="appointments.php">
                    <i class="fas fa-calendar-alt me-2"></i>View Appointments
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
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Dashboard</h2>
                <p class="text-muted">Welcome back, <?php echo $_SESSION['admin_name']; ?>!</p>
            </div>
            <div class="text-end">
                <small class="text-muted">Last updated: <?php echo date('Y-m-d H:i:s'); ?></small>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card primary">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-primary"><?php echo $stats['patients']; ?></h3>
                            <p class="mb-0">Total Patients</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-users fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card success">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-success"><?php echo $stats['hospitals']; ?></h3>
                            <p class="mb-0">Total Hospitals</p>
                            <small class="text-warning"><?php echo $stats['pending_hospitals']; ?> pending</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-hospital fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card info">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-info"><?php echo $stats['appointments']; ?></h3>
                            <p class="mb-0">Total Appointments</p>
                            <small class="text-primary"><?php echo $stats['today_appointments']; ?> today</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-calendar-alt fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card warning">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-warning"><?php echo $stats['vaccines']; ?></h3>
                            <p class="mb-0">Vaccine Types</p>
                            <small class="text-success"><?php echo $stats['available_vaccines']; ?> available</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-syringe fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Stats Row -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card success">
                    <div class="text-center">
                        <h2 class="text-success"><?php echo $stats['vaccinations_done']; ?></h2>
                        <p class="mb-0">Vaccinations Completed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card primary">
                    <div class="text-center">
                        <h2 class="text-primary"><?php echo $stats['today_appointments']; ?></h2>
                        <p class="mb-0">Today's Appointments</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card warning">
                    <div class="text-center">
                        <h2 class="text-warning"><?php echo $stats['pending_hospitals']; ?></h2>
                        <p class="mb-0">Pending Hospital Approvals</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-clock me-2"></i>Recent Activities</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_activities) > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_activities as $activity): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-<?php echo $activity['type'] == 'appointment' ? 'calendar' : 'hospital'; ?> me-2 text-primary"></i>
                                            <?php echo $activity['activity']; ?>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No recent activities found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
