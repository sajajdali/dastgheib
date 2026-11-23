<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Inventory;
use App\Models\InventorySection;
use Illuminate\Support\Collection;

/**
 * آیا نوبت به عکس قبل/بعد نیاز دارد؟
 * هر خدمت از بخش/زیربخش خودش خوانده می‌شود؛ اگر حتی یک خدمت الزام داشته باشد، نوبت الزام دارد.
 */
final class AppointmentPhotosRequirement
{
    public static function forAppointment(Appointment|array|null $appointment): bool
    {
        $services = self::servicesOf($appointment);
        if ($services === []) {
            return true;
        }

        $sections = InventorySection::query()->get(['id', 'photos_required', 'name'])->keyBy('id');
        $inventoryByName = null;

        foreach ($services as $service) {
            if (self::serviceRequiresPhotos($service, $sections, $inventoryByName)) {
                return true;
            }
        }

        return false;
    }

    public static function forServices(iterable $services, ?Collection $sections = null): bool
    {
        $list = [];
        foreach ($services as $service) {
            if (is_array($service)) {
                $list[] = $service;
            }
        }
        if ($list === []) {
            return true;
        }

        $sections ??= InventorySection::query()->get(['id', 'photos_required', 'name'])->keyBy('id');
        $inventoryByName = null;

        foreach ($list as $service) {
            if (self::serviceRequiresPhotos($service, $sections, $inventoryByName)) {
                return true;
            }
        }

        return false;
    }

    private static function servicesOf(Appointment|array|null $appointment): array
    {
        if ($appointment instanceof Appointment) {
            $services = $appointment->services;
        } else {
            $services = $appointment['services'] ?? [];
        }

        if (! is_array($services)) {
            return [];
        }

        return array_values(array_filter($services, function ($service) {
            if (! is_array($service)) {
                return false;
            }

            return trim((string) ($service['name'] ?? '')) !== ''
                || ! empty($service['section_id'])
                || ! empty($service['sectionId']);
        }));
    }

    private static function serviceRequiresPhotos(array $service, Collection $sections, ?Collection &$inventoryByName): bool
    {
        $sectionId = $service['section_id'] ?? $service['sectionId'] ?? null;
        if (! $sectionId) {
            $name = trim((string) ($service['name'] ?? ''));
            if ($name !== '') {
                $inventoryByName ??= Inventory::query()->get(['name', 'section_id'])->keyBy('name');
                $sectionId = $inventoryByName->get($name)?->section_id;
            }
        }

        if (! $sectionId) {
            return true;
        }

        $section = $sections->get((int) $sectionId);
        if (! $section) {
            return true;
        }

        return $section->photos_required !== false;
    }
}
