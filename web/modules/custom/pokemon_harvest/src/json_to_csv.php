<?php

/**
 * Converts a JSON file to a CSV file.
 * * @param string $filePath The path to the source JSON file.
 */
function convertJsonFileToCsv($filePath) {
    // 1. Check if the file exists
    if (!file_exists($filePath)) {
        die("Error: File '$filePath' not found.\n");
    }

    // 2. Read and decode JSON
    $jsonContent = file_get_contents($filePath);
    $data = json_decode($jsonContent, true);

    if (empty($data) || !is_array($data)) {
        die("Error: Invalid or empty JSON data in '$filePath'.\n");
    }

    // 3. Generate the dynamic filename: pokemon_YYYY-MM-DD.csv
    $outputFileName = 'pokemon_' . date('Y-m-d') . '.csv';

    // 4. Open file for writing
    $fp = fopen($outputFileName, 'w');

    // 5. Extract headers from the first object keys
    $firstRecord = reset($data);
    $headers = array_keys($firstRecord);
    fputcsv($fp, $headers);

    // 6. Write data rows
    foreach ($data as $row) {
        fputcsv($fp, $row);
    }

    fclose($fp);
    echo "Success: '$filePath' converted to '$outputFileName'.\n";
}

// --- Logic to handle the Command Line Argument ---

// $argv[1] is the first argument passed after the filename
if (isset($argv[1])) {
    convertJsonFileToCsv($argv[1]);
} else {
    echo "Usage: php json_to_csv.php <filename.json>\n";
}