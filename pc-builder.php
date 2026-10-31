<?php
// PC Builder: pick one part for each slot and PHP checks that they work together.
// The choices travel in the URL (pc-builder.php?cpu=23&motherboard=56...), so the form is a plain GET
// form, every build has its own shareable link, and the checks all run on the server.
// "Add build to cart" posts the chosen ids to PHP/shopping_cart/add_build.php.
// Part specs (part_type, socket, memory_type, wattage) are columns on the products table.

require_once __DIR__ . "/PHP/db_connect.php";
require_once __DIR__ . "/PHP/products/render.php";   // h(), money(), isOnSale()

// Slot => [label, icon, needed for a working PC?, drop-down prompt]
const SLOTS = [
    "cpu" => ["Processor (CPU)", "fa-microchip", true, "Choose a processor..."],
    "motherboard" => ["Motherboard", "fa-chess-board", true, "Choose a motherboard..."],
    "memory" => ["Memory (RAM)", "fa-memory", true, "Choose memory..."],
    "gpu" => ["Graphics card", "fa-display", false, "Choose a graphics card..."],
    "storage" => ["Storage", "fa-hard-drive", true, "Choose a drive..."],
    "psu" => ["Power supply", "fa-plug", true, "Choose a power supply..."],
    "cooler" => ["CPU cooler", "fa-fan", false, "Choose a CPU cooler..."],
    "case" => ["Case", "fa-box", true, "Choose a case..."],
];
const OTHER_PARTS_WATTS = 100;   // motherboard, memory, drives and fans, roughly
const PSU_HEADROOM = 1.3;        // recommend 30% more than the estimated draw

$parts = array_fill_keys(array_keys(SLOTS), []);
$result = mysqli_query($conn,
    "SELECT product_id, name, brand, price, old_price, stock, image_path, part_type, socket, memory_type, wattage
     FROM products WHERE is_active = 1 AND part_type IS NOT NULL ORDER BY price");
while ($row = mysqli_fetch_assoc($result)) {
    $parts[$row['part_type']][(int) $row['product_id']] = $row;
}
mysqli_close($conn);

// The build: one chosen product per slot, taken from the URL (ignored unless it is a real part for that slot)
$build = [];
foreach (SLOTS as $slot => $info) {
    $id = (int) ($_GET[$slot] ?? 0);
    if (isset($parts[$slot][$id])) {
        $build[$slot] = $parts[$slot][$id];
    }
}
$cpu = $build['cpu'] ?? null;
$board = $build['motherboard'] ?? null;
$memory = $build['memory'] ?? null;
$gpu = $build['gpu'] ?? null;
$psu = $build['psu'] ?? null;

// ---------- Compatibility checks: [level, message] with level ok / warning / error ----------
$checks = [];
if ($cpu && $board) {
    $checks[] = $cpu['socket'] === $board['socket']
        ? ["ok", "Processor and motherboard both use the {$cpu['socket']} socket."]
        : ["error", "The {$cpu['name']} needs an {$cpu['socket']} motherboard, but the {$board['name']} is {$board['socket']}."];
}
if ($board && $memory) {
    $checks[] = $board['memory_type'] === $memory['memory_type']
        ? ["ok", "The motherboard takes {$board['memory_type']} memory, which matches your RAM."]
        : ["error", "The {$board['name']} takes {$board['memory_type']} memory, but the {$memory['name']} is {$memory['memory_type']}."];
}

// Power: CPU + graphics card + the rest, then 30% headroom for a quiet, efficient power supply
$estimatedWatts = ($cpu['wattage'] ?? 0) + ($gpu['wattage'] ?? 0) + OTHER_PARTS_WATTS;
$recommendedWatts = (int) (ceil($estimatedWatts * PSU_HEADROOM / 50) * 50);
if ($psu) {
    if ($psu['wattage'] < $estimatedWatts) {
        $checks[] = ["error", "The {$psu['wattage']}W power supply is too small: this build needs about {$estimatedWatts}W."];
    } elseif ($psu['wattage'] < $recommendedWatts) {
        $checks[] = ["warning", "The {$psu['wattage']}W power supply works, but {$recommendedWatts}W or more would leave safer headroom."];
    } else {
        $checks[] = ["ok", "The {$psu['wattage']}W power supply covers the estimated {$estimatedWatts}W with room to spare."];
    }
}
// These Ryzen X3D processors don't come with a cooler in the box
if ($cpu && empty($build['cooler'])) {
    $checks[] = ["warning", "The {$cpu['name']} doesn't include a cooler. Add a CPU cooler."];
}
foreach ($build as $part) {
    if ($part['stock'] == 0) {
        $checks[] = ["error", "{$part['name']} is out of stock right now."];
    }
}

$missing = [];
foreach (SLOTS as $slot => [$label, , $required]) {
    if ($required && empty($build[$slot])) {
        $missing[] = $label;
    }
}
// Without a graphics card the processor must have built-in graphics; ours (X3D) only have a basic one
if ($cpu && !$gpu) {
    $checks[] = ["warning", "No graphics card: the processor's built-in graphics are fine for desktop work, but not for gaming."];
}

$errors = count(array_filter($checks, fn($c) => $c[0] === "error"));
$total = array_sum(array_column($build, 'price'));
$saving = array_sum(array_map(fn($p) => isOnSale($p) ? $p['old_price'] - $p['price'] : 0, $build));

if (!$build) {
    $status = ["empty", "fa-screwdriver-wrench", "Start by choosing a processor"];
} elseif ($errors) {
    $status = ["error", "fa-circle-xmark", $errors === 1 ? "1 problem to fix" : "$errors problems to fix"];
} elseif ($missing) {
    $status = ["warning", "fa-circle-half-stroke", "Compatible so far: " . count($missing) . " part" . (count($missing) === 1 ? "" : "s") . " still needed"];
} else {
    $status = ["ok", "fa-circle-check", "Every part is compatible"];
}

// Text shown after a part's name in its drop-down, e.g. "(AM5, DDR5)", plus a warning when it won't fit
function partNote($slot, $part, $cpu, $board)
{
    $specs = array_filter([$part['socket'], $part['memory_type'], $part['wattage'] ? "{$part['wattage']}W" : null]);
    $note = $specs ? " (" . implode(", ", $specs) . ")" : "";
    if ($slot === "motherboard" && $cpu && $part['socket'] !== $cpu['socket']) {
        $note .= " - doesn't fit this CPU";
    } elseif ($slot === "cpu" && $board && $part['socket'] !== $board['socket']) {
        $note .= " - doesn't fit this motherboard";
    } elseif ($slot === "memory" && $board && $part['memory_type'] !== $board['memory_type']) {
        $note .= " - doesn't fit this motherboard";
    }
    if ($part['stock'] == 0) {
        $note .= " - out of stock";
    }
    return $note;
}

// Ready-made starting points: [title, description, product names]
$presets = [
    ["AM5 gaming PC", "Ryzen 7 9800X3D + RX 9070 XT, for high-refresh 1440p",
        ["AMD Ryzen 7 9800X3D Processor", "ASUS TUF Gaming B650-PLUS Motherboard", "Crucial Pro 64GB (2x32GB) DDR5-6400 Kit", "AMD Radeon RX 9070 XT", "WD Blue 1TB SATA SSD", "Corsair RM850x Shift 850W Power Supply", "ARCTIC Liquid Freezer III Pro 360 CPU Cooler", "NZXT H5 Flow Mid-Tower Case"]],
    ["Creator workstation", "16-core Ryzen 9 9950X3D + RTX 5080 for editing and 3D",
        ["AMD Ryzen 9 9950X3D Processor", "ASUS TUF Gaming B650-PLUS Motherboard", "Crucial Pro 64GB (2x32GB) DDR5-6400 Kit", "NVIDIA GeForce RTX 5080 Founders Edition", "WD Blue 1TB SATA SSD", "Corsair RM850x Shift 850W Power Supply", "ARCTIC Liquid Freezer III Pro 360 CPU Cooler", "NZXT H5 Flow Mid-Tower Case"]],
];
$presetLinks = [];
foreach ($presets as [$title, $blurb, $names]) {
    $query = [];
    foreach ($parts as $slot => $list) {
        foreach ($list as $id => $part) {
            if (in_array($part['name'], $names, true)) {
                $query[$slot] = $id;
            }
        }
    }
    if (count($query) === count($names)) {   // only offer presets whose parts are all on sale
        $presetLinks[] = [$title, $blurb, "pc-builder.php?" . http_build_query($query)];
    }
}
$shareUrl = "pc-builder.php" . ($build ? "?" . http_build_query(array_map(fn($p) => $p['product_id'], $build)) : "");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="CSS/global.css">
    <link rel="stylesheet" href="CSS/shop.css">
    <link rel="stylesheet" href="CSS/product.css">
    <link rel="stylesheet" href="CSS/pc-builder.css">
    <link rel="stylesheet" href="CSS/dark.css">
    <link rel="stylesheet" href="CSS/light.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="JS/theme.js"></script>
    <script src="JS/menu.js" defer></script>
    <script src="JS/session.js" defer></script>
    <script src="JS/pc-builder.js" defer></script>
    <title>PC Builder | Quantum Gear</title>
</head>
<body>
    <header>
        <nav>
            <div class="nav-top">
                <img src="Images/logo.png" alt="Logo" class="logo">
                <div class="search-box">
                    <input type="text" placeholder="Search...">
                    <i class="fa fa-search"></i>
                </div>
                <!-- Light / dark slider: the knob sits under the active mode's icon (JS/theme.js) -->
                <button type="button" class="theme-toggle" id="themeToggle" role="switch" aria-checked="false" aria-label="Light mode">
                    <span class="theme-knob"></span>
                    <i class="fa-solid fa-sun" aria-hidden="true"></i>
                    <i class="fa-solid fa-moon" aria-hidden="true"></i>
                </button>
                <a href="order.html" class="cart-icon" aria-label="Cart">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span class="cart-count">0</span>
                </a>
                <a href="login.html" class="btn" id="openLoginBtn">Login/Register</a>
            </div>
            <div class="break"></div>
            <ul class="nav-links">
                <li class="menu-item">
                    <button class="menu-toggle" id="menuToggle" aria-haspopup="true" aria-expanded="false">
                        <i class="fa-solid fa-bars"></i> Menu <i class="fa-solid fa-chevron-down chevron"></i>
                    </button>
                    <div class="mega-menu" id="megaMenu">
                        <div class="mega-menu-col mega-menu-quick">
                            <ul>
                                <li><a href="hotdeals.php#shell-shocker"><i class="fa-solid fa-bolt"></i> Shell Shocker</a></li>
                                <li><a href="hotdeals.php#clearance"><i class="fa-solid fa-tags"></i> Clearance Deals</a></li>
                                <li><a href="hotdeals.php#email-deals"><i class="fa-regular fa-envelope"></i> Email Deals</a></li>
                                <li><a href="contact.html?subject=trade-in"><i class="fa-solid fa-rotate"></i> Trade In</a></li>
                            </ul>
                            <p class="mega-menu-heading">Help me build&hellip;</p>
                            <ul class="mega-menu-plain">
                                <li><a href="pc-builder.php">Desktop PC (PC Builder)</a></li>
                                <li><a href="shop.html?category=Computer%20Systems">Workstation</a></li>
                                <li><a href="shop.html?category=Networking">Home Network</a></li>
                                <li><a href="shop.html?category=Gaming%20%26%20VR">Game Room</a></li>
                                <li><a href="contact.html?subject=build">Server / NAS (ask us)</a></li>
                            </ul>
                        </div>
                        <div class="mega-menu-col mega-menu-products">
                            <p class="mega-menu-heading">All Products</p>
                            <!-- Same categories as the categories table in database/schema.sql -->
                            <ul>
                                <li><a href="shop.html?category=Components%20%26%20Storage"><span><i class="fa-solid fa-memory"></i> Components &amp; Storage</span><i class="fa-solid fa-chevron-right"></i></a></li>
                                <li><a href="shop.html?category=Computer%20Systems"><span><i class="fa-solid fa-desktop"></i> Computer Systems</span><i class="fa-solid fa-chevron-right"></i></a></li>
                                <li><a href="shop.html?category=Computer%20Peripherals"><span><i class="fa-solid fa-computer-mouse"></i> Computer Peripherals</span><i class="fa-solid fa-chevron-right"></i></a></li>
                                <li><a href="shop.html?category=Gaming%20%26%20VR"><span><i class="fa-solid fa-gamepad"></i> Gaming &amp; VR</span><i class="fa-solid fa-chevron-right"></i></a></li>
                                <li><a href="shop.html?category=Networking"><span><i class="fa-solid fa-wifi"></i> Networking</span><i class="fa-solid fa-chevron-right"></i></a></li>
                                <li><a href="shop.html?category=Electronics"><span><i class="fa-solid fa-headphones"></i> Electronics</span><i class="fa-solid fa-chevron-right"></i></a></li>
                                <li><a href="shop.html"><span><i class="fa-solid fa-store"></i> Shop All</span><i class="fa-solid fa-chevron-right"></i></a></li>
                            </ul>
                        </div>
                    </div>
                </li>
                <li><a href="index.php">Home</a></li>
                <li><a href="shop.html">Shop</a></li>
                <li><a href="pc-builder.php">PC Builder</a></li>
                <li><a href="contact.html">Contact Us</a></li>
                <li><a href="hotdeals.php">Hot Deals</a></li>
            </ul>
        </nav>
    </header>
    <main class="builder-page">
        <section class="page-hero">
            <img src="Images/Logo.png" alt="Quantum Gear logo" class="page-hero-logo">
            <div>
                <h1><i class="fa-solid fa-screwdriver-wrench"></i> PC Builder</h1>
                <p>Choose a part for each slot and we check that everything fits together: the processor socket, the memory type and whether the power supply is big enough. Then add the whole build to your cart in one click.</p>
            </div>
        </section>

<?php if ($presetLinks): ?>
        <div class="presets">
            <p class="presets-title">Not sure where to start? Try a ready-made build:</p>
<?php foreach ($presetLinks as [$title, $blurb, $url]): ?>
            <a class="preset" href="<?= h($url) ?>">
                <strong><?= h($title) ?></strong>
                <span><?= h($blurb) ?></span>
            </a>
<?php endforeach; ?>
        </div>
<?php endif; ?>

        <div class="builder-layout">
            <!-- A GET form: the build is in the URL, so PHP checks it and the link can be shared -->
            <form class="builder-form" id="builderForm" method="get" action="pc-builder.php">
<?php foreach (SLOTS as $slot => [$label, $icon, $required, $prompt]):
    $chosen = $build[$slot] ?? null;
?>
                <div class="slot<?= $chosen ? " filled" : "" ?>">
                    <span class="slot-icon"><i class="fa-solid <?= $icon ?>"></i></span>
                    <div class="slot-body">
                        <label for="slot-<?= $slot ?>"><?= $label ?><?= $required ? "" : " <small>optional</small>" ?></label>
                        <select id="slot-<?= $slot ?>" name="<?= $slot ?>">
                            <option value=""><?= $prompt ?></option>
<?php foreach ($parts[$slot] as $id => $part): ?>
                            <option value="<?= $id ?>"<?= $chosen && (int) $chosen['product_id'] === $id ? " selected" : "" ?>><?= h($part['name']) ?> - <?= money($part['price']) ?><?= h(partNote($slot, $part, $cpu, $board)) ?></option>
<?php endforeach; ?>
                        </select>
                    </div>
                    <span class="slot-thumb">
<?php if ($chosen): ?>
                        <a href="product.php?id=<?= (int) $chosen['product_id'] ?>" title="View <?= h($chosen['name']) ?>">
                            <?= $chosen['image_path'] ? '<img src="' . h($chosen['image_path']) . '" alt="">' : '<i class="fa-solid ' . $icon . '"></i>' ?>
                        </a>
<?php endif; ?>
                    </span>
                </div>
<?php endforeach; ?>
                <div class="builder-actions">
                    <button type="submit" class="pd-primary-btn"><i class="fa-solid fa-list-check"></i> Check compatibility</button>
                    <a href="pc-builder.php" class="builder-reset">Start over</a>
                </div>
            </form>

            <aside class="builder-summary" aria-label="Build summary">
                <p class="build-status <?= $status[0] ?>"><i class="fa-solid <?= $status[1] ?>"></i> <?= h($status[2]) ?></p>

<?php if ($checks): ?>
                <ul class="build-checks">
<?php foreach ($checks as [$level, $message]): ?>
                    <li class="<?= $level ?>"><i class="fa-solid <?= ["ok" => "fa-check", "warning" => "fa-triangle-exclamation", "error" => "fa-xmark"][$level] ?>"></i> <?= h($message) ?></li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
<?php if ($build && $missing): ?>
                <p class="build-missing"><strong>Still needed:</strong> <?= h(implode(", ", $missing)) ?></p>
<?php endif; ?>

                <dl class="build-numbers">
                    <dt>Estimated power</dt>
                    <dd><?= $build ? "{$estimatedWatts}W" : "–" ?><?= $build && !$psu ? " <small>(get a {$recommendedWatts}W+ supply)</small>" : "" ?></dd>
                    <dt>Parts</dt>
                    <dd><?= count($build) ?> of <?= count(SLOTS) ?></dd>
<?php if ($saving > 0): ?>
                    <dt>Deals included</dt>
                    <dd class="deal-saving"><i class="fa-solid fa-tag"></i> You save <?= money($saving) ?></dd>
<?php endif; ?>
                    <dt class="build-total">Total</dt>
                    <dd class="build-total"><?= money($total) ?></dd>
                </dl>

<?php if ($build && !$errors): ?>
                <form method="post" action="PHP/shopping_cart/add_build.php">
<?php foreach ($build as $part): ?>
                    <input type="hidden" name="ids[]" value="<?= (int) $part['product_id'] ?>">
<?php endforeach; ?>
                    <button type="submit" class="add-build-btn"><i class="fa-solid fa-cart-plus"></i> Add build to cart</button>
                </form>
                <p class="build-share"><i class="fa-solid fa-link"></i> <a href="<?= h($shareUrl) ?>">Link to this build</a> (copy it to save or share)</p>
<?php elseif ($errors): ?>
                <p class="build-blocked">Fix the problems above to add this build to your cart.</p>
<?php endif; ?>
            </aside>
        </div>
    </main>
    <footer>
        <div class="footer-contact">
            <div class="footer-contact-item">
                <i class="fa-solid fa-location-dot"></i>
                <div>
                    <h4>Visit Us</h4>
                    <p>50 Nanyang Ave, Singapore</p>
                </div>
            </div>
            <div class="footer-contact-item">
                <i class="fa-solid fa-phone"></i>
                <div>
                    <h4>Call Us</h4>
                    <p>+65 6123 4567</p>
                </div>
            </div>
            <div class="footer-contact-item">
                <i class="fa-regular fa-clock"></i>
                <div>
                    <h4>Working Hours</h4>
                    <p>Mon - Sat: 10:00 AM - 7:00 PM</p>
                </div>
            </div>
            <div class="footer-contact-item">
                <i class="fa-regular fa-envelope"></i>
                <div>
                    <h4>Email Us</h4>
                    <p>support@quantumgear.com</p>
                </div>
            </div>
        </div>
        <div class="footer-top">
            <div class="footer-col">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="shop.html">Shop</a></li>
                    <li><a href="pc-builder.php">PC Builder</a></li>
                    <li><a href="hotdeals.php">Hot Deals</a></li>
                    <li><a href="contact.html">Contact Us</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>Categories</h3>
                <ul>
                    <li><a href="shop.html?category=Components%20%26%20Storage">Components &amp; Storage</a></li>
                    <li><a href="shop.html?category=Computer%20Systems">Computer Systems</a></li>
                    <li><a href="shop.html?category=Computer%20Peripherals">Computer Peripherals</a></li>
                    <li><a href="shop.html?category=Gaming%20%26%20VR">Gaming &amp; VR</a></li>
                    <li><a href="shop.html?category=Networking">Networking</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 Quantum Gear. All rights reserved.</p>
            <ul class="footer-legal">
                <li><a href="contact.html#terms">Terms &amp; Conditions</a></li>
                <li><a href="contact.html#privacy">Privacy Policy</a></li>
            </ul>
        </div>
    </footer>
</body>
</html>