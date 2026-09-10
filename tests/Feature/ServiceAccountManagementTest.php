<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateIdeliumKey;
use App\Models\AuditEvent;
use App\Models\Costumer;
use App\Models\Role;
use App\Models\User;
use App\Services\ServiceAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ServiceAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private Costumer $customer;

    private Costumer $otherCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::forceCreate(['id' => 1, 'name' => 'superadmin']);
        Role::forceCreate(['id' => 2, 'name' => 'admin']);
        Role::forceCreate(['id' => 3, 'name' => 'user']);

        $this->customer = $this->createCustomer('first');
        $this->otherCustomer = $this->createCustomer('second');
    }

    public function test_admin_creates_service_account_with_one_time_secret_reveal(): void
    {
        $admin = $this->createUser(2, $this->customer);

        $response = $this->actingAs($admin)
            ->withHeader('Origin', 'https://localhost')
            ->postJson('/api/admin/service-accounts', [
                'name' => 'CI runner',
                'scopes' => ['runs.launch'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'CI runner')
            ->assertJsonMissingPath('data.secretHash');

        $secret = $response->json('secret');
        $this->assertIsString($secret);
        $this->assertStringStartsWith('idsa_', $secret);
        $event = AuditEvent::where('action', 'service_account.create')->firstOrFail();
        $this->assertSame('[REDACTED]', $event->afterValues['secret']);
        $this->assertSame('CI runner', $event->afterValues['name']);

        $this->actingAs($admin)
            ->withHeader('Origin', 'https://localhost')
            ->getJson('/api/admin/service-accounts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissing(['secret' => $secret])
            ->assertJsonMissingPath('data.0.secretHash');
    }

    public function test_user_without_capability_cannot_create_service_accounts(): void
    {
        $user = $this->createUser(3, $this->customer);

        $this->actingAs($user)
            ->withHeader('Origin', 'https://localhost')
            ->postJson('/api/admin/service-accounts', [
                'name' => 'blocked',
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'AUTHORIZATION_FORBIDDEN');
    }

    public function test_revoke_is_tenant_scoped(): void
    {
        $admin = $this->createUser(2, $this->customer);
        $foreign = app(ServiceAccountService::class)->create(
            $this->otherCustomer->id,
            'foreign'
        )['serviceAccount'];

        $this->actingAs($admin)
            ->withHeader('Origin', 'https://localhost')
            ->postJson('/api/admin/service-accounts/'.$foreign->id.'/revoke')
            ->assertNotFound();

        $this->assertNull($foreign->fresh()->revokedAt);
    }

    public function test_admin_can_revoke_own_tenant_service_account(): void
    {
        $admin = $this->createUser(2, $this->customer);
        $serviceAccount = app(ServiceAccountService::class)->create(
            $this->customer->id,
            'local'
        )['serviceAccount'];

        $this->actingAs($admin)
            ->withHeader('Origin', 'https://localhost')
            ->postJson('/api/admin/service-accounts/'.$serviceAccount->id.'/revoke')
            ->assertOk()
            ->assertJsonPath('data.id', $serviceAccount->id)
            ->assertJsonMissingPath('data.secretHash');

        $this->assertNotNull($serviceAccount->fresh()->revokedAt);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'service_account.revoke',
            'targetType' => 'service_account',
            'targetId' => (string) $serviceAccount->id,
        ]);
    }

    public function test_admin_receives_legacy_key_creation_and_last_use_metadata(): void
    {
        $admin = $this->createUser(2, $this->customer);
        $request = Request::create('/api/ideliumcl/testcycle/1', 'GET');
        $request->headers->set('Idelium-Key', $this->customer->apiKey);

        app(AuthenticateIdeliumKey::class)->handle(
            $request,
            fn () => response()->json(['ok' => true])
        );

        $response = $this->actingAs($admin)
            ->withHeader('Origin', 'https://localhost')
            ->getJson('/api/admin/apikey')
            ->assertOk()
            ->assertJsonPath('credentials.0.id', 'legacy-key')
            ->assertJsonPath('credentials.0.status', 'legacy')
            ->assertJsonPath('credentials.0.tenantId', (string) $this->customer->id);

        $this->assertNotNull($response->json('credentials.0.createdAt'));
        $this->assertNotNull($response->json('credentials.0.lastUsedAt'));
        $this->assertSame(
            substr($this->customer->apiKey, 0, 12),
            $response->json('credentials.0.keyPrefix')
        );
        $this->assertStringNotContainsString(
            $this->customer->apiKey,
            json_encode($response->json('credentials'), JSON_THROW_ON_ERROR)
        );
    }

    public function test_admin_rotates_legacy_key_with_an_approved_expiration(): void
    {
        $admin = $this->createUser(2, $this->customer);
        $originalKey = $this->customer->apiKey;

        $response = $this->actingAs($admin)
            ->withHeader('Origin', 'https://localhost')
            ->putJson('/api/admin/apikey', ['expiresInDays' => 90])
            ->assertOk()
            ->assertJsonPath('credentials.0.id', 'legacy-key')
            ->assertJsonPath('credentials.0.lastUsedAt', null);

        $rotated = $this->customer->fresh();
        $this->assertNotSame($originalKey, $rotated->apiKey);
        $this->assertNotNull($rotated->apiKeyCreatedAt);
        $this->assertNotNull($rotated->apiKeyExpiresAt);
        $this->assertSame(
            $rotated->apiKeyExpiresAt->toISOString(),
            $response->json('credentials.0.expiresAt')
        );
    }

    public function test_admin_cannot_use_an_unapproved_legacy_key_expiration(): void
    {
        $admin = $this->createUser(2, $this->customer);

        $this->actingAs($admin)
            ->withHeader('Origin', 'https://localhost')
            ->putJson('/api/admin/apikey', ['expiresInDays' => 7])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expiresInDays');
    }

    public function test_admin_receives_actionable_error_when_legacy_key_expiration_schema_is_missing(): void
    {
        $admin = $this->createUser(2, $this->customer);
        Schema::table('costumers', function (Blueprint $table): void {
            $table->dropColumn(['apiKeyCreatedAt', 'apiKeyExpiresAt', 'apiKeyLastUsedAt']);
        });

        $keyRequest = Request::create('/api/ideliumcl/testcycle/1', 'GET');
        $keyRequest->headers->set('Idelium-Key', $this->customer->apiKey);
        $keyResponse = app(AuthenticateIdeliumKey::class)->handle(
            $keyRequest,
            fn () => response()->json(['ok' => true])
        );
        $this->assertSame(200, $keyResponse->getStatusCode());

        $this->actingAs($admin)
            ->withHeader('Origin', 'https://localhost')
            ->getJson('/api/admin/apikey')
            ->assertOk()
            ->assertJsonPath('credentials.0.createdAt', $this->customer->created_at->toISOString());

        $this->actingAs($admin)
            ->withHeader('Origin', 'https://localhost')
            ->putJson('/api/admin/apikey', ['expiresInDays' => 90])
            ->assertConflict()
            ->assertJsonPath('error.code', 'LEGACY_API_KEY_SCHEMA_MISSING');
    }

    private function createCustomer(string $prefix): Costumer
    {
        return Costumer::forceCreate([
            'costumer' => ucfirst($prefix).' customer',
            'description' => ucfirst($prefix).' customer',
            'logo' => json_encode([]),
            'licenseExpiration' => now()->addYear(),
            'apiKey' => $prefix.'-api-key',
        ]);
    }

    private function createUser(int $role, Costumer $customer): User
    {
        return User::forceCreate([
            'name' => 'Test user',
            'role' => $role,
            'email' => uniqid('service-account-', true).'@example.test',
            'email_verified_at' => now(),
            'password' => Hash::make('SensitivePassword123!'),
            'idCostumer' => $customer->id,
        ]);
    }
}
