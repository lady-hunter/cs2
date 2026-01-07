// Video Call Configuration
const VIDEO_CALL_CONFIG = {
    iceServers: [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:stun1.l.google.com:19302' },
        { urls: 'stun:stun2.l.google.com:19302' }
    ]
};

let localStream = null;
let peerConnection = null;
let currentCallId = null;
let currentRemoteUserId = null;
let isAudioEnabled = true;
let isVideoEnabled = true;
let callStartTime = null;
let signalingInterval = null;
let incomingCallInterval = null;
let callTimeout = null;
let isInCall = false;
let callTimerInterval = null;

// Get base URL for API calls
function getApiUrl(action) {
    const path = window.location.pathname;
    if (path.includes('/view/')) {
        return `api/video_call.php?action=${action}`;
    }
    return `view/api/video_call.php?action=${action}`;
}

// Initialize video call (caller side)
async function initiateVideoCall(receiverId) {
    if (isInCall) {
        alert('You are already in a call');
        return;
    }
    
    try {
        currentRemoteUserId = receiverId;
        isInCall = true;
        
        // Get local media stream
        localStream = await navigator.mediaDevices.getUserMedia({
            audio: true,
            video: {
                width: { ideal: 1280 },
                height: { ideal: 720 }
            }
        });
        
        // Display local video
        const localVideo = document.getElementById('localVideo');
        if (localVideo) {
            localVideo.srcObject = localStream;
        }
        
        // Show video modal
        showVideoModal();
        updateCallStatus('Calling...');
        
        // Notify server and create call record
        const response = await fetch(getApiUrl('initiate_call'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `receiver_id=${receiverId}`
        });
        
        const data = await response.json();
        if (data.success) {
            currentCallId = data.call_id;
            
            // Create peer connection
            createPeerConnection();
            
            // Add local tracks to peer connection
            localStream.getTracks().forEach(track => {
                peerConnection.addTrack(track, localStream);
            });
            
            // Create and send offer
            const offer = await peerConnection.createOffer();
            await peerConnection.setLocalDescription(offer);
            
            // Send offer to receiver
            await fetch(getApiUrl('send_signal'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `to_user_id=${receiverId}&signal_type=offer&signal_data=${encodeURIComponent(JSON.stringify(offer))}`
            });
            
            // Start polling for signals
            startSignalingPolling();
            
            // Set timeout for unanswered call
            callTimeout = setTimeout(() => {
                if (peerConnection && peerConnection.connectionState !== 'connected') {
                    updateCallStatus('No answer');
                    setTimeout(() => endVideoCall(), 2000);
                }
            }, 30000);
            
        } else {
            throw new Error(data.error || 'Failed to initiate call');
        }
    } catch (error) {
        console.error('Error initiating call:', error);
        if (error.name === 'NotAllowedError' || error.name === 'NotFoundError') {
            alert('Cannot access camera/microphone. Please check permissions.');
        } else {
            alert('Error starting call: ' + error.message);
        }
        closeVideoCall();
    }
}

// Accept incoming call (receiver side)
async function acceptVideoCall() {
    try {
        isInCall = true;
        
        // Get local media
        localStream = await navigator.mediaDevices.getUserMedia({
            audio: true,
            video: { width: { ideal: 1280 }, height: { ideal: 720 } }
        });
        
        document.getElementById('localVideo').srcObject = localStream;
        showVideoModal();
        hideIncomingCallNotification();
        updateCallStatus('Connecting...');
        
        // Create peer connection if not exists
        if (!peerConnection) {
            createPeerConnection();
        }
        
        // Add local tracks
        localStream.getTracks().forEach(track => {
            peerConnection.addTrack(track, localStream);
        });
        
        // Set remote description (the offer we received)
        if (window.incomingOffer) {
            await peerConnection.setRemoteDescription(new RTCSessionDescription(window.incomingOffer));
            
            // Create and send answer
            const answer = await peerConnection.createAnswer();
            await peerConnection.setLocalDescription(answer);
            
            // Send answer to caller
            await fetch(getApiUrl('send_signal'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `to_user_id=${window.callerId}&signal_type=answer&signal_data=${encodeURIComponent(JSON.stringify(answer))}`
            });
        }
        
        // Update call status in database
        if (window.incomingCallId) {
            await fetch(getApiUrl('answer_call'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `call_id=${window.incomingCallId}`
            });
            currentCallId = window.incomingCallId;
        }
        
        currentRemoteUserId = window.callerId;
        
        // Start polling for ICE candidates
        startSignalingPolling();
        
    } catch (error) {
        console.error('Error accepting call:', error);
        if (error.name === 'NotAllowedError' || error.name === 'NotFoundError') {
            alert('Cannot access camera/microphone. Please check permissions.');
        }
        declineVideoCall();
    }
}

// Decline incoming call
async function declineVideoCall() {
    try {
        if (window.incomingCallId) {
            await fetch(getApiUrl('decline_call'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `call_id=${window.incomingCallId}`
            });
        }
        
        // Send decline signal
        if (window.callerId) {
            await fetch(getApiUrl('send_signal'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `to_user_id=${window.callerId}&signal_type=decline&signal_data=${encodeURIComponent(JSON.stringify({declined: true}))}`
            });
        }
        
        hideIncomingCallNotification();
        clearIncomingCallData();
    } catch (error) {
        console.error('Error declining call:', error);
        hideIncomingCallNotification();
    }
}

// End video call
async function endVideoCall() {
    try {
        // Clear timeout
        if (callTimeout) {
            clearTimeout(callTimeout);
            callTimeout = null;
        }
        
        // Stop all tracks
        if (localStream) {
            localStream.getTracks().forEach(track => track.stop());
            localStream = null;
        }
        
        // Close peer connection
        if (peerConnection) {
            peerConnection.close();
            peerConnection = null;
        }
        
        // Send end signal to remote user
        if (currentRemoteUserId) {
            await fetch(getApiUrl('send_signal'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `to_user_id=${currentRemoteUserId}&signal_type=end&signal_data=${encodeURIComponent(JSON.stringify({ended: true}))}`
            });
        }
        
        // Update server
        if (currentCallId) {
            await fetch(getApiUrl('end_call'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `call_id=${currentCallId}`
            });
        }
        
        // Clear signals
        await fetch(getApiUrl('clear_signals'), {
            method: 'POST'
        });
        
        closeVideoCall();
    } catch (error) {
        console.error('Error ending call:', error);
        closeVideoCall();
    }
}

// Create WebRTC peer connection
function createPeerConnection() {
    peerConnection = new RTCPeerConnection({ iceServers: VIDEO_CALL_CONFIG.iceServers });
    
    // Handle remote stream
    peerConnection.ontrack = (event) => {
        console.log('Received remote track:', event.track.kind);
        const remoteVideo = document.getElementById('remoteVideo');
        if (remoteVideo) {
            remoteVideo.srcObject = event.streams[0];
        }
        updateCallStatus('Connected');
        if (!callStartTime) {
            callStartTime = Date.now();
            startCallTimer();
        }
    };
    
    // Handle ICE candidates
    peerConnection.onicecandidate = async (event) => {
        if (event.candidate && currentRemoteUserId) {
            try {
                await fetch(getApiUrl('send_signal'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `to_user_id=${currentRemoteUserId}&signal_type=ice&signal_data=${encodeURIComponent(JSON.stringify(event.candidate))}`
                });
            } catch (error) {
                console.error('Error sending ICE candidate:', error);
            }
        }
    };
    
    // ICE gathering state change
    peerConnection.onicegatheringstatechange = () => {
        console.log('ICE gathering state:', peerConnection.iceGatheringState);
    };
    
    // ICE connection state change
    peerConnection.oniceconnectionstatechange = () => {
        console.log('ICE connection state:', peerConnection.iceConnectionState);
        
        if (peerConnection.iceConnectionState === 'connected' || 
            peerConnection.iceConnectionState === 'completed') {
            updateCallStatus('Connected');
            if (!callStartTime) {
                callStartTime = Date.now();
                startCallTimer();
            }
        } else if (peerConnection.iceConnectionState === 'disconnected') {
            updateCallStatus('Reconnecting...');
        } else if (peerConnection.iceConnectionState === 'failed') {
            updateCallStatus('Connection failed');
            setTimeout(() => endVideoCall(), 2000);
        }
    };
    
    // Connection state change
    peerConnection.onconnectionstatechange = () => {
        console.log('Connection state:', peerConnection.connectionState);
        
        if (peerConnection.connectionState === 'disconnected' || 
            peerConnection.connectionState === 'failed' ||
            peerConnection.connectionState === 'closed') {
            if (isInCall) {
                updateCallStatus('Call ended');
                setTimeout(() => endVideoCall(), 1000);
            }
        }
    };
}

// Toggle audio
function toggleAudio() {
    if (localStream) {
        localStream.getAudioTracks().forEach(track => {
            track.enabled = !track.enabled;
            isAudioEnabled = track.enabled;
        });
        const btn = document.getElementById('toggleAudio');
        if (btn) {
            btn.style.opacity = isAudioEnabled ? '1' : '0.5';
            btn.innerHTML = isAudioEnabled ? '🎤' : '🔇';
        }
    }
}

// Toggle video
function toggleVideo() {
    if (localStream) {
        localStream.getVideoTracks().forEach(track => {
            track.enabled = !track.enabled;
            isVideoEnabled = track.enabled;
        });
        const btn = document.getElementById('toggleVideo');
        if (btn) {
            btn.style.opacity = isVideoEnabled ? '1' : '0.5';
            btn.innerHTML = isVideoEnabled ? '📹' : '📷';
        }
    }
}

// Screen sharing
async function toggleScreenShare() {
    try {
        if (!peerConnection) return;
        
        const screenStream = await navigator.mediaDevices.getDisplayMedia({
            video: true
        });
        
        const screenTrack = screenStream.getVideoTracks()[0];
        
        // Replace video track in peer connection
        const sender = peerConnection.getSenders().find(s => s.track && s.track.kind === 'video');
        if (sender) {
            await sender.replaceTrack(screenTrack);
        }
        
        // Show screen share in local video
        document.getElementById('localVideo').srcObject = screenStream;
        
        // When screen sharing stops, revert to camera
        screenTrack.onended = async () => {
            const cameraTrack = localStream.getVideoTracks()[0];
            if (sender && cameraTrack) {
                await sender.replaceTrack(cameraTrack);
            }
            document.getElementById('localVideo').srcObject = localStream;
        };
        
    } catch (error) {
        console.error('Error sharing screen:', error);
        if (error.name !== 'AbortError') {
            alert('Could not share screen');
        }
    }
}

// Event listeners when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const toggleAudioBtn = document.getElementById('toggleAudio');
    if (toggleAudioBtn) {
        toggleAudioBtn.addEventListener('click', toggleAudio);
    }
    
    const toggleVideoBtn = document.getElementById('toggleVideo');
    if (toggleVideoBtn) {
        toggleVideoBtn.addEventListener('click', toggleVideo);
    }
    
    const endCallBtn = document.getElementById('endCall');
    if (endCallBtn) {
        endCallBtn.addEventListener('click', endVideoCall);
    }
    
    const toggleScreenBtn = document.getElementById('toggleScreen');
    if (toggleScreenBtn) {
        toggleScreenBtn.addEventListener('click', toggleScreenShare);
    }
    
    // Start checking for incoming calls
    checkIncomingCalls();
});

// Update call status
function updateCallStatus(status) {
    const statusEl = document.getElementById('callStatus');
    if (statusEl) {
        statusEl.textContent = status;
    }
}

// Show video modal
function showVideoModal() {
    const modal = document.getElementById('videoCallModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

// Close video call and cleanup
function closeVideoCall() {
    // Stop polling
    if (signalingInterval) {
        clearInterval(signalingInterval);
        signalingInterval = null;
    }
    
    // Clear call timer
    if (callTimerInterval) {
        clearInterval(callTimerInterval);
        callTimerInterval = null;
    }
    
    // Clear timeout
    if (callTimeout) {
        clearTimeout(callTimeout);
        callTimeout = null;
    }
    
    // Stop local stream
    if (localStream) {
        localStream.getTracks().forEach(track => track.stop());
        localStream = null;
    }
    
    // Close peer connection
    if (peerConnection) {
        peerConnection.close();
        peerConnection = null;
    }
    
    // Reset videos
    const localVideo = document.getElementById('localVideo');
    const remoteVideo = document.getElementById('remoteVideo');
    if (localVideo) localVideo.srcObject = null;
    if (remoteVideo) remoteVideo.srcObject = null;
    
    // Hide modal
    const modal = document.getElementById('videoCallModal');
    if (modal) {
        modal.style.display = 'none';
    }
    
    // Reset state
    currentCallId = null;
    currentRemoteUserId = null;
    callStartTime = null;
    isInCall = false;
    isAudioEnabled = true;
    isVideoEnabled = true;
    
    // Reset button states
    const toggleAudioBtn = document.getElementById('toggleAudio');
    const toggleVideoBtn = document.getElementById('toggleVideo');
    if (toggleAudioBtn) {
        toggleAudioBtn.style.opacity = '1';
        toggleAudioBtn.innerHTML = '🎤';
    }
    if (toggleVideoBtn) {
        toggleVideoBtn.style.opacity = '1';
        toggleVideoBtn.innerHTML = '📹';
    }
    
    stopRingtone();
    clearIncomingCallData();
}

// Start call timer
function startCallTimer() {
    if (callTimerInterval) {
        clearInterval(callTimerInterval);
    }
    
    callTimerInterval = setInterval(() => {
        if (callStartTime && isInCall) {
            const elapsed = Math.floor((Date.now() - callStartTime) / 1000);
            const minutes = Math.floor(elapsed / 60);
            const seconds = elapsed % 60;
            updateCallStatus(`${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`);
        }
    }, 1000);
}

// Poll for incoming signals
function startSignalingPolling() {
    // Clear existing interval
    if (signalingInterval) {
        clearInterval(signalingInterval);
    }
    
    signalingInterval = setInterval(async () => {
        if (!isInCall) {
            clearInterval(signalingInterval);
            return;
        }
        
        try {
            const response = await fetch(getApiUrl('get_signals'));
            const data = await response.json();
            
            if (data.signals && data.signals.length > 0) {
                for (const signal of data.signals) {
                    await handleSignal(signal);
                }
            }
        } catch (error) {
            console.error('Polling error:', error);
        }
    }, 500);
}

// Handle incoming signals
async function handleSignal(signal) {
    try {
        const signalData = JSON.parse(signal.signal_data);
        console.log('Received signal:', signal.signal_type);
        
        // Handle call end signal
        if (signal.signal_type === 'end') {
            updateCallStatus('Call ended');
            setTimeout(() => closeVideoCall(), 1000);
            return;
        }
        
        // Handle decline signal
        if (signal.signal_type === 'decline') {
            updateCallStatus('Call declined');
            setTimeout(() => closeVideoCall(), 2000);
            return;
        }
        
        // Create peer connection if needed
        if (!peerConnection) {
            createPeerConnection();
            
            if (localStream) {
                localStream.getTracks().forEach(track => {
                    peerConnection.addTrack(track, localStream);
                });
            }
        }
        
        if (signal.signal_type === 'answer') {
            // Caller receives answer
            if (peerConnection.signalingState === 'have-local-offer') {
                await peerConnection.setRemoteDescription(new RTCSessionDescription(signalData));
                updateCallStatus('Connecting...');
            }
        } else if (signal.signal_type === 'ice') {
            // Both sides receive ICE candidates
            if (peerConnection.remoteDescription) {
                try {
                    await peerConnection.addIceCandidate(new RTCIceCandidate(signalData));
                } catch (e) {
                    console.error('Error adding ICE candidate:', e);
                }
            }
        }
    } catch (error) {
        console.error('Error handling signal:', error);
    }
}

// Check for incoming calls periodically
function checkIncomingCalls() {
    // Don't check if already in a call
    if (isInCall) return;
    
    incomingCallInterval = setInterval(async () => {
        if (isInCall) return;
        
        try {
            // Check for offer signals (incoming calls)
            const response = await fetch(getApiUrl('get_signals'));
            const data = await response.json();
            
            if (data.signals) {
                for (const signal of data.signals) {
                    if (signal.signal_type === 'offer' && !isInCall) {
                        // Store offer data
                        window.incomingOffer = JSON.parse(signal.signal_data);
                        window.callerId = signal.from_user_id;
                        
                        // Get call info from server
                        const callResponse = await fetch(getApiUrl('check_incoming_calls'));
                        const callData = await callResponse.json();
                        
                        if (callData.has_incoming_call) {
                            window.incomingCallId = callData.call_id;
                            showIncomingCallNotification(callData.caller_name, callData.caller_avatar);
                        } else {
                            // Fallback - get caller name separately
                            const nameResponse = await fetch(getApiUrl('get_caller_name') + `&caller_id=${signal.from_user_id}`);
                            const nameData = await nameResponse.json();
                            
                            if (nameData.success) {
                                showIncomingCallNotification(nameData.name, nameData.avatar);
                            } else {
                                showIncomingCallNotification(`User #${signal.from_user_id}`, '../assets/default_avatar.jpg');
                            }
                        }
                        break;
                    }
                }
            }
        } catch (error) {
            console.error('Error checking calls:', error);
        }
    }, 2000);
}

// Show incoming call notification
function showIncomingCallNotification(callerName, callerAvatar) {
    const notification = document.getElementById('incomingCallNotification');
    if (!notification) return;
    
    const callerNameEl = document.getElementById('callerName');
    const callerAvatarEl = document.getElementById('callerAvatar');
    
    if (callerNameEl) {
        callerNameEl.textContent = `${callerName} is calling...`;
    }
    
    if (callerAvatarEl && callerAvatar) {
        callerAvatarEl.src = callerAvatar;
    }
    
    notification.style.display = 'flex';
    
    // Play ringtone
    playRingtone();
    
    // Auto-decline after 30 seconds
    setTimeout(() => {
        if (notification.style.display !== 'none') {
            declineVideoCall();
        }
    }, 30000);
}

// Hide incoming call notification
function hideIncomingCallNotification() {
    const notification = document.getElementById('incomingCallNotification');
    if (notification) {
        notification.style.display = 'none';
    }
    stopRingtone();
}

// Clear incoming call data
function clearIncomingCallData() {
    window.incomingOffer = null;
    window.callerId = null;
    window.incomingCallId = null;
}

// Ringtone functions
let ringtoneAudio = null;

function playRingtone() {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 440;
        oscillator.type = 'sine';
        gainNode.gain.value = 0.1;
        
        oscillator.start();
        
        // Beep pattern
        const beepPattern = () => {
            if (document.getElementById('incomingCallNotification')?.style.display !== 'none') {
                gainNode.gain.value = 0.1;
                setTimeout(() => { gainNode.gain.value = 0; }, 200);
                setTimeout(() => { gainNode.gain.value = 0.1; }, 400);
                setTimeout(() => { gainNode.gain.value = 0; }, 600);
                setTimeout(beepPattern, 2000);
            } else {
                oscillator.stop();
            }
        };
        beepPattern();
        
        ringtoneAudio = { oscillator, audioContext };
    } catch (e) {
        console.log('Could not play ringtone');
    }
}

function stopRingtone() {
    if (ringtoneAudio) {
        try {
            ringtoneAudio.oscillator.stop();
            ringtoneAudio.audioContext.close();
        } catch (e) {}
        ringtoneAudio = null;
    }
}

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (isInCall) {
        endVideoCall();
    }
});