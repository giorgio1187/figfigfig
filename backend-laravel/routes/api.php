<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RecipeController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AdminController;

/*
|--------------------------------------------------------------------------
| F.I.G - Rutas API del Sistema Gastronómico
|--------------------------------------------------------------------------
|
| Aquí se definen los endpoints de la API para el frontend SPA de FIG.
| Laravel inyecta automáticamente el prefijo "/api" a todas las rutas.
|
*/

// ============================================================
// 1. MÓDULO 2: AUTENTICACIÓN Y SESIONES
// ============================================================
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/session/{userId}', [AuthController::class, 'getSession']);
    Route::post('/change-password/{userId}', [AuthController::class, 'changePassword']);
});

// ============================================================
// 2. MÓDULO 3: GESTIÓN DE INVENTARIO Y BODEGA (Próximo Módulo)
// ============================================================
Route::prefix('inventory')->group(function () {
    Route::get('/', [InventoryController::class, 'getAll']);
    Route::get('/alerts/low-stock', [InventoryController::class, 'getLowStockAlerts']);
    Route::get('/check/{productId}', [InventoryController::class, 'checkProductAvailability']);
    Route::get('/{id}', [InventoryController::class, 'getById']);
    Route::post('/', [InventoryController::class, 'add']);
    Route::put('/{id}', [InventoryController::class, 'update']);
    Route::patch('/{id}/stock', [InventoryController::class, 'updateStock']);
    Route::patch('/{id}/restock', [InventoryController::class, 'restock']);
    Route::delete('/{id}', [InventoryController::class, 'delete']);
});

// ============================================================
// 3. MÓDULO 4: CATÁLOGO DE MENÚ Y RECETARIO
// ============================================================
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/categories', [ProductController::class, 'categories']);
    Route::get('/{id}', [ProductController::class, 'show']);
});

Route::prefix('recipes')->group(function () {
    Route::get('/', [RecipeController::class, 'index']);
    Route::get('/product/{productId}', [RecipeController::class, 'showByProduct']);
    Route::post('/', [RecipeController::class, 'store']);
    Route::patch('/{id}/ingredients', [RecipeController::class, 'addIngredient']);
    Route::delete('/{id}/ingredients/{ingredientId}', [RecipeController::class, 'removeIngredient']);
});

// ============================================================
// 4. MÓDULO 5: SALÓN Y GESTIÓN DE MESAS
// ============================================================
Route::prefix('tables')->group(function () {
    Route::get('/', [TableController::class, 'getAll']);
    Route::get('/available', [TableController::class, 'getAvailable']);
    Route::post('/merge', [TableController::class, 'mergeTables']);
    Route::post('/unmerge', [TableController::class, 'unmergeTable']);
    Route::get('/group/{sessionId}', [TableController::class, 'getTableGroup']);
    Route::get('/{id}', [TableController::class, 'getById']);
    Route::post('/', [TableController::class, 'store']);
    Route::put('/{id}', [TableController::class, 'update']);
    Route::patch('/{id}/edit', [TableController::class, 'editTable']);
    Route::patch('/{id}/status', [TableController::class, 'updateStatus']);
    Route::delete('/{id}', [TableController::class, 'delete']);
});

// ============================================================
// 5. MÓDULO 6: PEDIDOS Y COCINA (KDS) (Próximo Módulo)
// ============================================================
Route::prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'getAll']);
    Route::get('/kds', [OrderController::class, 'getKDSOrders']);
    Route::get('/pending-payment', [OrderController::class, 'getPendingPayment']);
    Route::get('/revenue/daily', [OrderController::class, 'getDailyRevenue']);
    Route::get('/{id}', [OrderController::class, 'getById']);
    Route::post('/', [OrderController::class, 'create']);
    Route::patch('/{id}/send-kitchen', [OrderController::class, 'sendToKitchen']);
    Route::patch('/{id}/mark-ready', [OrderController::class, 'markAsReady']);
    Route::patch('/{id}/deliver', [OrderController::class, 'deliver']);
    Route::patch('/{id}/pay', [OrderController::class, 'processPayment']);
    Route::patch('/{id}/cancel', [OrderController::class, 'cancel']);

    // Nuevos endpoints PUT solicitados
    Route::put('/{id}/prepare', [OrderController::class, 'prepare']);
    Route::put('/{id}/ready', [OrderController::class, 'markAsReadyPut']);
    Route::put('/{id}/pay', [OrderController::class, 'pay']);
});

Route::get('/reports/daily', [OrderController::class, 'dailyReport']);
Route::get('/reports/weekly', [OrderController::class, 'weeklyReport']);

// ============================================================
// 6. MÓDULO 6 (EXT): ADMINISTRACIÓN GENERAL Y ESTADÍSTICAS
// ============================================================
Route::prefix('admin')->group(function () {
    Route::get('/users', [AdminController::class, 'getAllUsers']);
    Route::get('/users/{id}', [AdminController::class, 'getUserById']);
    Route::post('/users', [AdminController::class, 'addUser']);
    Route::put('/users/{id}', [AdminController::class, 'editUser']);
    Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);
    Route::patch('/users/{id}/toggle-status', [AdminController::class, 'toggleUserStatus']);
    Route::get('/stats', [AdminController::class, 'getStats']);
});
