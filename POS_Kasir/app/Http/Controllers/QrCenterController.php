<?php

namespace App\Http\Controllers;

use App\Support\QrisHelper;
use Illuminate\Contracts\View\View;

class QrCenterController extends Controller
{
    /**
     * Printable QR stand: table self-order code + merchant QRIS code.
     */
    public function index(): View
    {
        $menuUrl = url('/');
        $staticQris = QrisHelper::getStaticPayload();
        $qrisDetails = QrisHelper::parseQrisDetails($staticQris);
        $qrisImg = QrisHelper::getQrImageUrl($staticQris, 260);

        return view('owner.qr-center', compact('menuUrl', 'staticQris', 'qrisDetails', 'qrisImg'));
    }
}
