<?php

namespace App\Modules\MasterData\Services;

use App\Modules\Access\Models\User;
use App\Modules\MasterData\Enums\PortSettingValueType;
use App\Modules\MasterData\Models\PortSetting;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Support\AppVersion;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Validation\ValidationException;

/**
 * Editing one port setting's value (P2-F1, FR-MD-18, CT-18 §4, DEC-12). The key, type, limits and description come from
 * the seeds; only the value changes. It is checked against the type, `min_value` / `max_value` and the cross-field rules,
 * stored as the text the AI workers parse, with `updated_by` (which stops the seeder overwriting it, CT-18 §3).
 *
 * The third CT-18 rule (`run_stale_after_seconds` at least the value computed by the AI Team) has no value yet and is not
 * checked here (known gap).
 */
class PortSettingService
{
    private const int MAX_VALUE_LENGTH = 255;

    private const string DECIMAL_PATTERN = '/^-?\d+(\.\d{1,6})?$/';

    /** Keys a rule reads together; they are locked together, so two edits cannot each pass against the other's old value. */
    private const array RULE_GROUPS = [
        ['part1_min_window_minutes', 'part1_time_step_minutes'],
        ['validation_result_ttl_seconds', 'validation_timeout_seconds'],
    ];

    public function __construct(private readonly AuditService $audit, private readonly RuleRefusal $refusals) {}

    /**
     * @param  mixed  $value  The new value as sent in the JSON body.
     * @param  string  $route  Method and path of the request, for the audit row of a refusal.
     *
     * @throws ValidationException 422 on `value`, with an `errors.port_settings.*` key, audited.
     */
    public function update(PortSetting $setting, mixed $value, string $version, User $actor, string $route): PortSetting
    {
        return $this->refusals->audited(fn () => $this->audit->transaction(function () use ($setting, $value, $version, $actor): PortSetting {
            $partners = $this->lockRuleGroup($setting->setting_key);
            $locked = VersionToken::lockAndAssert($setting, $version);

            $stored = $this->toStored($locked->value_type, $value);
            $this->assertWithinLimits($locked, $stored);
            $this->assertKeyRules($locked->setting_key, $stored, $partners);

            // The same value again writes nothing, so the setting stays open to a new default from the seeder.
            if ($stored === $locked->setting_value) {
                return $locked;
            }

            $locked->fill(['setting_value' => $stored, 'updated_by' => $actor->id])->save();
            $this->audit->recordUpdated($locked, $actor->id);

            return $locked->refresh();
        }), $actor, $route, 'port_settings', $setting->id);
    }

    /**
     * The stored values of the keys that share a rule with `$key`, locked in id order.
     *
     * @return array<string, string>
     */
    private function lockRuleGroup(string $key): array
    {
        foreach (self::RULE_GROUPS as $group) {
            if (in_array($key, $group, true)) {
                return PortSetting::query()->whereIn('setting_key', $group)->orderBy('id')->lockForUpdate()->get()
                    ->pluck('setting_value', 'setting_key')
                    ->all();
            }
        }

        return [];
    }

    /**
     * @throws ValidationException
     */
    private function toStored(PortSettingValueType $type, mixed $value): string
    {
        $stored = match ($type) {
            PortSettingValueType::Int => $this->integerText($value),
            PortSettingValueType::Decimal => $this->decimalText($value),
            PortSettingValueType::Bool => is_bool($value) ? ($value ? 'true' : 'false') : null,
            PortSettingValueType::String => is_string($value) && $value !== '' ? $value : null,
            PortSettingValueType::Json => is_array($value) ? (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
        };

        if ($stored === null) {
            $this->refuse('invalid_'.strtolower($type->value));
        }

        if (mb_strlen($stored) > self::MAX_VALUE_LENGTH) {
            $this->refuse('too_long');
        }

        return $stored;
    }

    private function integerText(mixed $value): ?string
    {
        if (! is_int($value) && ! (is_string($value) && preg_match('/^-?\d+$/', $value) === 1)) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return $integer === false ? null : (string) $integer;
    }

    private function decimalText(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            // Refuse more than 6 decimals, the precision of min_value and max_value.
            return round($value, 6) === $value ? rtrim(rtrim(sprintf('%.6F', $value), '0'), '.') : null;
        }

        return is_string($value) && preg_match(self::DECIMAL_PATTERN, $value) === 1 ? $value : null;
    }

    /**
     * @throws ValidationException
     */
    private function assertWithinLimits(PortSetting $setting, string $stored): void
    {
        if (! in_array($setting->value_type, [PortSettingValueType::Int, PortSettingValueType::Decimal], true)) {
            return;
        }

        if ($setting->min_value !== null && (float) $stored < (float) $setting->min_value) {
            $this->refuse('below_min');
        }

        if ($setting->max_value !== null && (float) $stored > (float) $setting->max_value) {
            $this->refuse('above_max');
        }
    }

    /**
     * Rules for particular keys. A cross-field rule applies only when both of its keys exist (CT-18 §4).
     *
     * @param  array<string, string>  $partners
     *
     * @throws ValidationException
     */
    private function assertKeyRules(string $key, string $stored, array $partners): void
    {
        if ($key === PortSettingMinAppVersion::SETTING_KEY && (strlen($stored) > 20 || preg_match(AppVersion::PATTERN, $stored) !== 1)) {
            $this->refuse('app_version_format');
        }

        $values = [...$partners, $key => $stored];

        if (isset($values['part1_min_window_minutes'], $values['part1_time_step_minutes'])) {
            $step = (int) $values['part1_time_step_minutes'];

            if ($step <= 0 || (int) $values['part1_min_window_minutes'] % $step !== 0) {
                $this->refuse('window_not_multiple_of_step');
            }
        }

        if (isset($values['validation_result_ttl_seconds'], $values['validation_timeout_seconds'])
            && (int) $values['validation_result_ttl_seconds'] <= (int) $values['validation_timeout_seconds']) {
            $this->refuse('ttl_not_greater_than_timeout');
        }
    }

    /**
     * @throws ValidationException always.
     */
    private function refuse(string $reason): never
    {
        throw ValidationException::withMessages(['value' => 'errors.port_settings.'.$reason]);
    }
}
