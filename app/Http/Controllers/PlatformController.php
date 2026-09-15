<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlatformRequest;
use App\Http\Requests\UpdatePlatformRequest;
use App\Models\Platform;
use App\Services\PlatformService;

class PlatformController extends Controller
{
    public function __construct(
        protected PlatformService $service
    ) {}

    public function index()
    {
        $platforms = $this->service->all();

        return view('platforms.index', compact('platforms'));
    }

    public function create()
    {
        return view('platforms.create');
    }

    public function store(StorePlatformRequest $request)
    {
        $platform = $this->service->create($request->validated());
        if ($request->wantsJson()) {
            return response()->json(['message' => 'Platform created.', 'data' => $platform], 201);
        }

        return redirect()->route('platforms.show', $platform)->with('success', 'Platform created successfully.');
    }

    public function show(Platform $platform)
    {
        $platform->load(['orders.items.product', 'payments']);

        return view('platforms.show', compact('platform'));
    }

    public function edit(Platform $platform)
    {
        return view('platforms.edit', compact('platform'));
    }

    public function update(UpdatePlatformRequest $request, Platform $platform)
    {
        $platform = $this->service->update($platform, $request->validated());
        if ($request->wantsJson()) {
            return response()->json(['message' => 'Platform updated.', 'data' => $platform]);
        }

        return redirect()->route('platforms.show', $platform)->with('success', 'Platform updated successfully.');
    }

    public function destroy(Platform $platform)
    {
        $this->service->delete($platform);
        if (request()->wantsJson()) {
            return response()->json(['message' => 'Platform deleted.']);
        }

        return redirect()->route('platforms.index')->with('success', 'Platform deleted successfully.');
    }
}
