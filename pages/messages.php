<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/auth/login.php');
}

$user_id = getCurrentUserId();
$_SESSION['unread_messages'] = getUnreadMessageCount($user_id);
$_SESSION['unread_notifications'] = getUnreadNotificationCount($user_id);

$conversations = getUserConversations($user_id);
$selected_conversation = isset($_GET['conversation']) ? (int) $_GET['conversation'] : 0;
$requested_user_id = isset($_GET['user']) ? (int) $_GET['user'] : 0;
$current_conversation = null;
$messages = [];
$other_user = null;
$messages_notice = '';

foreach ($conversations as $conversation) {
    if ((int) $conversation['id'] === $selected_conversation) {
        $current_conversation = $conversation;
        break;
    }
}

if (!$selected_conversation && $requested_user_id) {
    foreach ($conversations as $conversation) {
        if ((int) ($conversation['other_user_id'] ?? 0) === $requested_user_id) {
            redirect(SITE_URL . '/pages/messages.php?conversation=' . (int) $conversation['id']);
        }
    }

    if ($requested_user_id === $user_id) {
        $messages_notice = 'You cannot start a conversation with yourself.';
    } else {
        $requested_user = getUserById($requested_user_id);
        $messages_notice = $requested_user
            ? 'Start the conversation from one of their room listings so the chat stays tied to a real property.'
            : 'Conversation not found. Start from a room listing to open a chat.';
    }
}

if ($selected_conversation && $current_conversation) {
    $result = getConversationMessages($selected_conversation, $user_id);
    if ($result['success']) {
        $messages = $result['messages'];
        $current_conversation = array_merge($current_conversation, $result['conversation']);
        $other_user = getUserById((int) $current_conversation['other_user_id']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $conversation_id = isset($_POST['conversation_id']) ? (int) $_POST['conversation_id'] : 0;

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    if ($action === 'send_message') {
        $message = trim($_POST['message'] ?? '');
        if ($message === '') {
            echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
            exit;
        }
        if (!canSendMessage($user_id, $conversation_id)) {
            echo json_encode(['success' => false, 'message' => 'You cannot send messages in this conversation']);
            exit;
        }
        echo json_encode(sendMessage($conversation_id, $user_id, $message));
        exit;
    }

    if ($action === 'accept_request') {
        echo json_encode(acceptContactRequest($conversation_id, $user_id));
        exit;
    }

    if ($action === 'decline_request') {
        echo json_encode(declineContactRequest($conversation_id, $user_id));
        exit;
    }

    if ($action === 'typing') {
        $updated = updateTypingStatus($conversation_id, $user_id, ($_POST['is_typing'] ?? '') === 'true');
        echo json_encode($updated ? ['success' => true] : ['success' => false, 'message' => 'Access denied']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$page_title = 'Messages';
require_once __DIR__ . '/../includes/header.php';

$csrf_token = generateCSRFToken();
$current_user_avatar = getUserAvatarUrl($_SESSION['user_avatar'] ?? null);
$other_user_avatar = getUserAvatarUrl($other_user['avatar'] ?? ($current_conversation['other_user_avatar'] ?? null));
$room_image = getRoomImageUrl($current_conversation['room_image'] ?? null);
$last_message_id = !empty($messages) ? (int) end($messages)['id'] : 0;
$conversation_count = count($conversations);
?>
<section class="dashboard-shell">
    <div class="dashboard-card messages-shell-card" data-animate="fade-up">
        <div class="messages-layout">
            <aside class="messages-sidebar">
                <div class="messages-sidebar-header">
                    <span class="messages-shell-kicker">Inbox</span>
                    <div class="messages-sidebar-title-row">
                        <h1>Messages</h1>
                        <span class="messages-sidebar-count"><?php echo (int) $conversation_count; ?></span>
                    </div>
                    <p class="muted-text">Keep conversations organized across listings with a cleaner, room-based thread history.</p>
                </div>

                <div class="messages-thread-list">
                    <?php if (empty($conversations)): ?>
                        <div class="messages-empty-state">
                            <i class="fas fa-comments"></i>
                            <h3>No conversations yet</h3>
                            <p class="muted-text">Browse rooms and send a contact request to start chatting.</p>
                            <a href="<?php echo SITE_URL; ?>/pages/rooms.php" class="btn-primary">Browse Rooms</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conversation): ?>
                            <?php $is_active = (int) $conversation['id'] === $selected_conversation; ?>
                            <a href="<?php echo SITE_URL; ?>/pages/messages.php?conversation=<?php echo (int) $conversation['id']; ?>" class="messages-thread <?php echo $is_active ? 'active' : ''; ?>">
                                <div class="messages-thread-avatar-wrap">
                                    <img src="<?php echo getUserAvatarUrl($conversation['other_user_avatar'] ?? null); ?>" alt="<?php echo htmlspecialchars($conversation['other_user_name'] ?? 'User'); ?>" class="messages-thread-avatar">
                                    <?php $online = getOnlineStatus((int) $conversation['other_user_id']); ?>
                                    <?php if (($online['status'] ?? 'offline') === 'online'): ?>
                                        <span class="messages-thread-status"></span>
                                    <?php endif; ?>
                                </div>
                                <div class="messages-thread-body">
                                    <div class="messages-thread-topline">
                                        <strong><?php echo htmlspecialchars($conversation['other_user_name'] ?? 'User'); ?></strong>
                                        <span><?php echo !empty($conversation['last_message_time']) ? htmlspecialchars(timeAgo($conversation['last_message_time'])) : 'New'; ?></span>
                                    </div>
                                    <div class="messages-thread-room"><?php echo htmlspecialchars($conversation['room_title']); ?></div>
                                    <div class="messages-thread-preview-row">
                                        <div class="messages-thread-preview">
                                            <?php if ((int) ($conversation['last_message_sender_id'] ?? 0) === $user_id): ?>
                                                <span class="messages-thread-you">You:</span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($conversation['last_message'] ?? 'No messages yet'); ?>
                                        </div>
                                        <?php if (!empty($conversation['unread_count'])): ?>
                                            <span class="messages-count-badge"><?php echo (int) $conversation['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (($conversation['status'] ?? '') === 'pending'): ?>
                                        <span class="messages-state-badge pending"><?php echo (int) $conversation['landlord_id'] === $user_id ? 'Pending your response' : 'Awaiting landlord'; ?></span>
                                    <?php elseif (($conversation['status'] ?? '') === 'declined'): ?>
                                        <span class="messages-state-badge declined">Declined</span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>

            <div class="messages-chat-pane">
                <?php if ($current_conversation): ?>
                    <?php $other_status = getOnlineStatus((int) $current_conversation['other_user_id']); ?>
                    <div class="messages-chat-header" data-animate="fade-up">
                        <div class="messages-chat-user">
                            <img src="<?php echo $other_user_avatar; ?>" alt="<?php echo htmlspecialchars($other_user['full_name'] ?? $current_conversation['other_user_name']); ?>" class="messages-chat-user-avatar">
                            <div>
                                <h2><?php echo htmlspecialchars($other_user['full_name'] ?? $current_conversation['other_user_name']); ?></h2>
                                <div class="muted-text">
                                    <?php echo ($other_status['status'] ?? 'offline') === 'online' ? 'Online' : 'Last seen ' . htmlspecialchars(timeAgo($other_status['last_seen'] ?? date('Y-m-d H:i:s'))); ?>
                                </div>
                            </div>
                        </div>
                        <div class="messages-chat-room">
                            <img src="<?php echo $room_image; ?>" alt="<?php echo htmlspecialchars($current_conversation['room_title']); ?>" class="messages-chat-room-image">
                            <div class="messages-chat-room-copy">
                                <strong><?php echo htmlspecialchars($current_conversation['room_title']); ?></strong>
                                <div class="muted-text">NPR <?php echo number_format((float) $current_conversation['price']); ?>/month</div>
                            </div>
                            <div class="messages-chat-actions">
                                <a href="<?php echo SITE_URL; ?>/pages/room-detail.php?id=<?php echo (int) $current_conversation['room_id']; ?>" class="btn-outline btn-small">View Listing</a>
                                <?php if ((int) $current_conversation['landlord_id'] === $user_id && ($current_conversation['status'] ?? '') === 'accepted'): ?>
                                    <a href="<?php echo SITE_URL; ?>/pages/create-contract.php?room_id=<?php echo (int) $current_conversation['room_id']; ?>&tenant_id=<?php echo (int) $current_conversation['tenant_id']; ?>&conversation_id=<?php echo (int) $current_conversation['id']; ?>" class="btn-primary btn-small">Create Contract</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="messages-scroll" id="messagesList">
                        <?php foreach ($messages as $message): ?>
                            <?php $is_self = (int) $message['sender_id'] === $user_id; ?>
                            <div class="message-row <?php echo $is_self ? 'is-self' : ''; ?>">
                                <?php if (!$is_self): ?>
                                    <img src="<?php echo getUserAvatarUrl($message['avatar'] ?? null); ?>" alt="" class="message-avatar">
                                <?php endif; ?>
                                <div class="message-bubble-wrap <?php echo $is_self ? 'is-self' : ''; ?>">
                                    <div class="message-bubble <?php echo $is_self ? 'is-self' : ''; ?>" data-message-id="<?php echo (int) $message['id']; ?>"><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                    <div class="message-meta <?php echo $is_self ? 'is-self' : ''; ?>">
                                        <span><?php echo date('h:i A', strtotime($message['created_at'])); ?></span>
                                        <?php if ($is_self): ?>
                                            <span class="message-state-label" data-message-id="<?php echo (int) $message['id']; ?>"><?php echo !empty($message['is_read']) ? 'Read' : (!empty($message['is_delivered']) ? 'Delivered' : 'Sent'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($is_self): ?>
                                    <img src="<?php echo $current_user_avatar; ?>" alt="" class="message-avatar">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <div id="typingIndicator" class="typing-row" hidden>
                            <img src="<?php echo $other_user_avatar; ?>" alt="" class="message-avatar">
                            <div class="typing-bubble">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </div>
                    </div>

                    <?php if (($current_conversation['status'] ?? '') === 'pending' && (int) $current_conversation['landlord_id'] === $user_id): ?>
                        <div class="messages-banner pending">
                            <div>
                                <strong>New contact request</strong>
                                <div class="muted-text">Accept this request to open two-way chat with the tenant.</div>
                            </div>
                            <div class="messages-banner-actions">
                                <button type="button" class="btn-primary btn-small" onclick="handleRequest('accept')">Accept</button>
                                <button type="button" class="btn-outline btn-small" onclick="handleRequest('decline')">Decline</button>
                            </div>
                        </div>
                    <?php elseif (($current_conversation['status'] ?? '') === 'pending'): ?>
                        <div class="messages-banner pending">
                            <strong>Awaiting landlord response.</strong>
                            <div class="muted-text">Your initial request has been delivered.</div>
                        </div>
                    <?php elseif (($current_conversation['status'] ?? '') === 'declined'): ?>
                        <div class="messages-banner declined">
                            <strong>This request was declined.</strong>
                            <div class="muted-text">You can browse other listings and send a new request there.</div>
                        </div>
                    <?php endif; ?>

                    <?php if (($current_conversation['status'] ?? '') === 'accepted'): ?>
                        <form id="messageForm" class="messages-composer">
                            <textarea id="messageInput" placeholder="Write your message..." rows="2"></textarea>
                            <button type="submit" class="btn-primary">Send</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="messages-empty-chat" data-animate="fade-up">
                        <i class="fas fa-paper-plane"></i>
                        <h2>Select a conversation</h2>
                        <p class="muted-text">Choose a thread from the left to view messages and reply.</p>
                        <?php if ($messages_notice !== ''): ?>
                            <div class="alert alert-info"><?php echo htmlspecialchars($messages_notice); ?></div>
                        <?php endif; ?>
                        <a href="<?php echo SITE_URL; ?>/pages/rooms.php" class="btn-primary">Browse Rooms</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>



<script>
const conversationId = <?php echo $current_conversation ? (int) $current_conversation['id'] : 'null'; ?>;
const currentUserId = <?php echo (int) $user_id; ?>;
const csrfToken = <?php echo json_encode($csrf_token); ?>;
const currentUserAvatar = <?php echo json_encode($current_user_avatar); ?>;
const otherUserAvatar = <?php echo json_encode($other_user_avatar); ?>;
let lastMessageId = <?php echo $last_message_id; ?>;
let typingTimer = null;
let typingActive = false;
let livePollStatusHash = '';
let livePollTypingHash = '';
let livePollStopped = false;
let lastTypingPingAt = 0;

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function scrollToBottom() {
    const list = document.getElementById('messagesList');
    if (list) {
        list.scrollTop = list.scrollHeight;
    }
}

function appendMessage(message) {
    const list = document.getElementById('messagesList');
    if (!list) {
        return;
    }

    const isSelf = Number(message.sender_id) === currentUserId;
    const row = document.createElement('div');
    row.className = 'message-row' + (isSelf ? ' is-self' : '');

    const avatar = isSelf ? currentUserAvatar : otherUserAvatar;
    const state = isSelf ? (message.is_read ? 'Read' : (message.is_delivered ? 'Delivered' : 'Sent')) : '';
    const createdAt = message.created_at ? new Date(message.created_at.replace(' ', 'T')) : new Date();
    const timeLabel = createdAt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    row.innerHTML = `
        ${!isSelf ? `<img src="${avatar}" alt="" class="message-avatar">` : ''}
        <div class="message-bubble-wrap ${isSelf ? 'is-self' : ''}">
            <div class="message-bubble ${isSelf ? 'is-self' : ''}">${escapeHtml(message.message).replace(/\n/g, '<br>')}</div>
            <div class="message-meta ${isSelf ? 'is-self' : ''}">
                <span>${timeLabel}</span>
                ${isSelf ? `<span>${state}</span>` : ''}
            </div>
        </div>
        ${isSelf ? `<img src="${avatar}" alt="" class="message-avatar">` : ''}
    `;

    const typingIndicator = document.getElementById('typingIndicator');
    if (typingIndicator) {
        list.insertBefore(row, typingIndicator);
    } else {
        list.appendChild(row);
    }
}

function postAction(payload) {
    const formData = new FormData();
    Object.entries(payload).forEach(([key, value]) => formData.append(key, value));
    return fetch(window.location.href, { method: 'POST', body: formData }).then((response) => response.json());
}

document.getElementById('messageForm')?.addEventListener('submit', function (event) {
    event.preventDefault();
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    if (!message || !conversationId) {
        return;
    }

    postAction({ action: 'send_message', csrf_token: csrfToken, conversation_id: conversationId, message })
        .then((data) => {
            if (!data.success) {
                alert(data.message || 'Could not send message.');
                return;
            }
            input.value = '';
            lastMessageId = Math.max(lastMessageId, Number(data.message_id || 0));
            appendMessage({
                id: data.message_id,
                sender_id: currentUserId,
                message,
                created_at: data.created_at,
                is_delivered: Number(data.is_delivered || 0),
                is_read: Number(data.is_read || 0),
            });
            scrollToBottom();
            stopTyping();
        });
});

function handleRequest(action) {
    if (!conversationId) {
        return;
    }
    postAction({ action: action + '_request', csrf_token: csrfToken, conversation_id: conversationId })
        .then((data) => {
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.message || 'Could not update request.');
            }
        });
}

function sendTypingBeacon(isTyping) {
    if (!conversationId || !navigator.sendBeacon) {
        return;
    }
    const payload = new URLSearchParams({
        action: 'typing',
        csrf_token: csrfToken,
        conversation_id: String(conversationId),
        is_typing: isTyping ? 'true' : 'false'
    });
    navigator.sendBeacon(window.location.href, payload);
}

function sendTypingState(isTyping, force = false) {
    if (!conversationId) {
        return;
    }

    if (!force && typingActive === isTyping) {
        return;
    }

    typingActive = isTyping;
    lastTypingPingAt = isTyping ? Date.now() : 0;
    postAction({ action: 'typing', csrf_token: csrfToken, conversation_id: conversationId, is_typing: isTyping ? 'true' : 'false' })
        .catch(() => {
            typingActive = !isTyping;
            if (!isTyping) {
                lastTypingPingAt = 0;
            }
        });
}

function stopTyping(useBeacon = false) {
    window.clearTimeout(typingTimer);
    if (!typingActive) {
        return;
    }
    if (useBeacon) {
        typingActive = false;
        sendTypingBeacon(false);
        return;
    }
    sendTypingState(false);
}

const messageInput = document.getElementById('messageInput');
messageInput?.addEventListener('input', function () {
    if (!this.value.trim()) {
        stopTyping();
        return;
    }

    const now = Date.now();
    const shouldRefreshTyping = !typingActive || (now - lastTypingPingAt) > 900;
    sendTypingState(true, shouldRefreshTyping);

    window.clearTimeout(typingTimer);
    typingTimer = window.setTimeout(() => stopTyping(), 700);
});

messageInput?.addEventListener('blur', () => stopTyping(true));

messageInput?.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        document.getElementById('messageForm')?.dispatchEvent(new Event('submit'));
    }
});

document.addEventListener('visibilitychange', function () {
    if (document.visibilityState !== 'visible') {
        stopTyping(true);
    }
});

window.addEventListener('pagehide', () => {
    livePollStopped = true;
    stopTyping(true);
});
window.addEventListener('beforeunload', () => {
    livePollStopped = true;
    stopTyping(true);
});

function startLivePolling() {
    if (!conversationId || livePollStopped) {
        return;
    }

    const params = new URLSearchParams({
        conversation_id: String(conversationId),
        last_id: String(lastMessageId),
        mark_read: shouldMarkRead() ? '1' : '0',
        wait: '1',
        timeout: '12',
        status_hash: livePollStatusHash,
        typing_hash: livePollTypingHash
    });

    fetch(`${<?php echo json_encode(SITE_URL . '/api/get-new-messages.php'); ?>}?${params.toString()}`)
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                throw new Error(data.message || 'Polling failed');
            }

            livePollStatusHash = data.status_hash || livePollStatusHash;
            livePollTypingHash = data.typing_hash || livePollTypingHash;

            if (Array.isArray(data.messages)) {
                data.messages.forEach((message) => {
                    lastMessageId = Math.max(lastMessageId, Number(message.id || 0));
                    appendMessage(message);
                });
                if (data.messages.length > 0) {
                    scrollToBottom();
                }
            }

            updateMessageStatuses(data.status_updates);
            const typingIndicator = document.getElementById('typingIndicator');
            if (typingIndicator) {
                typingIndicator.hidden = !(Array.isArray(data.typing) && data.typing.length > 0);
            }

            startLivePolling();
        })
        .catch(() => {
            if (livePollStopped) {
                return;
            }
            window.setTimeout(startLivePolling, 1000);
        });
}

if (conversationId) {
    startLivePolling();
}

scrollToBottom();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
