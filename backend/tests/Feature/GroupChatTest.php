<?php

namespace Tests\Feature;

use App\Events\GroupChatMessageChanged;
use App\Http\Controllers\Api\GroupChatController;
use App\Http\Middleware\EnsureTenantModuleEnabled;
use App\Models\GroupChatMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Controllers are called directly (HTTP + RefreshDatabase does not work in this
 * project); tenant tables are created from the real tenant migrations.
 */
class GroupChatTest extends TestCase
{
    private const MEMBER = ['group_chat.view', 'group_chat.send', 'group_chat.edit', 'group_chat.delete'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'tenant/0001_01_01_000000_create_users_table.php',
            'tenant/2026_06_29_094648_create_permission_tables.php',
            '2026_08_07_000002_create_central_module_subscriptions_table.php',
            'tenant/2026_11_16_120000_create_group_chat_tables.php',
            'tenant/2026_11_16_120100_add_group_chat_permissions.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        tenancy()->tenant = null;
        tenancy()->initialized = false;
        parent::tearDown();
    }

    // ---------------------------------------------------------------- isolation

    public function test_module_gate_blocks_tenants_without_group_chat(): void
    {
        $middleware = app(EnsureTenantModuleEnabled::class);
        $next = fn () => response()->json(['ok' => true]);

        $this->actAsTenant('clinic-a', ['group_chat']);
        $this->assertSame(200, $middleware->handle(Request::create('/api/group-chat/messages'), $next, 'group_chat')->getStatusCode());

        $this->actAsTenant('clinic-b', []);
        $blocked = $middleware->handle(Request::create('/api/group-chat/messages'), $next, 'group_chat');
        $this->assertSame(403, $blocked->getStatusCode());
        $this->assertSame('module_disabled', $blocked->getData(true)['code']);
    }

    public function test_module_purchased_through_subscription_opens_the_gate(): void
    {
        \App\Models\CentralModuleSubscription::create([
            'tenant_id' => 'clinic-c', 'module_key' => 'group_chat', 'module_title' => 'چت آنلاین گروهی',
            'billing_period' => 'one_time', 'price_paid' => 0, 'starts_at' => now(), 'status' => 'active',
        ]);
        $this->actAsTenant('clinic-c', []);

        $response = app(EnsureTenantModuleEnabled::class)
            ->handle(Request::create('/'), fn () => response()->json(['ok' => true]), 'group_chat');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_realtime_channel_is_scoped_to_current_tenant_module_and_permission(): void
    {
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');
        $authorizer = Broadcast::getChannels()->get('clinic.{tenantId}.group-chat');
        $member = $this->member();
        $outsider = User::query()->create(['name' => 'بدون دسترسی', 'email' => 'x@test.local', 'password' => 'secret']);

        $this->actAsTenant('clinic-a', ['group_chat']);
        $this->assertTrue($authorizer($member, 'clinic-a'));
        $this->assertFalse($authorizer($member, 'clinic-b'), 'A user of clinic A must not join clinic B chat.');
        $this->assertFalse($authorizer($outsider, 'clinic-a'));

        $this->actAsTenant('clinic-b', []);
        $this->assertFalse($authorizer($member, 'clinic-b'), 'Tenant without the module must not get a chat channel.');
    }

    public function test_broadcast_goes_only_to_the_current_tenant_channel(): void
    {
        Event::fake([GroupChatMessageChanged::class]);
        $this->actAsTenant('clinic-a', ['group_chat']);

        $this->send($this->member(), ['type' => 'text', 'body' => 'سلام همکاران']);

        Event::assertDispatched(GroupChatMessageChanged::class, function (GroupChatMessageChanged $event) {
            $channels = array_map(fn ($channel) => $channel->name, $event->broadcastOn());

            return $event->action === 'created'
                && $channels === ['private-clinic.clinic-a.group-chat']
                && $event->message['body'] === 'سلام همکاران';
        });
    }

    public function test_voice_and_image_are_stored_on_the_tenant_public_disk(): void
    {
        $this->actAsTenant('clinic-a', ['group_chat']);
        $user = $this->member();

        $voice = $this->send($user, ['type' => 'voice', 'voice_duration' => 7], [
            'voice' => UploadedFile::fake()->create('voice.webm', 40, 'audio/webm'),
        ]);
        $image = $this->send($user, ['type' => 'image', 'body' => 'فرم جدید'], [
            'image' => UploadedFile::fake()->image('form.png', 120, 80),
        ]);

        $voiceRow = GroupChatMessage::query()->findOrFail($voice['id']);
        $imageRow = GroupChatMessage::query()->findOrFail($image['id']);
        $this->assertStringStartsWith('group-chat/voice/', $voiceRow->attachment_path);
        $this->assertStringStartsWith('group-chat/images/', $imageRow->attachment_path);
        Storage::disk('public')->assertExists([$voiceRow->attachment_path, $imageRow->attachment_path]);
        $this->assertSame(7, $voice['voice_duration']);
        // The raw storage path is never exposed; files go through the authenticated endpoint.
        $this->assertStringStartsWith('/api/group-chat/messages/'.$image['id'].'/attachment', $image['attachment_url']);
    }

    // ------------------------------------------------------------- edit/delete

    public function test_sender_can_edit_own_text_and_previous_text_is_kept(): void
    {
        $this->actAsTenant('clinic-a', ['group_chat']);
        $author = $this->member();
        $created = $this->send($author, ['type' => 'text', 'body' => 'متن اول']);

        $updated = $this->payload(fn () => app(GroupChatController::class)->update(
            $this->request($author, 'PATCH', ['body' => 'متن اصلاح‌شده']),
            GroupChatMessage::query()->findOrFail($created['id']),
        ));

        $this->assertSame('متن اصلاح‌شده', $updated['message']['body']);
        $this->assertTrue($updated['message']['is_edited']);
        $row = GroupChatMessage::query()->findOrFail($created['id']);
        $this->assertSame('متن اول', $row->edit_history[0]['body']);
    }

    public function test_other_user_cannot_edit_someone_elses_message(): void
    {
        $this->actAsTenant('clinic-a', ['group_chat']);
        $created = $this->send($this->member(), ['type' => 'text', 'body' => 'پیام من']);

        $this->assertForbidden(fn () => app(GroupChatController::class)->update(
            $this->request($this->member('other@test.local'), 'PATCH', ['body' => 'دستکاری']),
            GroupChatMessage::query()->findOrFail($created['id']),
        ));
        $this->assertSame('پیام من', GroupChatMessage::query()->findOrFail($created['id'])->body);
    }

    public function test_user_without_edit_permission_cannot_edit_even_own_message(): void
    {
        $this->actAsTenant('clinic-a', ['group_chat']);
        $author = $this->userWith(['group_chat.view', 'group_chat.send']);
        $created = $this->send($author, ['type' => 'text', 'body' => 'پیام']);

        $this->assertForbidden(fn () => app(GroupChatController::class)->update(
            $this->request($author, 'PATCH', ['body' => 'تغییر']),
            GroupChatMessage::query()->findOrFail($created['id']),
        ));
    }

    public function test_only_sender_or_moderator_can_delete(): void
    {
        $this->actAsTenant('clinic-a', ['group_chat']);
        $author = $this->member();
        $created = $this->send($author, ['type' => 'text', 'body' => 'پیام']);
        $message = fn () => GroupChatMessage::query()->findOrFail($created['id']);

        $this->assertForbidden(fn () => app(GroupChatController::class)
            ->destroy($this->request($this->member('other@test.local'), 'DELETE'), $message()));
        $this->assertFalse($message()->isDeleted());

        $moderator = $this->userWith([...self::MEMBER, 'group_chat.moderate'], 'mod@test.local');
        $this->payload(fn () => app(GroupChatController::class)->destroy($this->request($moderator, 'DELETE'), $message()));
        $this->assertTrue($message()->isDeleted());
        $this->assertSame($moderator->id, $message()->deleted_by_user_id);
    }

    public function test_sender_without_delete_permission_cannot_delete_own_message(): void
    {
        $this->actAsTenant('clinic-a', ['group_chat']);
        $author = $this->userWith(['group_chat.view', 'group_chat.send']);
        $created = $this->send($author, ['type' => 'text', 'body' => 'پیام']);

        $this->assertForbidden(fn () => app(GroupChatController::class)
            ->destroy($this->request($author, 'DELETE'), GroupChatMessage::query()->findOrFail($created['id'])));
    }

    // ----------------------------------------------------------------- masking

    public function test_deleted_message_is_kept_in_database_but_masked_in_api_output(): void
    {
        Event::fake([GroupChatMessageChanged::class]);
        $this->actAsTenant('clinic-a', ['group_chat']);
        $author = $this->member();
        $created = $this->send($author, ['type' => 'image', 'body' => 'کپشن محرمانه'], [
            'image' => UploadedFile::fake()->image('secret.jpg'),
        ]);
        $row = GroupChatMessage::query()->findOrFail($created['id']);

        $this->payload(fn () => app(GroupChatController::class)->destroy($this->request($author, 'DELETE'), $row));

        // پایگاه داده و فایل دست‌نخورده می‌مانند.
        $row->refresh();
        $this->assertSame('کپشن محرمانه', $row->body);
        Storage::disk('public')->assertExists($row->attachment_path);

        // خروجی فهرست هیچ محتوایی نشان نمی‌دهد.
        $listed = $this->payload(fn () => app(GroupChatController::class)->index($this->request($this->member('reader@test.local'), 'GET')));
        $message = collect($listed['messages'])->firstWhere('id', $row->id);
        $this->assertTrue($message['is_deleted']);
        $this->assertSame('این پیام حذف شده است', $message['deleted_text']);
        $this->assertNull($message['body']);
        $this->assertNull($message['attachment_url']);
        $this->assertStringNotContainsString('کپشن محرمانه', json_encode($listed, JSON_UNESCAPED_UNICODE));

        // رویداد زنده هم محتوا را لو نمی‌دهد.
        Event::assertDispatched(GroupChatMessageChanged::class, fn ($event) => $event->action === 'deleted'
            && $event->message['body'] === null && $event->message['attachment_url'] === null);

        // فایل هم از مسیر API قابل دریافت نیست.
        $this->expectException(HttpException::class);
        app(GroupChatController::class)->attachment($row);
    }

    public function test_deleted_message_cannot_be_edited(): void
    {
        $this->actAsTenant('clinic-a', ['group_chat']);
        $author = $this->member();
        $created = $this->send($author, ['type' => 'text', 'body' => 'پیام']);
        $row = GroupChatMessage::query()->findOrFail($created['id']);
        $this->payload(fn () => app(GroupChatController::class)->destroy($this->request($author, 'DELETE'), $row));

        try {
            app(GroupChatController::class)->update($this->request($author, 'PATCH', ['body' => 'بازگردانی']), $row->fresh());
            $this->fail('Editing a deleted message must fail.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertSame('پیام', $row->fresh()->body);
    }

    // ----------------------------------------------------------------- helpers

    private function actAsTenant(string $id, array $modules): void
    {
        $tenant = new Tenant();
        $tenant->setAttribute('id', $id);
        $tenant->setAttribute('module_ids', $modules);
        tenancy()->tenant = $tenant;
        tenancy()->initialized = true;
    }

    private function member(string $email = 'member@test.local'): User
    {
        return $this->userWith(self::MEMBER, $email);
    }

    private function userWith(array $permissions, string $email = 'member@test.local'): User
    {
        $user = User::query()->firstOrCreate(['email' => $email], ['name' => 'کاربر '.$email, 'password' => 'secret']);
        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function request(User $user, string $method, array $data = [], array $files = []): Request
    {
        $request = Request::create('/api/group-chat/messages', $method, $data, [], $files);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function send(User $user, array $data, array $files = []): array
    {
        return $this->payload(fn () => app(GroupChatController::class)->store($this->request($user, 'POST', $data, $files)))['message'];
    }

    private function payload(callable $action): array
    {
        return $action()->getData(true);
    }

    private function assertForbidden(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected a 403 response.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
