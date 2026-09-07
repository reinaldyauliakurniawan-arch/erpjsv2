<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** Daftar notifikasi terbaru + jumlah belum dibaca (untuk lonceng topbar). */
    public function index(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->latest()->limit(20)->get()->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Notifikasi',
                'body' => $n->data['body'] ?? '',
                'url' => $n->data['url'] ?? null,
                'icon' => $n->data['icon'] ?? 'notifications',
                'read' => $n->read_at !== null,
                'ago' => $n->created_at->diffForHumans(),
            ]),
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $n = $request->user()->notifications()->findOrFail($id);
        $n->markAsRead();

        return response()->json(['ok' => true, 'url' => $n->data['url'] ?? null]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['ok' => true]);
    }
}
