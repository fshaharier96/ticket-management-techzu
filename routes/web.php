<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TicketAssignmentController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketReplyController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Tickets
    Route::resource('tickets', TicketController::class)->except('destroy');
    Route::post('tickets/{ticket}/resolve', [TicketController::class, 'resolve'])->name('tickets.resolve');
    Route::post('tickets/{ticket}/close', [TicketController::class, 'close'])->name('tickets.close');

    // Assignment
    Route::post('tickets/{ticket}/accept', [TicketAssignmentController::class, 'accept'])->name('tickets.accept');
    Route::post('tickets/{ticket}/assign', [TicketAssignmentController::class, 'assign'])->name('tickets.assign');
    Route::post('tickets/{ticket}/auto-assign', [TicketAssignmentController::class, 'auto'])->name('tickets.auto-assign');

    // Conversation + attachments
    Route::post('tickets/{ticket}/replies', [TicketReplyController::class, 'store'])->name('tickets.replies.store');
    Route::get('attachments/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');

    // CSV import (admin only; the middleware is a second layer on top of controller checks)
    Route::middleware('role:admin')->prefix('imports')->name('imports.')->group(function () {
        Route::get('/', [ImportController::class, 'index'])->name('index');
        Route::get('create', [ImportController::class, 'create'])->name('create');
        Route::post('/', [ImportController::class, 'store'])->name('store');
        Route::get('{batch}', [ImportController::class, 'show'])->name('show');
        Route::get('{batch}/errors', [ImportController::class, 'errors'])->name('errors');
    });

    // Breeze profile routes (keep as generated)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';