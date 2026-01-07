<?php
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$conn = $connection;
$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'initiate_call':
        initiateCall($conn, $user_id);
        break;
    case 'answer_call':
        answerCall($conn, $user_id);
        break;
    case 'decline_call':
        declineCall($conn, $user_id);
        break;
    case 'end_call':
        endCall($conn, $user_id);
        break;
    case 'send_signal':
        sendSignal($conn, $user_id);
        break;
    case 'get_signals':
        getSignals($conn, $user_id);
        break;
    case 'check_incoming_calls':
        checkIncomingCalls($conn, $user_id);
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
}

// ============================================
// INITIATE CALL
// ============================================
function initiateCall($conn, $user_id) {
    $receiver_id = $_POST['receiver_id'] ?? null;
    
    if (!$receiver_id) {
        echo json_encode(['error' => 'Receiver ID required']);
        return;
    }
    
    // Clear old pending calls and signals
    $conn->query("UPDATE call_history SET status = 'missed' WHERE caller_id = $user_id AND status = 'pending'");
    $conn->query("DELETE FROM call_signals WHERE from_user_id = $user_id OR to_user_id = $user_id");
    
    // Create new call
    $stmt = $conn->prepare("INSERT INTO call_history (caller_id, receiver_id, call_type, status, started_at) VALUES (?, ?, 'video', 'pending', NOW())");
    $stmt->bind_param('ii', $user_id, $receiver_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'call_id' => $conn->insert_id
        ]);
    } else {
        echo json_encode(['error' => 'Failed to create call']);
    }
}

// ============================================
// ANSWER CALL
// ============================================
function answerCall($conn, $user_id) {
    $call_id = $_POST['call_id'] ?? null;
    
    if (!$call_id) {
        echo json_encode(['error' => 'Call ID required']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE call_history SET status = 'completed' WHERE id = ? AND receiver_id = ?");
    $stmt->bind_param('ii', $call_id, $user_id);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
}

// ============================================
// DECLINE CALL
// ============================================
function declineCall($conn, $user_id) {
    $call_id = $_POST['call_id'] ?? null;
    
    if (!$call_id) {
        echo json_encode(['error' => 'Call ID required']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE call_history SET status = 'declined' WHERE id = ? AND receiver_id = ?");
    $stmt->bind_param('ii', $call_id, $user_id);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
}

// ============================================
// END CALL
// ============================================
function endCall($conn, $user_id) {
    $call_id = $_POST['call_id'] ?? null;
    
    if (!$call_id) {
        echo json_encode(['error' => 'Call ID required']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE call_history SET ended_at = NOW(), duration = TIMESTAMPDIFF(SECOND, started_at, NOW()) WHERE id = ? AND (caller_id = ? OR receiver_id = ?)");
    $stmt->bind_param('iii', $call_id, $user_id, $user_id);
    $stmt->execute();
    
    // Clear signals
    $conn->query("DELETE FROM call_signals WHERE from_user_id = $user_id OR to_user_id = $user_id");
    
    echo json_encode(['success' => true]);
}

// ============================================
// SEND SIGNAL
// ============================================
function sendSignal($conn, $user_id) {
    $to_user_id = $_POST['to_user_id'] ?? null;
    $signal_type = $_POST['signal_type'] ?? null;
    $signal_data = $_POST['signal_data'] ?? '{}';
    
    if (!$to_user_id || !$signal_type) {
        echo json_encode(['error' => 'Missing parameters']);
        return;
    }
    
    $stmt = $conn->prepare("INSERT INTO call_signals (from_user_id, to_user_id, signal_type, signal_data, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param('iiss', $user_id, $to_user_id, $signal_type, $signal_data);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to send signal']);
    }
}

// ============================================
// GET SIGNALS
// ============================================
function getSignals($conn, $user_id) {
    $delete = $_GET['delete'] ?? '1';
    
    $stmt = $conn->prepare("SELECT * FROM call_signals WHERE to_user_id = ? ORDER BY created_at ASC");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $signals = [];
    $ids = [];
    
    while ($row = $result->fetch_assoc()) {
        $signals[] = $row;
        // Delete all except offer (offer will be deleted when call starts)
        if ($row['signal_type'] !== 'offer' && $delete === '1') {
            $ids[] = $row['id'];
        }
    }
    
    // Delete processed signals
    if (!empty($ids)) {
        $conn->query("DELETE FROM call_signals WHERE id IN (" . implode(',', $ids) . ")");
    }
    
    echo json_encode(['signals' => $signals]);
}

// ============================================
// CHECK INCOMING CALLS
// ============================================
function checkIncomingCalls($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT ch.id, ch.caller_id, u.firstname, u.lastname, u.avatar 
        FROM call_history ch 
        JOIN users u ON ch.caller_id = u.id 
        WHERE ch.receiver_id = ? AND ch.status = 'pending' 
        ORDER BY ch.started_at DESC 
        LIMIT 1
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'has_incoming_call' => true,
            'call_id' => $row['id'],
            'caller_id' => $row['caller_id'],
            'caller_name' => $row['firstname'] . ' ' . $row['lastname'],
            'caller_avatar' => $row['avatar'] ?: '../assets/default_avatar.jpg'
        ]);
    } else {
        echo json_encode(['has_incoming_call' => false]);
    }
}
?>
