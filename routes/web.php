<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/rooms', [DashboardController::class, 'rooms'])->name('dashboard.rooms');
Route::get('/floor-plan', [DashboardController::class, 'floorPlan'])->name('floor-plan');

Route::view('/rooms', 'pages.rooms')->name('rooms');
Route::view('/reservations', 'pages.reservations')->name('reservations');
Route::view('/schedule', 'pages.schedule')->name('schedule');
Route::view('/availability', 'pages.availability')->name('availability');
Route::view('/equipment', 'pages.equipment')->name('equipment');
Route::view('/equipment-requests', 'pages.equipment-requests')->name('equipment-requests');
Route::view('/reports', 'pages.reports')->name('reports');
Route::view('/activity-logs', 'pages.activity-logs')->name('activity-logs');
Route::view('/settings', 'pages.settings')->name('settings');
Route::view('/help', 'pages.help')->name('help');
