<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Aquí defines todos los canales privados de broadcasting.
| Estos canales controlan quién puede escuchar qué eventos.
|
*/

Broadcast::channel('user.{id}', function ($user, $id) {
    // 🔐 Solo el usuario dueño puede escuchar su canal
    return (int) $user->id === (int) $id;
});

/*
|--------------------------------------------------------------------------
| Opcional (compatibilidad Laravel default)
|--------------------------------------------------------------------------
|
| Este canal es el que Laravel usa por defecto para notifications.
| Puedes dejarlo o eliminarlo si no lo usas.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});