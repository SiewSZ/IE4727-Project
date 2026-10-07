<?php
// Lists the images in Images/carousel/ so the home page carousel can build its slides.
// To change the carousel, just add, replace or delete image files in that folder.
// Slides are shown in filename order (e.g. prefix files with 1_, 2_, 3_ to control the order).
// Replies with JSON: {"images": [{"src": "...", "alt": "..."}, ...]}

$folder = __DIR__ . "/../Images/carousel/";
$allowed = ["jpg", "jpeg", "png", "webp", "gif", "svg", "avif"];

$files = [];
foreach (scandir($folder) ?: [] as $file) {
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (is_file($folder . $file) && in_array($extension, $allowed)) {
        $files[] = $file;
    }
}
// "Natural" order, so 2_ comes before 10_
natcasesort($files);

$images = [];
foreach ($files as $file) {
    // Alt text from the filename: "1_RTX5090-Launch.svg" -> "RTX5090 Launch promotion"
    $label = pathinfo($file, PATHINFO_FILENAME);
    $label = preg_replace('/^\d+[_\-\s]*/', '', $label);
    $label = trim(str_replace(["_", "-"], " ", $label));
    $images[] = [
        "src" => "Images/carousel/" . rawurlencode($file),
        "alt" => ($label === "" ? "Store" : $label) . " promotion"
    ];
}

header("Content-Type: application/json");
echo json_encode(["images" => $images]);
?>
