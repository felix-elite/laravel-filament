<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TestController extends Controller
{
    public function analyzeCSV(Request $request)
    {
        if ($request->hasFile('csv')) {
            $file = $request->file('csv');
            $content = file_get_contents($file->path());
            
            // Output raw content
            echo "<h3>Raw Content:</h3>";
            echo "<pre>" . htmlspecialchars($content) . "</pre>";
            
            // Output array representation
            echo "<h3>Array Representation:</h3>";
            $lines = explode("\n", $content);
            echo "<pre>" . print_r($lines, true) . "</pre>";
            
            // Save for reference
            Storage::put('debug/sample-import.csv', $content);
            
            return "CSV analysis complete";
        }
        
        return view('test.csv-form', [
            'message' => 'Upload a CSV to analyze its format'
        ]);
    }
} 