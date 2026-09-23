<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\TestController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test', [TestController::class, 'test']);


Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('products.index');
    Route::post('/', [ProductController::class, 'store'])->name('products.store');
    Route::get('/{id}', [ProductController::class, 'show'])->name('products.show')->whereNumber('id');
    Route::put('/{id}', [ProductController::class, 'update'])->name('products.update')->whereNumber('id');
    Route::delete('/{id}', [ProductController::class, 'destroy'])->name('products.destroy')->whereNumber('id');
});
