<?php

namespace Tests\Feature;

use App\Http\Controllers\HumanResourceController;
use App\Jobs\InitializeInventoryTags;
use Illuminate\Support\Facades\DB;
use Mockery;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Tests\TestCase;

class InitializeInventoryTagsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/tenant/2026_06_07_075551_create_app_settings_table.php'))->up();
    }

    private function provision(): void
    {
        $tenant = Mockery::mock(TenantWithDatabase::class);
        $tenant->shouldReceive('run')->once()->andReturnUsing(fn (callable $callback) => $callback());
        (new InitializeInventoryTags($tenant))->handle();
    }

    public function test_new_site_gets_every_service_finder_zone_once(): void
    {
        $source = file_get_contents(base_path('../Frontend/src/components/products.vue'));
        $this->assertSame(1, preg_match('/const zoneOptions = ref\(\[(.*?)\]\)/s', $source, $matches));
        preg_match_all("/'([^']+)'/u", $matches[1], $zones);

        $this->provision();
        $this->provision();

        $this->assertSame($zones[1], app(HumanResourceController::class)->serviceTags());
        $this->assertSame(1, DB::table('app_settings')->where('key', 'service_tags')->count());
    }

    public function test_retry_preserves_custom_tags_and_sms_templates(): void
    {
        $value = json_encode([['name' => 'تگ اختصاصی', 'sms_template' => 'متن پیامک']], JSON_UNESCAPED_UNICODE);
        DB::table('app_settings')->insert(['key' => 'service_tags', 'value' => $value]);

        $this->provision();

        $this->assertSame($value, DB::table('app_settings')->where('key', 'service_tags')->value('value'));
    }

    public function test_retry_does_not_restore_deleted_tags(): void
    {
        $this->provision();
        DB::table('app_settings')->where('key', 'service_tags')->update(['value' => '[]']);

        $this->provision();

        $this->assertSame([], app(HumanResourceController::class)->serviceTags());
    }
}
