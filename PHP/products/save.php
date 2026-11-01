<?php
// Adds a new product, or updates one when product_id is sent.
// Expects POST (multipart form) fields from the admin product form:
//   product_id (blank = new), name, brand, category_id, regular_price, sale_price (blank = not on sale),
//   stock, description, features (one per line), part_type, socket, memory_type, wattage (PC Builder),
//   is_active (checkbox), image (optional file)
// Success: back to admin.php (Inventory tab) with a message
// Failure: back to the product form with the reason, the typed values filled in again (fail() below)

require_once __DIR__ . "/common.php";
require_once __DIR__ . "/../orders/mailer.php";   // notifyBackInStock()
requirePost("admin.php?tab=inventory");
requireAdmin();

$productId = (int) ($_POST['product_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$brand = trim($_POST['brand'] ?? '');
$categoryId = (int) ($_POST['category_id'] ?? 0);
$regularPrice = trim($_POST['regular_price'] ?? '');
$salePrice = trim($_POST['sale_price'] ?? '');
$stock = $_POST['stock'] ?? '';
$description = trim($_POST['description'] ?? '');
// One key feature per line: drop blank lines and stray spaces so product.php gets a clean list
$features = implode("\n", array_filter(array_map('trim', preg_split('/\R/', $_POST['features'] ?? ''))));
$isActive = isset($_POST['is_active']) ? 1 : 0;
// PC Builder specs: all optional, blank means "doesn't apply"
$partType = $_POST['part_type'] ?? '';
$socket = $_POST['socket'] ?? '';
$memoryType = $_POST['memory_type'] ?? '';
$wattage = trim($_POST['wattage'] ?? '');

// Back to the form with the reason. The typed values are kept in the session, so admin.php can
// fill them in again instead of the admin having to start over.
function fail($message)
{
    global $productId;
    $_SESSION['product_form'] = array_diff_key($_POST, ['csrf_token' => 0]);
    goBack("admin.php?tab=inventory&" . ($productId > 0 ? "edit=$productId" : "add=1") . "#productForm", $message);
}

// Check again on the server: browser validation can be bypassed
if ($name === '' || strlen($name) > 150) {
    fail("Product name is required (max 150 characters).");
}
if ($brand === '' || strlen($brand) > 50) {
    fail("Brand is required (max 50 characters).");
}
if (!is_numeric($regularPrice) || $regularPrice < 0) {
    fail("Regular price must be 0 or more.");
}
if ($salePrice !== '' && (!is_numeric($salePrice) || $salePrice < 0 || $salePrice >= $regularPrice)) {
    fail("Sale price must be lower than the regular price, or left blank.");
}
if (!in_array($partType, ['', 'cpu', 'motherboard', 'memory', 'gpu', 'psu', 'cooler', 'case', 'storage'], true)
    || !in_array($socket, ['', 'AM4', 'AM5', 'LGA1700', 'LGA1851'], true)
    || !in_array($memoryType, ['', 'DDR4', 'DDR5'], true)) {
    fail("Please choose the PC Builder options from the lists.");
}
if ($wattage !== '' && (!preg_match('/^\d+$/', $wattage) || $wattage < 1 || $wattage > 3000)) {
    fail("Watts must be a whole number from 1 to 3000, or left blank.");
}
if (!preg_match('/^\d+$/', (string) $stock)) {
    fail("Stock must be a whole number, 0 or more.");
}

$stmt = mysqli_prepare($conn, "SELECT category_id FROM categories WHERE category_id = ?");
mysqli_stmt_bind_param($stmt, "i", $categoryId);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
$categoryExists = mysqli_stmt_num_rows($stmt) > 0;
mysqli_stmt_close($stmt);
if (!$categoryExists) {
    fail("Please choose a valid category.");
}

// The table stores what customers pay (price) and, during a sale, the regular price (old_price)
$price = $salePrice === '' ? $regularPrice : $salePrice;
$oldPrice = $salePrice === '' ? null : $regularPrice;
$description = $description === '' ? null : $description;
$features = $features === '' ? null : $features;
$partType = $partType === '' ? null : $partType;
$socket = $socket === '' ? null : $socket;
$memoryType = $memoryType === '' ? null : $memoryType;
$wattage = $wattage === '' ? null : (int) $wattage;

// When editing, look up the current image so it can be kept or replaced
$currentImage = null;
if ($productId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT image_path, stock FROM products WHERE product_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $productId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) {
        fail("That product no longer exists.");
    }
    $currentImage = $row['image_path'];
    $oldStock = (int) $row['stock'];
}

// Optional image upload: only real images up to 2 MB, saved under a random name
$imagePath = $currentImage;
$upload = $_FILES['image'] ?? null;
if ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 2 * 1024 * 1024) {
        fail("Image upload failed. Images must be 2 MB or smaller.");
    }
    $allowed = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp", "image/gif" => "gif"];
    $mime = mime_content_type($upload['tmp_name']);
    if (!isset($allowed[$mime])) {
        fail("Image must be a JPG, PNG, WEBP or GIF file.");
    }

    $folder = __DIR__ . "/../../" . UPLOAD_DIR;
    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }
    $fileName = bin2hex(random_bytes(8)) . "." . $allowed[$mime];
    if (!move_uploaded_file($upload['tmp_name'], $folder . $fileName)) {
        fail("Could not save the image.");
    }
    $imagePath = UPLOAD_DIR . $fileName;
}

try {
    if ($productId > 0) {
        $stmt = mysqli_prepare($conn,
            "UPDATE products SET name = ?, brand = ?, category_id = ?, price = ?, old_price = ?,
                    stock = ?, description = ?, features = ?, part_type = ?, socket = ?, memory_type = ?, wattage = ?,
                    image_path = ?, is_active = ?
             WHERE product_id = ?");
        mysqli_stmt_bind_param($stmt, "ssiddisssssisii",
            $name, $brand, $categoryId, $price, $oldPrice, $stock, $description, $features,
            $partType, $socket, $memoryType, $wattage, $imagePath, $isActive, $productId);
    } else {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO products (name, brand, category_id, price, old_price, stock, description, features,
                                   part_type, socket, memory_type, wattage, image_path, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssiddisssssisi",
            $name, $brand, $categoryId, $price, $oldPrice, $stock, $description, $features,
            $partType, $socket, $memoryType, $wattage, $imagePath, $isActive);
    }
    mysqli_stmt_execute($stmt);
    $savedId = $productId > 0 ? $productId : mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
} catch (mysqli_sql_exception $e) {
    // Don't leave a new image behind if the row wasn't saved
    if ($imagePath !== $currentImage) {
        deleteUploadedImage($imagePath);
    }
    if ($e->getCode() === 1062) {
        fail("A product with this name already exists.");
    }
    fail("Could not save the product.");
}

// The new image replaced an old uploaded one, so the old file is no longer needed
if ($imagePath !== $currentImage) {
    deleteUploadedImage($currentImage);
}

// Restocked from 0: email the customers who asked to be told
if ($productId > 0 && $oldStock === 0 && (int) $stock > 0) {
    notifyBackInStock($conn, $productId);
}

goBack("admin.php?tab=inventory#product-$savedId", "Saved \"$name\".", "success");
?>
