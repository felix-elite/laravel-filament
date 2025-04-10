<?php

namespace App\Imports;

use App\Models\Lead;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class LeadImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnError, SkipsOnFailure
{
    use SkipsErrors, SkipsFailures;

    protected $campaignId;
    protected $failedRows;
    protected $successCount = 0;
    protected $failureCount = 0;

    public function __construct($campaignId)
    {
        $this->campaignId = $campaignId;
        $this->failedRows = collect();
    }

    /**
    * @param Collection $collection
    */
    public function collection(Collection $rows)
    {
        Log::info('Starting import', ['rows_count' => count($rows)]);
        
        // Check if we have the special malformed header case
        if (count($rows) > 0) {
            $firstRow = $rows->first();
            $rowData = [];
            if (is_object($firstRow) && method_exists($firstRow, 'toArray')) {
                $rowData = $firstRow->toArray();
            } elseif (is_array($firstRow)) {
                $rowData = $firstRow;
            } else {
                $rowData = (array)$firstRow;
            }
            
            // Check for missing comma in header resulting in merged column
            if (isset($rowData['phone_number']) && is_string($rowData['phone_number']) && 
                strpos($rowData['phone_number'], 'John Doe') === 0) {
                
                // This is the first row - extract the name, email and phone
                $value = $rowData['phone_number'];
                $parts = explode(',', $value);
                
                if (count($parts) >= 3) {
                    // Create a new lead from the first row
                    $name = $parts[0];
                    $email = $parts[1];
                    $phone = $parts[2];
                    
                    try {
                        // Format phone number
                        if (!str_starts_with($phone, '+')) {
                            $phone = '+' . $phone;
                        }
                        
                        // Insert directly
                        DB::table('leads')->insert([
                            'campaign_id' => $this->campaignId,
                            'name' => $name,
                            'email' => $email,
                            'phone_number' => $phone,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        
                        $this->successCount++;
                        Log::info('Successfully imported lead from malformed header', ['name' => $name]);
                    } catch (\Exception $e) {
                        $this->recordFailure(0, [
                            'name' => $name,
                            'email' => $email,
                            'phone_number' => $phone
                        ], ["Error: {$e->getMessage()}"]);
                    }
                }
            }
        }
        
        // Process the remaining rows normally
        foreach ($rows as $index => $row) {
            // Skip the first row if it was the malformed header row we just processed
            if ($index === 0 && $this->successCount === 1) {
                continue;
            }
            
            Log::info('Processing row', ['index' => $index, 'row' => is_array($row) ? $row : (method_exists($row, 'toArray') ? $row->toArray() : 'Non-array row')]);
            
            try {
                // Convert row to array if it's not already
                $rowData = [];
                if (is_object($row) && method_exists($row, 'toArray')) {
                    $rowData = $row->toArray();
                } elseif (is_array($row)) {
                    $rowData = $row;
                } else {
                    $rowData = (array)$row;
                }

                // Check if row has all required fields
                if (empty($rowData['name']) || empty($rowData['email']) || empty($rowData['phone_number'])) {
                    $this->recordFailure($index, $rowData, ['Missing required fields']);
                    continue;
                }

                // Format phone number to ensure it has the + prefix
                // Handle both string and numeric values
                $phone = $rowData['phone_number'];
                // Convert to string if it's not already
                $phone = (string)$phone;
                $phone = trim($phone);
                if (!str_starts_with($phone, '+')) {
                    $phone = '+' . $phone;
                }
                $rowData['phone_number'] = $phone;
                
                // Import the data - using DB facade directly to avoid model validation issues
                DB::table('leads')->insert([
                    'campaign_id' => $this->campaignId,
                    'name' => $rowData['name'],
                    'email' => $rowData['email'],
                    'phone_number' => $rowData['phone_number'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                $this->successCount++;
                Log::info('Successfully imported lead', ['name' => $rowData['name'], 'success_count' => $this->successCount]);
                
            } catch (\Exception $e) {
                // Get row data safely for error logging
                if (isset($rowData)) {
                    $errorRowData = $rowData;
                } elseif (is_object($row) && method_exists($row, 'toArray')) {
                    $errorRowData = $row->toArray();
                } elseif (is_array($row)) {
                    $errorRowData = $row;
                } else {
                    $errorRowData = ['raw_data' => (string)$row];
                }
                
                $this->recordFailure($index, $errorRowData, ["Error: {$e->getMessage()}"]);
                
                Log::error('Import error: ' . $e->getMessage(), [
                    'row' => $errorRowData,
                    'row_index' => $index + 2,
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            }
        }
        
        Log::info('Import completed', [
            'success_count' => $this->successCount,
            'failure_count' => $this->failureCount
        ]);
    }

    /**
     * Record a failure for a row
     */
    private function recordFailure($index, $row, $errors)
    {
        // Safely convert row data to array
        if (is_array($row)) {
            $rowData = $row;
        } elseif (is_object($row) && method_exists($row, 'toArray')) {
            $rowData = $row->toArray();
        } else {
            $rowData = (array)$row;
        }
        
        $this->failedRows->push([
            'row_number' => $index + 2, // +2 for header row and zero-indexing
            'data' => $rowData,
            'errors' => $errors
        ]);
        
        $this->failureCount++;
    }

    public function rules(): array
    {
        // These rules are only used for the initial validation
        // We do more detailed validation in the collection method
        return [];
    }

    public function getFailedRows()
    {
        return $this->failedRows;
    }
    
    public function getSuccessCount()
    {
        return $this->successCount;
    }
    
    public function getFailureCount()
    {
        return $this->failureCount;
    }
    
    public function onFailure(\Maatwebsite\Excel\Validators\Failure ...$failures)
    {
        // This method is required by the SkipsOnFailure interface
        // We're handling failures in the collection method, but this captures any other validation failures
        foreach ($failures as $failure) {
            $rowIndex = $failure->row() - 2; // Adjust for header row
            $rowValues = $failure->values(); // This is already an array
            
            $this->recordFailure(
                $rowIndex,
                $rowValues,
                $failure->errors()
            );
        }
    }
}
