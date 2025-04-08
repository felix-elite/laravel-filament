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
            
            if ($failedRows->isEmpty()) {
                return [
                    'success' => true,
                    'message' => 'All leads imported successfully.',
                    'error_report_path' => null
                ];
            }

            $errorReportPath = $this->generateErrorReport($failedRows);

            return [
                'success' => false,
                'message' => count($failedRows) . ' leads failed to import.',
                'error_report_path' => $errorReportPath
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    protected function generateErrorReport($failedRows)
    {
        $csv = Writer::createFromString('');
        
        // Add headers
        $headers = array_merge(
            array_keys($failedRows->first()['row']),
            ['Validation Errors']
        );
        $csv->insertOne($headers);

        // Add rows
        foreach ($failedRows as $failedRow) {
            $row = array_merge(
                array_values($failedRow['row']),
                [implode(', ', $failedRow['errors'])]
            );
            $csv->insertOne($row);
        }

        $filename = 'lead-import-errors-' . now()->format('Y-m-d-H-i-s') . '.csv';
        Storage::put('public/error-reports/' . $filename, $csv->getContent());

        return $filename;
    }
} 