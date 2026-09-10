<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\InventorySection;
use App\Models\Patient;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AppointmentReferralWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_referral_reward_is_idempotent_and_reversed_when_appointment_is_removed(): void
    {
        $this->actingAs($this->appointmentUser());
        $referrer = Patient::create(['first_name'=>'معرف','last_name'=>'تست','phone'=>'09120000001','file_number'=>'REF-1','gender'=>'زن']);
        Patient::create(['first_name'=>'بیمار','last_name'=>'تست','phone'=>'09120000002','file_number'=>'PAT-1','gender'=>'مرد']);
        $section = InventorySection::create(['name'=>'خدمات','sort_order'=>1]);
        Inventory::create([
            'section_id'=>$section->id, 'name'=>'خدمت تست', 'amount'=>1000000, 'active'=>true,
            'default_commission_type'=>'percent', 'default_commission_value'=>10,
        ]);

        $payload = ['month'=>'1405-04','appointments'=>[array_merge($this->appointmentPayload(), [
            'referrer_phone'=>$referrer->phone,
            'services'=>[['name'=>'خدمت تست','cc'=>2,'addons'=>[]]],
        ])]];

        $this->postJson('/api/appointments', $payload)->assertOk();
        $this->assertSame(200000, (int) $referrer->fresh()->wallet_balance);
        $this->postJson('/api/appointments', $payload)->assertOk();
        $this->assertSame(200000, (int) $referrer->fresh()->wallet_balance);
        $this->assertSame(1, WalletTransaction::where('source_type','referral_reward')->count());

        // A blank browser draft must never mean "delete the whole month".
        $this->postJson('/api/appointments', ['month'=>'1405-04','appointments'=>[]])->assertUnprocessable();
        $this->assertSame(200000, (int) $referrer->fresh()->wallet_balance);
    }

    public function test_wallet_payment_is_withdrawn_once_and_returned_after_deleting_appointment(): void
    {
        $this->actingAs($this->appointmentUser());
        $patient = Patient::create(['first_name'=>'بیمار','last_name'=>'کیف پول','phone'=>'09120000003','file_number'=>'PAT-2','gender'=>'زن']);
        $patient->walletTransactions()->create(['type'=>'deposit','amount'=>500000,'description'=>'شارژ اولیه','source_type'=>'manual']);
        $payload = ['month'=>'1405-05','appointments'=>[array_merge($this->appointmentPayload(), [
            'phone'=>$patient->phone, 'file_number'=>$patient->file_number, 'wallet_applied'=>300000,
        ])]];

        $this->postJson('/api/appointments', $payload)->assertOk();
        $this->assertSame(200000, (int) $patient->fresh()->wallet_balance);
        $this->postJson('/api/appointments', $payload)->assertOk();
        $this->assertSame(200000, (int) $patient->fresh()->wallet_balance);

        $this->postJson('/api/appointments', ['month'=>'1405-05','appointments'=>[]])->assertUnprocessable();
        $this->assertSame(200000, (int) $patient->fresh()->wallet_balance);
    }

    public function test_each_service_discount_is_stored_and_capped_independently(): void
    {
        $this->actingAs($this->appointmentUser());
        $section = InventorySection::create(['name' => 'خدمات تخفیف', 'sort_order' => 1]);
        Inventory::create(['section_id' => $section->id, 'name' => 'خدمت اول', 'amount' => 100000, 'active' => true]);
        Inventory::create(['section_id' => $section->id, 'name' => 'خدمت دوم', 'amount' => 200000, 'active' => true]);
        Inventory::create(['section_id' => $section->id, 'name' => 'خدمت جانبی', 'amount' => 50000, 'active' => true]);

        $payload = ['month' => '1405-06', 'appointments' => [array_merge($this->appointmentPayload(), [
            'services' => [
                ['name' => 'خدمت اول', 'cc' => 2, 'discount' => 30000, 'addons' => [
                    ['name' => 'خدمت جانبی', 'cc' => 1, 'discount' => 10000],
                ]],
                ['name' => 'خدمت دوم', 'cc' => 1, 'discount' => 999999, 'addons' => []],
            ],
            'discount' => 1,
            'original_amount' => 1,
            'amount' => 1,
        ])]];

        $this->postJson('/api/appointments', $payload)->assertOk();

        $appointment = \App\Models\Appointment::firstOrFail();
        $this->assertSame(30000, (int) $appointment->services[0]['discount']);
        $this->assertSame(10000, (int) $appointment->services[0]['addons'][0]['discount']);
        $this->assertSame(200000, (int) $appointment->services[1]['discount']);
        $this->assertSame(450000, (int) $appointment->original_amount);
        $this->assertSame(240000, (int) $appointment->discount);
        $this->assertSame(210000, (int) $appointment->amount);
    }

    public function test_each_booking_deposit_allocation_is_stored_as_a_separate_service_transaction(): void
    {
        $patient = Patient::create(['first_name' => 'بیمار', 'last_name' => 'بیعانه', 'phone' => '09120000009', 'file_number' => 'DEP-1', 'gender' => 'زن']);

        $this->postJson("/api/patients/{$patient->id}/wallet/deposit", [
            'amount' => 350000,
            'allocations' => [
                ['section' => 'پوست', 'subsection' => 'تزریق', 'service' => 'ژل لب', 'amount' => 200000],
                ['section' => 'پوست', 'subsection' => 'تزریق', 'parent_service' => 'ژل لب', 'service' => 'بی‌حسی', 'amount' => 150000],
            ],
        ])->assertOk()->assertJsonPath('wallet_balance', 350000);

        $transactions = WalletTransaction::where('patient_id', $patient->id)->orderBy('id')->get();
        $this->assertCount(2, $transactions);
        $this->assertSame([200000, 150000], $transactions->map(fn ($item) => (int) $item->amount)->all());
        $this->assertSame('ژل لب', $transactions[0]->metadata['services'][0]['service']);
        $this->assertSame('بی‌حسی', $transactions[1]->metadata['services'][0]['service']);
        $this->assertSame('ژل لب', $transactions[1]->metadata['services'][0]['parent_service']);
    }

    public function test_booking_deposit_rejects_an_allocation_total_mismatch_without_creating_transactions(): void
    {
        $patient = Patient::create(['first_name' => 'بیمار', 'last_name' => 'نامعتبر', 'phone' => '09120000010', 'file_number' => 'DEP-2', 'gender' => 'مرد']);

        $this->postJson("/api/patients/{$patient->id}/wallet/deposit", [
            'amount' => 300000,
            'allocations' => [
                ['service' => 'خدمت اول', 'amount' => 100000],
                ['service' => 'خدمت دوم', 'amount' => 100000],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('allocations');

        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    private function appointmentPayload(): array
    {
        return [
            'day_num'=>1, 'sort_order'=>1, 'lastname'=>'بیمار تست', 'phone'=>'09120000002',
            'file_number'=>'PAT-1', 'time'=>'10:00', 'services'=>[], 'amount'=>2000000,
            'original_amount'=>2000000, 'wallet_applied'=>0,
        ];
    }

    private function appointmentUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('appointments.create', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('appointments.update', 'web'));

        return $user;
    }
}
