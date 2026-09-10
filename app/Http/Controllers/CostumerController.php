<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLegacyApiKeyRequest;
use App\Library\ApiKey;
use App\Models\Costumer;
use App\Services\CapabilityService;
use App\Support\EnterpriseGridResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class CostumerController extends Controller
{
    public function __construct(private readonly CapabilityService $capabilities) {}

    public function index(Request $request)
    {
        $this->capabilities->require($request->user(), 'customers.manage');

        $query = Costumer::select([
            'id',
            'costumer',
            'description',
            'licenseExpiration',
            'created_at',
            'updated_at',
        ]);

        return app(EnterpriseGridResponse::class)->build(
            $request,
            $query,
            [
                'id',
                'costumer',
                'description',
                'licenseExpiration',
                'created_at',
                'updated_at',
            ],
            'created_at',
            'asc',
            ['costumer', 'description'],
            ['id'],
        );
    }

    public function store(Request $request)
    {
        $this->capabilities->require($request->user(), 'customers.manage');

        $this->validate($request, [
            'costumer' => 'required',
        ]);
        $startDate = time();
        $apiKey = new ApiKey;
        $costumer = new Costumer;
        $costumer->costumer = strtoupper($request->input('costumer'));
        $costumer->description = strtoupper($request->input('description'));
        $costumer->licenseExpiration = date('Y-m-d H:i:s', strtotime('+365 day', $startDate));
        $costumer->apiKey = $apiKey->generateApiSignature();
        $costumer->apiKeyCreatedAt = now();
        $costumer->logo = '[]';
        $costumer->save();

        return $this->index($request);
    }

    public function show(Request $request, $id)
    {
        $this->capabilities->require($request->user(), 'customers.manage');

        return Costumer::findorFail($id);
    }

    public function getKey(Request $request)
    {
        $this->capabilities->require($request->user(), 'api_keys.manage');

        $usageColumns = $this->legacyApiKeyUsageColumns();
        $costumer = Costumer::query()
            ->select(array_values(array_unique(array_merge([
                'id',
                'apiKey',
                'created_at',
            ], $usageColumns))))
            ->where('id', Auth::user()->idCostumer)
            ->first();
        if ($costumer !== null) {
            return response()->json([
                'apiKey' => $costumer->apiKey,
                'credentials' => [[
                    'actor' => 'legacy',
                    'createdAt' => optional($costumer->apiKeyCreatedAt ?? $costumer->created_at)->toISOString(),
                    'expiresAt' => optional($costumer->apiKeyExpiresAt)->toISOString(),
                    'id' => 'legacy-key',
                    'keyPrefix' => substr($costumer->apiKey, 0, 12),
                    'lastUsedAt' => optional($costumer->apiKeyLastUsedAt)->toISOString(),
                    'legacy' => true,
                    'name' => 'Legacy API key',
                    'scopes' => ['legacy'],
                    'status' => 'legacy',
                    'tenantId' => (string) $costumer->id,
                ]],
            ]);
        }

        return Auth::user()->idCostumer;
    }

    public function updateKey(UpdateLegacyApiKeyRequest $request)
    {
        $this->capabilities->require($request->user(), 'api_keys.manage');

        $usageColumns = $this->legacyApiKeyUsageColumns();
        if ($request->validated('expiresInDays') !== null && ! in_array('apiKeyExpiresAt', $usageColumns, true)) {
            return response()->json([
                'error' => [
                    'code' => 'LEGACY_API_KEY_SCHEMA_MISSING',
                    'message' => 'Legacy API key expiration requires the latest database migration. Run the Idelium API migrations before replacing the key with an expiration policy.',
                ],
            ], 409);
        }

        $apiKey = new ApiKey;
        $costumer = Costumer::findorFail(Auth::user()->idCostumer);
        $costumer->apiKey = $apiKey->generateApiSignature();
        if (in_array('apiKeyCreatedAt', $usageColumns, true)) {
            $costumer->apiKeyCreatedAt = now();
        }
        if (in_array('apiKeyExpiresAt', $usageColumns, true)) {
            $costumer->apiKeyExpiresAt = $request->validated('expiresInDays') === null
                ? null
                : now()->addDays($request->integer('expiresInDays'));
        }
        if (in_array('apiKeyLastUsedAt', $usageColumns, true)) {
            $costumer->apiKeyLastUsedAt = null;
        }
        $costumer->save();

        return response()->json([
            'apiKey' => $costumer->apiKey,
            'credentials' => [[
                'actor' => 'legacy',
                'createdAt' => optional($costumer->apiKeyCreatedAt ?? $costumer->created_at)->toISOString(),
                'expiresAt' => optional($costumer->apiKeyExpiresAt)->toISOString(),
                'id' => 'legacy-key',
                'keyPrefix' => substr($costumer->apiKey, 0, 12),
                'lastUsedAt' => null,
                'legacy' => true,
                'name' => 'Legacy API key',
                'scopes' => ['legacy'],
                'status' => 'legacy',
                'tenantId' => (string) $costumer->id,
            ]],
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->capabilities->require($request->user(), 'customers.manage');

        $this->validate($request, [
            'costumer' => 'required',
            'description' => 'required',
        ]);

        $costumer = Costumer::findorFail($id);
        $costumer->costumer = strtoupper($request->input('costumer'));
        $costumer->description = strtoupper($request->input('description'));
        $costumer->save();

        return $this->index($request);
    }

    public function destroy(Request $request, $id)
    {
        $this->capabilities->require($request->user(), 'customers.manage');

        $costumer = Costumer::findorFail($id);
        if ($costumer->delete()) {
            return $this->index($request);
        }
    }

    private function legacyApiKeyUsageColumns(): array
    {
        return array_values(array_filter(
            ['apiKeyCreatedAt', 'apiKeyExpiresAt', 'apiKeyLastUsedAt'],
            fn (string $column): bool => Schema::hasColumn('costumers', $column),
        ));
    }
}
