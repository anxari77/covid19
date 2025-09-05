<?php
require_once 'config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['id'])) {
    echo "Patient ID not provided.";
    exit;
}

$patient_id = $_GET['id'];

// Get patient details
$query = "SELECT p.*, u.name, u.email, u.created_at as user_created_at 
          FROM patients p 
          JOIN users u ON p.user_id = u.id 
          WHERE p.id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $patient_id);
$stmt->execute();
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    echo "Patient not found.";
    exit;
}

// Get patient appointments
$query = "SELECT a.*, h.name as hospital_name, h.address as hospital_address 
          FROM appointments a 
          JOIN hospitals h ON a.hospital_id = h.id 
          WHERE a.patient_id = ? 
          ORDER BY a.date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $patient_id);
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get patient vaccinations
$query = "SELECT v.*, vac.name as vaccine_name, vac.manufacturer 
          FROM vaccinations v 
          JOIN vaccines vac ON v.vaccine_id = vac.id 
          WHERE v.patient_id = ? 
          ORDER BY v.date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $patient_id);
$stmt->execute();
$vaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get test results
$query = "SELECT tr.*, a.date as test_date, h.name as hospital_name 
          FROM test_results tr 
          JOIN appointments a ON tr.appointment_id = a.id 
          JOIN hospitals h ON a.hospital_id = h.id 
          WHERE a.patient_id = ? 
          ORDER BY tr.updated_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $patient_id);
$stmt->execute();
$test_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-md-6">
        <h6><i class="fas fa-user me-2"></i>Personal Information</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Name:</strong></td>
                <td><?php echo htmlspecialchars($patient['name']); ?></td>
            </tr>
            <tr>
                <td><strong>Email:</strong></td>
                <td><?php echo htmlspecialchars($patient['email']); ?></td>
            </tr>
            <tr>
                <td><strong>Phone:</strong></td>
                <td><?php echo htmlspecialchars($patient['phone']); ?></td>
            </tr>
            <tr>
                <td><strong>Date of Birth:</strong></td>
                <td><?php echo date('M d, Y', strtotime($patient['dob'])); ?></td>
            </tr>
            <tr>
                <td><strong>Address:</strong></td>
                <td><?php echo htmlspecialchars($patient['address']); ?></td>
            </tr>
            <tr>
                <td><strong>Registered:</strong></td>
                <td><?php echo date('M d, Y H:i', strtotime($patient['created_at'])); ?></td>
            </tr>
        </table>
    </div>
    <div class="col-md-6">
        <h6><i class="fas fa-chart-bar me-2"></i>Summary</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Total Appointments:</strong></td>
                <td><?php echo count($appointments); ?></td>
            </tr>
            <tr>
                <td><strong>Total Vaccinations:</strong></td>
                <td><?php echo count($vaccinations); ?></td>
            </tr>
            <tr>
                <td><strong>Test Results:</strong></td>
                <td><?php echo count($test_results); ?></td>
            </tr>
        </table>
    </div>
</div>

<hr>

<!-- Appointments -->
<h6><i class="fas fa-calendar-alt me-2"></i>Appointments</h6>
<?php if (count($appointments) > 0): ?>
    <div class="table-responsive">
        <table class="table table-sm table-striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Hospital</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td><?php echo date('M d, Y H:i', strtotime($appointment['date'])); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $appointment['type'] == 'test' ? 'info' : 'success'; ?>">
                                <?php echo ucfirst($appointment['type']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($appointment['hospital_name']); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $appointment['status'] == 'approved' ? 'success' : 
                                    ($appointment['status'] == 'rejected' ? 'danger' : 'warning'); 
                            ?>">
                                <?php echo ucfirst($appointment['status']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p class="text-muted">No appointments found.</p>
<?php endif; ?>

<hr>

<!-- Vaccinations -->
<h6><i class="fas fa-syringe me-2"></i>Vaccinations</h6>
<?php if (count($vaccinations) > 0): ?>
    <div class="table-responsive">
        <table class="table table-sm table-striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Vaccine</th>
                    <th>Dose</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vaccinations as $vaccination): ?>
                    <tr>
                        <td><?php echo date('M d, Y H:i', strtotime($vaccination['date'])); ?></td>
                        <td>
                            <?php echo htmlspecialchars($vaccination['vaccine_name']); ?>
                            <small class="text-muted">(<?php echo htmlspecialchars($vaccination['manufacturer']); ?>)</small>
                        </td>
                        <td>Dose <?php echo $vaccination['dose_number']; ?></td>
                        <td>
                            <span class="badge bg-<?php echo $vaccination['status'] == 'done' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($vaccination['status']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p class="text-muted">No vaccinations found.</p>
<?php endif; ?>

<hr>

<!-- Test Results -->
<h6><i class="fas fa-vial me-2"></i>Test Results</h6>
<?php if (count($test_results) > 0): ?>
    <div class="table-responsive">
        <table class="table table-sm table-striped">
            <thead>
                <tr>
                    <th>Test Date</th>
                    <th>Hospital</th>
                    <th>Result</th>
                    <th>Remarks</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($test_results as $result): ?>
                    <tr>
                        <td><?php echo date('M d, Y H:i', strtotime($result['test_date'])); ?></td>
                        <td><?php echo htmlspecialchars($result['hospital_name']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $result['result'] == 'negative' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($result['result']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($result['remarks']); ?></td>
                        <td><?php echo date('M d, Y H:i', strtotime($result['updated_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p class="text-muted">No test results found.</p>
<?php endif; ?>
