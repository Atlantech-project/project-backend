<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\PortSettingIndexRequest;
use App\Modules\MasterData\Http\Requests\UpdatePortSettingRequest;
use App\Modules\MasterData\Http\Resources\PortSettingCollection;
use App\Modules\MasterData\Http\Resources\PortSettingResource;
use App\Modules\MasterData\Models\PortSetting;
use App\Modules\MasterData\Services\PortSettingService;

/**
 * The typed port settings editor (P2-F1, FR-MD-18, P-62). Both routes carry `ability:port-setup.manage` (DEC-12: only
 * the Port Admin). Settings are never created or deleted here: the keys come from the seeds (CT-18 §3).
 */
class PortSettingController extends Controller
{
    public function __construct(private readonly PortSettingService $settings) {}

    /**
     * GET /api/v1/port-settings: a page ordered by key; 401, 403, 422.
     */
    public function index(PortSettingIndexRequest $request): PortSettingCollection
    {
        return new PortSettingCollection($request->page()->paginate(PortSetting::query()->orderBy('setting_key')));
    }

    /**
     * PUT /api/v1/port-settings/{setting}: the new value; 404, 409 VERSION_CONFLICT, 422.
     */
    public function update(UpdatePortSettingRequest $request, PortSetting $setting): PortSettingResource
    {
        $updated = $this->settings->update($setting, $request->input('value'), $request->validated('version'), $request->user()->id);

        return new PortSettingResource($updated);
    }
}
