<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'name',
        'email',
        'phone_number',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public static function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone_number' => 'required|max:20|regex:/^\+?[1-9]\d{1,14}$/',
        ];
    }
}
