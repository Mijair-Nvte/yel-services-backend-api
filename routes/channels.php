<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function ($user, $id) {
    \Log::info('CHANNEL AUTH', [
        'auth_user_id' => $user->id,
        'channel_id' => $id,
    ]);

    return true; // 🔥 FORZAR
});