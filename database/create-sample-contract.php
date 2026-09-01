<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    die('Access denied');
}

if (defined('IS_PRODUCTION') && IS_PRODUCTION) {
    http_response_code(403);
    die('Disabled in production');
}

$tenant = $conn->query("SELECT id FROM users WHERE role = 'tenant' ORDER BY id ASC LIMIT 1")->fetch();
$landlord = $conn->query("SELECT id FROM users WHERE role = 'landlord' ORDER BY id ASC LIMIT 1")->fetch();
$room = null;
if ($landlord) {
    $room = $conn->query('SELECT id FROM rooms WHERE landlord_id = ' . (int) $landlord['id'] . ' ORDER BY id ASC LIMIT 1')->fetch();
}

if ($tenant && $landlord && $room) {
    $conversation_id = getOrCreateConversation((int) $room['id'], (int) $tenant['id'], (int) $landlord['id']);
    acceptContactRequest($conversation_id, (int) $landlord['id']);
    $result = createContract([
        'room_id' => (int) $room['id'],
        'tenant_id' => (int) $tenant['id'],
        'landlord_id' => (int) $landlord['id'],
        'conversation_id' => $conversation_id,
        'monthly_rent' => 15000,
        'advance_amount' => 15000,
        'deposit_amount' => 30000,
        'contract_start_date' => date('Y-m-d'),
        'contract_end_date' => date('Y-m-d', strtotime('+1 year')),
        'is_indefinite' => false,
        'notice_period' => 30,
        'payment_day' => 1,
        'utilities_included' => false,
        'electricity_charge' => 'As per meter',
        'water_charge' => 'Rs. 500 fixed',
        'internet_charge' => 'Rs. 1000 optional',
        'guest_policy' => 'Guests allowed with prior notice. Overnight stays limited to 3 days.',
        'pet_policy' => 'No pets allowed.',
        'smoking_policy' => 'No smoking inside the room.',
        'noise_policy' => 'Please maintain silence after 10 PM.',
        'additional_rules' => 'Keep common areas clean.',
    ]);

    if ($result['success']) {
        echo 'Sample contract created! ID: ' . $result['contract_id'] . '<br>';
        echo 'Contract Number: ' . htmlspecialchars($result['contract_number']) . '<br><br>';
        echo '<a href="../pages/contract-detail.php?id=' . (int) $result['contract_id'] . '">View Contract</a>';
    } else {
        echo 'Error: ' . htmlspecialchars($result['message']);
    }
} else {
    echo 'Need at least one tenant, one landlord, and one room.';
}
?>
