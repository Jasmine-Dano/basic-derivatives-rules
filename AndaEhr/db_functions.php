<?php
// db_functions.php - UPDATED VERSION WITH 3-ROLE SUPPORT
// PostgreSQL database connection and utility functions

// Database configuration
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_PORT')) define('DB_PORT', '5432');
if (!defined('DB_NAME')) define('DB_NAME', 'anda_ehr');
if (!defined('DB_USER')) define('DB_USER', 'postgres');
if (!defined('DB_PASS')) define('DB_PASS', 'mama143papa');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

/**
 * Establish PostgreSQL database connection
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            error_log("✅ Database connection successful to: " . DB_NAME);
        } catch (PDOException $e) {
            error_log("❌ Database connection failed: " . $e->getMessage());
            return null;
        }
    }
    return $pdo;
}

// ==================== DATABASE SETUP FUNCTIONS ====================

/**
 * Initialize database with required tables for 3-role system
 */
function initializeDatabase() {
    error_log("🛠️ Initializing database with 3-role system...");
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        // 1. Create users table with YOUR EXACT STRUCTURE
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id SERIAL PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(100) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                role VARCHAR(20) DEFAULT 'patient' CHECK (role IN ('doctor', 'nurse', 'patient')),
                created_at TIMESTAMP DEFAULT NOW()
            )
        ");
        error_log("✅ Users table checked/created with correct structure");
        
        // 2. Add additional columns for 3-role system if they don't exist
        $columns = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'users'")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('is_active', $columns)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN is_active BOOLEAN DEFAULT true");
            error_log("✅ Added is_active column to users table");
        }
        
        if (!in_array('last_login', $columns)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN last_login TIMESTAMP");
            error_log("✅ Added last_login column to users table");
        }
        
        // Add role-specific columns
        if (!in_array('phone_number', $columns)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN phone_number VARCHAR(20)");
            error_log("✅ Added phone_number column to users table");
        }
        
        if (!in_array('address', $columns)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN address TEXT");
            error_log("✅ Added address column to users table");
        }
        
        if (!in_array('date_of_birth', $columns)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN date_of_birth DATE");
            error_log("✅ Added date_of_birth column to users table");
        }
        
        if (!in_array('gender', $columns)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN gender VARCHAR(10)");
            error_log("✅ Added gender column to users table");
        }
        
        // Doctor-specific columns
        if (!in_array('specialization', $columns)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN specialization VARCHAR(100)");
            error_log("✅ Added specialization column to users table");
        }
        
        if (!in_array('license_number', $columns)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN license_number VARCHAR(50)");
            error_log("✅ Added license_number column to users table");
        }
        
        // Nurse-specific column
        if (!in_array('nurse_license', $columns)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN nurse_license VARCHAR(50)");
            error_log("✅ Added nurse_license column to users table");
        }
        
        // Patient-specific column (link to patients table)
        if (!in_array('patient_id', $columns)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN patient_id VARCHAR(20)");
            error_log("✅ Added patient_id column to users table");
        }
        
        // 3. Check if patients table exists, create if not
        $check = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'patients')");
        if (!$check->fetchColumn()) {
            // Create patients table matching your EXACT database structure
            $pdo->exec("
                CREATE TABLE patients (
                    id SERIAL PRIMARY KEY,
                    patient_id VARCHAR(20) UNIQUE NOT NULL,
                    full_name VARCHAR(100) NOT NULL,
                    address TEXT,
                    dob DATE,
                    age INTEGER,
                    sex VARCHAR(10),
                    contact VARCHAR(20),
                    unique_id VARCHAR(50),
                    date_registered DATE DEFAULT CURRENT_DATE,
                    is_archived BOOLEAN DEFAULT false,
                    date_archived DATE,
                    created_at TIMESTAMP DEFAULT NOW(),
                    updated_at TIMESTAMP DEFAULT NOW()
                )
            ");
            error_log("✅ Patients table created with correct structure");
        }
        
        // 4. Create consultations table with role support if not exists
        $check = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'consultations')");
        if (!$check->fetchColumn()) {
            $pdo->exec("
                CREATE TABLE consultations (
                    id SERIAL PRIMARY KEY,
                    patient_id VARCHAR(20) REFERENCES patients(patient_id),
                    consultation_date TIMESTAMP DEFAULT NOW(),
                    temperature NUMERIC(4,2),
                    pulse INTEGER,
                    respiratory_rate INTEGER,
                    blood_pressure VARCHAR(10),
                    oxygen_saturation INTEGER,
                    weight NUMERIC(5,2),
                    height NUMERIC(5,2),
                    bmi NUMERIC(5,2),
                    doctor_notes TEXT,
                    nurse_notes TEXT,
                    doctor_orders TEXT,
                    workflow_status VARCHAR(20) DEFAULT 'pending_vitals',
                    vital_signs_recorded_by INTEGER REFERENCES users(id),
                    vital_signs_recorded_at TIMESTAMP,
                    orders_issued_by INTEGER REFERENCES users(id),
                    orders_issued_at TIMESTAMP,
                    is_emergency BOOLEAN DEFAULT false,
                    priority VARCHAR(20) DEFAULT 'routine',
                    created_by INTEGER REFERENCES users(id),
                    created_at TIMESTAMP DEFAULT NOW(),
                    updated_at TIMESTAMP DEFAULT NOW()
                )
            ");
            error_log("✅ Consultations table created with role support");
        }
        
        // 5. Create indexes
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_patients_patient_id ON patients(patient_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_patients_is_archived ON patients(is_archived)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_consultations_patient ON consultations(patient_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_consultations_status ON consultations(workflow_status)");
        
        // 6. Create default admin user if no users exist
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        if ($stmt->fetchColumn() == 0) {
            // Create default admin/doctor user
            $default_password = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->exec("
                INSERT INTO users (username, password, full_name, email, role, is_active) 
                VALUES ('admin', '$default_password', 'System Administrator', 'admin@anda-ehr.com', 'doctor', true)
            ");
            error_log("✅ Created default admin user: admin / admin123");
            
            // Create a sample doctor
            $doctor_password = password_hash('doctor123', PASSWORD_DEFAULT);
            $pdo->exec("
                INSERT INTO users (username, password, full_name, email, role, specialization, license_number, is_active) 
                VALUES ('dr.smith', '$doctor_password', 'Dr. John Smith', 'dr.smith@hospital.com', 'doctor', 'General Medicine', 'MD-12345', true)
            ");
            
            // Create a sample nurse
            $nurse_password = password_hash('nurse123', PASSWORD_DEFAULT);
            $pdo->exec("
                INSERT INTO users (username, password, full_name, email, role, nurse_license, is_active) 
                VALUES ('nurse.jones', '$nurse_password', 'Nurse Sarah Jones', 'nurse.jones@hospital.com', 'nurse', 'RN-67890', true)
            ");
            
            error_log("✅ Created sample doctor and nurse users");
        }
        
        error_log("✅ Database initialization completed with 3-role system");
        return true;
        
    } catch (Exception $e) {
        error_log("❌ Database initialization error: " . $e->getMessage());
        return false;
    }
}

// ==================== USER AUTHENTICATION FUNCTIONS (3-ROLE SUPPORT) ====================

/**
 * Authenticate user with username/email and password with role check
 */
function authenticateUserWithRole($username, $password, $role) {
    error_log("🔐 Authentication attempt for: $username with role: $role");
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $sql = "SELECT * FROM users 
                WHERE (username = :username OR email = :username) 
                AND role = :role
                AND is_active = true";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':username' => $username,
            ':role' => $role
        ]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            error_log("✅ Password verification successful for: " . $user['username'] . " (Role: " . $user['role'] . ")");
            
            updateLastLogin($user['id']);
            return $user;
        } else {
            error_log("❌ Authentication failed for: $username with role: $role");
            return false;
        }
    } catch (PDOException $e) {
        error_log("❌ Authentication error: " . $e->getMessage());
        return false;
    }
}

/**
 * Authenticate user (legacy function for backward compatibility)
 */
function authenticateUser($username, $password) {
    error_log("🔐 Legacy authentication attempt for: " . $username);
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $sql = "SELECT * FROM users WHERE username = :username OR email = :email";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':username' => $username,
            ':email' => $username
        ]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            error_log("✅ Legacy authentication successful for: " . $user['username']);
            
            if (isset($user['is_active']) && !$user['is_active']) {
                error_log("❌ User account is inactive: " . $user['username']);
                return false;
            }
            
            updateLastLogin($user['id']);
            return $user;
        } else {
            error_log("❌ Legacy authentication failed for: " . $username);
            return false;
        }
    } catch (PDOException $e) {
        error_log("❌ Legacy authentication error: " . $e->getMessage());
        return false;
    }
}

// ==================== USER MANAGEMENT FUNCTIONS ====================

/**
 * Get user by ID
 */
function getUserById($user_id) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $sql = "SELECT * FROM users WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $user_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("❌ Get user error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user by username
 */
function getUserByUsername($username) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $sql = "SELECT * FROM users WHERE username = :username";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':username' => $username]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("❌ Get user by username error: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a new user with role support
 */
function createUser($user_data) {
    error_log("👤 Creating new user: " . ($user_data['username'] ?? 'Unknown'));
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        // Check if username or email already exists
        if (isset($user_data['username']) && usernameExists($user_data['username'])) {
            error_log("❌ Username already exists: " . $user_data['username']);
            return false;
        }
        
        if (isset($user_data['email']) && emailExists($user_data['email'])) {
            error_log("❌ Email already exists: " . $user_data['email']);
            return false;
        }
        
        // Default role to patient if not specified
        $role = $user_data['role'] ?? 'patient';
        
        // Generate username from email if not provided (for patient registration)
        $username = $user_data['username'] ?? '';
        if (empty($username) && isset($user_data['email'])) {
            $username = strtok($user_data['email'], '@');
            $username = preg_replace('/[^a-zA-Z0-9]/', '', $username);
            
            // Make unique if exists
            $base_username = $username;
            $counter = 1;
            while (usernameExists($username)) {
                $username = $base_username . $counter;
                $counter++;
            }
        }
        
        // Build SQL based on available data
        $sql = "INSERT INTO users (username, password, full_name, email, role";
        $params = [
            ':username' => $username,
            ':password' => password_hash($user_data['password'], PASSWORD_DEFAULT),
            ':full_name' => $user_data['full_name'],
            ':email' => $user_data['email'],
            ':role' => $role
        ];
        
        // Add optional fields if provided
        $optional_fields = ['phone_number', 'address', 'date_of_birth', 'gender', 'specialization', 'license_number', 'nurse_license'];
        foreach ($optional_fields as $field) {
            if (isset($user_data[$field]) && !empty($user_data[$field])) {
                $sql .= ", $field";
                $params[":$field"] = $user_data[$field];
            }
        }
        
        $sql .= ") VALUES (" . implode(', ', array_keys($params)) . ") RETURNING id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $new_id = $stmt->fetchColumn();
        error_log("✅ User created successfully with ID: " . $new_id . " (Role: " . $role . ")");
        
        // If patient, also create patient record
        if ($role == 'patient') {
            createPatientFromUser($new_id, $user_data);
        }
        
        return $new_id;
    } catch (PDOException $e) {
        error_log("❌ Create user error: " . $e->getMessage());
        return false;
    }
}

/**
 * Update user information
 */
function updateUser($user_id, $user_data) {
    error_log("✏️ Updating user ID: " . $user_id);
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $sql = "UPDATE users SET 
                full_name = :full_name,
                email = :email,
                role = :role";
        
        // Add password update if provided
        if (!empty($user_data['password'])) {
            $sql .= ", password = :password";
        }
        
        // Add optional fields
        $optional_fields = ['phone_number', 'address', 'date_of_birth', 'gender', 'specialization', 'license_number', 'nurse_license'];
        foreach ($optional_fields as $field) {
            if (isset($user_data[$field])) {
                $sql .= ", $field = :$field";
            }
        }
        
        $sql .= " WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        
        $params = [
            ':id' => $user_id,
            ':full_name' => $user_data['full_name'],
            ':email' => $user_data['email'],
            ':role' => $user_data['role'] ?? 'patient'
        ];
        
        if (!empty($user_data['password'])) {
            $params[':password'] = password_hash($user_data['password'], PASSWORD_DEFAULT);
        }
        
        foreach ($optional_fields as $field) {
            if (isset($user_data[$field])) {
                $params[":$field"] = $user_data[$field];
            }
        }
        
        $result = $stmt->execute($params);
        
        if ($result) {
            error_log("✅ User updated successfully: " . $user_id);
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("❌ Update user error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all users
 */
function getAllUsers() {
    $pdo = getDBConnection();
    if (!$pdo) return [];
    
    try {
        $sql = "SELECT id, username, full_name, email, role, created_at, 
                       is_active, last_login, phone_number, specialization
                FROM users ORDER BY role, full_name";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("❌ Get all users error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get users by specific role
 */
function getUsersByRole($role) {
    $pdo = getDBConnection();
    if (!$pdo) return [];
    
    try {
        $sql = "SELECT id, username, full_name, email, phone_number, 
                       specialization, license_number, nurse_license,
                       date_of_birth, gender, address
                FROM users 
                WHERE role = :role AND is_active = true 
                ORDER BY full_name";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':role' => $role]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("❌ Get users by role error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all doctors
 */
function getDoctors() {
    return getUsersByRole('doctor');
}

/**
 * Get all nurses
 */
function getNurses() {
    return getUsersByRole('nurse');
}

/**
 * Get all patients (users with patient role)
 */
function getPatientUsers() {
    $pdo = getDBConnection();
    if (!$pdo) return [];
    
    try {
        $sql = "SELECT u.*, p.patient_id as linked_patient_id, p.full_name as patient_full_name, 
                       p.contact, p.address as patient_address
                FROM users u
                LEFT JOIN patients p ON u.patient_id = p.patient_id
                WHERE u.role = 'patient' AND u.is_active = true 
                ORDER BY u.full_name";
        
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("❌ Get patient users error: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if username exists
 */
function usernameExists($username) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $sql = "SELECT COUNT(*) FROM users WHERE username = :username";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':username' => $username]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("❌ Username check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if email exists
 */
function emailExists($email) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $sql = "SELECT COUNT(*) FROM users WHERE email = :email";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':email' => $email]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("❌ Email check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Update user's last login timestamp
 */
function updateLastLogin($user_id) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $sql = "UPDATE users SET last_login = NOW() WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([':id' => $user_id]);
    } catch (PDOException $e) {
        error_log("❌ Update last login error: " . $e->getMessage());
        return false;
    }
}

/**
 * Set user active status
 */
function setUserActiveStatus($user_id, $is_active) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $sql = "UPDATE users SET is_active = :is_active WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $user_id,
            ':is_active' => $is_active
        ]);
    } catch (PDOException $e) {
        error_log("❌ Set user active status error: " . $e->getMessage());
        return false;
    }
}

// ==================== PATIENT FUNCTIONS ====================

/**
 * Create patient record from user registration
 */
function createPatientFromUser($user_id, $user_data) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        // Generate patient ID
        $stmt = $pdo->query("SELECT COALESCE(MAX(CAST(REPLACE(patient_id, 'PAT-', '') AS INTEGER)), 0) as max_id FROM patients WHERE patient_id LIKE 'PAT-%'");
        $result = $stmt->fetch();
        $next_number = ($result['max_id'] ?? 0) + 1;
        $patient_number = str_pad($next_number, 3, '0', STR_PAD_LEFT);
        $patient_id = 'PAT-' . $patient_number;
        
        // Calculate age from date of birth
        $age = 0;
        if (!empty($user_data['date_of_birth'])) {
            $dob = new DateTime($user_data['date_of_birth']);
            $today = new DateTime();
            $age = $dob->diff($today)->y;
        }
        
        $sql = "INSERT INTO patients 
                (patient_id, full_name, address, dob, age, sex, contact, date_registered)
                VALUES (:patient_id, :full_name, :address, :dob, :age, :sex, :contact, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':patient_id' => $patient_id,
            ':full_name' => $user_data['full_name'],
            ':address' => $user_data['address'] ?? '',
            ':dob' => $user_data['date_of_birth'] ?? null,
            ':age' => $age,
            ':sex' => $user_data['gender'] ?? '',
            ':contact' => $user_data['phone_number'] ?? ''
        ]);
        
        if ($result) {
            // Update user with patient_id
            $update_sql = "UPDATE users SET patient_id = :patient_id WHERE id = :user_id";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([
                ':patient_id' => $patient_id,
                ':user_id' => $user_id
            ]);
            
            error_log("✅ Patient record created and linked to user: " . $patient_id);
            return $patient_id;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("❌ Create patient from user error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get patients (active or archived)
 */
function getPatients($archived = false, $limit = null) {
    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            error_log("❌ No database connection in getPatients()");
            return [];
        }
        
        error_log("🔍 getPatients() called with archived = " . ($archived ? 'true' : 'false'));
        
        // Simple query that definitely works
        $sql = "SELECT * FROM patients WHERE is_archived = :archived ORDER BY created_at DESC";
        
        if ($limit !== null && is_numeric($limit) && $limit > 0) {
            $sql .= " LIMIT :limit";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':archived', $archived, PDO::PARAM_BOOL);
        
        if ($limit !== null && is_numeric($limit) && $limit > 0) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $patients = $stmt->fetchAll();
        
        error_log("✅ getPatients() returned " . count($patients) . " patients (archived: " . ($archived ? 'Yes' : 'No') . ")");
        
        return $patients;
    } catch (Exception $e) {
        error_log("❌ Get patients error: " . $e->getMessage());
        error_log("❌ Stack trace: " . $e->getTraceAsString());
        return [];
    }
}

/**
 * Get patient by ID
 */
function getPatientById($patient_id) {
    try {
        $pdo = getDBConnection();
        if (!$pdo) return false;
        
        $sql = "SELECT * FROM patients WHERE patient_id = :patient_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':patient_id' => $patient_id]);
        $patient = $stmt->fetch();
        
        if ($patient) {
            error_log("✅ Found patient: " . $patient['patient_id']);
            return $patient;
        }
        
        error_log("❌ Patient not found: " . $patient_id);
        return false;
    } catch (Exception $e) {
        error_log("❌ Get patient by ID error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user by patient ID
 */
function getUserByPatientId($patient_id) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $sql = "SELECT * FROM users WHERE patient_id = :patient_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':patient_id' => $patient_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("❌ Get user by patient ID error: " . $e->getMessage());
        return false;
    }
}

/**
 * Add a new patient
 */
function addPatient($patient_data) {
    error_log("➕ ADD PATIENT STARTED");
    error_log("📋 Received data: " . print_r($patient_data, true));
    
    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            error_log("❌ No database connection");
            return false;
        }
        
        // Check required fields
        if (empty($patient_data['full_name'])) {
            error_log("❌ Missing full name");
            return false;
        }
        
        // Get next patient ID
        $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(patient_id, 5) AS UNSIGNED)) as max_id FROM patients");
        $result = $stmt->fetch();
        $next_number = ($result['max_id'] ?? 0) + 1;
        $patient_number = str_pad($next_number, 3, '0', STR_PAD_LEFT);
        $patient_id = 'PAT-' . $patient_number;
        error_log("🆔 Generated Patient ID: " . $patient_id);
        
        // Generate unique ID
        $unique_id = 'MH-' . date('ymdHis');
        error_log("🆔 Generated Unique ID: " . $unique_id);
        
        // Calculate age if DOB is provided
        $age = $patient_data['age'] ?? 0;
        if (!empty($patient_data['dob'])) {
            $dob = new DateTime($patient_data['dob']);
            $today = new DateTime();
            $age = $today->diff($dob)->y;
            error_log("📅 Calculated age from DOB: " . $age);
        }
        
        // Using correct column names from your database (dob and contact)
        $sql = "INSERT INTO patients (
            patient_id, 
            full_name, 
            address, 
            dob,
            age, 
            sex, 
            contact,
            unique_id, 
            date_registered, 
            is_archived, 
            created_at
        ) VALUES (
            :patient_id,
            :full_name,
            :address,
            :dob,
            :age,
            :sex,
            :contact,
            :unique_id,
            CURRENT_DATE,
            false,
            NOW()
        ) RETURNING patient_id";
        
        error_log("📝 SQL: " . $sql);
        
        $stmt = $pdo->prepare($sql);
        
        // Map form data to database columns
        $params = [
            ':patient_id' => $patient_id,
            ':full_name' => trim($patient_data['full_name']),
            ':address' => $patient_data['address'] ?? '',
            ':dob' => !empty($patient_data['dob']) ? $patient_data['dob'] : null,
            ':age' => $age,
            ':sex' => $patient_data['sex'] ?? '',
            ':contact' => $patient_data['contact'] ?? '',
            ':unique_id' => $unique_id
        ];
        
        error_log("📋 Parameters: " . print_r($params, true));
        
        $stmt->execute($params);
        
        $new_id = $stmt->fetchColumn();
        
        if ($new_id) {
            error_log("✅ PATIENT ADDED SUCCESSFULLY: " . $new_id);
            
            // Double-check it was added
            $verify = $pdo->prepare("SELECT * FROM patients WHERE patient_id = :patient_id");
            $verify->execute([':patient_id' => $new_id]);
            $added_patient = $verify->fetch();
            
            if ($added_patient) {
                error_log("✅ Verification passed - Patient in database:");
                error_log("   ID: " . $added_patient['patient_id']);
                error_log("   Name: " . $added_patient['full_name']);
                error_log("   DOB: " . ($added_patient['dob'] ?? 'N/A'));
                error_log("   Contact: " . ($added_patient['contact'] ?? 'N/A'));
                error_log("   Archived: " . ($added_patient['is_archived'] ? 'Yes' : 'No'));
            }
            
            return $new_id;
        } else {
            error_log("❌ Failed to get new patient ID");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("❌ ADD PATIENT ERROR: " . $e->getMessage());
        error_log("❌ Error Trace: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * Update patient information
 */
function updatePatient($patient_id, $patient_data) {
    try {
        $pdo = getDBConnection();
        if (!$pdo) return false;
        
        // Calculate age if DOB changed
        $age = $patient_data['age'] ?? 0;
        if (!empty($patient_data['dob'])) {
            $dob = new DateTime($patient_data['dob']);
            $today = new DateTime();
            $age = $today->diff($dob)->y;
        }
        
        // Using correct column names
        $sql = "UPDATE patients SET 
                full_name = :full_name,
                address = :address,
                dob = :dob,
                age = :age,
                sex = :sex,
                contact = :contact,
                updated_at = NOW()
                WHERE patient_id = :patient_id";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':patient_id' => $patient_id,
            ':full_name' => trim($patient_data['full_name']),
            ':address' => $patient_data['address'] ?? '',
            ':dob' => !empty($patient_data['dob']) ? $patient_data['dob'] : null,
            ':age' => $age,
            ':sex' => $patient_data['sex'] ?? '',
            ':contact' => $patient_data['contact'] ?? ''
        ]);
        
        if ($result) {
            error_log("✅ Patient updated: " . $patient_id);
        } else {
            error_log("❌ Patient update failed: " . $patient_id);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("❌ Update patient error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get patient statistics
 */
function getPatientStatistics() {
    $stats = ['total' => 0, 'male' => 0, 'female' => 0, 'other' => 0, 'new' => 0];
    
    try {
        $pdo = getDBConnection();
        if (!$pdo) return $stats;
        
        // Total active patients
        $stmt = $pdo->query("SELECT COUNT(*) FROM patients WHERE is_archived = false");
        $stats['total'] = (int)$stmt->fetchColumn();
        
        // Gender distribution for active patients
        $stmt = $pdo->query("SELECT sex, COUNT(*) as count FROM patients WHERE is_archived = false GROUP BY sex");
        $results = $stmt->fetchAll();
        
        foreach ($results as $row) {
            $sex = strtolower(trim($row['sex'] ?? ''));
            if ($sex == 'male') $stats['male'] = (int)$row['count'];
            elseif ($sex == 'female') $stats['female'] = (int)$row['count'];
            else $stats['other'] += (int)$row['count'];
        }
        
        // New patients (last 30 days)
        $stmt = $pdo->query("SELECT COUNT(*) FROM patients WHERE date_registered >= CURRENT_DATE - INTERVAL '30 days' AND is_archived = false");
        $stats['new'] = (int)$stmt->fetchColumn();
        
        error_log("📊 Stats - Total: " . $stats['total'] . ", Male: " . $stats['male'] . ", Female: " . $stats['female'] . ", New: " . $stats['new']);
        
    } catch (Exception $e) {
        error_log("❌ Get statistics error: " . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Archive or unarchive a patient
 */
function archivePatient($patient_id, $archive = true) {
    try {
        $pdo = getDBConnection();
        if (!$pdo) return false;
        
        $sql = "UPDATE patients SET is_archived = :archive, date_archived = " . ($archive ? "NOW()" : "NULL") . ", updated_at = NOW() WHERE patient_id = :patient_id";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':archive' => $archive,
            ':patient_id' => $patient_id
        ]);
        
        $action = $archive ? 'archived' : 'unarchived';
        if ($result) {
            error_log("✅ Patient " . $patient_id . " " . $action);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("❌ Archive patient error: " . $e->getMessage());
        return false;
    }
}

/**
 * Search patients by name or ID
 */
function searchPatients($search_term, $archived = false) {
    try {
        $pdo = getDBConnection();
        if (!$pdo) return [];
        
        $sql = "SELECT * FROM patients 
                WHERE is_archived = :archived 
                AND (full_name ILIKE :search 
                     OR patient_id ILIKE :search 
                     OR address ILIKE :search)
                ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':archived' => $archived,
            ':search' => '%' . $search_term . '%'
        ]);
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("❌ Search patients error: " . $e->getMessage());
        return [];
    }
}

// ==================== CONSULTATION FUNCTIONS ====================

/**
 * Create a new consultation
 */
function createConsultation($patient_id, $user_id) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $sql = "INSERT INTO consultations 
                (patient_id, created_by, workflow_status, created_at)
                VALUES (:patient_id, :user_id, 'pending_vitals', NOW()) 
                RETURNING id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':patient_id' => $patient_id,
            ':user_id' => $user_id
        ]);
        
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("❌ Create consultation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get consultations by patient ID
 */
function getConsultationsByPatientId($patient_id) {
    $pdo = getDBConnection();
    if (!$pdo) return [];
    
    try {
        $sql = "SELECT c.*, u.full_name as doctor_name 
                FROM consultations c 
                LEFT JOIN users u ON c.orders_issued_by = u.id 
                WHERE c.patient_id = :patient_id 
                ORDER BY c.consultation_date DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':patient_id' => $patient_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("❌ Get consultations error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get consultations pending nurse review
 */
function getPendingVitals() {
    $pdo = getDBConnection();
    if (!$pdo) return [];
    
    try {
        $sql = "SELECT c.*, p.full_name as patient_name, p.patient_id
                FROM consultations c 
                JOIN patients p ON c.patient_id = p.patient_id 
                WHERE c.workflow_status = 'pending_vitals' 
                AND p.is_archived = false
                ORDER BY c.created_at ASC";
        
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("❌ Get pending vitals error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get consultations pending doctor review
 */
function getPendingDoctorReview() {
    $pdo = getDBConnection();
    if (!$pdo) return [];
    
    try {
        $sql = "SELECT c.*, p.full_name as patient_name, p.patient_id
                FROM consultations c 
                JOIN patients p ON c.patient_id = p.patient_id 
                WHERE c.workflow_status = 'vitals_completed' 
                AND p.is_archived = false
                ORDER BY c.priority DESC, c.created_at ASC";
        
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("❌ Get pending doctor review error: " . $e->getMessage());
        return [];
    }
}

/**
 * Update vital signs (nurse function)
 */
function updateVitalSigns($consultation_id, $data, $nurse_id) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        // Calculate BMI if weight and height provided
        $bmi = null;
        if (isset($data['weight']) && isset($data['height']) && $data['height'] > 0) {
            $height_m = $data['height'] / 100;
            $bmi = round($data['weight'] / ($height_m * $height_m), 2);
        }
        
        $sql = "UPDATE consultations SET 
                temperature = :temperature,
                pulse = :pulse,
                respiratory_rate = :respiratory_rate,
                blood_pressure = :blood_pressure,
                oxygen_saturation = :oxygen_saturation,
                weight = :weight,
                height = :height,
                bmi = :bmi,
                nurse_notes = :nurse_notes,
                vital_signs_recorded_by = :nurse_id,
                vital_signs_recorded_at = NOW(),
                workflow_status = 'vitals_completed',
                updated_at = NOW()
                WHERE id = :consultation_id";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':consultation_id' => $consultation_id,
            ':temperature' => $data['temperature'] ?? null,
            ':pulse' => $data['pulse'] ?? null,
            ':respiratory_rate' => $data['respiratory_rate'] ?? null,
            ':blood_pressure' => $data['blood_pressure'] ?? null,
            ':oxygen_saturation' => $data['oxygen_saturation'] ?? null,
            ':weight' => $data['weight'] ?? null,
            ':height' => $data['height'] ?? null,
            ':bmi' => $bmi,
            ':nurse_notes' => $data['nurse_notes'] ?? null,
            ':nurse_id' => $nurse_id
        ]);
        
        if ($result) {
            error_log("✅ Vital signs updated for consultation: " . $consultation_id);
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("❌ Update vital signs error: " . $e->getMessage());
        return false;
    }
}

/**
 * Update doctor orders (doctor function)
 */
function updateDoctorOrders($consultation_id, $orders, $doctor_id) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $sql = "UPDATE consultations SET 
                doctor_orders = :orders,
                orders_issued_by = :doctor_id,
                orders_issued_at = CASE 
                    WHEN orders_issued_at IS NULL THEN NOW() 
                    ELSE orders_issued_at 
                END,
                orders_updated_by = :doctor_id,
                orders_updated_at = NOW(),
                workflow_status = 'completed',
                updated_at = NOW()
                WHERE id = :consultation_id";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':orders' => $orders,
            ':doctor_id' => $doctor_id,
            ':consultation_id' => $consultation_id
        ]);
        
        if ($result) {
            error_log("✅ Doctor orders updated for consultation: " . $consultation_id);
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("❌ Update doctor orders error: " . $e->getMessage());
        return false;
    }
}

// ==================== PERMISSION FUNCTIONS ====================

/**
 * Check if user has permission
 */
function hasPermission($user_role, $permission) {
    // Simple permission mapping for 3 roles
    $permissions = [
        'doctor' => [
            'view_patients', 'edit_patients', 'create_consultations', 
            'edit_consultations', 'view_consultations', 'add_doctor_notes',
            'issue_prescriptions', 'order_tests', 'admit_patients', 
            'discharge_patients', 'view_reports', 'edit_vitals'
        ],
        'nurse' => [
            'view_patients', 'edit_patients', 'create_consultations', 
            'view_consultations', 'record_vitals', 'edit_vitals',
            'add_nurse_notes', 'view_reports', 'administer_medications'
        ],
        'patient' => [
            'view_own_profile', 'edit_own_profile', 'view_own_consultations',
            'view_own_vitals', 'view_own_prescriptions', 'request_appointments',
            'view_own_appointments', 'view_own_lab_results'
        ]
    ];
    
    return isset($permissions[$user_role]) && in_array($permission, $permissions[$user_role]);
}

// ==================== UTILITY FUNCTIONS ====================

/**
 * Calculate age from DOB
 */
function calculateAgeFromDOB($dob) {
    if (empty($dob)) return 0;
    
    $birthdate = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birthdate)->y;
    return $age;
}

/**
 * Validate Philippine contact number
 */
function validateContactNumber($contact) {
    // Remove all non-digit characters
    $clean = preg_replace('/[^0-9]/', '', $contact);
    
    // Check if it's a valid PH mobile number (09XXXXXXXXX or 9XXXXXXXXX)
    if (strlen($clean) === 11 && substr($clean, 0, 2) === '09') {
        return $clean;
    } elseif (strlen($clean) === 10 && substr($clean, 0, 1) === '9') {
        return '0' . $clean;
    }
    
    return false;
}

// Initialize database on first include
initializeDatabase();