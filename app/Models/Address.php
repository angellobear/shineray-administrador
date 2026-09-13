<?php

namespace App\Models;

use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $addressable_type
 * @property int $addressable_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $phone
 * @property string $address_1
 * @property string|null $address_2
 * @property string $city
 * @property string $province
 * @property string $country_code
 * @property string $postal_code
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['first_name', 'last_name', 'phone', 'address_1', 'address_2', 'city', 'province', 'country_code', 'postal_code', 'metadata'])]
class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'country_code' => 'EC',
        'postal_code' => '000000',
    ];

    /** @return MorphTo<Model, $this> */
    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
