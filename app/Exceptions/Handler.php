<?php

namespace App\Exceptions;

use App\Services\ActivityLogger;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Symfony\Component\HttpFoundation\Exception\PostTooLargeException;
use Illuminate\Session\TokenMismatchException;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        // $this->reportable(function (Throwable $e) {
        //     //
        // });
        $this->renderable(function (PostTooLargeException $e, $request) {
            if ($request->routeIs('recruitments.step.store')) {
                return back()->with(
                    'error',
                    'Total ukuran seluruh file yang diupload terlalu besar untuk diterima server. ' .
                        'Pastikan setiap dokumen maksimal 2MB, lalu coba upload ulang satu per satu.'
                );
            }
        });

        $this->renderable(function (TokenMismatchException $e, $request) {
            if ($request->routeIs('recruitments.step.store')) {
                return back()->with(
                    'error',
                    'Sesi form kadaluarsa atau ukuran upload terlalu besar sehingga sesi terputus. ' .
                        'Silakan muat ulang halaman dan coba lagi dengan ukuran file yang lebih kecil.'
                );
            }
        });
    }

    public function report(Throwable $e)
    {
        ActivityLogger::log([
            'action' => 'error',
            'description' => $e->getMessage(),
        ]);

        parent::report($e);
    }
}
