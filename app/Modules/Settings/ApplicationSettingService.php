<?php

namespace App\Modules\Settings;

use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\Crypt;

class ApplicationSettingService
{
    public function get(string $key, mixed $default = null): mixed
    {
        $row = ApplicationSetting::where('key', $key)->first();

        return $row ? $row->getResolvedValue() : $default;
    }

    public function set(
        string $key,
        mixed $value,
        string $group = 'general',
        bool $encrypt = false,
        ?string $description = null,
    ): ApplicationSetting {
        $isArray = is_array($value);
        $valueText = null;
        $valueJson = null;

        if ($isArray && ! $encrypt) {
            $valueJson = $value;
        } elseif ($value !== null) {
            $raw = $isArray ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
            $valueText = $encrypt ? Crypt::encryptString($raw) : $raw;
        }

        return ApplicationSetting::updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'value_text' => $valueText,
                'value_json' => $valueJson,
                'is_encrypted' => $encrypt,
                'description' => $description,
            ]
        );
    }
}
