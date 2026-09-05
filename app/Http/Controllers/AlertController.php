<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Repositories\AlertRepository;
use Illuminate\Http\JsonResponse;

class AlertController extends Controller
{
    public function __construct(
        protected AlertRepository $repo
    ) {}

    public function index()
    {
        $type = request('type');
        $unreadOnly = request('unread') === '1' ? true : null;
        $alerts = $this->repo->paginated(20, $type, $unreadOnly);
        $unreadCount = $this->repo->unreadCount();

        return view('alerts.index', compact('alerts', 'unreadCount'));
    }

    public function markAsRead(Alert $alert): JsonResponse
    {
        $alert = $this->repo->markAsRead($alert);
        return response()->json(['message' => 'Alert marked as read.', 'data' => $alert]);
    }

    public function markAllAsRead(): JsonResponse
    {
        $count = $this->repo->markAllAsRead();
        return response()->json(['message' => "{$count} alerts marked as read."]);
    }

    public function unreadCount(): JsonResponse
    {
        return response()->json(['count' => $this->repo->unreadCount()]);
    }
}
