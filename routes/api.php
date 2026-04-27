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
use App\Http\Controllers\Api\OrgPaymentLinkMappingController;
use App\Http\Controllers\Api\OrgPositionController;
use App\Http\Controllers\Api\SalesController;
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

            // ==========================================
            // 👑 CONFIGURACIÓN RAÍZ DE LA COMPAÑÍA (Lógica de seguridad en el Controlador)
            // ==========================================
            // Estas rutas NO llevan middleware 'can' porque validaremos al 'Owner' internamente
            Route::put('/', [OrgCompanyController::class, 'update']);
            Route::delete('/', [OrgCompanyController::class, 'destroy']);

            // 📊 Dashboard
            Route::get('/dashboard', [DashboardController::class, 'overview'])->middleware('can:view_dashboard');

            // ==========================================
            // 👥 GESTIÓN DE USUARIOS DE LA COMPAÑÍA
            // ==========================================

            // 📖 Directorio Público (Solo requiere estar en la compañía) se usara para consultas directa a usuario
            Route::get('/directory', [OrgCompanyUserController::class, 'directory']);

            // Metodo para ver las posiciones
            Route::get('/positions', [OrgPositionController::class, 'index']);

            // 👁️ Ver Usuarios (Settings)
            Route::middleware('can:view_users')->group(function () {
                Route::get('/users', [OrgCompanyUserController::class, 'index']);
                Route::get('/users/{id}', [OrgCompanyUserController::class, 'show']);
            });

            // ⚙️ Administrar Usuarios (Settings)
            Route::middleware('can:manage_users')->group(function () {
                Route::post('/invitations', [OrgCompanyInvitationController::class, 'store']);
                Route::put('/users/{id}', [OrgCompanyUserController::class, 'update']);
                Route::delete('/users/{id}', [OrgCompanyUserController::class, 'destroy']);
            });

            // 💰 VENTAS / SALES
            Route::group([], function () {
                // Ver Ventas
                Route::get('/sales', [SalesController::class, 'index'])->middleware('can:view_sales');

                // Gestionar Ventas
                Route::middleware('can:manage_sales')->group(function () {
                    Route::put('/sales/{saleId}/commission', [SalesController::class, 'updateCommission']);
                    Route::post('/sales/export-pdf', [SalesController::class, 'exportPdf']);
                    Route::put('/sales/{saleId}', [SalesController::class, 'update']);
                    Route::delete('/sales/{saleId}', [SalesController::class, 'destroy']);
                });
            });

            // 🔗 Payment Links GHL (Dentro de Route::prefix('org-companies/{uid}'))
            Route::group([], function () {
                // Ver Mapeos
                Route::get('/payment-link-mappings', [OrgPaymentLinkMappingController::class, 'index'])->middleware('can:view_payment_links');

                // Gestionar Mapeos
                Route::middleware('can:manage_payment_links')->group(function () {
                    Route::post('/payment-link-mappings', [OrgPaymentLinkMappingController::class, 'store']);
                    Route::put('/payment-link-mappings/{mappingUid}', [OrgPaymentLinkMappingController::class, 'update']);
                    Route::delete('/payment-link-mappings/{mappingUid}', [OrgPaymentLinkMappingController::class, 'destroy']);
                });
            });

            // 📅 EVENTOS / CALENDARIO (Bloque Unificado)
            Route::group([], function () {
                // Ver Calendario
                Route::middleware('can:view_calendar')->group(function () {
                    Route::get('/events', [OrgEventController::class, 'index']);
                    Route::get('/events/{eventUid}', [OrgEventController::class, 'show']);
                });

                // Gestionar Calendario
                Route::middleware('can:manage_calendar')->group(function () {
                    Route::post('/events', [OrgEventController::class, 'store']);
                    Route::put('/events/{eventUid}', [OrgEventController::class, 'update']);
                    Route::delete('/events/{eventUid}', [OrgEventController::class, 'destroy']);
                });
            });

            // 📢 AVISOS / NOTICES (BLOQUE UNIFICADO)
            Route::group([], function () {
                // Ver Avisos
                Route::middleware('can:view_notices')->group(function () {
                    Route::get('/notices', [OrgCompanyNoticeController::class, 'index']);
                    Route::get('/notices/{noticeUid}', [OrgCompanyNoticeController::class, 'show']);
                    Route::get('/areas/{areaUid}/notices', [OrgCompanyNoticeController::class, 'indexArea']);
                });

                // Gestionar Avisos
                Route::middleware('can:manage_notices')->group(function () {
                    Route::post('/notices', [OrgCompanyNoticeController::class, 'store']);
                    Route::put('/notices/{noticeUid}', [OrgCompanyNoticeController::class, 'update']);
                    Route::delete('/notices/{noticeUid}', [OrgCompanyNoticeController::class, 'destroy']);
                    Route::post('/notices/{noticeUid}/pin', [OrgCompanyNoticeController::class, 'pin']);
                    Route::post('/notices/{noticeUid}/unpin', [OrgCompanyNoticeController::class, 'unpin']);
                });
            });

            // ==========================================
            // 🌐 ENLACES DE LA COMPAÑÍA (Link Block)
            // ==========================================
            Route::group([], function () {
                // Ver Enlaces
                Route::middleware('can:view_company_links')->group(function () {
                    Route::get('/links', [OrgCompanyLinkController::class, 'index']);
                    Route::get('/links/{linkUid}', [OrgCompanyLinkController::class, 'show']);
                });

                // Gestionar Enlaces
                Route::middleware('can:manage_company_links')->group(function () {
                    Route::post('/links', [OrgCompanyLinkController::class, 'store']);
                    Route::put('/links/{linkUid}', [OrgCompanyLinkController::class, 'update']);
                    Route::delete('/links/{linkUid}', [OrgCompanyLinkController::class, 'destroy']);
                });
            });

            // 🧩 ÁREAS / DEPARTAMENTOS (Bloque Unificado)
            Route::group([], function () {
                // Ver Áreas
                Route::middleware('can:view_areas')->group(function () {
                    Route::get('/areas', [OrgAreaController::class, 'index']);
                    Route::get('/areas/{areaUid}', [OrgAreaController::class, 'show']);
                    Route::get('/areas/{areaUid}/team', [OrgAreaUserRoleController::class, 'byArea'])->middleware('can:view_users');
                    Route::get('/areas/{areaUid}/notices', [OrgCompanyNoticeController::class, 'indexArea'])->middleware('can:view_notices');
                });

                // Gestionar Áreas
                Route::middleware('can:manage_areas')->group(function () {
                    Route::post('/areas', [OrgAreaController::class, 'store']);
                    Route::put('/areas/{areaUid}', [OrgAreaController::class, 'update']);
                    Route::delete('/areas/{areaUid}', [OrgAreaController::class, 'destroy']);

                    Route::post('/area-assignments', [OrgAreaUserRoleController::class, 'store']);
                    Route::delete('/area-assignments/{id}', [OrgAreaUserRoleController::class, 'destroy']);
                });
            });

            // 💬 Chat (Bloque Unificado y Protegido)
            Route::group([], function () {
                Route::get('/chats', [ChatController::class, 'index']);
                Route::get('/chats/direct/{userId}', [ChatController::class, 'getOrCreateDirect']);
                Route::post('/chats/{conversationId}/messages', [ChatController::class, 'sendMessage']);
                Route::post('/chats/{conversationId}/read', [ChatController::class, 'markAsRead']);
                Route::delete('/chats/{conversationId}/clear', [ChatController::class, 'clearConversation']);
                Route::put('/messages/{messageId}', [ChatController::class, 'updateMessage']);
                Route::delete('/messages/{messageId}', [ChatController::class, 'deleteMessage']);
            });

        });

        // ========================================================================
        // 🔄 RUTAS SECUNDARIAS (Endpoints directos por UID que no llevan el prefijo de la compañía)
        // ========================================================================

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
