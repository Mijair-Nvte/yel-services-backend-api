<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\Auth\LoginController as AuthLoginController;
use App\Http\Controllers\Api\Auth\LogoutController as AuthLogoutController;
use App\Http\Controllers\Api\Auth\MeController as AuthMeController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FolderController;
use App\Http\Controllers\Api\NoticeLevelController;
use App\Http\Controllers\Api\OrgAreaController;
use App\Http\Controllers\Api\OrgAreaUserRoleController;
use App\Http\Controllers\Api\OrgCompanyController;
use App\Http\Controllers\Api\OrgCompanyInvitationController;
use App\Http\Controllers\Api\OrgCompanyLinkController;
use App\Http\Controllers\Api\OrgCompanyNoticeController;
use App\Http\Controllers\Api\OrgCompanyUserController;
use App\Http\Controllers\Api\OrgEventController;
use App\Http\Controllers\Api\OrgPositionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // 🔓 públicas
    Route::post('/register', RegisterController::class);
    Route::post('/login', AuthLoginController::class);

    Route::get('/org-invitations/{token}', [OrgCompanyInvitationController::class, 'show']);
    Route::post('/org-invitations/{token}/accept', [OrgCompanyInvitationController::class, 'accept']);

    // 📄 Documentos (públicos)
    Route::get('/documents/{uid}/view', [DocumentController::class, 'view']);
    Route::get('/documents/{uid}/download', [DocumentController::class, 'download']);

    // 🔒 protegidas
    Route::middleware('auth:sanctum')->group(function () {

        // ✉️ Invitaciones
        Route::post(
            '/org-companies/{uid}/invitations',
            [OrgCompanyInvitationController::class, 'store']
        );

        Route::get('/me', AuthMeController::class);
        Route::post('/logout', AuthLogoutController::class);

        // 👤 Account
        Route::get('/account', [AccountController::class, 'show']);
        Route::put('/account', [AccountController::class, 'update']);
        Route::post('/account/avatar', [AccountController::class, 'uploadAvatar']);

        // 🏢 Workspaces
        Route::get('/org-companies', [OrgCompanyController::class, 'index']);
        Route::post('/org-companies', [OrgCompanyController::class, 'store']);

        Route::get('/org-companies/{uid}', [OrgCompanyController::class, 'show']);
        Route::put('/org-companies/{uid}', [OrgCompanyController::class, 'update']);
        Route::delete('/org-companies/{uid}', [OrgCompanyController::class, 'destroy']);

        // 🧩 Áreas (siempre dentro de workspace)
        Route::get('/org-companies/{uid}/areas', [OrgAreaController::class, 'index']);
        Route::post('/org-companies/{uid}/areas', [OrgAreaController::class, 'store']);

        Route::get('/org-areas/{uid}', [OrgAreaController::class, 'show']);

        Route::put('/org-areas/{uid}', [OrgAreaController::class, 'update']);
        Route::delete('/org-areas/{uid}', [OrgAreaController::class, 'destroy']);

        Route::get(
            '/org-areas/{uid}/team',
            [OrgAreaUserRoleController::class, 'byArea']
        );

        // 🎭 Roles (globales por ahora)
        Route::get(
            '/org-companies/{uid}/positions',
            [OrgPositionController::class, 'index']
        );

        Route::post(
            '/org-companies/{uid}/positions',
            [OrgPositionController::class, 'store']
        );

        Route::delete(
            '/org-companies/{uid}/positions/{id}',
            [OrgPositionController::class, 'destroy']
        );

        // 📅 Eventos calendario
        Route::get('/org-companies/{uid}/events', [OrgEventController::class, 'index']);
        Route::post('/org-companies/{uid}/events', [OrgEventController::class, 'store']);
        Route::get('/org-companies/{uid}/events/{eventUid}', [OrgEventController::class, 'show']);
        Route::put('/org-companies/{uid}/events/{eventUid}', [OrgEventController::class, 'update']);
        Route::delete('/org-companies/{uid}/events/{eventUid}', [OrgEventController::class, 'destroy']);

        // 👤 Asignaciones
        Route::get('/org-area-user-roles', [OrgAreaUserRoleController::class, 'index']);
        Route::post('/org-area-user-roles', [OrgAreaUserRoleController::class, 'store']);

        Route::get('/org-area-user-roles/{id}', [OrgAreaUserRoleController::class, 'show']);
        Route::put('/org-area-user-roles/{id}', [OrgAreaUserRoleController::class, 'update']);
        Route::delete('/org-area-user-roles/{id}', [OrgAreaUserRoleController::class, 'destroy']);

        // 👥 Equipo / Staff
        Route::get('/org-companies/{uid}/team', [OrgCompanyUserController::class, 'index']);

        Route::post(
            '/org-companies/{uid}/team',
            [OrgCompanyUserController::class, 'store']
        );

        Route::get(
            '/org-companies/{uid}/team/{id}',
            [OrgCompanyUserController::class, 'show']
        );

        Route::put(
            '/org-companies/{uid}/team/{id}',
            [OrgCompanyUserController::class, 'update']
        );

        Route::delete(
            '/org-companies/{uid}/team/{id}',
            [OrgCompanyUserController::class, 'destroy']
        );

        // Avisos globales por compañía

        Route::post(
            '/org-companies/{uid}/notices',
            [OrgCompanyNoticeController::class, 'store']
        );

        Route::get(
            '/org-company-notices/{uid}',
            [OrgCompanyNoticeController::class, 'show']
        );

        Route::put(
            '/org-company-notices/{uid}',
            [OrgCompanyNoticeController::class, 'update']
        );

        Route::delete(
            '/org-company-notices/{uid}',
            [OrgCompanyNoticeController::class, 'destroy']
        );

        Route::post(
            '/org-company-notices/{uid}/pin',
            [OrgCompanyNoticeController::class, 'pin']
        );

        Route::post(
            '/org-company-notices/{uid}/unpin',
            [OrgCompanyNoticeController::class, 'unpin']
        );

        // Links globales por compañía
        Route::get(
            '/org-companies/{uid}/links',
            [OrgCompanyLinkController::class, 'index']
        );

        Route::post(
            '/org-companies/{uid}/links',
            [OrgCompanyLinkController::class, 'store']
        );

        Route::get(
            '/org-company-links/{uid}',
            [OrgCompanyLinkController::class, 'show']
        );

        Route::put(
            '/org-company-links/{uid}',
            [OrgCompanyLinkController::class, 'update']
        );

        Route::delete(
            '/org-company-links/{uid}',
            [OrgCompanyLinkController::class, 'destroy']
        );

        Route::get('/notice-levels', [NoticeLevelController::class, 'index']);

        Route::get('/org-companies/{uid}/notices', [OrgCompanyNoticeController::class, 'index']);

        Route::get(
            '/org-companies/{uid}/areas/{areaUid}/notices',
            [OrgCompanyNoticeController::class, 'indexArea']
        );

        // 📂 Carpetas
        Route::get('/folders', [FolderController::class, 'index']);          // 👈 ROOTS
        Route::get('/folders/{folder}/children', [FolderController::class, 'children']);
        Route::post('/folders', [FolderController::class, 'store']);
        Route::delete('/folders/{folder}', [FolderController::class, 'destroy']);
        Route::put('/folders/{folder}', [FolderController::class, 'update']);

        // 📄 Documentos
        Route::get('/folders/{folderUid}/documents', [DocumentController::class, 'byFolder']);
        Route::post('/documents', [DocumentController::class, 'store']);
        Route::get('/documents/{uid}', [DocumentController::class, 'show']);

        Route::delete('/documents/{uid}', [DocumentController::class, 'destroy']);

        Route::post('/documents/presign', [DocumentController::class, 'presign']);
        Route::post('/documents/confirm', [DocumentController::class, 'confirm']);

    });
});
