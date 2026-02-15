<?php

use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\AdvancementRuleController;
use App\Http\Controllers\Admin\RoundController;
use App\Http\Controllers\Admin\RoundListController;
use App\Http\Controllers\Admin\RoundTemplateController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\TournamentController;
use App\Http\Controllers\Admin\UserApprovalController;
use App\Http\Controllers\ControlController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicDisplayController;
use App\Http\Controllers\TimetableController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/display', [PublicDisplayController::class, 'index'])->name('display.index');
Route::get('/display/rounds/{round}/state', [PublicDisplayController::class, 'state'])->name('display.round.state');
Route::get('/timetable', [TimetableController::class, 'index'])->name('timetable.index');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware(['approved_admin'])->group(function () {
        Route::get('/control', [ControlController::class, 'index'])->name('control.index');
        Route::post('/control/rounds/{round}/action', [ControlController::class, 'action'])->name('control.round.action');

        Route::prefix('/admin')->name('admin.')->group(function () {
            Route::get('/rounds', [RoundListController::class, 'index'])->name('rounds.index');
            Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
            Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
            Route::patch('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
            Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');

            Route::get('/tournaments', [TournamentController::class, 'index'])->name('tournaments.index');
            Route::post('/tournaments', [TournamentController::class, 'store'])->name('tournaments.store');
            Route::post('/tournaments/clone-rules', [TournamentController::class, 'cloneRules'])->name('tournaments.clone-rules');
            Route::get('/tournaments/{tournament}', [TournamentController::class, 'show'])->name('tournaments.show');
            Route::patch('/tournaments/{tournament}', [TournamentController::class, 'update'])->name('tournaments.update');
            Route::post('/tournaments/{tournament}/teams', [TournamentController::class, 'addTeam'])->name('tournaments.teams.add');
            Route::delete('/tournaments/{tournament}/teams/{team}', [TournamentController::class, 'removeTeam'])->name('tournaments.teams.remove');

            Route::post('/tournaments/{tournament}/round-templates', [RoundTemplateController::class, 'store'])->name('round-templates.store');
            Route::patch('/round-templates/{roundTemplate}', [RoundTemplateController::class, 'update'])->name('round-templates.update');
            Route::delete('/round-templates/{roundTemplate}', [RoundTemplateController::class, 'destroy'])->name('round-templates.destroy');

            Route::post('/tournaments/{tournament}/groups', [GroupController::class, 'store'])->name('groups.store');
            Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');

            Route::post('/tournaments/{tournament}/rounds', [RoundController::class, 'store'])->name('rounds.store');
            Route::patch('/rounds/{round}', [RoundController::class, 'update'])->name('rounds.update');
            Route::delete('/rounds/{round}', [RoundController::class, 'destroy'])->name('rounds.destroy');
            Route::post('/rounds/{round}/overwrite-result', [RoundController::class, 'overwriteResult'])->name('rounds.overwrite-result');
            Route::post('/rounds/{round}/group', [RoundController::class, 'updateGroup'])->name('rounds.group.update');
            Route::post('/rounds/{round}/participants', [RoundController::class, 'updateParticipants'])->name('rounds.participants.update');

            Route::post('/tournaments/{tournament}/advancement-rules', [AdvancementRuleController::class, 'store'])->name('advancement-rules.store');
            Route::patch('/advancement-rules/{advancementRule}', [AdvancementRuleController::class, 'update'])->name('advancement-rules.update');
            Route::delete('/advancement-rules/{advancementRule}', [AdvancementRuleController::class, 'destroy'])->name('advancement-rules.destroy');
        });
    });

    Route::middleware(['role:super_admin'])->group(function () {
        Route::get('/admin/user-approvals', [UserApprovalController::class, 'index'])->name('admin.user-approvals.index');
        Route::patch('/admin/user-approvals/{user}', [UserApprovalController::class, 'update'])->name('admin.user-approvals.update');
    });
});

require __DIR__.'/auth.php';
