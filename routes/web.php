<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('expenses', 'Expenses')->name('expenses');
    Route::inertia('income', 'Income')->name('income');
    Route::inertia('budgets', 'Budgets')->name('budgets');
});

require __DIR__.'/settings.php';
