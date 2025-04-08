<?php

namespace App\Imports;

use App\Models\Lead;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Illuminate\Support\Facades\Validator;

class LeadImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnError
{
    use SkipsErrors;

    protected $campaignId;
    protected $failedRows;

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
        foreach ($rows as $row) {
            $validator = Validator::make($row->toArray(), Lead::rules());

            if ($validator->fails()) {
                $this->failedRows->push([
                    'row' => $row->toArray(),
                    'errors' => $validator->errors()->all()
                ]);
                continue;
            }

            Lead::create([
                'campaign_id' => $this->campaignId,
                'name' => $row['name'],
                'email' => $row['email'],
                'phone_number' => $row['phone_number'],
            ]);
        }
    }

    public function rules(): array
    {
        return Lead::rules();
    }

    public function getFailedRows()
    {
        return $this->failedRows;
    }
}
