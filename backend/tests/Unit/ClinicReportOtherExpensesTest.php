<?php

namespace Tests\Unit;

use App\Models\Expense;
use App\Services\ClinicReportService;
use ReflectionClass;
use Tests\TestCase;

class ClinicReportOtherExpensesTest extends TestCase
{
    private function invokeReport(string $method, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionClass(ClinicReportService::class);

        return $reflection->getMethod($method)->invoke($reflection->newInstanceWithoutConstructor(), ...$arguments);
    }

    public function test_other_clinic_expenses_query_excludes_independent_kpi_categories(): void
    {
        $query = $this->invokeReport('otherClinicExpensesQuery', [
            'from' => '1404-01-01',
            'to' => '1404-01-31',
        ]);

        $sql = strtolower($query->toSql());
        $bindings = $query->getBindings();

        $this->assertStringContainsString('type', $sql);
        $this->assertContains('expense', $bindings);
        $this->assertContains('1404-01-01', $bindings);
        $this->assertContains('1404-01-31', $bindings);
        $this->assertStringContainsString('not like', $sql);
        $this->assertTrue(collect($bindings)->contains(fn ($value) => is_string($value) && str_contains($value, 'تبلیغ')));
        $this->assertTrue(collect($bindings)->contains(fn ($value) => is_string($value) && str_contains($value, 'حقوق')));
        $this->assertTrue(collect($bindings)->contains(fn ($value) => is_string($value) && str_contains($value, 'پزشک')));
        $this->assertTrue(collect($bindings)->contains(fn ($value) => is_string($value) && str_contains($value, 'مواد')));
    }

    public function test_expense_detail_row_maps_invoice_fields(): void
    {
        $row = new Expense([
            'title' => '  اجاره ماهانه  ',
            'amount' => 1500000,
            'invoice_number' => ' 12 ',
            'occurred_on' => '1404-01-10',
            'party' => '  مالک  ',
            'category' => 'اجاره',
            'payment_status' => 'paid',
            'note' => '  یادداشت اجاره  ',
        ]);
        $row->id = 9;

        $this->assertSame([
            'id' => 9,
            'title' => 'اجاره ماهانه',
            'amount' => 1500000.0,
            'invoice_number' => '12',
            'occurred_on' => '1404-01-10',
            'party' => 'مالک',
            'category' => 'اجاره',
            'payment_status' => 'paid',
            'note' => 'یادداشت اجاره',
        ], $this->invokeReport('expenseDetailRow', $row));
    }
}
