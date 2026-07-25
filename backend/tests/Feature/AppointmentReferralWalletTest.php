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

        $this->postJson('/api/appointments', ['month'=>'1405-04','appointments'=>[]])->assertOk();
        $this->assertSame(0, (int) $referrer->fresh()->wallet_balance);
        $this->assertDatabaseHas('wallet_transactions', ['patient_id'=>$referrer->id,'source_type'=>'reversal','type'=>'withdraw','amount'=>200000]);
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

        $this->postJson('/api/appointments', ['month'=>'1405-05','appointments'=>[]])->assertOk();
        $this->assertSame(500000, (int) $patient->fresh()->wallet_balance);
        $this->assertDatabaseHas('wallet_transactions', ['patient_id'=>$patient->id,'source_type'=>'reversal','type'=>'deposit','amount'=>300000]);
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
