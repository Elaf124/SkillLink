<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\Identity\ProviderProfileController;
use App\Http\Controllers\Api\Identity\CustomerProfileController;
use App\Http\Controllers\Api\Identity\PayoutMethodController;
use App\Http\Controllers\Api\Identity\ProviderVerificationController;
use App\Http\Controllers\Api\Admin\ProviderVerificationController as AdminProviderVerificationController;
use App\Http\Controllers\Api\ServiceProvider\CategoryController;
use App\Http\Controllers\Api\ServiceProvider\SkillController;
use App\Http\Controllers\Api\ServiceProvider\ServiceController;
use App\Http\Controllers\Api\Marketplace\JobController;
use App\Http\Controllers\Api\Marketplace\OfferController;
use App\Http\Controllers\Api\Marketplace\BookingController;
use App\Http\Controllers\Api\Marketplace\FavoriteController;
use App\Http\Controllers\Api\Marketplace\ConversationController;
use App\Http\Controllers\Api\Marketplace\MilestoneController;
use App\Http\Controllers\Api\Marketplace\TimeLogController;
use App\Http\Controllers\Api\Finance\PaymentController;
use App\Http\Controllers\Api\Finance\WalletController;
use App\Http\Controllers\Api\Trust\ReviewController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SupportContactController;
use App\Http\Controllers\Api\Admin\FinanceDashboardController;
use App\Http\Controllers\Api\Admin\SupportDashboardController;

Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::get('/user', [UserController::class, 'show']);
    Route::patch('/user', [UserController::class, 'update']);
    Route::delete('/user', [UserController::class, 'destroy']);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Password reset (public, 6-digit code)
Route::post('/forgot-password', [PasswordResetController::class, 'send'])->middleware('throttle:10,1');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Email verification (6-digit code) — token is issued at registration
    Route::post('/email/verify', [EmailVerificationController::class, 'verify'])->middleware('throttle:10,1');
    Route::post('/email/verification-code', [EmailVerificationController::class, 'send'])->middleware('throttle:10,1');
});
Route::middleware(['auth:sanctum', 'verified.email', 'role:provider'])->group(function () {
    Route::post('/provider/onboarding', [ProviderProfileController::class, 'onboard']);
    Route::get('/provider/profile', [ProviderProfileController::class, 'show']);
    Route::put('/provider/profile', [ProviderProfileController::class, 'update']);
    Route::post('/provider/profile/portfolio', [ProviderProfileController::class, 'storePortfolio']);
    Route::delete('/provider/profile/portfolio/{id}', [ProviderProfileController::class, 'destroyPortfolio']);

    // Payout methods
    Route::get('/provider/payout-methods', [PayoutMethodController::class, 'index']);
    Route::post('/provider/payout-methods', [PayoutMethodController::class, 'store']);
    Route::put('/provider/payout-methods/{id}', [PayoutMethodController::class, 'update']);
    Route::post('/provider/payout-methods/{id}/default', [PayoutMethodController::class, 'setDefault']);
    Route::delete('/provider/payout-methods/{id}', [PayoutMethodController::class, 'destroy']);

    // Verification documents (provider side)
    Route::get('/provider/verification', [ProviderVerificationController::class, 'index']);
    Route::post('/provider/verification', [ProviderVerificationController::class, 'store']);
    Route::delete('/provider/verification/{id}', [ProviderVerificationController::class, 'destroy']);

    // Wallet + withdrawals
    Route::get('/provider/wallet', [WalletController::class, 'show']);
    Route::get('/provider/payouts', [WalletController::class, 'payouts']);
    Route::post('/provider/payouts', [WalletController::class, 'withdraw']);
});
Route::middleware(['auth:sanctum', 'verified.email', 'role:customer'])->group(function () {
    Route::get('/customer/profile', [CustomerProfileController::class, 'show']);
    Route::put('/customer/profile', [CustomerProfileController::class, 'update']);
});
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);
Route::get('/skills', [SkillController::class, 'index']);
// Public route — no auth needed, anyone can browse verified providers
Route::get('/providers', [ProviderProfileController::class, 'indexPublic']);
Route::get('/providers/{id}', [ProviderProfileController::class, 'showPublic']);
// Public
Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{id}', [ServiceController::class, 'show']);
//provider-only
Route::middleware(['auth:sanctum', 'verified.email', 'role:provider'])->group(function () {
    Route::post('/services', [ServiceController::class, 'store']);
    Route::put('/services/{id}', [ServiceController::class, 'update']);
    Route::delete('/services/{id}', [ServiceController::class, 'destroy']);
});
// Public/provider browsing
Route::get('/jobs', [JobController::class, 'index']);
Route::get('/jobs/{id}', [JobController::class, 'show']);

// Customer-only
Route::middleware(['auth:sanctum', 'verified.email', 'role:customer'])->group(function () {
    Route::post('/jobs', [JobController::class, 'store']);
    Route::get('/my-jobs', [JobController::class, 'myJobs']);
    Route::put('/jobs/{id}', [JobController::class, 'update']);
    Route::delete('/jobs/{id}', [JobController::class, 'destroy']);
});
Route::middleware(['auth:sanctum', 'verified.email', 'role:provider'])->group(function () {
    Route::post('/jobs/{jobId}/offers', [OfferController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'verified.email', 'role:customer'])->group(function () {
    Route::get('/jobs/{jobId}/offers', [OfferController::class, 'index']);
    Route::post('/offers/{offerId}/accept', [OfferController::class, 'accept']);
    Route::post('/offers/{offerId}/reject', [OfferController::class, 'reject']);
});
Route::middleware(['auth:sanctum', 'verified.email', 'role:customer'])->group(function () {
    Route::get('/my-bookings/customer', [BookingController::class, 'myBookingsAsCustomer']);
    Route::post('/bookings/{id}/confirm-completion', [BookingController::class, 'confirmCompletion']);
    Route::post('/bookings/{id}/cancel-by-customer', [BookingController::class, 'cancelByCustomer']);
});

Route::middleware(['auth:sanctum', 'verified.email', 'role:provider'])->group(function () {
    Route::get('/my-bookings/provider', [BookingController::class, 'myBookingsAsProvider']);
    Route::post('/bookings/{id}/accept', [BookingController::class, 'accept']);
    Route::post('/bookings/{id}/reject', [BookingController::class, 'rejectByProvider']);
    // DEPRECATED: the `in_progress` step was removed; kept as a no-op for old clients.
    Route::post('/bookings/{id}/start-progress', [BookingController::class, 'startProgress']);
    Route::post('/bookings/{id}/mark-awaiting-confirmation', [BookingController::class, 'markAwaitingConfirmation']);
    Route::post('/bookings/{id}/cancel-by-provider', [BookingController::class, 'cancelByProvider']);
});

Route::middleware(['auth:sanctum', 'verified.email'])->group(function () {
    Route::get('/bookings/{id}', [BookingController::class, 'show']);

    // Milestones — provider proposes, customer approves & pays per stage
    Route::get('/bookings/{bookingId}/milestones', [MilestoneController::class, 'index']);
    Route::post('/bookings/{bookingId}/milestones', [MilestoneController::class, 'store']);
    Route::patch('/milestones/{id}', [MilestoneController::class, 'update']);
    Route::delete('/milestones/{id}', [MilestoneController::class, 'destroy']);
    Route::post('/milestones/{id}/complete', [MilestoneController::class, 'complete']);
    Route::post('/milestones/{id}/approve', [MilestoneController::class, 'approve']);
    Route::post('/milestones/{id}/pay', [MilestoneController::class, 'pay']);

    // Time logs — hourly work; provider logs, customer approves
    Route::get('/bookings/{bookingId}/time-logs', [TimeLogController::class, 'index']);
    Route::post('/bookings/{bookingId}/time-logs', [TimeLogController::class, 'store']);
    Route::patch('/time-logs/{id}', [TimeLogController::class, 'update']);
    Route::delete('/time-logs/{id}', [TimeLogController::class, 'destroy']);
    Route::post('/time-logs/{id}/approve', [TimeLogController::class, 'approve']);
    Route::post('/time-logs/{id}/reject', [TimeLogController::class, 'reject']);
});
Route::middleware(['auth:sanctum', 'verified.email', 'role:customer'])->group(function () {
    Route::post('/bookings/{bookingId}/pay', [PaymentController::class, 'pay']);
});

Route::middleware(['auth:sanctum', 'verified.email'])->group(function () {
    Route::get('/bookings/{bookingId}/payments', [PaymentController::class, 'show']);
});
Route::middleware(['auth:sanctum', 'verified.email', 'role:customer'])->group(function () {
    Route::post('/bookings/{bookingId}/review', [ReviewController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'verified.email', 'role:provider'])->group(function () {
    Route::post('/reviews/{reviewId}/respond', [ReviewController::class, 'respond']);
});

Route::get('/providers/{providerId}/reviews', [ReviewController::class, 'forProvider']);

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

Route::middleware(['auth:sanctum', 'verified.email', 'role:customer'])->group(function () {
    Route::post('/services/{serviceId}/book', [BookingController::class, 'store']);
});

// Favorites — a customer's shortlisted providers
Route::middleware(['auth:sanctum', 'verified.email', 'role:customer'])->group(function () {
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{providerId}', [FavoriteController::class, 'destroy']);
});

// In-app notifications
Route::middleware(['auth:sanctum', 'verified.email'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
});

// Messaging — customer <-> provider conversations, persisted server-side
Route::middleware(['auth:sanctum', 'verified.email'])->group(function () {
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::get('/conversations/{id}/messages', [ConversationController::class, 'messages']);
    Route::post('/conversations/{id}/messages', [ConversationController::class, 'sendMessage']);
    Route::post('/conversations/{id}/read', [ConversationController::class, 'markRead']);
    Route::post('/conversations/{id}/messages/{messageId}/respond', [ConversationController::class, 'respondToProposal']);
});

// "Contact Support & Report Issues" form on /support — stored as a technical UserReport
Route::middleware(['auth:sanctum', 'verified.email'])->group(function () {
    Route::post('/support/technical-inquiries', [SupportContactController::class, 'store']);
});

// Admin Finance Routes
Route::middleware(['auth:sanctum', 'verified.email', 'role:admin_finance,admin'])->prefix('admin/finance')->group(function () {
    Route::get('/stats', [FinanceDashboardController::class, 'stats']);
    Route::get('/transactions', [FinanceDashboardController::class, 'transactions']);
    Route::get('/payout-readiness', [FinanceDashboardController::class, 'payoutReadiness']);
});

// Admin Support Routes
Route::middleware(['auth:sanctum', 'verified.email', 'role:admin_support,admin'])->prefix('admin/support')->group(function () {
    Route::get('/stats', [SupportDashboardController::class, 'stats']);
    Route::get('/disputes', [SupportDashboardController::class, 'disputes']);
    Route::patch('/disputes/{id}', [SupportDashboardController::class, 'updateDispute']);
    Route::get('/reports', [SupportDashboardController::class, 'reports']);
    Route::patch('/reports/{id}', [SupportDashboardController::class, 'updateReport']);
});

// Admin — provider verification review (owned by admin_support per the role brief)
Route::middleware(['auth:sanctum', 'verified.email', 'role:admin_support,admin'])->group(function () {
    Route::get('/admin/verifications', [AdminProviderVerificationController::class, 'index']);
    Route::post('/admin/verifications/documents/{id}/review', [AdminProviderVerificationController::class, 'reviewDocument']);
    Route::post('/admin/verifications/providers/{providerId}/decision', [AdminProviderVerificationController::class, 'decideProvider']);
});
