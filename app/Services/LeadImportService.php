<?php

namespace App\Services;

use App\Imports\LeadImport;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use League\Csv\Writer;
use Illuminate\Http\UploadedFile;

class LeadImportService
{
    public function import($file, $campaignId)
    {
        try {
            $import = new LeadImport($campaignId);
            
            if ($file instanceof UploadedFile) {
                Excel::import($import, $file);
            } else {
                // If it's a string path, use the path directly
                Excel::import($import, Storage::path($file));
            }

            $failedRows = $import->getFailedRows();
            $successCount = $import->getSuccessCount();
            
            if ($failedRows->isEmpty()) {
                return [
                    'success' => true,
                    'message' => $successCount . ' leads imported successfully.',
                    'error_report_path' => null,
                    'imported_count' => $successCount
                ];
            }

            $errorReportPath = $this->generateErrorReport($failedRows);

            return [
                'success' => $successCount > 0,
                'message' => ($successCount > 0 ? $successCount . ' leads imported successfully. ' : '') . 
                             count($failedRows) . ' leads failed to import.',
                'error_report_path' => $errorReportPath,
                'imported_count' => $successCount,
                'failed_count' => count($failedRows)
            ];
        } catch (\Exception $e) {
            \Log::error('Import exception: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }

    protected function generateErrorReport($failedRows)
    {
        if ($failedRows->isEmpty()) {
            return null;
        }
        
        $csv = Writer::createFromString('');
        
        // Get the first failed row to determine structure
        $firstRow = $failedRows->first();
        
        // Add headers - use the data keys as headers
        $headers = array_merge(
            array_keys($firstRow['data']),
            ['Validation Errors']
        );
        $csv->insertOne($headers);

        // Add rows
        foreach ($failedRows as $failedRow) {
            $row = array_merge(
                array_values($failedRow['data']), 
                [implode(', ', $failedRow['errors'])]
            );
            $csv->insertOne($row);
        }

        $filename = 'lead-import-errors-' . now()->format('Y-m-d-H-i-s') . '.csv';
        Storage::put('public/error-reports/' . $filename, $csv->getContent());

        return $filename;
    }
} 