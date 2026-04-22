<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\Auth\LoginController as AuthLoginController;
use App\Http\Controllers\Api\Auth\LogoutController as AuthLogoutController;
use App\Http\Controllers\Api\Auth\MeController as AuthMeController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FolderController;
use App\Http\Controllers\Api\NoticeLevelController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrgAreaController;
use App\Http\Controllers\Api\OrgAreaUserRoleController;
use App\Http\Controllers\Api\OrgCompanyController;
use App\Http\Controllers\Api\OrgCompanyInvitationController;
use App\Http\Controllers\Api\OrgCompanyLinkController;
use App\Http\Controllers\Api\OrgCompanyNoticeController;
use App\Http\Controllers\Api\OrgCompanyUserController;
use App\Http\Controllers\Api\OrgEventController;
use App\Http\Controllers\Api\OrgMemberController;
use App\Http\Controllers\Api\OrgPositionController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // 🔗 Webhooks
    Route::post('/webhooks/ghl', [WebhookController::class, 'handleGHL']);
    Route::post('/webhooks/ghl/service-form', [WebhookController::class, 'handleServiceForm']);

    // 🔓 Rutas Públicas
    Route::post('/register', RegisterController::class);
    Route::post('/login', AuthLoginController::class);

    Route::get('/org-invitations/{token}', [OrgCompanyInvitationController::class, 'show']);
    Route::post('/org-invitations/{token}/accept', [OrgCompanyInvitationController::class, 'accept']);

    // 📄 Documentos (públicos)
    Route::get('/documents/{uid}/view', [DocumentController::class, 'view']);
    Route::get('/documents/{uid}/download', [DocumentController::class, 'download']);

    // 🔒 Rutas Protegidas
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Support\Facades\Broadcast::auth($request);
        });

        Route::get('/me', AuthMeController::class);
        Route::post('/logout', AuthLogoutController::class);

        // 👤 Account
        Route::get('/account', [AccountController::class, 'show']);
        Route::put('/account', [AccountController::class, 'update']);
        Route::post('/account/avatar', [AccountController::class, 'uploadAvatar']);

        // 🏢 Compañías (Nivel General - Todos pueden listar las suyas y crear nuevas)
        Route::get('/org-companies', [OrgCompanyController::class, 'index']);
        Route::post('/org-companies', [OrgCompanyController::class, 'store']);
        Route::get('/org-companies/{uid}', [OrgCompanyController::class, 'show']);

        // ========================================================================
        // 🚀 RUTAS CONTEXTUALES DE LA COMPAÑÍA (Requieren permisos de Spatie)
        // ========================================================================
        Route::prefix('org-companies/{uid}')->middleware([\App\Http\Middleware\SetTenantContext::class])->group(function () {

            Route::get('/my-permissions', function (string $uid) {
                $user = auth()->user();

                // Gracias a tu SetTenantContext, Spatie ya está filtrando por esta empresa
                return response()->json([
                    'roles' => $user->getRoleNames(), // ej: ['admin'] o ['user']
                    'permissions' => $user->getAllPermissions()->pluck('name'), // ej: ['view_dashboard', 'manage_team', ...]
                ]);
            });

            // ⚙️ Gestión de la Compañía (Requiere manage_team o un permiso admin)
            Route::middleware('can:manage_team')->group(function () {
                // Cambiar rol de un usuario
                Route::put('/members/{id}/role', [OrgMemberController::class, 'updateRole']);

                // Obtener lista de roles disponibles (para el Select de la UI)
                Route::get('/roles-list', [OrgMemberController::class, 'getAvailableRoles']);

                Route::put('/', [OrgCompanyController::class, 'update']);
                Route::delete('/', [OrgCompanyController::class, 'destroy']);
                Route::post('/invitations', [OrgCompanyInvitationController::class, 'store']);
            });

            // 📊 Dashboard
            Route::get('/dashboard', [DashboardController::class, 'overview'])->middleware('can:view_dashboard');

            // 🧩 Áreas
            Route::get('/areas', [OrgAreaController::class, 'index'])->middleware('can:view_areas');
            Route::post('/areas', [OrgAreaController::class, 'store'])->middleware('can:manage_areas');
            Route::get('/areas/{areaUid}/notices', [OrgCompanyNoticeController::class, 'indexArea'])->middleware('can:view_notices');

            // 👥 Equipo y Roles
            Route::get('/team', [OrgCompanyUserController::class, 'index'])->middleware('can:view_team');
            Route::get('/team/{id}', [OrgCompanyUserController::class, 'show'])->middleware('can:view_team');
            Route::get('/positions', [OrgPositionController::class, 'index'])->middleware('can:view_team');

            Route::get('/members', [OrgMemberController::class, 'index'])->middleware('can:view_team');
            Route::get('/members/{id}', [OrgMemberController::class, 'show'])->middleware('can:view_team');

            Route::middleware('can:manage_team')->group(function () {

                Route::put('/members/{id}', [OrgMemberController::class, 'update']);
                Route::delete('/members/{id}', [OrgMemberController::class, 'destroy']);

                Route::post('/team', [OrgCompanyUserController::class, 'store']);
                Route::put('/team/{id}', [OrgCompanyUserController::class, 'update']);
                Route::delete('/team/{id}', [OrgCompanyUserController::class, 'destroy']);
                Route::post('/positions', [OrgPositionController::class, 'store']);
                Route::delete('/positions/{id}', [OrgPositionController::class, 'destroy']);
            });

            // 💰 Ventas
            Route::get('/sales', [\App\Http\Controllers\Api\SalesController::class, 'index'])->middleware('can:view_sales');
            Route::middleware('can:manage_sales')->group(function () {
                Route::put('/sales/{saleId}/commission', [\App\Http\Controllers\Api\SalesController::class, 'updateCommission']);
                Route::post('/sales/export-pdf', [\App\Http\Controllers\Api\SalesController::class, 'exportPdf']);
                Route::put('/sales/{saleId}', [\App\Http\Controllers\Api\SalesController::class, 'update']);
                Route::delete('/sales/{saleId}', [\App\Http\Controllers\Api\SalesController::class, 'destroy']);
            });

            // 🔗 Payment Links GHL
            Route::get('/payment-link-mappings', [\App\Http\Controllers\Api\OrgPaymentLinkMappingController::class, 'index'])->middleware('can:view_payment_links');
            Route::middleware('can:manage_payment_links')->group(function () {
                Route::post('/payment-link-mappings', [\App\Http\Controllers\Api\OrgPaymentLinkMappingController::class, 'store']);
                Route::put('/payment-link-mappings/{mappingUid}', [\App\Http\Controllers\Api\OrgPaymentLinkMappingController::class, 'update']);
                Route::delete('/payment-link-mappings/{mappingUid}', [\App\Http\Controllers\Api\OrgPaymentLinkMappingController::class, 'destroy']);
            });

            // 📅 Eventos / Calendario
            Route::get('/events', [OrgEventController::class, 'index'])->middleware('can:view_calendar');
            Route::get('/events/{eventUid}', [OrgEventController::class, 'show'])->middleware('can:view_calendar');
            Route::middleware('can:manage_calendar')->group(function () {
                Route::post('/events', [OrgEventController::class, 'store']);
                Route::put('/events/{eventUid}', [OrgEventController::class, 'update']);
                Route::delete('/events/{eventUid}', [OrgEventController::class, 'destroy']);
            });

            // 📢 Avisos / Notices
            Route::get('/notices', [OrgCompanyNoticeController::class, 'index'])->middleware('can:view_notices');
            Route::post('/notices', [OrgCompanyNoticeController::class, 'store'])->middleware('can:manage_notices');

            // 🌐 Links de la Compañía
            Route::get('/links', [OrgCompanyLinkController::class, 'index'])->middleware('can:view_company_links');
            Route::post('/links', [OrgCompanyLinkController::class, 'store'])->middleware('can:manage_company_links');

            // 💬 Chat
            Route::get('/chats', [ChatController::class, 'index'])->middleware('can:access_chat');
            Route::get('/chats/direct/{userId}', [ChatController::class, 'getOrCreateDirect'])->middleware('can:access_chat');
        });

        // ========================================================================
        // 🔄 RUTAS SECUNDARIAS (Endpoints directos por UID que no llevan el prefijo de la compañía)
        // ========================================================================

        // 🧩 Áreas y Asignaciones
        Route::get('/org-areas/{uid}', [OrgAreaController::class, 'show'])->middleware('can:view_areas');
        Route::get('/org-areas/{uid}/team', [OrgAreaUserRoleController::class, 'byArea'])->middleware('can:view_team');
        Route::middleware('can:manage_areas')->group(function () {
            Route::put('/org-areas/{uid}', [OrgAreaController::class, 'update']);
            Route::delete('/org-areas/{uid}', [OrgAreaController::class, 'destroy']);
        });

        Route::get('/org-area-user-roles', [OrgAreaUserRoleController::class, 'index'])->middleware('can:view_team');
        Route::get('/org-area-user-roles/{id}', [OrgAreaUserRoleController::class, 'show'])->middleware('can:view_team');
        Route::middleware('can:manage_team')->group(function () {
            Route::post('/org-area-user-roles', [OrgAreaUserRoleController::class, 'store']);
            Route::put('/org-area-user-roles/{id}', [OrgAreaUserRoleController::class, 'update']);
            Route::delete('/org-area-user-roles/{id}', [OrgAreaUserRoleController::class, 'destroy']);
        });

        // 📢 Avisos Directos
        Route::get('/org-company-notices/{uid}', [OrgCompanyNoticeController::class, 'show'])->middleware('can:view_notices');
        Route::middleware('can:manage_notices')->group(function () {
            Route::put('/org-company-notices/{uid}', [OrgCompanyNoticeController::class, 'update']);
            Route::delete('/org-company-notices/{uid}', [OrgCompanyNoticeController::class, 'destroy']);
            Route::post('/org-company-notices/{uid}/pin', [OrgCompanyNoticeController::class, 'pin']);
            Route::post('/org-company-notices/{uid}/unpin', [OrgCompanyNoticeController::class, 'unpin']);
        });

        // 🌐 Links Directos
        Route::get('/org-company-links/{uid}', [OrgCompanyLinkController::class, 'show'])->middleware('can:view_company_links');
        Route::middleware('can:manage_company_links')->group(function () {
            Route::put('/org-company-links/{uid}', [OrgCompanyLinkController::class, 'update']);
            Route::delete('/org-company-links/{uid}', [OrgCompanyLinkController::class, 'destroy']);
        });

        // 💬 Chat Acciones
        Route::middleware('can:access_chat')->group(function () {
            Route::post('/chats/{conversationId}/messages', [ChatController::class, 'sendMessage']);
            Route::post('/chats/{conversationId}/read', [ChatController::class, 'markAsRead']);
            Route::delete('/chats/{conversationId}/clear', [ChatController::class, 'clearConversation']);
            Route::put('/messages/{messageId}', [ChatController::class, 'updateMessage']);
            Route::delete('/messages/{messageId}', [ChatController::class, 'deleteMessage']);
        });

        // 🔔 Notificaciones (Globales del usuario)
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread', [NotificationController::class, 'unread']);
        Route::get('/notifications/unread/count', [NotificationController::class, 'countUnread']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAll']);
        Route::get('/notice-levels', [NoticeLevelController::class, 'index']);

        // 📂 Carpetas y Documentos
        Route::middleware('can:view_documents')->group(function () {
            Route::get('/folders', [FolderController::class, 'index']);
            Route::get('/folders/{folder}/children', [FolderController::class, 'children']);
            Route::get('/folders/{folderUid}/documents', [DocumentController::class, 'byFolder']);
            Route::get('/documents/{uid}', [DocumentController::class, 'show']);
        });

        Route::middleware('can:manage_documents')->group(function () {
            Route::post('/folders', [FolderController::class, 'store']);
            Route::put('/folders/{folder}', [FolderController::class, 'update']);
            Route::delete('/folders/{folder}', [FolderController::class, 'destroy']);
            Route::post('/documents', [DocumentController::class, 'store']);
            Route::delete('/documents/{uid}', [DocumentController::class, 'destroy']);
            Route::post('/documents/presign', [DocumentController::class, 'presign']);
            Route::post('/documents/confirm', [DocumentController::class, 'confirm']);
        });

    });
});
