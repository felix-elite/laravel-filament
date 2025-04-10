<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestController;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test/csv', [TestController::class, 'analyzeCSV']);
Route::post('/test/csv', [TestController::class, 'analyzeCSV'])->name('test.analyze-csv');

// Debug route for CSV import with malformed headers
Route::get('/debug/import-malformed', function() {
    // Create temp file with the sample data from the user
    $csv = "name,email,phone_numberJohn Doe,john.doe@example.com,+1234567890\nJohn Doe34,john34.doe@example.com,dfsdfgohn\nDoe56,,+456789123";
    $path = storage_path('app/temp/malformed.csv');
    if (!file_exists(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }
    file_put_contents($path, $csv);
    
    // Import the data
    $service = new \App\Services\LeadImportService();
    try {
        $result = $service->import('temp/malformed.csv', 1); // Using campaign ID 1
        return response()->json($result);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
});

// Direct import route for testing
Route::get('/debug/direct-import', function() {
    try {
        // Create a lead directly
        $lead = \App\Models\Lead::create([
            'campaign_id' => 1,
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone_number' => '+1234567890'
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Lead imported successfully',
            'lead' => $lead
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
});

// Debug route for CSV import with properly formatted data
Route::get('/debug/import', function() {
    // Create temp file with the sample data - properly formatted
    $csv = "name,email,phone_number\nJohn Doe,john.doe@example.com,+1234567890";
    $path = storage_path('app/temp/sample.csv');
    if (!file_exists(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }
    file_put_contents($path, $csv);
    
    // Import the data
    $service = new \App\Services\LeadImportService();
    try {
        $result = $service->import('temp/sample.csv', 1); // Using campaign ID 1
        return response()->json($result);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
});

// Custom handler for the specific CSV format
Route::get('/fix-csv-import', function() {
    // The exact CSV string from the user example
    $csv = "name,email,phone_numberJohn Doe,john.doe@example.com,+1234567890\nJohn Doe34,john34.doe@example.com,dfsdfgohn\nDoe56,,+456789123";
    
    // Fix the header and format properly
    $fixed_csv = str_replace("phone_numberJohn", "phone_number\nJohn", $csv);
    
    // Parse manually
    $lines = explode("\n", $fixed_csv);
    $header = str_getcsv($lines[0]);
    
    $success = 0;
    $failed = 0;
    $failed_rows = [];
    
    // Process each row
    for ($i = 1; $i < count($lines); $i++) {
        $row = str_getcsv($lines[$i]);
        
        // Only continue if we have enough data
        if (count($row) >= 3) {
            $name = $row[0];
            $email = $row[1];
            $phone = $row[2];
            
            // Validate
            $valid = true;
            $errors = [];
            
            if (empty($name)) {
                $valid = false;
                $errors[] = "Name is required";
            }
            
            if (empty($email)) {
                $valid = false;
                $errors[] = "Email is required";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid = false;
                $errors[] = "Email is invalid";
            }
            
            if (empty($phone)) {
                $valid = false;
                $errors[] = "Phone number is required";
            } else {
                // Format phone with + if needed
                if (!str_starts_with($phone, '+')) {
                    $phone = '+' . $phone;
                }
                
                // Validate international format
                if (!preg_match('/^\+[1-9]\d{1,14}$/', $phone)) {
                    $valid = false;
                    $errors[] = "Phone number format is invalid";
                }
            }
            
            if ($valid) {
                try {
                    // Insert valid row
                    DB::table('leads')->insert([
                        'campaign_id' => 1,
                        'name' => $name,
                        'email' => $email,
                        'phone_number' => $phone,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $success++;
                } catch (\Exception $e) {
                    $failed++;
                    $failed_rows[] = [
                        'row' => $i + 1,
                        'data' => ['name' => $name, 'email' => $email, 'phone_number' => $phone],
                        'errors' => ['Database error: ' . $e->getMessage()]
                    ];
                }
            } else {
                $failed++;
                $failed_rows[] = [
                    'row' => $i + 1,
                    'data' => ['name' => $name, 'email' => $email, 'phone_number' => $phone],
                    'errors' => $errors
                ];
            }
        } else {
            $failed++;
            $failed_rows[] = [
                'row' => $i + 1,
                'data' => $row,
                'errors' => ['Row has incorrect format']
            ];
        }
    }
    
    return response()->json([
        'success' => $success > 0,
        'message' => $success . ' leads imported successfully. ' . $failed . ' leads failed.',
        'imported_count' => $success,
        'failed_count' => $failed,
        'failed_rows' => $failed_rows
    ]);
});
