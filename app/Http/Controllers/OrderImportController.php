<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportOrdersRequest;
use App\Services\OrderImportService;
use App\Services\PlatformService;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderImportController extends Controller
{
    public function __construct(
        protected OrderImportService $importer,
        protected PlatformService $platformService
    ) {}

    public function create()
    {
        return view('orders.import', [
            'platforms' => $this->platformService->allActive(),
            'aliases' => OrderImportService::ALIASES,
            'chargeColumns' => OrderImportService::CHARGE_COLUMNS,
        ]);
    }

    public function store(ImportOrdersRequest $request)
    {
        $data = $request->validated();

        try {
            $result = $this->importer->import(
                (string) $request->file('file')->getRealPath(),
                (bool) ($data['dry_run'] ?? false),
                isset($data['platform_id']) ? (int) $data['platform_id'] : null,
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return view('orders.import-results', [
            'result' => $result,
            'fileName' => $request->file('file')->getClientOriginalName(),
        ]);
    }

    /**
     * A ready-to-fill CSV so the columns never have to be guessed.
     */
    public function template(): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print $this->importer->template(),
            'order-import-template.csv',
            ['Content-Type' => 'text/csv'],
        );
    }
}
