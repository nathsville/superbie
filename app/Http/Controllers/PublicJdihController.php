<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * The outbound notice for JDIH ("/jdih"; PHASE J).
 *
 * JDIH is NOT rebuilt inside SuperBie and nothing is scraped, embedded or
 * synced (D-J1). This page exists for one reason: the portal must never send a
 * visitor straight to an external site (D-J2). It states the destination, and
 * the exit happens only from the visitor's explicit action — there is no meta
 * refresh, no timer, no scripted redirect.
 *
 * The destination is a single constant so the notice, the tests and any future
 * caller read the same address. The outbound anchor opens in a new tab with
 * rel="noopener noreferrer" (D-J3). See JDIH_INTEGRATION_PLAN.md (D-J1…D-J5).
 */
class PublicJdihController extends Controller
{
    /** Official JDIH Kota Parepare site (owner-provided, 2026-10-18). */
    public const DESTINATION = 'https://jdih.pareparekota.go.id/';

    public function __invoke(): View
    {
        return view('public.jdih', [
            'destination' => self::DESTINATION,
        ]);
    }
}
