<?php

namespace App\Reporting\DynamicReports;

use App\Models\ResourceEarningLine;
use Illuminate\Support\Facades\DB;

/**
 * پورسانت پزشک یا مشاورِ یک نوبت، دقیقاً از همان ردیف‌های دفتر حقوق (resource_earning_lines)
 * که صفحهٔ «تسویه حقوق» جمع می‌زند: فقط ردیف‌های فعال و همهٔ انواع پورسانت خدمات
 * (درصد شخص، قانون اختصاصی انبار و پورسانت پیش‌فرض انبار). پاداش پلکانی ماهانه است و به نوبت وصل نیست.
 */
final class ReportCommissionTotals
{
    /** پزشک = فیلد doctor نوبت یا خدمت؛ مشاور = فیلد consultant که در حقوق «پرسنل» (staff) است. */
    public const RESOURCE_TYPES = ['doctorCommission' => 'doctor', 'consultantCommission' => 'staff'];

    /** عبارت SQL جمع پورسانت برای نوبتی که شناسه‌اش {$appointmentId} است. */
    public static function expression(string $appointmentId, string $resourceType): string
    {
        $resourceType = self::resourceType($resourceType);
        $types = collect(ResourceEarningLine::COMMISSION_TYPES)->map(fn ($type) => "'{$type}'")->implode(', ');

        return "(SELECT COALESCE(SUM(commission_lines.amount), 0) FROM resource_earning_lines AS commission_lines"
            ." WHERE commission_lines.appointment_id = {$appointmentId}"
            ." AND commission_lines.resource_type = '{$resourceType}'"
            ." AND commission_lines.status = 'active'"
            ." AND commission_lines.earning_type IN ({$types}))";
    }

    public static function forAppointment(?int $appointmentId, string $resourceType): ?float
    {
        if (! $appointmentId) return null;
        $row = DB::selectOne('SELECT '.self::expression('?', $resourceType).' AS total', [$appointmentId]);
        return round((float) ($row->total ?? 0));
    }

    private static function resourceType(string $resourceType): string
    {
        if (! in_array($resourceType, self::RESOURCE_TYPES, true)) {
            throw new \InvalidArgumentException("Unknown payroll resource type [{$resourceType}].");
        }
        return $resourceType;
    }
}
