<?php

namespace Tests\Unit;

use App\Support\AppointmentStatuses;
use PHPUnit\Framework\TestCase;

class AppointmentStatusesTest extends TestCase
{
    public function test_waiting_slot_is_not_counted_as_confirmed_scheduled(): void
    {
        $this->assertTrue(AppointmentStatuses::isWaitingSlot('در انتظار وقت'));
        $this->assertTrue(AppointmentStatuses::isWaitingSlot('waiting_slot'));
        $this->assertFalse(AppointmentStatuses::isConfirmedScheduled('در انتظار وقت'));
        $this->assertTrue(AppointmentStatuses::isConfirmedScheduled('وقت داده شد'));
        $this->assertFalse(AppointmentStatuses::isScheduledForReports('در انتظار وقت'));
        $this->assertTrue(AppointmentStatuses::isScheduledForReports('وقت داده شد'));
        $this->assertContains('در انتظار وقت', AppointmentStatuses::all());
    }
}
