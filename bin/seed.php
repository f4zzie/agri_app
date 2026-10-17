<?php
/**
 * AgriTrack – demo data seeder
 *
 *   php bin/seed.php              seed an empty database (refuses if users exist)
 *   php bin/seed.php --if-empty   seed only when there are no users (used by Docker)
 *   php bin/seed.php --force      delete ALL rows first, then seed
 *
 * Creates two demo logins (see README) plus ~30 farmers and buyers across
 * Kenyan counties, current listings priced in KES, conversations with
 * ratings, and 12 months of price history so the trends page has data.
 * Deterministic (fixed random seed) so screenshots are reproducible.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}

require_once __DIR__ . '/../config.php';

const DEMO_PASSWORD = 'Demo@1234';

$opts = getopt('', ['force', 'if-empty']);
$db   = getDB();

$userCount = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($userCount > 0 && !isset($opts['force'])) {
    if (isset($opts['if-empty'])) {
        echo "Database already has users; skipping seed.\n";
        exit(0);
    }
    fwrite(STDERR, "Database already has {$userCount} user(s). Use --force to wipe and reseed.\n");
    exit(1);
}

mt_srand(207); // SCO 207 :)

$rand  = static fn (float $min, float $max): float => $min + mt_rand() / mt_getrandmax() * ($max - $min);
$pick  = static fn (array $a) => $a[array_rand($a)];
$county = array_flip(counties()); // name => id

// ---------------------------------------------------------------------------
// Reference data: produce, typical unit, base price (KES), counties that grow it
// ---------------------------------------------------------------------------
$produce = [
    // name,               category,             unit,     base,  varieties,                        counties
    ['Maize',              'Grains & Cereals',   'kg',      48,   ['H614', 'DH04', 'Pioneer 3253'], ['Trans Nzoia', 'Uasin Gishu', 'Nakuru', 'Bungoma']],
    ['Rice',               'Grains & Cereals',   'kg',     190,   ['Mwea Pishori', 'Basmati 370'],  ['Kirinyaga', 'Kisumu']],
    ['Sorghum',            'Grains & Cereals',   'kg',      65,   ['Gadam', 'Serena'],              ['Siaya', 'Busia', 'Makueni']],
    ['Beans',              'Legumes & Pulses',   'kg',     145,   ['Rosecoco', 'Mwitemania', 'Nyayo'], ['Bungoma', 'Nyeri', 'Meru', 'Kakamega']],
    ['Green Grams',        'Legumes & Pulses',   'kg',     170,   ['N26 Ndengu', 'KS20'],           ['Kitui', 'Machakos', 'Makueni']],
    ['Irish Potatoes',     'Roots & Tubers',     'kg',      48,   ['Shangi', 'Dutch Robjin', 'Unica'], ['Nyandarua', 'Meru', 'Nakuru', 'Elgeyo-Marakwet']],
    ['Sweet Potatoes',     'Roots & Tubers',     'kg',      55,   ['Kabode', 'Vitaa'],              ['Homa Bay', 'Migori', 'Busia']],
    ['Cassava',            'Roots & Tubers',     'kg',      45,   ['Migyera', 'Tajirika'],          ['Busia', 'Kilifi', 'Siaya']],
    ['Tomatoes',           'Vegetables',         'kg',      85,   ['Anna F1', 'Kilele F1', 'Rio Grande'], ['Kirinyaga', 'Kajiado', 'Machakos', 'Laikipia']],
    ['Sukuma Wiki',        'Vegetables',         'bundle',  25,   ['Collards', 'Thousand-headed'],  ['Kiambu', 'Nyeri', 'Murang\'a']],
    ['Cabbage',            'Vegetables',         'piece',   45,   ['Gloria F1', 'Copenhagen'],      ['Nyandarua', 'Meru', 'Kiambu']],
    ['Onions',             'Vegetables',         'kg',     100,   ['Red Creole', 'Red Pinoy F1'],   ['Kajiado', 'Laikipia', 'Narok']],
    ['Carrots',            'Vegetables',         'kg',      70,   ['Nantes', 'Chantenay'],          ['Nyandarua', 'Meru']],
    ['Avocado',            'Fruits',             'kg',      95,   ['Hass', 'Fuerte'],               ['Murang\'a', 'Kiambu', 'Meru', 'Kisii']],
    ['Mangoes',            'Fruits',             'piece',   25,   ['Apple', 'Ngowe', 'Tommy Atkins'], ['Makueni', 'Machakos', 'Kilifi']],
    ['Bananas',            'Fruits',             'bundle', 450,   ['Cavendish', 'Matooke'],         ['Kisii', 'Meru', 'Kirinyaga']],
    ['Pineapples',         'Fruits',             'piece',  110,   ['Smooth Cayenne'],               ['Murang\'a', 'Kiambu', 'Kakamega']],
    ['Passion Fruit',      'Fruits',             'kg',     130,   ['Purple', 'Yellow'],             ['Uasin Gishu', 'Kirinyaga', 'Embu']],
    ['Milk',               'Dairy & Eggs',       'litre',   55,   ['Fresh raw'],                    ['Kiambu', 'Nyandarua', 'Nakuru', 'Bomet']],
    ['Eggs',               'Dairy & Eggs',       'crate',  450,   ['Kienyeji', 'Layers'],           ['Kiambu', 'Machakos', 'Nakuru']],
    ['Kienyeji Chicken',   'Meat & Poultry',     'piece',  950,   ['Improved Kienyeji'],            ['Kakamega', 'Machakos', 'Kisumu']],
    ['Dhania',             'Herbs & Spices',     'bundle',  15,   ['Coriander'],                    ['Kiambu', 'Kajiado']],
    ['Chillies',           'Herbs & Spices',     'kg',     220,   ['Bird\'s eye', 'Long cayenne'],  ['Kilifi', 'Kwale', 'Makueni']],
];

// Seasonal multiplier by month (1–12): prices rise in the lean months
// before the long-rains harvest and dip after it.
$season = [1 => 1.00, 2 => 1.05, 3 => 1.10, 4 => 1.14, 5 => 1.12, 6 => 1.04,
           7 => 0.95, 8 => 0.90, 9 => 0.92, 10 => 0.97, 11 => 1.00, 12 => 1.02];

// ---------------------------------------------------------------------------
// People
// ---------------------------------------------------------------------------
$farmerNames = ['Wanjiru Kamau', 'Otieno Ochieng', 'Chebet Kiprono', 'Mwangi Njoroge', 'Achieng Atieno', 'Kiplagat Rotich',
    'Nyambura Wairimu', 'Mutua Musyoka', 'Wafula Wekesa', 'Akinyi Odhiambo', 'Kariuki Gitau', 'Jepkosgei Sang',
    'Muthoni Ngugi', 'Barasa Simiyu', 'Kendi Mwiti', 'Omondi Onyango', 'Nduta Waweru', 'Kimani Macharia',
    'Wanjala Nafula', 'Moraa Nyaboke', 'Kioko Mutiso', 'Cherono Langat', 'Gathoni Mugo', 'Ouma Okoth'];
$buyers = [
    ['Grace Njeri',     'buyer@agritrack.test', 'Nairobi',  'Fresh Basket Grocers'],
    ['David Kiprop',    null, 'Uasin Gishu', 'Eldoret Hotel Kitchen'],
    ['Fatuma Hassan',   null, 'Mombasa',    'Pwani Fresh Mart'],
    ['Peter Maina',     null, 'Nakuru',     'Lakeside Supermarket'],
    ['Lilian Awuor',    null, 'Kisumu',     'Lake Basin Caterers'],
    ['Samuel Mutiso',   null, 'Machakos',   'Ukambani Schools Supply'],
    ['Esther Wambui',   null, 'Kiambu',     'Thika Road Restaurant'],
    ['Hassan Abdi',     null, 'Nairobi',    'Eastleigh Wholesale'],
];

$hash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);

if (isset($opts['force'])) {
    echo "Deleting existing rows...\n";
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['ratings', 'messages', 'inquiries', 'price_history', 'crops', 'activity_log',
              'password_resets', 'login_attempts', 'users'] as $table) {
        $db->exec("DELETE FROM {$table}");
        $db->exec("ALTER TABLE {$table} AUTO_INCREMENT = 1");
    }
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
}

$db->beginTransaction();

$insUser = $db->prepare(
    'INSERT INTO users (name, email, password_hash, email_verified, role, phone, county_id, created_at)
     VALUES (?, ?, ?, 1, ?, ?, ?, ?)'
);
$phone = static fn (): string => '07' . mt_rand(10, 99) . ' ' . mt_rand(100000, 999999);
$daysAgo = static fn (int $d): string => date('Y-m-d H:i:s', strtotime("-{$d} days") - mt_rand(0, 36000));

// Farmers. The first one is the documented demo login.
$farmerIds = [];
foreach ($farmerNames as $i => $name) {
    $email = $i === 0 ? 'farmer@agritrack.test'
                      : strtolower(str_replace(' ', '.', $name)) . '@example.co.ke';
    $insUser->execute([$name, $email, $hash, 'farmer', $phone(), null, $daysAgo(mt_rand(200, 400))]);
    $farmerIds[] = (int) $db->lastInsertId();
}
$buyerIds = [];
foreach ($buyers as [$name, $email, $countyName, $business]) {
    $email ??= strtolower(explode(' ', $name)[0]) . '@' . strtolower(preg_replace('/[^a-z]/i', '', $business)) . '.co.ke';
    $insUser->execute(["{$name} ({$business})", $email, $hash, 'buyer', $phone(), $county[$countyName], $daysAgo(mt_rand(100, 300))]);
    $buyerIds[] = (int) $db->lastInsertId();
}

// ---------------------------------------------------------------------------
// Listings: each farmer grows 3–5 products suited to their county
// ---------------------------------------------------------------------------
$categories = array_flip(categories());
$insCrop = $db->prepare(
    'INSERT INTO crops (user_id, name, variety, category_id, price, unit, quantity, status, growth_stage,
                        planting_date, expected_harvest, notes, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$notes = ['Well dried and sorted, ready for collection.', 'Can deliver within the county for bulk orders.',
    'Grown with certified seed. Discount for orders above 500 units.', 'Fresh from the farm, harvested this week.',
    'Organic manure only, no synthetic pesticides.', 'Available weekly. Call to arrange pick-up.', null, null];

$cropIds = [];
$setCounty = $db->prepare('UPDATE users SET county_id = ? WHERE id = ?');

foreach ($farmerIds as $idx => $farmerId) {
    // Give the farmer a county where at least two products grow
    $myProduce = [];
    if ($idx === 0) {
        $farmerCounty = 'Nakuru';
    } else {
        $p0 = $pick($produce);
        $farmerCounty = $pick($p0[5]);
    }
    $setCounty->execute([$county[$farmerCounty], $farmerId]);
    foreach ($produce as $p) {
        if (in_array($farmerCounty, $p[5], true)) {
            $myProduce[] = $p;
        }
    }
    if ($idx === 0) {
        // Demo farmer: fixed, so the README and screenshots stay accurate
        $order = ['Maize', 'Irish Potatoes', 'Milk', 'Eggs'];
        usort($myProduce, static fn ($a, $b) => array_search($a[0], $order, true) <=> array_search($b[0], $order, true));
    } else {
        shuffle($myProduce);
        $myProduce = array_slice($myProduce, 0, mt_rand(3, 5));
    }

    foreach ($myProduce as $j => [$name, $cat, $unit, $base, $varieties, $_]) {
        $price = round($base * $season[(int) date('n')] * $rand(0.88, 1.12), $base >= 100 ? 0 : 1);

        // Mix of states: most available now, some still growing with a harvest date.
        // Livestock products (milk, eggs, chicken) have no growth stage.
        $livestock = in_array($cat, ['Dairy & Eggs', 'Meat & Poultry'], true);
        $roll = mt_rand(1, 100);
        if ($livestock) {
            [$status, $stage, $plantAgo, $harvestIn] = [$idx === 0 || $roll <= 85 ? 'available' : 'out_of_stock', null, null, null];
        } elseif ($idx === 0 && $j < 2) {
            // Demo farmer: two crops due within the week, to show reminders
            [$status, $stage, $plantAgo, $harvestIn] = ['out_of_stock', 'growing', 110, 3 + 3 * $j];
        } elseif ($roll <= 60) {
            [$status, $stage, $plantAgo, $harvestIn] = ['available', 'harvested', mt_rand(100, 160), -mt_rand(3, 30)];
        } elseif ($roll <= 75) {
            [$status, $stage, $plantAgo, $harvestIn] = ['available', null, null, null];
        } elseif ($roll <= 90) {
            [$status, $stage, $plantAgo, $harvestIn] = ['out_of_stock', $pick(['planted', 'growing']), mt_rand(20, 90), mt_rand(5, 60)];
        } else {
            [$status, $stage, $plantAgo, $harvestIn] = ['out_of_stock', $pick(['harvested', 'failed']), mt_rand(120, 200), -mt_rand(10, 40)];
        }

        $insCrop->execute([
            $farmerId, $name, $pick($varieties), $categories[$cat], $price, $unit,
            $status === 'available' ? mt_rand(2, 80) * ($base > 300 ? 1 : 10) : null,
            $status, $stage,
            $plantAgo !== null ? date('Y-m-d', strtotime("-{$plantAgo} days")) : null,
            $harvestIn !== null ? date('Y-m-d', strtotime(($harvestIn >= 0 ? '+' : '') . "{$harvestIn} days")) : null,
            $pick($notes),
            $daysAgo(mt_rand(1, 60)),
        ]);
        $cropIds[] = ['id' => (int) $db->lastInsertId(), 'farmer' => $farmerId, 'name' => $name,
                      'unit' => $unit, 'status' => $status, 'price' => $price];
    }
}

// ---------------------------------------------------------------------------
// 12 months of price history per product and county (seasonal + trend + noise)
// ---------------------------------------------------------------------------
$insHist = $db->prepare(
    'INSERT INTO price_history (product_key, unit, county_id, price, recorded_at) VALUES (?, ?, ?, ?, ?)'
);
$historyRows = 0;
foreach ($produce as [$name, $_c, $unit, $base, $_v, $counties]) {
    $trend = $rand(-0.10, 0.15); // year-on-year drift
    foreach ($counties as $cName) {
        $countyFactor = $rand(0.92, 1.08); // some counties are consistently cheaper
        for ($m = 12; $m >= 1; $m--) {
            $ts    = strtotime("first day of -{$m} months");
            $month = (int) date('n', $ts);
            for ($k = 0, $n = mt_rand(2, 5); $k < $n; $k++) {
                $p = $base * $season[$month] * $countyFactor * (1 + $trend * (12 - $m) / 12) * $rand(0.93, 1.07);
                $insHist->execute([
                    strtolower($name), $unit, $county[$cName], round($p, $base >= 100 ? 0 : 1),
                    date('Y-m-d H:i:s', $ts + mt_rand(0, 27) * 86400 + mt_rand(0, 86399)),
                ]);
                $historyRows++;
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Conversations, messages and ratings
// ---------------------------------------------------------------------------
$openers = [
    'Hello, I need about {qty} {unit} of {product} every week for our kitchen. Can you supply from next Monday?',
    'Good morning. Is the {product} still available? I would like {qty} {unit}. What is your best price for that quantity?',
    'Habari, we are looking for a steady supplier of {product}. Could you do {qty} {unit} delivered to our store?',
    'Hi! Can I collect {qty} {unit} of {product} this Friday? Please confirm the quality first.',
];
$farmerReplies = [
    'Yes, it is available. For that quantity I can do KSh %s per %s, delivery included within the county.',
    'Asante for reaching out. I can supply. Price is KSh %s per %s; I can send photos on WhatsApp.',
    'Yes please. KSh %s per %s if you take the full amount. When would you like delivery?',
];
$buyerReplies = ['That works for us. Please deliver on Tuesday morning.', 'Deal. I will pay via M-Pesa on delivery.',
    'Sounds good, kindly confirm the exact delivery time.', 'Great, let us proceed. Asante sana!'];
$reviews = [5 => ['Excellent quality and delivered on time.', 'Very reliable farmer, will order again.', 'Produce was fresh and well packed.'],
            4 => ['Good quality, delivery was a bit late.', 'Good price and friendly communication.'],
            3 => ['Quality was okay but some items were damaged.', 'Took long to respond but delivered.'],
            2 => ['Quantity was less than agreed.'], 1 => ['Did not deliver as agreed.']];

$insInq = $db->prepare('INSERT INTO inquiries (crop_id, buyer_id, contact_phone, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
$insMsg = $db->prepare('INSERT INTO messages (inquiry_id, sender_id, body, created_at) VALUES (?, ?, ?, ?)');
$insRat = $db->prepare('INSERT INTO ratings (inquiry_id, score, comment, created_at) VALUES (?, ?, ?, ?)');

$available = array_values(array_filter($cropIds, static fn ($c) => $c['status'] === 'available'));
$demoFarmerCrops = array_values(array_filter($available, static fn ($c) => $c['farmer'] === $farmerIds[0]));
$pairs = [];
$threads = 0;
for ($n = 0; $n < 60; $n++) {
    // Make sure both demo accounts have conversations to show
    $crop  = $n < 4 && $demoFarmerCrops ? $demoFarmerCrops[$n % count($demoFarmerCrops)] : $pick($available);
    $buyer = $n % 5 === 0 ? $buyerIds[0] : $pick($buyerIds);
    if (isset($pairs[$crop['id'] . '-' . $buyer])) {
        continue;
    }
    $pairs[$crop['id'] . '-' . $buyer] = true;

    $start  = strtotime('-' . mt_rand(1, 50) . ' days') - mt_rand(0, 40000);
    $qty    = mt_rand(2, 40) * ($crop['unit'] === 'kg' ? 10 : 1);
    $unitTr = $crop['unit'];
    $status = $n === 0 ? 'pending' : $pick(['pending', 'read', 'responded', 'responded', 'completed', 'completed', 'completed']);
    $insInq->execute([$crop['id'], $buyer, null, $status, date('Y-m-d H:i:s', $start), date('Y-m-d H:i:s', $start)]);
    $inqId = (int) $db->lastInsertId();
    $threads++;

    $t = $start;
    $body = strtr($pick($openers), ['{qty}' => $qty, '{unit}' => $unitTr, '{product}' => strtolower($crop['name'])]);
    $insMsg->execute([$inqId, $buyer, $body, date('Y-m-d H:i:s', $t)]);

    if (in_array($status, ['responded', 'completed'], true)) {
        $t += mt_rand(1800, 30000);
        $offer = number_format($crop['price'] * $rand(0.93, 1.0), $crop['price'] >= 100 ? 0 : 1);
        $insMsg->execute([$inqId, $crop['farmer'], sprintf($pick($farmerReplies), $offer, $unitTr), date('Y-m-d H:i:s', $t)]);
    }
    if ($status === 'completed') {
        $t += mt_rand(1800, 20000);
        $insMsg->execute([$inqId, $buyer, $pick($buyerReplies), date('Y-m-d H:i:s', $t)]);
        if (mt_rand(1, 100) <= 80) {
            $score = $pick([5, 5, 5, 4, 4, 4, 3, 2]);
            $insRat->execute([$inqId, $score, $pick($reviews[$score]), date('Y-m-d H:i:s', $t + mt_rand(86400, 300000))]);
        }
    }
}

// A little activity history for the demo accounts
$insLog = $db->prepare('INSERT INTO activity_log (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?)');
foreach ([[$farmerIds[0], 'add_product', 'Listed product: Maize'], [$farmerIds[0], 'login', 'User logged in'],
          [$buyerIds[0], 'login', 'User logged in'], [$buyerIds[0], 'send_inquiry', 'Inquiry sent for: Tomatoes']] as [$u, $a, $d]) {
    $insLog->execute([$u, $a, $d, '127.0.0.1', $daysAgo(mt_rand(1, 10))]);
}

$db->commit();

printf(
    "Seeded %d farmers, %d buyers, %d listings, %d conversations, %d price-history rows.\n\n" .
    "Demo logins (password %s):\n  farmer@agritrack.test  (Wanjiru Kamau, Nakuru)\n  buyer@agritrack.test   (Grace Njeri, Fresh Basket Grocers)\n",
    count($farmerIds), count($buyerIds), count($cropIds), $threads, $historyRows, DEMO_PASSWORD
);
