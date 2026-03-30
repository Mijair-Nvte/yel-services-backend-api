<?php

namespace App\Services\Notifications;

use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Enviar a múltiples usuarios (ASYNC 🔥)
     */
    public function send(
        Collection|array $users,
        string $type,
        array $data,
        ?int $orgCompanyId = null
    ): void {
        $users = $this->normalizeUsers($users);

        if ($users->isEmpty()) {
            return;
        }

        foreach ($users as $user) {
            SendNotificationJob::dispatch(
                $user->id,
                $type,
                $data,
                $orgCompanyId
            );
        }
    }

    /**
     * Enviar a un solo usuario
     */
    public function sendToUser(
        User $user,
        string $type,
        array $data,
        ?int $orgCompanyId = null
    ): void {
        $this->send([$user], $type, $data, $orgCompanyId);
    }

    /**
     * Enviar por IDs
     */
    public function sendToUserIds(
        array $userIds,
        string $type,
        array $data,
        ?int $orgCompanyId = null
    ): void {
        $users = User::whereIn('id', $userIds)->get();

        $this->send($users, $type, $data, $orgCompanyId);
    }

    /**
     * Enviar a toda una compañía
     */
    public function sendToCompany(
        int $orgCompanyId,
        string $type,
        array $data
    ): void {
        $users = User::whereHas('companies', function ($query) use ($orgCompanyId) {
            $query->where('org_company_id', $orgCompanyId);
        })->get();

        $this->send($users, $type, $data, $orgCompanyId);
    }

    /**
     * Marcar una como leída
     */
    public function markAsRead(Notification $notification): void
    {
        if (! $notification->read_at) {
            $notification->update([
                'read_at' => now(),
            ]);
        }
    }

    /**
     * Marcar todas como leídas
     */
    public function markAllAsRead(User $user): void
    {
        $user->notifications()
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);
    }

    /**
     * Obtener notificaciones
     */
    public function getUserNotifications(User $user, int $limit = 20)
    {
        return $user->notifications()
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Obtener no leídas
     */
    public function getUnread(User $user)
    {
        return $user->notifications()
            ->whereNull('read_at')
            ->latest()
            ->get();
    }

    /**
     * Contar no leídas
     */
    public function countUnread(User $user): int
    {
        return $user->notifications()
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Normalizar users
     */
    protected function normalizeUsers(Collection|array $users): Collection
    {
        return $users instanceof Collection ? $users : collect($users);
    }
}
