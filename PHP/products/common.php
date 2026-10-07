<?php
// Shared helpers for the product endpoints.
// Every endpoint answers with JSON so shop.js and admin.js can read the result.

require_once __DIR__ . "/../db_connect.php";

// Send a JSON reply and stop
function respond($status, $data)
{
    http_response_code($status);
    header("Content-Type: application/json");
    echo json_encode($data);
    exit;
}

// Only accept POST for anything that changes the database
function requirePost()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(405, ["error" => "Use POST for this request."]);
    }
}

// Images uploaded from the admin page are stored here (relative to the project root).
// Kept separate from Images/products/ so deleting a product never removes the bundled images.
const UPLOAD_DIR = "Images/uploads/";

// Remove an uploaded product image, but never touch files outside Images/uploads/
function deleteUploadedImage($imagePath)
{
    if ($imagePath && str_starts_with($imagePath, UPLOAD_DIR)) {
        $file = __DIR__ . "/../../" . $imagePath;
        if (is_file($file)) {
            unlink($file);
        }
    }
}
?>
