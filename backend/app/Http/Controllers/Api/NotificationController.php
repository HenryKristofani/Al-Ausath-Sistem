<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $user = $request->user();

        $data = $user->notifications()->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($data);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $notif = $user->notifications()->where('id', $id)->first();
        if (! $notif) {
            return response()->json(['message' => 'Notifikasi tidak ditemukan.'], 404);
        }

        $notif->markAsRead();

        return response()->json(['message' => 'Notifikasi ditandai terbaca.']);
    }

    public function readAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->unreadNotifications->markAsRead();

        return response()->json(['message' => 'Semua notifikasi ditandai terbaca.']);
    }
}
