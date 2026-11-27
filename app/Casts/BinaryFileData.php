<?php

namespace App\Casts;

use App\Services\DocumentPreviewService;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** Prevent PDO's text binding from truncating PostgreSQL bytea at a NUL byte. */
class BinaryFileData implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return DocumentPreviewService::normalizeBinary($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $bytes = is_resource($value) ? DocumentPreviewService::normalizeBinary($value) : $value;

        return DocumentPreviewService::databaseValue($bytes, $model->getConnection()->getDriverName());
    }
}
