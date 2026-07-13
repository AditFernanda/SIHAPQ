<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware keamanan: menyisipkan HTTP security headers ke SETIAP respons.
 *
 * Tujuannya menutup celah keamanan umum di sisi browser sesuai praktik OWASP.
 * Setiap header punya alasan spesifik (lihat komentar per baris di bawah).
 */
class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Cegah situs disematkan (iframe) oleh domain lain -> lindungi dari clickjacking.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Larang browser menebak-nebak tipe file -> cegah serangan MIME sniffing.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Batasi sumber aset ke domain sendiri -> tutup mayoritas jalur XSS.
        // Semua aset di-host sendiri (tanpa CDN). 'unsafe-inline' masih diperlukan
        // untuk data chart (@json) dan gaya inline Blade.
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "img-src 'self' data:",
            "style-src 'self' 'unsafe-inline'",
            "script-src 'self' 'unsafe-inline'",
            "font-src 'self'",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ]));

        // Batasi info alamat halaman yang dikirim ke situs lain -> jaga privasi URL.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Matikan akses kamera/mikrofon/lokasi karena aplikasi memang tidak memakainya.
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Bila diakses via HTTPS, paksa browser selalu pakai HTTPS selama 1 tahun (HSTS).
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
