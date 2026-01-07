// ============================================
// VIDEO CALL - SIMPLE & WORKING VERSION
// ============================================

const ICE_SERVERS = [
    { urls: 'stun:stun.l.google.com:19302' },
    { urls: 'stun:stun1.l.google.com:19302' }
];

// Global state
let localStream = null;
let peerConnection = null;
let currentCallId = null;
let remoteUserId = null;
let pollingInterval = null;
let isInCall = false;

// ============================================
// API HELPER
// ============================================
function apiUrl(action) {
    if (window.location.pathname.includes('/view/')) {
        return `api/video_call.php?action=${action}`;
    }
    return `view/api/video_call.php?action=${action}`;
}

async function apiPost(action, data) {
    const response = await fetch(apiUrl(action), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: data
    });
    return response.json();
}

async function apiGet(action) {
    const response = await fetch(apiUrl(action));
    return response.json();
}

// ============================================
// UI HELPERS
// ============================================
function showModal() {
    document.getElementById('videoCallModal').style.display = 'flex';
}

function hideModal() {
    document.getElementById('videoCallModal').style.display = 'none';
}

function showIncoming(name, avatar) {
    const el = document.getElementById('incomingCallNotification');
    document.getElementById('callerName').textContent = name + ' is calling...';
    if (avatar) document.getElementById('callerAvatar').src = avatar;
    el.style.display = 'flex';
}

function hideIncoming() {
    document.getElementById('incomingCallNotification').style.display = 'none';
}

function setStatus(text) {
    const el = document.getElementById('callStatus');
    if (el) el.textContent = text;
}

// ============================================
// WEBRTC PEER CONNECTION
// ============================================
function createPC() {
    console.log('Creating PeerConnection...');
    const pc = new RTCPeerConnection({ iceServers: ICE_SERVERS });
    
    pc.ontrack = (e) => {
        console.log('Got remote track');
        document.getElementById('remoteVideo').srcObject = e.streams[0];
        setStatus('Connected');
    };
    
    pc.onicecandidate = async (e) => {
        if (e.candidate && remoteUserId) {
            console.log('Sending ICE candidate');
            await apiPost('send_signal', 
                `to_user_id=${remoteUserId}&signal_type=ice&signal_data=${encodeURIComponent(JSON.stringify(e.candidate))}`
            );
        }
    };
    
    pc.oniceconnectionstatechange = () => {
        console.log('ICE state:', pc.iceConnectionState);
        if (pc.iceConnectionState === 'connected') {
            setStatus('Connected');
        }
        if (pc.iceConnectionState === 'failed') {
            setStatus('Connection failed');
        }
    };
    
    return pc;
}

// ============================================
// START CALL (Caller)
// ============================================
async function initiateVideoCall(receiverId) {
    if (isInCall) {
        alert('Already in a call');
        return;
    }
    
    console.log('Starting call to:', receiverId);
    
    try {
        // Get camera/mic
        localStream = await navigator.mediaDevices.getUserMedia({ 
            audio: true, 
            video: true 
        });
        document.getElementById('localVideo').srcObject = localStream;
        
        showModal();
        setStatus('Calling...');
        isInCall = true;
        remoteUserId = receiverId;
        
        // Create call record
        const result = await apiPost('initiate_call', `receiver_id=${receiverId}`);
        if (!result.success) {
            throw new Error(result.error);
        }
        currentCallId = result.call_id;
        console.log('Call ID:', currentCallId);
        
        // Create peer connection
        peerConnection = createPC();
        
        // Add tracks
        localStream.getTracks().forEach(track => {
            peerConnection.addTrack(track, localStream);
        });
        
        // Create offer
        const offer = await peerConnection.createOffer();
        await peerConnection.setLocalDescription(offer);
        console.log('Created offer');
        
        // Send offer
        await apiPost('send_signal',
            `to_user_id=${receiverId}&signal_type=offer&signal_data=${encodeURIComponent(JSON.stringify(offer))}`
        );
        console.log('Sent offer');
        
        // Start polling for answer
        startPolling();
        
        // Timeout after 30s
        setTimeout(() => {
            if (isInCall && peerConnection && peerConnection.iceConnectionState !== 'connected') {
                setStatus('No answer');
                setTimeout(endVideoCall, 2000);
            }
        }, 30000);
        
    } catch (err) {
        console.error('Call error:', err);
        alert('Error: ' + err.message);
        cleanup();
    }
}

// ============================================
// ACCEPT CALL (Receiver)
// ============================================
async function acceptVideoCall() {
    console.log('Accepting call...');
    
    try {
        // Stop checking for calls
        if (window.checkCallsInterval) {
            clearInterval(window.checkCallsInterval);
        }
        
        hideIncoming();
        showModal();
        setStatus('Connecting...');
        isInCall = true;
        
        // Get camera/mic
        localStream = await navigator.mediaDevices.getUserMedia({ 
            audio: true, 
            video: true 
        });
        document.getElementById('localVideo').srcObject = localStream;
        
        // Create peer connection
        peerConnection = createPC();
        
        // Add tracks
        localStream.getTracks().forEach(track => {
            peerConnection.addTrack(track, localStream);
        });
        
        // Set remote description (offer)
        if (window.pendingOffer) {
            console.log('Setting remote description (offer)');
            await peerConnection.setRemoteDescription(new RTCSessionDescription(window.pendingOffer));
            
            // Create answer
            const answer = await peerConnection.createAnswer();
            await peerConnection.setLocalDescription(answer);
            console.log('Created answer');
            
            // Send answer
            await apiPost('send_signal',
                `to_user_id=${window.pendingCallerId}&signal_type=answer&signal_data=${encodeURIComponent(JSON.stringify(answer))}`
            );
            console.log('Sent answer');
            
            remoteUserId = window.pendingCallerId;
            currentCallId = window.pendingCallId;
            
            // Update DB
            await apiPost('answer_call', `call_id=${currentCallId}`);
            
            // Clear pending data
            window.pendingOffer = null;
            window.pendingCallerId = null;
            window.pendingCallId = null;
        }
        
        // Start polling for ICE candidates
        startPolling();
        
    } catch (err) {
        console.error('Accept error:', err);
        alert('Error: ' + err.message);
        cleanup();
    }
}

// ============================================
// DECLINE CALL
// ============================================
async function declineVideoCall() {
    console.log('Declining call');
    hideIncoming();
    
    if (window.pendingCallId) {
        await apiPost('decline_call', `call_id=${window.pendingCallId}`);
    }
    if (window.pendingCallerId) {
        await apiPost('send_signal',
            `to_user_id=${window.pendingCallerId}&signal_type=decline&signal_data=${encodeURIComponent('{}')}`
        );
    }
    
    window.pendingOffer = null;
    window.pendingCallerId = null;
    window.pendingCallId = null;
    
    // Restart checking
    startCheckingCalls();
}

// ============================================
// END CALL
// ============================================
async function endVideoCall() {
    console.log('Ending call');
    
    if (remoteUserId) {
        await apiPost('send_signal',
            `to_user_id=${remoteUserId}&signal_type=end&signal_data=${encodeURIComponent('{}')}`
        );
    }
    
    if (currentCallId) {
        await apiPost('end_call', `call_id=${currentCallId}`);
    }
    
    cleanup();
}

// ============================================
// CLEANUP
// ============================================
function cleanup() {
    console.log('Cleanup');
    
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
    
    if (localStream) {
        localStream.getTracks().forEach(t => t.stop());
        localStream = null;
    }
    
    if (peerConnection) {
        peerConnection.close();
        peerConnection = null;
    }
    
    document.getElementById('localVideo').srcObject = null;
    document.getElementById('remoteVideo').srcObject = null;
    
    hideModal();
    hideIncoming();
    
    isInCall = false;
    currentCallId = null;
    remoteUserId = null;
    
    // Restart checking for calls
    startCheckingCalls();
}

// ============================================
// POLLING FOR SIGNALS
// ============================================
function startPolling() {
    if (pollingInterval) clearInterval(pollingInterval);
    
    pollingInterval = setInterval(async () => {
        if (!isInCall) {
            clearInterval(pollingInterval);
            return;
        }
        
        try {
            const data = await apiGet('get_signals');
            
            if (data.signals && data.signals.length > 0) {
                for (const sig of data.signals) {
                    await handleSignal(sig);
                }
            }
        } catch (err) {
            console.error('Polling error:', err);
        }
    }, 500);
}

async function handleSignal(sig) {
    console.log('Got signal:', sig.signal_type);
    
    try {
        const signalData = JSON.parse(sig.signal_data);
        
        if (sig.signal_type === 'end' || sig.signal_type === 'decline') {
            setStatus(sig.signal_type === 'decline' ? 'Call declined' : 'Call ended');
            setTimeout(cleanup, 1500);
            return;
        }
        
        if (sig.signal_type === 'answer' && peerConnection) {
            if (peerConnection.signalingState === 'have-local-offer') {
                console.log('Setting remote description (answer)');
                await peerConnection.setRemoteDescription(new RTCSessionDescription(signalData));
            }
        }
        
        if (sig.signal_type === 'ice' && peerConnection) {
            if (peerConnection.remoteDescription) {
                console.log('Adding ICE candidate');
                await peerConnection.addIceCandidate(new RTCIceCandidate(signalData));
            }
        }
    } catch (err) {
        console.error('Signal error:', err);
    }
}

// ============================================
// CHECK FOR INCOMING CALLS
// ============================================
function startCheckingCalls() {
    if (window.checkCallsInterval) {
        clearInterval(window.checkCallsInterval);
    }
    
    window.checkCallsInterval = setInterval(async () => {
        if (isInCall) return;
        
        // Don't check if notification already showing
        const notif = document.getElementById('incomingCallNotification');
        if (notif && notif.style.display === 'flex') return;
        
        try {
            // Check DB for pending call
            const callData = await apiGet('check_incoming_calls');
            
            if (callData.has_incoming_call) {
                // Get offer signal
                const sigData = await apiGet('get_signals&delete=0');
                
                if (sigData.signals) {
                    for (const sig of sigData.signals) {
                        if (sig.signal_type === 'offer') {
                            console.log('Incoming call from:', callData.caller_id);
                            
                            window.pendingOffer = JSON.parse(sig.signal_data);
                            window.pendingCallerId = sig.from_user_id;
                            window.pendingCallId = callData.call_id;
                            
                            showIncoming(callData.caller_name, callData.caller_avatar);
                            return;
                        }
                    }
                }
            }
        } catch (err) {
            // Ignore errors
        }
    }, 2000);
}

// ============================================
// TOGGLE AUDIO/VIDEO
// ============================================
function toggleAudio() {
    if (localStream) {
        const track = localStream.getAudioTracks()[0];
        if (track) {
            track.enabled = !track.enabled;
            document.getElementById('toggleAudio').style.opacity = track.enabled ? 1 : 0.5;
        }
    }
}

function toggleVideo() {
    if (localStream) {
        const track = localStream.getVideoTracks()[0];
        if (track) {
            track.enabled = !track.enabled;
            document.getElementById('toggleVideo').style.opacity = track.enabled ? 1 : 0.5;
        }
    }
}

// ============================================
// INIT
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    // Button handlers
    const audioBtn = document.getElementById('toggleAudio');
    const videoBtn = document.getElementById('toggleVideo');
    const endBtn = document.getElementById('endCall');
    
    if (audioBtn) audioBtn.onclick = toggleAudio;
    if (videoBtn) videoBtn.onclick = toggleVideo;
    if (endBtn) endBtn.onclick = endVideoCall;
    
    // Start checking for incoming calls
    startCheckingCalls();
    
    console.log('Video call initialized');
});

// Cleanup on page leave
window.addEventListener('beforeunload', () => {
    if (isInCall) {
        endVideoCall();
    }
});
