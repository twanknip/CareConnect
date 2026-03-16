<?php
/**
 * MediPortal - Backend API
 * Uses database for all data
 * No hardcoded values
 */

session_start();
header('Content-Type: text/plain; charset=utf-8');

// Include configuration
require_once __DIR__ . '/config.php';

// Get database connection
$conn = getDBConnection();

// Get action
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

// Route to appropriate handler
switch($action) {
    case 'login':
        handleLogin($conn);
        break;
    case 'stats':
        handleStats($conn);
        break;
    case 'search':
        handleSearch($conn);
        break;
    case 'users':
        handleUsers($conn);
        break;
    case 'messages':
        handleMessages($conn);
        break;
    case 'send_message':
        handleSendMessage($conn);
        break;
    case 'save_settings':
        handleSaveSettings($conn);
        break;
    case 'user_info':
        handleUserInfo($conn);
        break;
    case 'conversations':
        handleConversations($conn);
        break;
    case 'get_messages':
        handleGetMessages($conn);
        break;
    default:
        echo 'Invalid action';
}

closeDBConnection($conn);
exit;

// ============================================================
// LOGIN - BRUTE FORCE VULNERABLE (No rate limiting)
// ============================================================
/**
 * VULNERABILITY: No rate limiting, no account lockout
 * Allows unlimited login attempts
 * Data comes from database, not hardcoded
 */
function handleLogin($conn) {
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($username) || empty($password)) {
        echo 'FAILED|Empty credentials';
        logAttempt($conn, $username, false);
        return;
    }
    
    try {
        // Query database for user
        $query = "SELECT id, username, password, full_name FROM users WHERE username = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo 'FAILED|Invalid username or password';
            logAttempt($conn, $username, false);
            $stmt->close();
            return;
        }
        
        $user = $result->fetch_assoc();
        $stmt->close();
        
        // VULNERABILITY: Weak password verification (plain text comparison)
        // Should use password_hash() and password_verify() in production
        if ($user['password'] !== $password) {
            echo 'FAILED|Invalid username or password';
            logAttempt($conn, $username, false);
            return;
        }
        
        // Login successful
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        
        // Update last login timestamp
        $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param('i', $user['id']);
        $updateStmt->execute();
        $updateStmt->close();
        
        echo 'SUCCESS';
        logAttempt($conn, $username, true);
        
    } catch (Exception $e) {
        logError($e->getMessage());
        echo 'ERROR|Database error';
    }
}

// ============================================================
// DASHBOARD STATS
// ============================================================
/**
 * Get dashboard statistics from database
 */
function handleStats($conn) {
    try {
        // Get user ID from session
        if (!isset($_SESSION['user_id'])) {
            echo '0|0|0';
            return;
        }
        
        $userId = $_SESSION['user_id'];
        
        // Total patients assigned to this doctor
        $query1 = "SELECT COUNT(*) as total FROM patients WHERE assigned_doctor_id = ?";
        $stmt1 = $conn->prepare($query1);
        $stmt1->bind_param('i', $userId);
        $stmt1->execute();
        $result1 = $stmt1->get_result()->fetch_assoc();
        $totalPatients = $result1['total'];
        $stmt1->close();
        
        // Appointments today
        $query2 = "SELECT COUNT(*) as total FROM patients 
                   WHERE assigned_doctor_id = ? AND next_appointment = CURDATE()";
        $stmt2 = $conn->prepare($query2);
        $stmt2->bind_param('i', $userId);
        $stmt2->execute();
        $result2 = $stmt2->get_result()->fetch_assoc();
        $appointmentsToday = $result2['total'];
        $stmt2->close();
        
        // Pending reviews (simulated)
        $pendingReviews = max(0, $appointmentsToday - 5);
        
        echo "$totalPatients|$appointmentsToday|$pendingReviews";
        
    } catch (Exception $e) {
        logError($e->getMessage());
        echo '0|0|0';
    }
}

// ============================================================
// PATIENT SEARCH - XSS VULNERABLE
// ============================================================
/**
 * VULNERABILITY: XSS - Unescaped output in HTML
 * User input is displayed without proper HTML escaping
 */
function handleSearch($conn) {
    try {
        if (!isset($_SESSION['user_id'])) {
            echo '<p>Please log in first</p>';
            return;
        }
        
        $query = isset($_GET['q']) ? $_GET['q'] : '';
        $userId = $_SESSION['user_id'];
        
        if (empty($query)) {
            echo '<p style="color: var(--gray-400); text-align: center;">Start typing to search...</p>';
            return;
        }
        
        // Search patients assigned to this doctor
        // Using LIKE for search (proper parameterized query)
        $searchQuery = "SELECT id, patient_id, first_name, last_name, age, email, medical_history 
                       FROM patients 
                       WHERE assigned_doctor_id = ? 
                       AND (first_name LIKE ? OR last_name LIKE ? OR patient_id LIKE ? OR email LIKE ?)
                       LIMIT 20";
        
        $stmt = $conn->prepare($searchQuery);
        $searchParam = "%$query%";
        $stmt->bind_param('issss', $userId, $searchParam, $searchParam, $searchParam, $searchParam);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // VULNERABILITY: XSS - Query parameter displayed without escaping
            // Attacker can inject: "><script>alert('XSS')</script>
            echo '<p style="color: var(--gray-400); text-align: center;">No patients found matching: ' . $query . '</p>';
            $stmt->close();
            return;
        }
        
        echo '<ul class="patient-list">';
        while ($patient = $result->fetch_assoc()) {
            echo '<li class="patient-item">';
            echo '<div class="patient-info">';
            echo '<div class="patient-name">' . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . '</div>';
            echo '<div class="patient-meta">ID: ' . htmlspecialchars($patient['patient_id']) . 
                 ' | Age: ' . $patient['age'] . 
                 ' | ' . htmlspecialchars($patient['email']) . '</div>';
            echo '</div>';
            echo '<button class="btn-view" onclick="alert(\'Details for ' . addslashes($patient['first_name'] . ' ' . $patient['last_name']) . '\')">View</button>';
            echo '</li>';
        }
        echo '</ul>';
        
        $stmt->close();
        
    } catch (Exception $e) {
        logError($e->getMessage());
        echo '<p>Error searching patients</p>';
    }
}

// ============================================================
// GET CHAT USERS
// ============================================================
/**
 * Get list of all users for chat
 */
function handleUsers($conn) {
    try {
        if (!isset($_SESSION['user_id'])) {
            echo '<li style="padding: 12px;">Please log in first</li>';
            return;
        }
        
        $userId = $_SESSION['user_id'];
        
        // Get all users except current user
        $query = "SELECT id, username, full_name FROM users 
                  WHERE id != ? AND is_active = TRUE 
                  ORDER BY full_name";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo '<li style="padding: 12px; color: var(--gray-400);">No users available</li>';
            $stmt->close();
            return;
        }
        
        while ($user = $result->fetch_assoc()) {
            echo '<li class="user-list-item" onclick="selectChatUser(' . $user['id'] . ', \'' . 
                 addslashes($user['full_name']) . '\')">';
            echo '👤 ' . htmlspecialchars($user['full_name']);
            echo '</li>';
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        logError($e->getMessage());
        echo '<li style="padding: 12px; color: red;">Error loading users</li>';
    }
}

// ============================================================
// GET CHAT MESSAGES
// ============================================================
/**
 * Get messages between current user and selected user
 */
function handleMessages($conn) {
    try {
        if (!isset($_SESSION['user_id'])) {
            echo '<div style="text-align: center; padding: 20px; color: var(--gray-400);">Please log in</div>';
            return;
        }
        
        $userId = $_SESSION['user_id'];
        $recipientId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        
        if ($recipientId === 0) {
            echo '<div style="text-align: center; padding: 20px; color: var(--gray-400);">Select a user to chat</div>';
            return;
        }
        
        // Get messages between users
        $query = "SELECT sender_id, (SELECT full_name FROM users WHERE id = messages.sender_id) as sender_name, 
                         message, created_at 
                  FROM messages 
                  WHERE (sender_id = ? AND recipient_id = ?) 
                     OR (sender_id = ? AND recipient_id = ?)
                  ORDER BY created_at ASC
                  LIMIT 50";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iiii', $userId, $recipientId, $recipientId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo '<div style="text-align: center; padding: 20px; color: var(--gray-400);">No messages yet. Start a conversation!</div>';
            $stmt->close();
            return;
        }
        
        while ($msg = $result->fetch_assoc()) {
            $isOwn = $msg['sender_id'] == $userId ? 'own' : 'other';
            echo '<div class="message ' . $isOwn . '">';
            // VULNERABILITY: XSS - Message displayed without escaping
            // If stored XSS exists (from malicious message), it will execute here
            echo htmlspecialchars($msg['message']);
            echo '</div>';
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        logError($e->getMessage());
        echo '<div style="color: red;">Error loading messages</div>';
    }
}

// ============================================================
// SEND MESSAGE
// ============================================================
/**
 * Send a chat message
 */
function handleSendMessage($conn) {
    try {
        if (!isset($_SESSION['user_id'])) {
            echo 'ERROR|Not logged in';
            return;
        }
        
        $senderId = $_SESSION['user_id'];
        $recipientId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $message = isset($_POST['message']) ? $_POST['message'] : '';
        
        if (empty($message) || $recipientId === 0) {
            echo 'ERROR|Invalid message';
            return;
        }
        
        // Insert message into database
        $query = "INSERT INTO messages (sender_id, recipient_id, message) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param('iis', $senderId, $recipientId, $message);
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        echo 'OK';
        $stmt->close();
        
    } catch (Exception $e) {
        logError($e->getMessage());
        echo 'ERROR|' . $e->getMessage();
    }
}

// ============================================================
// SAVE SETTINGS
// ============================================================
/**
 * Update user settings from database
 */
function handleSaveSettings($conn) {
    try {
        if (!isset($_SESSION['user_id'])) {
            echo 'ERROR|Not logged in';
            return;
        }
        
        $userId = $_SESSION['user_id'];
        $fullName = isset($_POST['name']) ? $_POST['name'] : '';
        $email = isset($_POST['email']) ? $_POST['email'] : '';
        $department = isset($_POST['dept']) ? $_POST['dept'] : '';
        
        if (empty($fullName) || empty($email)) {
            echo 'ERROR|Name and email required';
            return;
        }
        
        // Update user in database
        $query = "UPDATE users SET full_name = ?, email = ?, department = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param('sssi', $fullName, $email, $department, $userId);
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        // Update session
        $_SESSION['full_name'] = $fullName;
        
        echo 'OK|Settings updated';
        $stmt->close();
        
    } catch (Exception $e) {
        logError($e->getMessage());
        echo 'ERROR|' . $e->getMessage();
    }
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

/**
 * Log login attempts for security analysis
 */
function logAttempt($conn, $username, $success) {
    try {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $successFlag = $success ? 1 : 0;
        
        $query = "INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssi', $username, $ipAddress, $successFlag);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        // Fail silently - don't interrupt the login process
    }
}

// ============================================================
// NEW CHAT SYSTEM HANDLERS
// ============================================================

/**
 * Get current user information
 */
function handleUserInfo($conn) {
    try {
        if (!isset($_SESSION['user_id'])) {
            echo 'ERROR|Not logged in';
            return;
        }
        
        $userId = $_SESSION['user_id'];
        
        $query = "SELECT id, username, full_name, role FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$result) {
            echo 'ERROR|User not found';
            return;
        }
        
        echo $result['id'] . '|' . $result['full_name'] . '|' . $result['role'];
        
    } catch (Exception $e) {
        logError($e->getMessage());
        echo 'ERROR|' . $e->getMessage();
    }
}

/**
 * Get all conversations for current user
 * For doctors: shows all assigned patients
 * For patients: shows assigned doctor
 */
function handleConversations($conn) {
    try {
        if (!isset($_SESSION['user_id'])) {
            echo 'ERROR|Not logged in';
            return;
        }
        
        $userId = $_SESSION['user_id'];
        
        // Get current user's role
        $userQuery = "SELECT role FROM users WHERE id = ?";
        $userStmt = $conn->prepare($userQuery);
        $userStmt->bind_param('i', $userId);
        $userStmt->execute();
        $userResult = $userStmt->get_result()->fetch_assoc();
        $userStmt->close();
        
        $userRole = $userResult['role'] ?? 'staff';
        
        if ($userRole === 'doctor') {
            // Doctor: show all patients assigned to them
            $query = "SELECT 
                        p.id as user_id,
                        CONCAT(p.first_name, ' ', p.last_name) as name,
                        'Patient' as role,
                        COALESCE(m.message, '') as last_message,
                        COALESCE(m.created_at, '') as timestamp,
                        p.patient_id
                     FROM patients p
                     LEFT JOIN (
                        SELECT * FROM messages 
                        WHERE (sender_id = ? OR recipient_id = ?)
                        ORDER BY created_at DESC LIMIT 1
                     ) m ON (m.sender_id = ? OR m.recipient_id = ?)
                     WHERE p.assigned_doctor_id = ?
                     GROUP BY p.id
                     ORDER BY COALESCE(m.created_at, p.created_at) DESC";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('iiiii', $userId, $userId, $userId, $userId, $userId);
        } else {
            // Patient: show assigned doctor
            $query = "SELECT 
                        u.id as user_id,
                        u.full_name as name,
                        u.role,
                        COALESCE(m.message, '') as last_message,
                        COALESCE(m.created_at, '') as timestamp,
                        NULL as patient_id
                     FROM patients p
                     LEFT JOIN users u ON p.assigned_doctor_id = u.id
                     LEFT JOIN (
                        SELECT * FROM messages 
                        WHERE (sender_id = ? OR recipient_id = ?)
                        ORDER BY created_at DESC LIMIT 1
                     ) m ON (m.sender_id = ? OR m.recipient_id = ?)
                     WHERE FIND_IN_SET(?, GROUP_CONCAT(p.id))
                     GROUP BY u.id
                     ORDER BY COALESCE(m.created_at, p.created_at) DESC";
            
            // Simpler approach: find the patient record first
            $patientQuery = "SELECT assigned_doctor_id FROM patients WHERE id = (SELECT MIN(id) FROM patients LIMIT 1)";
            $stmt = $conn->prepare("SELECT 
                        u.id as user_id,
                        u.full_name as name,
                        u.role,
                        COALESCE(m.message, '') as last_message,
                        COALESCE(m.created_at, '') as timestamp
                     FROM users u
                     LEFT JOIN (
                        SELECT * FROM messages 
                        WHERE sender_id = ? OR recipient_id = ?
                        ORDER BY created_at DESC LIMIT 1
                     ) m ON (m.sender_id = u.id OR m.recipient_id = u.id)
                     WHERE u.role = 'doctor'
                     LIMIT 1");
            
            $stmt->bind_param('ii', $userId, $userId);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows === 0) {
            echo 'ERROR|No conversations';
            return;
        }
        
        $output = '';
        while ($row = $result->fetch_assoc()) {
            $unreadCount = getUnreadCount($conn, $userId, $row['user_id']);
            $output .= $row['user_id'] . '|' . 
                      $row['name'] . '|' . 
                      $row['role'] . '|' . 
                      substr($row['last_message'], 0, 50) . '|' . 
                      $row['timestamp'] . '|' . 
                      ($row['patient_id'] ?? '') . '|' .
                      $unreadCount . "\n";
        }
        
        echo trim($output);
        
    } catch (Exception $e) {
        logError($e->getMessage());
        echo 'ERROR|' . $e->getMessage();
    }
}

/**
 * Get messages with a specific user
 */
function handleGetMessages($conn) {
    try {
        if (!isset($_SESSION['user_id'])) {
            echo 'ERROR|Not logged in';
            return;
        }
        
        $userId = $_SESSION['user_id'];
        $recipientId = isset($_GET['recipient_id']) ? intval($_GET['recipient_id']) : 0;
        
        if ($recipientId === 0) {
            echo 'ERROR|No recipient';
            return;
        }
        
        $query = "SELECT 
                    sender_id,
                    (SELECT full_name FROM users WHERE id = messages.sender_id) as sender_name,
                    message,
                    created_at
                  FROM messages
                  WHERE (sender_id = ? AND recipient_id = ?) 
                     OR (sender_id = ? AND recipient_id = ?)
                  ORDER BY created_at ASC
                  LIMIT 100";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iiii', $userId, $recipientId, $recipientId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows === 0) {
            echo '';
            return;
        }
        
        $output = '';
        while ($msg = $result->fetch_assoc()) {
            $output .= $msg['sender_id'] . '|' . 
                      $msg['sender_name'] . '|' . 
                      $msg['message'] . '|' . 
                      $msg['created_at'] . "\n";
        }
        
        echo trim($output);
        
    } catch (Exception $e) {
        logError($e->getMessage());
        echo 'ERROR|' . $e->getMessage();
    }
}

/**
 * Get count of unread messages between two users
 */
function getUnreadCount($conn, $userId, $senderId) {
    try {
        $query = "SELECT COUNT(*) as count FROM messages 
                  WHERE sender_id = ? AND recipient_id = ? AND is_read = 0";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ii', $senderId, $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return $result['count'] ?? 0;
        
    } catch (Exception $e) {
        return 0;
    }
}

?>