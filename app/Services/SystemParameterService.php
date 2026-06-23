<?php

namespace App\Services;

use App\Models\SystemParameter;
use App\Support\SystemParameterSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SystemParameterService
{
    public function groupedParameters(): array
    {
        $stored = SystemParameter::query()
            ->orderBy('key')
            ->get()
            ->keyBy('key');

        return $this->formatGrouped($stored);
    }

    public function parameterDetail(string $key): ?array
    {
        if (! SystemParameterSchema::has($key)) {
            return null;
        }

        $schema = SystemParameterSchema::definitions()[$key];
        $row    = SystemParameter::query()->where('key', $key)->first();

        return array_merge($schema, [
            'key'        => $key,
            'value'      => $row
                ? SystemParameterSchema::castValue($row->value, $schema['type'])
                : SystemParameterSchema::castValue(SystemParameterSchema::defaults()[$key] ?? '', $schema['type']),
            'updated_at' => $row?->updated_at,
            'updated_by' => $row?->updated_by,
        ]);
    }

    /**
     * @param  array<int, array{key: string, value: mixed}>  $parameters
     */
    public function updateParameters(array $parameters, int $userId): int
    {
        $updated = 0;

        DB::transaction(function () use ($parameters, $userId, &$updated) {
            foreach ($parameters as $param) {
                $key    = $param['key'];
                $schema = SystemParameterSchema::definitions()[$key];
                $value  = SystemParameterSchema::sanitizeValue($param['value'], $schema['type']);

                $oldValue = SystemParameter::query()->where('key', $key)->value('value');

                SystemParameter::query()->updateOrInsert(
                    ['key' => $key],
                    ['value' => $value, 'updated_by' => $userId, 'updated_at' => now()]
                );

                activity()->withProperties(['key' => $key, 'old_value' => $oldValue, 'new_value' => $value])
                    ->log("System parameter updated: {$key}");

                $updated++;
            }
        });

        $this->flushCache();

        return $updated;
    }

    public function debugLoggingStatus(): array
    {
        $row = SystemParameter::query()
            ->where('key', SystemParameterSchema::DEBUG_LOGGING_KEY)
            ->first();

        $enabled = $row
            ? (bool) (int) $row->value
            : false;

        return [
            'enabled'    => $enabled,
            'updated_at' => $row?->updated_at,
            'updated_by' => $row?->updated_by,
        ];
    }

    public function setDebugLogging(bool $enabled, int $userId): array
    {
        $key      = SystemParameterSchema::DEBUG_LOGGING_KEY;
        $oldValue = SystemParameter::query()->where('key', $key)->value('value');
        $value    = $enabled ? '1' : '0';

        DB::transaction(function () use ($key, $value, $userId, $oldValue, $enabled) {
            SystemParameter::query()->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_by' => $userId, 'updated_at' => now()]
            );

            activity()->withProperties([
                'key'       => $key,
                'old_value' => $oldValue,
                'new_value' => $value,
            ])->log('System parameter updated: ' . $key);

            activity()->log('Debug logging ' . ($enabled ? 'enabled' : 'disabled'));
        });

        $this->flushCache();
        $this->applyDebugLoggingConfig($enabled);

        return $this->debugLoggingStatus();
    }

    public function resetToDefaults(int $userId): array
    {
        DB::transaction(function () use ($userId) {
            foreach (SystemParameterSchema::defaults() as $key => $defaultValue) {
                $oldValue = SystemParameter::query()->where('key', $key)->value('value');

                if ((string) $oldValue === (string) $defaultValue) {
                    continue;
                }

                SystemParameter::query()->updateOrInsert(
                    ['key' => $key],
                    ['value' => $defaultValue, 'updated_by' => $userId, 'updated_at' => now()]
                );

                activity()->withProperties([
                    'key'       => $key,
                    'old_value' => $oldValue,
                    'new_value' => $defaultValue,
                ])->log("System parameter updated: {$key}");
            }

            activity()->log('System parameters reset to defaults');
        });

        $this->flushCache();
        $this->applyDebugLoggingConfig(false);

        return $this->groupedParameters();
    }

    public function upsert(string $key, mixed $value, int $userId): void
    {
        if (! SystemParameterSchema::has($key)) {
            return;
        }

        $schema = SystemParameterSchema::definitions()[$key];

        SystemParameter::query()->updateOrInsert(
            ['key' => $key],
            [
                'value'      => SystemParameterSchema::sanitizeValue($value, $schema['type']),
                'updated_by' => $userId,
                'updated_at' => now(),
            ]
        );
    }

    public function applyDebugLoggingFromStorage(): void
    {
        if (! $this->canReadParameters()) {
            return;
        }

        $enabled = SystemParameter::query()
            ->where('key', SystemParameterSchema::DEBUG_LOGGING_KEY)
            ->value('value') === '1';

        $this->applyDebugLoggingConfig($enabled);
    }

    private function formatGrouped(Collection $stored): array
    {
        $grouped = [];

        foreach (SystemParameterSchema::definitions() as $paramKey => $schema) {
            $row = $stored->get($paramKey);

            $grouped[$schema['group']][] = [
                'key'        => $paramKey,
                'label'      => $schema['label'],
                'type'       => $schema['type'],
                'value'      => $row
                    ? SystemParameterSchema::castValue($row->value, $schema['type'])
                    : SystemParameterSchema::castValue(
                        SystemParameterSchema::defaults()[$paramKey] ?? '',
                        $schema['type']
                    ),
                'updated_at' => $row?->updated_at,
                'updated_by' => $row?->updated_by,
            ];
        }

        return $grouped;
    }

    private function applyDebugLoggingConfig(bool $enabled): void
    {
        $level = $enabled ? 'debug' : (string) env('LOG_LEVEL', 'info');

        config(['logging.channels.single.level' => $level]);
        config(['logging.channels.daily.level' => $level]);
        config(['logging.channels.stack.channels' => explode(',', (string) env('LOG_STACK', 'single'))]);
    }

    private function flushCache(): void
    {
        flush_cache_tags(['system_parameters']);
    }

    private function canReadParameters(): bool
    {
        try {
            return SystemParameter::query()->getConnection()->getSchemaBuilder()->hasTable('system_parameters');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
