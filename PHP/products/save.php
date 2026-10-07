<?php
// Adds a new product, or updates one when product_id is sent.
// Expects POST (multipart form) fields from the admin product form:
//   product_id (blank = new), name, brand, category_id, price, old_price,
//   stock, description, is_active (checkbox), image (optional file)
// Replies with JSON: {"ok": true, "id": <product_id>} or {"error": "..."}

require_once __DIR__ . "/common.php";
requirePost();

$productId = (int) ($_POST['product_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$brand = trim($_POST['brand'] ?? '');
$categoryId = (int) ($_POST['category_id'] ?? 0);
$price = $_POST['price'] ?? '';
$oldPrice = trim($_POST['old_price'] ?? '');
$stock = $_POST['stock'] ?? '';
$description = trim($_POST['description'] ?? '');
$isActive = isset($_POST['is_active']) ? 1 : 0;

// Check again on the server: browser validation can be bypassed
if ($name === '' || strlen($name) > 150) {
    respond(400, ["error" => "Product name is required (max 150 characters)."]);
}
if ($brand === '' || strlen($brand) > 50) {
    respond(400, ["error" => "Brand is required (max 50 characters)."]);
}
if (!is_numeric($price) || $price < 0) {
    respond(400, ["error" => "Price must be 0 or more."]);
}
if ($oldPrice !== '' && (!is_numeric($oldPrice) || $oldPrice < 0)) {
    respond(400, ["error" => "Original price must be 0 or more, or left blank."]);
}
if (!preg_match('/^\d+$/', (string) $stock)) {
    respond(400, ["error" => "Stock must be a whole number, 0 or more."]);
}

$stmt = mysqli_prepare($conn, "SELECT category_id FROM categories WHERE category_id = ?");
mysqli_stmt_bind_param($stmt, "i", $categoryId);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
$categoryExists = mysqli_stmt_num_rows($stmt) > 0;
mysqli_stmt_close($stmt);
if (!$categoryExists) {
    respond(400, ["error" => "Please choose a valid category."]);
}

$oldPrice = $oldPrice === '' ? null : $oldPrice;
$description = $description === '' ? null : $description;

// When editing, look up the current image so it can be kept or replaced
$currentImage = null;
if ($productId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT image_path FROM products WHERE product_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $productId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) {
        respond(404, ["error" => "That product no longer exists."]);
    }
    $currentImage = $row['image_path'];
}

// Optional image upload: only real images up to 2 MB, saved under a random name
$imagePath = $currentImage;
$upload = $_FILES['image'] ?? null;
if ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 2 * 1024 * 1024) {
        respond(400, ["error" => "Image upload failed. Images must be 2 MB or smaller."]);
    }
    $allowed = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp", "image/gif" => "gif"];
    $mime = mime_content_type($upload['tmp_name']);
    if (!isset($allowed[$mime])) {
        respond(400, ["error" => "Image must be a JPG, PNG, WEBP or GIF file."]);
    }

    $folder = __DIR__ . "/../../" . UPLOAD_DIR;
    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }
    $fileName = bin2hex(random_bytes(8)) . "." . $allowed[$mime];
    if (!move_uploaded_file($upload['tmp_name'], $folder . $fileName)) {
        respond(500, ["error" => "Could not save the image."]);
    }
    $imagePath = UPLOAD_DIR . $fileName;
}

try {
    if ($productId > 0) {
        $stmt = mysqli_prepare($conn,
            "UPDATE products SET name = ?, brand = ?, category_id = ?, price = ?, old_price = ?,
                    stock = ?, description = ?, image_path = ?, is_active = ?
             WHERE product_id = ?");
        mysqli_stmt_bind_param($stmt, "ssiddissii",
            $name, $brand, $categoryId, $price, $oldPrice, $stock, $description, $imagePath, $isActive, $productId);
    } else {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO products (name, brand, category_id, price, old_price, stock, description, image_path, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssiddissi",
            $name, $brand, $categoryId, $price, $oldPrice, $stock, $description, $imagePath, $isActive);
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
        respond(409, ["error" => "A product with this name already exists."]);
    }
    respond(500, ["error" => "Could not save the product."]);
}

// The new image replaced an old uploaded one, so the old file is no longer needed
if ($imagePath !== $currentImage) {
    deleteUploadedImage($currentImage);
}

mysqli_close($conn);
respond(200, ["ok" => true, "id" => $savedId]);
?>
