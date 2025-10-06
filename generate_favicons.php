<?php
/**
 * Favicon Generator Script
 * This script will create different sizes of favicon files from your existing favicon.png
 * 
 * Requirements: GD extension must be enabled in PHP
 * Usage: Run this script from your browser or command line
 */

// Check if GD extension is available
if (!extension_loaded('gd')) {
    die('GD extension is required to run this script. Please enable it in your PHP configuration.');
}

// Source favicon file
$sourceFile = 'favicon.png';

// Check if source file exists
if (!file_exists($sourceFile)) {
    die("Source file '$sourceFile' not found. Please make sure favicon.png exists in the project root.");
}

// Load the source image
$sourceImage = imagecreatefrompng($sourceFile);
if (!$sourceImage) {
    die("Could not load source image '$sourceFile'. Please check if it's a valid PNG file.");
}

// Get source dimensions
$sourceWidth = imagesx($sourceImage);
$sourceHeight = imagesy($sourceImage);

echo "<h2>Favicon Generator</h2>";
echo "<p>Source image: {$sourceWidth}x{$sourceHeight} pixels</p>";

// Define the sizes we need
$sizes = [
    'favicon-16x16.png' => 16,
    'favicon-32x32.png' => 32,
    'apple-touch-icon.png' => 180
];

$successCount = 0;
$totalCount = count($sizes);

foreach ($sizes as $filename => $size) {
    echo "<p>Creating $filename ({$size}x{$size})... ";
    
    // Create new image with the target size
    $newImage = imagecreatetruecolor($size, $size);
    
    // Enable alpha blending
    imagealphablending($newImage, false);
    imagesavealpha($newImage, true);
    
    // Fill with transparent background
    $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
    imagefill($newImage, 0, 0, $transparent);
    
    // Resize the image
    if (imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $size, $size, $sourceWidth, $sourceHeight)) {
        // Save the image
        if (imagepng($newImage, $filename)) {
            echo "✓ Success</p>";
            $successCount++;
        } else {
            echo "✗ Failed to save</p>";
        }
    } else {
        echo "✗ Failed to resize</p>";
    }
    
    // Clean up
    imagedestroy($newImage);
}

// Create favicon.ico (this is more complex, so we'll create a simple version)
echo "<p>Creating favicon.ico... ";
$icoImage = imagecreatetruecolor(32, 32);
imagealphablending($icoImage, false);
imagesavealpha($icoImage, true);
$transparent = imagecolorallocatealpha($icoImage, 0, 0, 0, 127);
imagefill($icoImage, 0, 0, $transparent);

if (imagecopyresampled($icoImage, $sourceImage, 0, 0, 0, 0, 32, 32, $sourceWidth, $sourceHeight)) {
    // For simplicity, we'll save as PNG and rename
    if (imagepng($icoImage, 'favicon_temp.png')) {
        // Note: For a true .ico file, you'd need additional processing
        // For now, we'll just copy the 32x32 PNG as favicon.ico
        if (copy('favicon_temp.png', 'favicon.ico')) {
            unlink('favicon_temp.png');
            echo "✓ Success (Note: This is a PNG file with .ico extension)</p>";
            $successCount++;
        } else {
            echo "✗ Failed to create .ico file</p>";
        }
    } else {
        echo "✗ Failed to create temporary file</p>";
    }
} else {
    echo "✗ Failed to resize for .ico</p>";
}

imagedestroy($icoImage);

// Clean up
imagedestroy($sourceImage);

echo "<h3>Summary</h3>";
echo "<p>Successfully created $successCount out of " . ($totalCount + 1) . " favicon files.</p>";

if ($successCount == ($totalCount + 1)) {
    echo "<p style='color: green;'><strong>✓ All favicon files created successfully!</strong></p>";
    echo "<p>Your favicon files are now ready:</p>";
    echo "<ul>";
    echo "<li>favicon.ico (32x32)</li>";
    echo "<li>favicon-16x16.png</li>";
    echo "<li>favicon-32x32.png</li>";
    echo "<li>apple-touch-icon.png (180x180)</li>";
    echo "</ul>";
    echo "<p><strong>Note:</strong> You can now delete this generate_favicons.php file as it's no longer needed.</p>";
} else {
    echo "<p style='color: red;'><strong>⚠ Some files failed to create. Please check the error messages above.</strong></p>";
}

echo "<hr>";
echo "<p><em>Generated on: " . date('Y-m-d H:i:s') . "</em></p>";
?>
