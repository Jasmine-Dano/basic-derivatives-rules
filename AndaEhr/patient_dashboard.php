<?php
require_once 'common_functions.php';

// Check if user is patient
$user = checkRoleAccess(['patient']);
$user_id = $user['user_id'];
$user_role = $user['user_role'];
$user_name = $user['user_name'];

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit();
}

// Get patient info
$patient = getPatientByUserId($user_id);
$patient_id = $patient ? $patient['patient_id'] : null;

// Get consultations
$consultations = $patient_id ? getConsultationsByRole($user_id, $user_role, $patient_id) : [];
$stats = getDashboardStats($user_role, $user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Health - Anda EHR</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2ecc71;
            --secondary-color: #27ae60;
            --light-bg: #f8f9fa;
            --border-color: #dee2e6;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 0;
        }
        
        .patient-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header */
        .patient-header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .patient-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .patient-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            font-weight: bold;
        }
        
        .patient-details h1 {
            margin: 0 0 5px 0;
            color: #2c3e50;
        }
        
        .patient-id {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .logout-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: #c0392b;
        }
        
        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            text-align: center;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin: 0 auto 15px;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        /* Health Card */
        .health-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .card-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2c3e50;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-bg);
        }
        
        .health-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            padding: 15px;
            background: var(--light-bg);
            border-radius: 8px;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        /* Consultations */
        .consultation-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .consultation-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            border-left: 4px solid var(--primary-color);
        }
        
        .consultation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .consultation-date {
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .consultation-status {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .vital-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .vital-label {
            font-size: 0.85rem;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        
        .vital-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .medical-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .doctor-orders {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            line-height: 1.6;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #7f8c8d;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #bdc3c7;
            margin-bottom: 20px;
        }
        
        .no-data {
            color: #95a5a6;
            font-style: italic;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .action-btn {
            flex: 1;
            background: white;
            border: 2px solid var(--border-color);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
        }
        
        .action-btn:hover {
            border-color: var(--primary-color);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .action-icon {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .patient-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .patient-info {
                flex-direction: column;
                text-align: center;
            }
            
            .quick-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="patient-container">
        <!-- Header -->
        <div class="patient-header">
            <div class="patient-info">
                <div class="patient-avatar">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
                <div class="patient-details">
                    <h1><?php echo htmlspecialchars($user_name); ?></h1>
                    <?php if ($patient): ?>
                        <div class="patient-id">
                            <i class="fas fa-id-card"></i> 
                            Patient ID: <?php echo htmlspecialchars($patient['patient_id']); ?>
                        </div>
                        <div class="patient-id">
                            <i class="fas fa-phone"></i> 
                            <?php echo htmlspecialchars($patient['contact']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <button class="logout-btn" onclick="window.location.href='?logout=true'">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-stethoscope"></i>
                </div>
                <div class="stat-value"><?php echo $stats['my_consultations'] ?? 0; ?></div>
                <div class="stat-label">Total Consultations</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['completed_consultations'] ?? 0; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-ambulance"></i>
                </div>
                <div class="stat-value"><?php echo $stats['emergency_visits'] ?? 0; ?></div>
                <div class="stat-label">Emergency Visits</div>
            </div>
        </div>
        
        <!-- Personal Health Information -->
        <div class="health-card">
            <h2 class="card-title">
                <i class="fas fa-user-circle"></i>
                Personal Health Information
            </h2>
            
            <div class="health-info-grid">
                <?php if ($patient): ?>
                    <div class="info-item">
                        <div class="info-label">Date of Birth</div>
                        <div class="info-value">
                            <?php 
                            echo $patient['dob'] ? date('F d, Y', strtotime($patient['dob'])) : 'Not provided';
                            ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Age</div>
                        <div class="info-value">
                            <?php echo $patient['age'] ? $patient['age'] . ' years' : 'Not provided'; ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Sex</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($patient['sex'] ?? 'Not provided'); ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Address</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($patient['address'] ?? 'Not provided'); ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Date Registered</div>
                        <div class="info-value">
                            <?php 
                            echo $patient['date_registered'] ? date('F d, Y', strtotime($patient['date_registered'])) : 'Not available';
                            ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Unique ID</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($patient['unique_id'] ?? 'Not assigned'); ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <div class="info-value">Patient information not found</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Consultation History -->
        <div class="health-card">
            <h2 class="card-title">
                <i class="fas fa-history"></i>
                My Consultation History
            </h2>
            
            <div class="consultation-list">
                <?php if (!empty($consultations)): ?>
                    <?php foreach ($consultations as $consultation): ?>
                        <div class="consultation-card">
                            <div class="consultation-header">
                                <div class="consultation-date">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo date('F d, Y - h:i A', strtotime($consultation['consultation_date'])); ?>
                                </div>
                                <div class="consultation-status <?php echo $consultation['workflow_status'] == 'completed' ? 'status-completed' : 'status-pending'; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $consultation['workflow_status'])); ?>
                                </div>
                            </div>
                            
                            <?php if ($consultation['temperature'] || $consultation['blood_pressure'] || $consultation['pulse']): ?>
                                <div class="vitals-grid">
                                    <?php if ($consultation['temperature']): ?>
                                        <div class="vital-item">
                                            <div class="vital-label">Temperature</div>
                                            <div class="vital-value"><?php echo $consultation['temperature']; ?>°C</div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($consultation['blood_pressure']): ?>
                                        <div class="vital-item">
                                            <div class="vital-label">Blood Pressure</div>
                                            <div class="vital-value"><?php echo $consultation['blood_pressure']; ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($consultation['pulse']): ?>
                                        <div class="vital-item">
                                            <div class="vital-label">Pulse</div>
                                            <div class="vital-value"><?php echo $consultation['pulse']; ?> bpm</div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($consultation['respiratory_rate']): ?>
                                        <div class="vital-item">
                                            <div class="vital-label">Respiratory Rate</div>
                                            <div class="vital-value"><?php echo $consultation['respiratory_rate']; ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($consultation['oxygen_saturation']): ?>
                                        <div class="vital-item">
                                            <div class="vital-label">Oxygen Saturation</div>
                                            <div class="vital-value"><?php echo $consultation['oxygen_saturation']; ?>%</div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($consultation['weight']): ?>
                                        <div class="vital-item">
                                            <div class="vital-label">Weight</div>
                                            <div class="vital-value"><?php echo $consultation['weight']; ?> kg</div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($consultation['height']): ?>
                                        <div class="vital-item">
                                            <div class="vital-label">Height</div>
                                            <div class="vital-value"><?php echo $consultation['height']; ?> cm</div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($consultation['bmi']): ?>
                                        <div class="vital-item">
                                            <div class="vital-label">BMI</div>
                                            <div class="vital-value"><?php echo $consultation['bmi']; ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="no-data">No vital signs recorded for this consultation.</div>
                            <?php endif; ?>
                            
                            <?php if ($consultation['doctor_orders'] || $consultation['doctor_notes']): ?>
                                <div class="medical-section">
                                    <h3 class="section-title">
                                        <i class="fas fa-file-medical-alt"></i>
                                        Doctor's Assessment
                                    </h3>
                                    
                                    <?php if ($consultation['doctor_orders']): ?>
                                        <div class="doctor-orders">
                                            <strong>Orders:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($consultation['doctor_orders'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($consultation['doctor_notes']): ?>
                                        <div class="doctor-orders" style="margin-top: 15px;">
                                            <strong>Notes:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($consultation['doctor_notes'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No Consultation History</h3>
                        <p>You haven't had any consultations yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="#" class="action-btn">
                <div class="action-icon">
                    <i class="fas fa-calendar-plus"></i>
                </div>
                <div>Book Appointment</div>
            </a>
            
            <a href="#" class="action-btn">
                <div class="action-icon">
                    <i class="fas fa-prescription"></i>
                </div>
                <div>View Prescriptions</div>
            </a>
            
            <a href="#" class="action-btn">
                <div class="action-icon">
                    <i class="fas fa-file-medical"></i>
                </div>
                <div>Medical Reports</div>
            </a>
            
            <a href="#" class="action-btn">
                <div class="action-icon">
                    <i class="fas fa-user-edit"></i>
                </div>
                <div>Update Profile</div>
            </a>
        </div>
    </div>
    
    <script>
        // Simple interactions
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                alert('This feature is coming soon!');
            });
        });
    </script>
</body>
</html>