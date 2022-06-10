<?php

namespace App\View\Components\History;

use App\Models\Setting;
use Illuminate\View\Component;
use OwenIt\Auditing\Models\Audit;

class SettingsEntry extends Component
{
    public function __construct(private Audit $entry, private array $changes = [])
    {
    }

    public function render()
    {
        $timestamp = formatDateTime($this->entry->created_at);

        foreach ($this->entry->getModified() as $field => $change) {
            $this->processChange($field, $change);
        }

        return view('components.history-entry', [
            'timestamp' => $timestamp,
            'changes' => $this->changes,
        ]);
    }

    protected function processChange(string $field, array $changeData): void
    {
        $fieldName = $this->processFieldName($field);
        [$oldValue, $newValue] = $this->processValues($field, $changeData);

        $change = trans('linkace.history_changed', [
            'fieldname' => $fieldName,
            'oldvalue' => htmlspecialchars($oldValue),
            'newvalue' => htmlspecialchars($newValue),
        ]);

        $this->changes[] = $change;
    }

    /**
     * Change the field name appearance to make sure it is properly displayed
     * in the audit log.
     * All guest settings will get the 'guest_' prefix removed and prepend
     * 'Guest Setting:' to the field name.
     * If the setting of a user was changed, append this info to the field.
     *
     * @param string $field
     * @return string
     */
    protected function processFieldName(string $field)
    {
        if (str_starts_with($field, 'guest_')) {
            $field = str_replace('guest_', '', $field);
            $prepend = trans('settings.guest_settings') . ': ';
        }

        if (str_starts_with($field, 'share_')) {
            $service = str_replace('share_', '', $field);
            return trans('settings.sharing') . ': ' . trans('sharing.service.' . $service);
        }

        if ($this->entry->auditable->user_id !== null) {
            $append = sprintf(' %s %s', trans('user.for_user'), $this->entry->auditable->user_id);
        }

        return ($prepend ?? '') . trans('settings.' . $field) . ($append ?? '');
    }

    /**
     * Apply specialized methods for different fields to handle particular
     * formatting needs of these fields.
     *
     * @param string $field
     * @param array  $changeData
     * @return array
     */
    protected function processValues(string $field, array $changeData): array
    {
        $oldValue = $changeData['old'] ?? null;
        $newValue = $changeData['new'] ?? null;

        if (str_contains($field, 'guest_share_') || str_contains($field, 'share_')) {
            $field = 'share_service';
        }

        if (isset(Setting::$auditModifiers[$field])) {
            $modifier = app(Setting::$auditModifiers[$field]);
            $oldValue = $modifier->modify($oldValue);
            $newValue = $modifier->modify($newValue);
            return [$oldValue, $newValue];
        }

        return [$oldValue, $newValue];
    }
}