<?php
/**
 * Seed sales transactions for all agents
 * Run from: C:\xampp\htdocs\generallink
 * Command:  php seed_sales_data.php
 */

$pdo = new PDO('mysql:host=127.0.0.1;dbname=generallink;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Starting sales seed...\n";

// Get products and vendors
$products = $pdo->query("SELECT product_id, product_name, product_type FROM products WHERE is_active=1 LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
$vendors  = $pdo->query("SELECT vendor_id, vendor_name FROM vendors WHERE is_active=1 LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

if (empty($products)) { echo "ERROR: No active products found.\n"; exit(1); }
if (empty($vendors))  { echo "ERROR: No active vendors found.\n"; exit(1); }

// All agents with sales potential (non-admin)
$agents = $pdo->query("
    SELECT agent_id, full_name, role, parent_id, group_id
    FROM agents
    WHERE is_deleted=0 AND role != 'ADMIN' AND status='ACTIVE'
")->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($agents) . " agents.\n";

// Premium ranges by role
$premiumRanges = [
    'GROUP_LEADER' => [800,  2000],
    'TEAM_LEADER'  => [500,  3000],
    'INTRODUCER'   => [300,  2500],
];

// Transaction statuses weighted
$statuses = ['ACTIVE','ACTIVE','ACTIVE','ACTIVE','ACTIVE','PENDING_RENEWAL','LAPSED'];

$inserted = 0;

foreach ($agents as $agent) {
    $range = $premiumRanges[$agent['role']] ?? [300, 1500];

    // Number of transactions: GL=2-4, TL=3-6, Intro=1-5
    $txCount = match($agent['role']) {
        'GROUP_LEADER' => rand(2, 4),
        'TEAM_LEADER'  => rand(3, 6),
        default        => rand(1, 5),
    };

    for ($t = 0; $t < $txCount; $t++) {
        $product = $products[array_rand($products)];
        $vendor  = $vendors[array_rand($vendors)];
        $status  = $statuses[array_rand($statuses)];
        $premium = rand($range[0], $range[1]);

        // Spread across last 6 months
        $monthsAgo   = rand(0, 5);
        $dayOfMonth  = rand(1, 28);
        $createdAt   = date('Y-m-d H:i:s', strtotime("-{$monthsAgo} months -{$dayOfMonth} days"));
        $coverStart  = date('Y-m-d', strtotime($createdAt));
        $coverEnd    = date('Y-m-d', strtotime($createdAt . ' +1 year'));
        $renewalDate = $status === 'PENDING_RENEWAL'
            ? date('Y-m-d', strtotime('+' . rand(1,60) . ' days'))
            : $coverEnd;

        $policyId     = sprintf('%08x-%04x-%04x-%04x-%12s',
            rand(0,0xffffffff), rand(0,0xffff), rand(0x4000,0x4fff),
            rand(0x8000,0xbfff), bin2hex(random_bytes(6)));
        $policyNumber = 'POL-' . strtoupper(substr($agent['agent_code'], 0, 3)) . '-' . date('Y', strtotime($createdAt)) . '-' . str_pad($inserted + 1, 4, '0', STR_PAD_LEFT);

        // Get or create a dummy customer_id
        $customerId = null;
        $custRow = $pdo->query("SELECT customer_id FROM customers LIMIT 1 OFFSET " . rand(0,7))->fetch(PDO::FETCH_ASSOC);
        if ($custRow) $customerId = $custRow['customer_id'];

        $pdo->prepare("
            INSERT INTO sales_transactions
                (policy_id, policy_number, agent_id, vendor_id, product_id, customer_id,
                 premium_amount, status, coverage_start, coverage_end, renewal_date,
                 is_deleted, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, ?, ?,
                 ?, ?, ?, ?, ?,
                 0, ?, ?)
        ")->execute([
            $policyId, $policyNumber,
            $agent['agent_id'], $vendor['vendor_id'], $product['product_id'], $customerId,
            $premium, $status, $coverStart, $coverEnd, $renewalDate,
            $createdAt, $createdAt,
        ]);

        $inserted++;
    }
    echo "  {$agent['full_name']} ({$agent['role']}) — {$txCount} transactions\n";
}

echo "\nDone! Inserted {$inserted} sales transactions.\n";
echo "Now run: php artisan cache:clear\n";
