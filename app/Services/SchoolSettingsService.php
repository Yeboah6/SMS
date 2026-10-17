<?php

namespace App\Services;

use App\Models\SchoolSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SchoolSettingsService
{
    /**
     * @return array{
     *     grade_settings: array{bands: list<array{label: string, minimum_percentage: int}>},
     *     fee_settings: array{currency_code: string, default_due_days: int},
     *     notification_settings: array{database_notices: bool, email_notices: bool}
     * }
     */
    public function defaults(): array
    {
        return [
            'grade_settings' => [
                'bands' => [
                    ['label' => 'A', 'minimum_percentage' => 80],
                    ['label' => 'B', 'minimum_percentage' => 70],
                    ['label' => 'C', 'minimum_percentage' => 60],
                    ['label' => 'D', 'minimum_percentage' => 50],
                    ['label' => 'F', 'minimum_percentage' => 0],
                ],
            ],
            'fee_settings' => [
                'currency_code' => 'USD',
                'default_due_days' => 30,
            ],
            'notification_settings' => [
                'database_notices' => true,
                'email_notices' => true,
            ],
        ];
    }

    /**
     * @return list<array{label: string, minimum_percentage: int|float}>
     */
    public function gradeBands(): array
    {
        $settings = SchoolSetting::query()
            ->where('singleton_key', 'primary')
            ->first()?->grade_settings ?? [];

        return array_replace(
            $this->defaults()['grade_settings'],
            $settings,
        )['bands'];
    }

    /**
     * @param  list<array{label: string, minimum_percentage: int|float}>  $bands
     */
    public function gradeForPercentage(?float $percentage, array $bands): ?string
    {
        if ($percentage === null) {
            return null;
        }

        foreach ($bands as $band) {
            if ($percentage >= $band['minimum_percentage']) {
                return $band['label'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): SchoolSetting
    {
        $logo = $data['logo'] ?? null;
        $removeLogo = (bool) ($data['remove_logo'] ?? false);
        unset($data['logo'], $data['remove_logo']);

        if ($logo !== null && ! $logo instanceof UploadedFile) {
            throw new RuntimeException('The school logo upload is invalid.');
        }

        $settings = SchoolSetting::query()->where('singleton_key', 'primary')->first();
        $previousLogoPath = $settings?->logo_path;
        $newLogoPath = null;
        $defaults = $this->defaults();

        foreach (['grade_settings', 'fee_settings', 'notification_settings'] as $settingsKey) {
            if (! array_key_exists($settingsKey, $data)) {
                $data[$settingsKey] = $settings?->{$settingsKey} ?? $defaults[$settingsKey];
            }
        }

        if ($logo instanceof UploadedFile) {
            $storedPath = $logo->store('school/logo', 'public');

            if (! is_string($storedPath)) {
                throw new RuntimeException('The school logo could not be stored.');
            }

            $newLogoPath = $storedPath;
            $data['logo_path'] = $storedPath;
        } elseif ($removeLogo) {
            $data['logo_path'] = null;
        }

        try {
            $settings = DB::transaction(fn (): SchoolSetting => SchoolSetting::query()->updateOrCreate(
                ['singleton_key' => 'primary'],
                $data,
            ));
        } catch (Throwable $exception) {
            if ($newLogoPath !== null) {
                $this->deleteLogo(
                    $newLogoPath,
                    'Unable to remove the uploaded school logo after settings could not be saved.',
                );
            }

            throw $exception;
        }

        if ($previousLogoPath !== null && ($newLogoPath !== null || $removeLogo)) {
            $this->deleteLogo($previousLogoPath, 'Unable to remove the previous school logo.');
        }

        return $settings;
    }

    private function deleteLogo(string $path, string $message): void
    {
        try {
            if (Storage::disk('public')->exists($path) && ! Storage::disk('public')->delete($path)) {
                Log::warning($message, ['path' => $path]);
            }
        } catch (Throwable $exception) {
            Log::warning($message, [
                'path' => $path,
                'exception' => $exception,
            ]);
        }
    }
}
