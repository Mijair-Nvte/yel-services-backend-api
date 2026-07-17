<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\LoginController as AuthLoginController;
use App\Http\Controllers\Api\Auth\LogoutController as AuthLogoutController;
use App\Http\Controllers\Api\Auth\MeController as AuthMeController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Auth\VerifyOtpController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FolderController;
use App\Http\Controllers\Api\LoanApplication\LoanApplicationController;
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
use App\Http\Controllers\Api\OrgInsuranceApplicationController;
use App\Http\Controllers\Api\OrgPaymentLinkMappingController;
use App\Http\Controllers\Api\OrgPositionController;
use App\Http\Controllers\Api\OrgServiceController;
use App\Http\Controllers\Api\Partner\PartnerDashboardController;
use App\Http\Controllers\Api\Partner\PartnerSaleController;
use App\Http\Controllers\Api\SalesController;
use App\Http\Controllers\Api\Store\PublicOrgServiceController;
use App\Http\Controllers\Api\Store\StripeCheckoutController;
use App\Http\Controllers\Api\Yelpro\OrgEventYelProController;
use App\Http\Controllers\Api\Yelpro\YelproFolderController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // 🔗 Webhooks
    Route::post('/webhooks/ghl', [WebhookController::class, 'handleGHL']);
    Route::post('/webhooks/ghl/service-form', [WebhookController::class, 'handleServiceForm']);

    // 🔓 Rutas Públicas
    Route::post('/register', RegisterController::class);
    Route::post('/login', AuthLoginController::class);
    Route::post('/login/verify', VerifyOtpController::class);
    Route::post('/auth/request-verification', App\Http\Controllers\Api\Auth\RequestVerificationController::class);

    Route::post('/forgot-password', ForgotPasswordController::class);
    Route::post('/reset-password', ResetPasswordController::class);

    // ruta de la tienda donde se mostraran los servicios
    Route::get('/public/org-companies/{uid}/services', [PublicOrgServiceController::class, 'index']);
    Route::get('/public/org-companies/{uid}/validate-referral/{code}', [PublicOrgServiceController::class, 'validateReferral']);

    // ruta para iniciar sesion de pago
    // routes/api.php
    Route::post('/public/org-companies/{uid}/checkout/create-session', [StripeCheckoutController::class, 'createSession']);

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

                // 1. Buscamos la compañía para saber quién es el dueño
                $company = \App\Models\OrgCompany::where('uid', $uid)->first();

                // 2. Verificamos si el usuario actual es el dueño
                $isOwner = $company && $company->owner_id === $user->id;

                // Gracias a tu SetTenantContext, Spatie ya está filtrando por esta empresa
                return response()->json([
                    'is_owner' => $isOwner, // ✅ Pasamos esta nueva bandera al frontend
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
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

            // ==========================================
            // 📦 SERVICIOS / STRIPE PRODUCTS
            // ==========================================
            Route::group([], function () {
                // Ver Servicios
                Route::get('/services', [OrgServiceController::class, 'index'])->middleware('can:view_services');

                // Gestionar Servicios
                Route::middleware('can:manage_services')->group(function () {
                    Route::post('/services', [OrgServiceController::class, 'store']);
                    Route::put('/services/{serviceUid}', [OrgServiceController::class, 'update']);
                    Route::delete('/services/{serviceUid}', [OrgServiceController::class, 'destroy']);
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
                Route::get('/chats/group', [ChatController::class, 'getOrCreateGroup']);
                Route::get('/chats/direct/{userId}', [ChatController::class, 'getOrCreateDirect']);
                Route::post('/chats/{conversationId}/messages', [ChatController::class, 'sendMessage']);
                Route::post('/chats/{conversationId}/read', [ChatController::class, 'markAsRead']);
                Route::delete('/chats/{conversationId}/clear', [ChatController::class, 'clearConversation']);
                Route::put('/messages/{messageId}', [ChatController::class, 'updateMessage']);
                Route::delete('/messages/{messageId}', [ChatController::class, 'deleteMessage']);
            });

            // ==========================================
            // 📁 CARPETAS Y DOCUMENTOS (NUEVO BLOQUE)
            // ==========================================
            Route::group([], function () {
                Route::middleware('can:view_documents')->group(function () {
                    Route::get('/folders', [FolderController::class, 'index']);
                    Route::get('/folders/{folder}/children', [FolderController::class, 'children']);
                    Route::get('/folders/{folderUid}/documents', [DocumentController::class, 'byFolder']);
                    Route::get('/documents/{documentUid}', [DocumentController::class, 'show']);
                });

                Route::middleware('can:manage_documents')->group(function () {
                    Route::post('/folders', [FolderController::class, 'store']);
                    Route::put('/folders/{folder}', [FolderController::class, 'update']);
                    Route::delete('/folders/{folder}', [FolderController::class, 'destroy']);
                    Route::post('/folders/{folderUid}/compartir', [FolderController::class, 'compartir']);

                    Route::post('/documents', [DocumentController::class, 'store']);
                    Route::delete('/documents/{documentUid}', [DocumentController::class, 'destroy']);
                    Route::post('/documents/presign', [DocumentController::class, 'presign']);
                    Route::post('/documents/confirm', [DocumentController::class, 'confirm']);
                });
            });

            // ==========================================
            // ⏱️ TIME TRACKING
            // ==========================================
            Route::group([], function () {
                // Estas rutas son de uso personal para cada usuario, no requieren un permiso de "manage" o "view" global
                Route::get('/time-tracking/status', [\App\Http\Controllers\Api\OrgTimeTrackingController::class, 'currentStatus']);
                Route::post('/time-tracking/check-in', [\App\Http\Controllers\Api\OrgTimeTrackingController::class, 'checkIn']);
                Route::post('/time-tracking/check-out', [\App\Http\Controllers\Api\OrgTimeTrackingController::class, 'checkOut']);

                Route::get('/time-tracking', [\App\Http\Controllers\Api\OrgTimeTrackingController::class, 'index'])->middleware('can:view_time_tracking');
            });

            // ==========================================
            // 🛡️ SEGUROS / INSURANCE (Admin Module)
            // ==========================================
            Route::group([], function () {
                // Ver Solicitudes
                Route::middleware('can:view_insurance')->group(function () {
                    Route::get('/insurance-applications', [OrgInsuranceApplicationController::class, 'index']);
                    Route::get('/insurance-applications/{applicationUid}', [OrgInsuranceApplicationController::class, 'show']);
                });

                // Gestionar Solicitudes
                Route::middleware('can:manage_insurance')->group(function () {
                    Route::put('/insurance-applications/{applicationUid}', [OrgInsuranceApplicationController::class, 'update']);
                    Route::delete('/insurance-applications/{applicationUid}', [OrgInsuranceApplicationController::class, 'destroy']);
                });
            });

            // ==========================================
            // 🏠 INVESTOR READY (ADMIN MODULE)
            // ==========================================
            Route::group([], function () {
                // Configuración de Tiers/Niveles (Start, Plus, Elite)
                Route::get('/investor-tiers', [\App\Http\Controllers\Api\OrgInvestorTierController::class, 'index']);

                Route::middleware('can:manage_investors')->group(function () {
                    Route::post('/investor-tiers', [\App\Http\Controllers\Api\OrgInvestorTierController::class, 'store']);
                    Route::put('/investor-tiers/{tierUid}', [\App\Http\Controllers\Api\OrgInvestorTierController::class, 'update']);
                    Route::delete('/investor-tiers/{tierUid}', [\App\Http\Controllers\Api\OrgInvestorTierController::class, 'destroy']);
                });

                // Gestión de Propiedades de los Inversionistas
                Route::get('/properties', [\App\Http\Controllers\Api\OrgPropertyController::class, 'index']);

                Route::middleware('can:manage_investors')->group(function () {
                    Route::post('/properties', [\App\Http\Controllers\Api\OrgPropertyController::class, 'store']);
                    Route::put('/properties/{propertyUid}', [\App\Http\Controllers\Api\OrgPropertyController::class, 'update']);
                    Route::delete('/properties/{propertyUid}', [\App\Http\Controllers\Api\OrgPropertyController::class, 'destroy']);
                });
            });

            // ==========================================
            // 📝 PRÉSTAMOS / LOAN APPLICATIONS (User Flow)
            // ==========================================
            Route::group([], function () {
                // Obtener o inicializar la solicitud del usuario logueado
                Route::get('/loans/my-application', [LoanApplicationController::class, 'myApplication']);

                // Guardar progreso de una sección específica (requiere el UID de la solicitud)
                Route::post('/loans/{loanUid}/sections', [LoanApplicationController::class, 'saveSection']);
            });

            // ==========================================
            // 🤝 ADMINISTRACIÓN DE PARTNERS (Admin Module)
            // ==========================================
            Route::group([], function () {
                // Listar todas las solicitudes (puedes filtrar por ?status=pending)
                Route::get('/partners', [\App\Http\Controllers\Api\OrgPartnerAdminController::class, 'index']);

                // Ver detalle de una solicitud específica
                Route::get('/partners/{partnerId}', [\App\Http\Controllers\Api\OrgPartnerAdminController::class, 'show']);

                // Aprobar o rechazar
                Route::post('/partners/{partnerId}/approve', [\App\Http\Controllers\Api\OrgPartnerAdminController::class, 'approve']);
                Route::post('/partners/{partnerId}/reject', [\App\Http\Controllers\Api\OrgPartnerAdminController::class, 'reject']);
            });

            // 🤝 PARTNERS (Opt-in Program) - NUEVA RUTA

            Route::get('/partner-program/status', [\App\Http\Controllers\Api\Partner\PartnerOptInController::class, 'status']);
            Route::post('/partner-program/join', [\App\Http\Controllers\Api\Partner\PartnerOptInController::class, 'join']);
            Route::post('partner/sales/export-pdf', [PartnerSaleController::class, 'exportPdf']);

            // ==========================================
            // 🛠️ ÓRDENES DE SERVICIO (OPERACIONES / KANBAN)
            // ==========================================
            Route::group([], function () {
                // Ver y listar órdenes
                Route::get('/service-orders', [\App\Http\Controllers\Api\OrgServiceOrderController::class, 'index']);
                Route::get('/service-orders/{orderUid}', [\App\Http\Controllers\Api\OrgServiceOrderController::class, 'show']);

                // Gestionar (Actualizar pipeline, asignar equipo, etc.)
                Route::put('/service-orders/{orderUid}', [\App\Http\Controllers\Api\OrgServiceOrderController::class, 'update']);
                Route::delete('/service-orders/{orderUid}', [\App\Http\Controllers\Api\OrgServiceOrderController::class, 'destroy']);
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

    });

    // Rutas de Afiliados
    Route::prefix('partner')->group(function () {
        // Registro Público
        Route::post('/register', App\Http\Controllers\Api\Partner\AffiliateRegisterController::class);

        Route::post('/register/verify', App\Http\Controllers\Api\Partner\VerifyRegistrationController::class);

        // Rutas Protegidas
        Route::middleware('auth:sanctum')->group(function () {

            Route::get('/sales', [PartnerSaleController::class, 'index']);

            Route::get('/me', App\Http\Controllers\Api\Partner\AffiliateMeController::class);

            // ==========================================
            // 🏢 MÓDULO INVESTOR READY (Portal del Partner) cambios
            // ==========================================
            Route::prefix('investor-ready')->group(function () {

                // Propiedades del Partner
                Route::get('/properties', [\App\Http\Controllers\Api\Partner\InvestorReady\PropertyController::class, 'index']);
                Route::get('/properties/{propertyUid}', [\App\Http\Controllers\Api\Partner\InvestorReady\PropertyController::class, 'show']);

                // Niveles y Beneficios
                Route::get('/my-tier', [\App\Http\Controllers\Api\Partner\InvestorReady\InvestorTierController::class, 'current']);
                Route::get('/tiers', [\App\Http\Controllers\Api\Partner\InvestorReady\InvestorTierController::class, 'index']);

            });

            // ==========================================
            // 📁 MIS ARCHIVOS (Carpetas y Documentos Globales del Usuario)
            // ==========================================
            // Requerimos el UID de la compañía en la URL para saber a qué entorno asociar los registros,
            // pero NO validamos permisos de Spatie.
            Route::prefix('companies/{companyUid}')->group(function () {

                // 📂 Gestión de Carpetas
                Route::get('/folders', [\App\Http\Controllers\Api\Partner\PartnerFolderController::class, 'index']);
                Route::post('/folders', [\App\Http\Controllers\Api\Partner\PartnerFolderController::class, 'store']);
                Route::get('/folders/{folderUid}', [\App\Http\Controllers\Api\Partner\PartnerFolderController::class, 'show']);
                Route::put('/folders/{folderUid}', [\App\Http\Controllers\Api\Partner\PartnerFolderController::class, 'update']);
                Route::delete('/folders/{folderUid}', [\App\Http\Controllers\Api\Partner\PartnerFolderController::class, 'destroy']);

                // 📄 Gestión de Documentos
                Route::post('/documents/presign', [\App\Http\Controllers\Api\Partner\PartnerDocumentController::class, 'presign']);
                Route::post('/documents/confirm', [\App\Http\Controllers\Api\Partner\PartnerDocumentController::class, 'confirm']);
                Route::delete('/documents/{documentUid}', [\App\Http\Controllers\Api\Partner\PartnerDocumentController::class, 'destroy']);

                Route::post('/documents/{documentUid}/send-email', [\App\Http\Controllers\Api\Partner\PartnerDocumentController::class, 'sendViaEmail'])
                    ->middleware('throttle:5,1');

                // 👁️ Visualizar/Descargar (Firma URL de lectura temporal de R2)
                Route::get('/documents/{documentUid}/view', [\App\Http\Controllers\Api\Partner\PartnerDocumentController::class, 'view']);
                Route::get('/documents/{documentUid}/download', [\App\Http\Controllers\Api\Partner\PartnerDocumentController::class, 'download']);

                // ==========================================
                // 📅 CALENDARIO (Portal del Partner - Solo Lectura)
                // ==========================================
                Route::prefix('events')->group(function () {
                    // 📋 Listar eventos en un rango de fechas
                    Route::get('/', [\App\Http\Controllers\Api\Partner\Calendar\CalendarController::class, 'index']);

                    // 👁️ Ver detalle de un evento específico
                    Route::get('/{eventUid}', [\App\Http\Controllers\Api\Partner\Calendar\CalendarController::class, 'show']);
                });

                // ==========================================
                // 🏦 DATOS BANCARIOS (Portal del Partner)
                // ==========================================
                Route::prefix('bank-accounts')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Api\Partner\PartnerBankAccountController::class, 'index']);
                    Route::post('/', [\App\Http\Controllers\Api\Partner\PartnerBankAccountController::class, 'store']);
                    Route::get('/{accountUid}', [\App\Http\Controllers\Api\Partner\PartnerBankAccountController::class, 'show']);
                    Route::put('/{accountUid}', [\App\Http\Controllers\Api\Partner\PartnerBankAccountController::class, 'update']);
                    Route::delete('/{accountUid}', [\App\Http\Controllers\Api\Partner\PartnerBankAccountController::class, 'destroy']);
                });

                // ==========================================
                // 🛡️ YEL INSURANCE (Portal del Cliente/Partner)
                // ==========================================
                Route::prefix('insurance-applications')->group(function () {
                    // 📋 Listar solicitudes (GET: /api/v1/partner/companies/{companyUid}/insurance-applications)
                    Route::get('/', [\App\Http\Controllers\Api\Partner\Insurance\InsuranceApplicationController::class, 'index']);

                    // ➕ Crear nueva solicitud (POST: /api/v1/partner/companies/{companyUid}/insurance-applications)
                    Route::post('/', [\App\Http\Controllers\Api\Partner\Insurance\InsuranceApplicationController::class, 'store']);

                    // 👁️ Ver detalle específico (GET: /api/v1/partner/companies/{companyUid}/insurance-applications/{applicationUid})
                    Route::get('/{applicationUid}', [\App\Http\Controllers\Api\Partner\Insurance\InsuranceApplicationController::class, 'show']);
                });

                // 👇 Ruta para el catálogo de servicios del Partner
                Route::get('/partner-services', [\App\Http\Controllers\Api\Partner\PartnerServiceController::class, 'index']);

                Route::post('/partner-services/{serviceUid}/send-email', [\App\Http\Controllers\Api\Partner\PartnerServiceController::class, 'sendViaEmail'])
                    ->middleware('throttle:5,1');

                Route::prefix('loan-applications')->group(function () {
                    // 📋 Listar solicitudes
                    Route::get('/', [\App\Http\Controllers\Api\Partner\Loan\LoanApplicationController::class, 'index']);

                    // ➕ Crear nueva solicitud
                    Route::post('/', [\App\Http\Controllers\Api\Partner\Loan\LoanApplicationController::class, 'store']);

                    // 👁️ Ver detalle específico
                    Route::get('/{applicationUid}', [\App\Http\Controllers\Api\Partner\Loan\LoanApplicationController::class, 'show']);
                });

                // Dashboard Stats & Gráficas de uso exclusivo del Yel Pro
                Route::get('/dashboard/stats', [PartnerDashboardController::class, 'index']);
            });

        });
    });

    // rutas para yelpro

    Route::middleware('auth:sanctum')->group(function () {

        Route::prefix('yelpro')->group(function () {

            // Todas las rutas de YelPro requerirán el UID de la compañía
            Route::prefix('companies/{companyUid}')->group(function () {

                // 📂 Listar carpetas compartidas con YelPro
                // GET: /api/v1/yelpro/companies/{companyUid}/shared-folders
                Route::get('/shared-folders', [YelproFolderController::class, 'index']);

                Route::get('/events', [OrgEventYelProController::class, 'index']);
                Route::get('/events/{eventUid}', [OrgEventYelProController::class, 'show']);

                Route::post('/events/{eventUid}/attendance', [OrgEventYelProController::class, 'toggleAttendance']);

            });

        });

    });

});
