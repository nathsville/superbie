<?php

namespace App\Http\Controllers\Weather;

use App\Http\Controllers\Controller;
use App\Services\Weather\WeatherDisplayService;
use App\Services\Weather\WeatherLocationRegistry;
use Illuminate\View\View;

/**
 * Public, read-only weather page (owner decision O-2: no login required).
 *
 * MULTI-LOCATION: `/cuaca` renders the configured DEFAULT location, and
 * `/cuaca/{key}` renders any location by its stable key. Both share the same
 * view; when only one location is configured (owner decision: Kota Parepare)
 * the picker is simply not shown.
 *
 * Shows the migrated WINDY feature: current conditions (derived from the
 * nearest forecast slot), the 3-day forecast, the computed early-warning status,
 * the source attribution, and the Windy.com embed.
 *
 * There is no write path here and no per-user data — it is a public surface.
 */
class PublicWeatherController extends Controller
{
    public function __construct(
        private readonly WeatherDisplayService $weather,
        private readonly WeatherLocationRegistry $registry,
    ) {}

    /**
     * `/cuaca` — the default location.
     */
    public function index(): View
    {
        abort_unless((bool) config('weather.enabled'), 404);

        return $this->render($this->registry->defaultKey());
    }

    /**
     * `/cuaca/{location}` — one named location.
     */
    public function show(string $location): View
    {
        abort_unless((bool) config('weather.enabled'), 404);

        abort_if($this->registry->find($location) === null, 404);

        return $this->render($location);
    }

    private function render(?string $key): View
    {
        return view('public.weather', [
            'weather' => $this->weather->snapshot($key),
            'locations' => $this->weather->locations(),
            'activeLocation' => $key,
        ]);
    }
}
