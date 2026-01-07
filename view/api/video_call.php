<?php
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Use correct connection variable from db.php
$conn = $connection;

$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

switch ($action) {
    case 'initiate_call':
        initiateCall();
        break;
    case 'answer_call':
        answerCall();
        break;
    case 'decline_call':
        declineCall();
        break;
    case 'end_call':
        endCall();
        break;
    case 'send_signal':
        sendSignal();
        break;
    case 'get_signals':
        getSignals();
        break;
    case 'check_incoming_calls':
        checkIncomingCalls();
        break;
    case 'get_caller_name':
        getCallerName();
        break;
    case 'clear_signals':
        clearSignals();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function initiateCall() {
    global $conn, $user_id;
    $receiver_id = $_POST['receiver_id'] ?? null;
    
    if (!$receiver_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Receiver ID required']);
        return;
    }
    
    // Clear any existing pending calls from this user
    $conn->query("UPDATE call_history SET status = 'missed' WHERE caller_id = $user_id AND status = 'pending'");
    
    // Also clear old signals
    $conn->query("DELETE FROM call_signals WHERE from_user_id = $user_id OR to_user_id = $user_id");
    
    $query = "INSERT INTO call_history (caller_id, receiver_id, call_type, status, started_at) 
              VALUES (?, ?, 'video', 'pending', NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $user_id, $receiver_id);
    
    if ($stmt->execute()) {
        $call_id = $conn->insert_id;
        echo json_encode([
            'success' => true,
            'call_id' => $call_id,
            'receiver_id' => $receiver_id,
            'message' => 'Call initiated'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to initiate call']);
    }
}

function answerCall() {
    global $conn, $user_id;
    $call_id = $_POST['call_id'] ?? null;
    
    if (!$call_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Call ID required']);
        return;
    }
    
    $query = "UPDATE call_history SET status = 'completed' WHERE id = ? AND receiver_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $call_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to answer call']);
    }
}

function declineCall() {
    global $conn, $user_id;
    $call_id = $_POST['call_id'] ?? null;
    
    if (!$call_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Call ID required']);
        return;
    }
    
    $query = "UPDATE call_history SET status = 'declined' WHERE id = ? AND receiver_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $call_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to decline call']);
    }
}

function endCall() {
    global $conn, $user_id;
    $call_id = $_POST['call_id'] ?? null;
    
    if (!$call_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Call ID required']);
        return;
    }
    
    $query = "UPDATE call_history SET ended_at = NOW(), 
              duration = TIMESTAMPDIFF(SECOND, started_at, NOW()) 
              WHERE id = ? AND (caller_id = ? OR receiver_id = ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iii', $call_id, $user_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to end call']);
    }
}

function sendSignal() {
    global $conn, $user_id;
    $to_user_id = $_POST['to_user_id'] ?? null;
    $signal_type = $_POST['signal_type'] ?? null;
    $signal_data = $_POST['signal_data'] ?? null;
    
    if (!$to_user_id || !$signal_type) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing parameters']);
        return;
    }
    
    $query = "INSERT INTO call_signals (from_user_id, to_user_id, signal_type, signal_data) 
              VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iiss', $user_id, $to_user_id, $signal_type, $signal_data);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to send signal']);
    }
}

function getSignals() {
    global $conn, $user_id;
    
    $query = "SELECT * FROM call_signals WHERE to_user_id = ? ORDER BY created_at ASC LIMIT 100";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $signals = [];
    $ids_to_delete = [];
    while ($row = $result->fetch_assoc()) {
        $signals[] = $row;
        $ids_to_delete[] = $row['id'];
    }
    
    // Delete retrieved signals to prevent duplicate processing
    if (!empty($ids_to_delete)) {
        $ids_str = implode(',', array_map('intval', $ids_to_delete));
        $conn->query("DELETE FROM call_signals WHERE id IN ($ids_str)");
    }
    
    echo json_encode(['signals' => $signals]);
}

function checkIncomingCalls() {
    global $conn, $user_id;
    
    // Check for pending calls where current user is the receiver
    $query = "SELECT ch.*, u.firstname, u.lastname, u.avatar 
              FROM call_history ch 
              JOIN users u ON ch.caller_id = u.id 
              WHERE ch.receiver_id = ? AND ch.status = 'pending' 
              ORDER BY ch.started_at DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'has_incoming_call' => true,
            'call_id' => $row['id'],
            'caller_id' => $row['caller_id'],
            'caller_name' => $row['firstname'] . ' ' . $row['lastname'],
            'caller_avatar' => $row['avatar'] ?? '../assets/default_avatar.jpg'
        ]);
    } else {
        echo json_encode(['has_incoming_call' => false]);
    }
}

function getCallerName() {
    global $conn;
    $caller_id = $_GET['caller_id'] ?? null;
    
    if (!$caller_id) {
        echo json_encode(['error' => 'Caller ID required']);
        return;
    }
    
    $query = "SELECT firstname, lastname, avatar FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $caller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'name' => $row['firstname'] . ' ' . $row['lastname'],
            'avatar' => $row['avatar'] ?? '../assets/default_avatar.jpg'
        ]);
    } else {
        echo json_encode(['error' => 'User not found']);
    }
}

function clearSignals() {
    global $conn, $user_id;
    
    $query = "DELETE FROM call_signals WHERE to_user_id = ? OR from_user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $user_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to clear signals']);
    }
}
?>