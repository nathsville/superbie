import Alpine from 'alpinejs';
// Leaflet global bootstrap + plugins (Prompt 5/5). ORDER MATTERS:
// `leaflet-global.js` sets a global `L` that the UMD/IIFE plugins in
// `leaflet-plugins.js` attach to, so it must be imported (and evaluated) first.
import L from './leaflet-global.js';
import './leaflet-plugins.js';
import { initNavigation } from './navigation.js';

window.Alpine = Alpine;

/**
 * Avatar image with a graceful initial-letter fallback.
 *
 * The server renders the account's real uploaded photo when `avatar_path` is
 * set, and always renders an initial-letter avatar alongside it. This component
 * flips to the initial when the <img> fails to load (`x-on:error`), so a broken
 * URL / missing file / unreachable storage host never shows the browser's
 * broken-image icon. Purely presentational: no data is fetched or fabricated.
 */
Alpine.data('avatarImage', () => ({
    failed: false,

    fail() {
        this.failed = true;
    },
}));

/**
 * Attachment picker (complaint form).
 *
 * A native `<input type="file" multiple>` REPLACES its whole selection every
 * time the picker is reopened, so adding files one-by-one silently discards the
 * previous ones. This component ACCUMULATES picks across multiple selections by
 * rebuilding the input's FileList through a DataTransfer, and mirrors the list
 * in the DOM with per-file remove controls.
 *
 * Progressive enhancement only: the real `<input name="attachments[]" multiple>`
 * stays in the form and works without JavaScript. The server remains the
 * authority for count/format/size limits — this only prevents avoidable
 * round-trips and makes the multi-file affordance obvious.
 *
 * The raw File objects live in a closure array (never made reactive) because
 * Alpine's reactive Proxy wrapping a File breaks `DataTransferItemList.add()`.
 */
Alpine.data('attachmentPicker', (config = {}) => {
    const max = Number(config.max) > 0 ? Number(config.max) : 10;
    const maxMb = Number(config.maxMb) > 0 ? Number(config.maxMb) : 20;
    const mimes = Array.isArray(config.mimes) ? config.mimes.map((m) => String(m).toLowerCase()) : [];

    // Non-reactive source of truth for the real File objects, each with a stable
    // id so remove()/re-render never depends on shifting array positions.
    let raw = [];
    let nextId = 1;

    return {
        // Reactive mirror used for rendering (plain data only).
        items: [],
        overflow: false,
        rejected: 0,

        isAllowed(file) {
            if (mimes.length) {
                const ext = (file.name.split('.').pop() || '').toLowerCase();
                if (!mimes.includes(ext)) return false;
            }
            if (maxMb && file.size > maxMb * 1024 * 1024) return false;
            return true;
        },

        isDuplicate(file) {
            return raw.some((e) => e.file.name === file.name
                && e.file.size === file.size
                && e.file.lastModified === file.lastModified);
        },

        onChange(event) {
            this.overflow = false;
            this.rejected = 0;

            for (const file of Array.from(event.target.files || [])) {
                if (!this.isAllowed(file)) { this.rejected += 1; continue; }
                if (this.isDuplicate(file)) continue;
                if (raw.length >= max) { this.overflow = true; continue; }
                raw.push({ id: nextId++, file });
            }

            this.refresh();
        },

        remove(id) {
            raw = raw.filter((e) => e.id !== id);
            this.overflow = false;
            this.refresh();
        },

        refresh() {
            this.items = raw.map((e) => ({ id: e.id, name: e.file.name, size: e.file.size }));
            this.syncInput();
        },

        syncInput() {
            const input = this.$refs.input;
            if (!input || typeof DataTransfer === 'undefined') return;
            const dt = new DataTransfer();
            raw.forEach((e) => dt.items.add(e.file));
            input.files = dt.files;
        },

        sizeLabel(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        },
    };
});

/**
 * Point-in-polygon test (ray casting) over one ring of `[lng, lat]` pairs.
 * Used ONLY to SUGGEST the Kecamatan/Kelurahan from a map point — the server
 * always re-validates the submitted pair.
 */
function pointInRing(x, y, ring) {
    let inside = false;
    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        const xi = ring[i][0], yi = ring[i][1];
        const xj = ring[j][0], yj = ring[j][1];
        if (((yi > y) !== (yj > y)) && (x < ((xj - xi) * (y - yi)) / (yj - yi) + xi)) {
            inside = !inside;
        }
    }
    return inside;
}

/** A GeoJSON polygon = [exterior, ...holes]; inside exterior and no hole. */
function pointInPolygon(x, y, polygon) {
    if (!Array.isArray(polygon) || polygon.length === 0) return false;
    if (!pointInRing(x, y, polygon[0])) return false;
    for (let h = 1; h < polygon.length; h += 1) {
        if (pointInRing(x, y, polygon[h])) return false;
    }
    return true;
}

/** Supports Polygon and MultiPolygon; coordinates are [lng, lat]. */
function pointInGeometry(lng, lat, geometry) {
    if (!geometry || !Array.isArray(geometry.coordinates)) return false;
    if (geometry.type === 'Polygon') {
        return pointInPolygon(lng, lat, geometry.coordinates);
    }
    if (geometry.type === 'MultiPolygon') {
        return geometry.coordinates.some((poly) => pointInPolygon(lng, lat, poly));
    }
    return false;
}

/** First feature whose polygon contains the point, or null. */
function findBoundaryFeature(features, lat, lng) {
    if (!Array.isArray(features)) return null;
    for (const feature of features) {
        if (pointInGeometry(lng, lat, feature.geometry)) return feature;
    }
    return null;
}

/**
 * Dependent location picker — Kecamatan → Kelurahan (FINAL location rule).
 *
 * The server is the security boundary (the pair is validated server-side); this
 * component is UX ONLY. It filters the Kelurahan options to the currently chosen
 * Kecamatan so a citizen/operator cannot accidentally (or by DOM tampering)
 * submit a mismatched pair.
 *
 * Progressive enhancement: both controls stay NATIVE `<select>` elements whose
 * values submit as a normal HTML form. No AJAX, no extra dependency. The full
 * Kecamatan + Kelurahan payload is embedded server-side (2 queries total) and
 * filtering happens in the browser. (The OPTIONAL geographic map/GPS lives in a
 * SEPARATE `locationMap` component below — administrative and geographic
 * location are deliberately independent.)
 *
 * Behavior:
 *  - Kecamatan empty  → Kelurahan is DISABLED and shows a "pick first" hint.
 *  - Kecamatan chosen → Kelurahan enables and lists ONLY that Kecamatan's rows.
 *  - Kecamatan CHANGED→ the selected Kelurahan is RESET (never kept stale).
 *  - Old input on a failed validation redirect is preserved when still valid.
 *
 * Optional `boundaries` (a GeoJSON FeatureCollection of Parepare kelurahan
 * polygons carrying `kecamatan_id`/`kelurahan_id`) enables `suggestFromCoordinates`:
 * when the user marks a point on the map / uses GPS, the matching
 * Kecamatan + Kelurahan are AUTO-SELECTED. This is a convenience only — the
 * fields stay REQUIRED, remain fully editable, and are still validated
 * server-side. If the point falls outside the bundled boundaries nothing is
 * changed and an honest hint is shown.
 */
Alpine.data('locationPicker', (config = {}) => {
    const kecamatanList = Array.isArray(config.kecamatan) ? config.kecamatan : [];
    const kelurahanList = Array.isArray(config.kelurahan) ? config.kelurahan : [];
    const boundaries = config.boundaries && Array.isArray(config.boundaries.features)
        ? config.boundaries.features
        : [];

    return {
        selectedKecamatanId: config.selectedKecamatanId != null ? String(config.selectedKecamatanId) : '',
        selectedKelurahanId: config.selectedKelurahanId != null ? String(config.selectedKelurahanId) : '',
        kecamatanList,
        kelurahanList,
        boundaries,

        // Detection feedback: '' | 'matched' | 'outside'
        detectState: '',

        get hasKecamatan() {
            return this.kecamatanList.length > 0;
        },

        get canDetect() {
            return this.boundaries.length > 0;
        },

        get filteredKelurahan() {
            if (!this.selectedKecamatanId) return [];
            return this.kelurahanList.filter(
                (k) => String(k.kecamatan_id) === String(this.selectedKecamatanId),
            );
        },

        get hasKelurahanForSelected() {
            return this.filteredKelurahan.length > 0;
        },

        /**
         * Reset the Kelurahan whenever the Kecamatan changes. `x-model` updates
         * `selectedKecamatanId` first (its `change` listener is registered before
         * this handler), so the reset always reflects the NEW Kecamatan.
         */
        onKecamatanChange() {
            this.selectedKelurahanId = '';
            this.detectState = '';
        },

        /**
         * Suggest Kecamatan/Kelurahan from a geographic point (map click / GPS).
         * Returns true when a boundary matched AND mapped to a known master row.
         * Never clears an existing manual choice on failure — the citizen keeps
         * full control.
         */
        suggestFromCoordinates(lat, lng) {
            const latitude = Number(lat);
            const longitude = Number(lng);
            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return false;

            const feature = findBoundaryFeature(this.boundaries, latitude, longitude);
            if (!feature) {
                this.detectState = 'outside';
                return false;
            }

            const kecamatanId = String(feature.properties.kecamatan_id);
            const kelurahanId = String(feature.properties.kelurahan_id);

            const kecKnown = this.kecamatanList.some((k) => String(k.id) === kecamatanId);
            const kelKnown = this.kelurahanList.some(
                (k) => String(k.id) === kelurahanId && String(k.kecamatan_id) === kecamatanId,
            );
            if (!kecKnown || !kelKnown) {
                this.detectState = 'outside';
                return false;
            }

            this.selectedKecamatanId = kecamatanId;
            this.detectState = 'matched';
            // Set the Kelurahan on the next tick so the dependent `<option>`
            // elements (rendered by `x-for` from the new Kecamatan) already exist
            // when `x-model` applies the value — avoids a select/options race.
            //
            // NB: use the global `Alpine.nextTick`, NOT `this.$nextTick`. Alpine
            // only injects `$`-magics onto the TOP-LEVEL `x-data` object; this
            // `locationPicker` object is a nested property, so `this.$nextTick`
            // would be `undefined` and throw before the Kelurahan is assigned.
            Alpine.nextTick(() => {
                this.selectedKelurahanId = kelurahanId;
            });
            return true;
        },
    };
});

/**
 * Report-location map (Prompt 4/5) — Leaflet + OpenStreetMap.
 *
 * SEPARATE from `locationPicker`: the administrative Kecamatan/Kelurahan pair
 * stays REQUIRED and is chosen from master dropdowns; this component only adds
 * an OPTIONAL geographic point (latitude/longitude).
 *
 * Privacy / safety contract:
 *  - A one-shot device-geolocation request is made ONCE, ONLY when the user
 *    presses the GPS button. Never on load, never a continuous position watch,
 *    no background tracking.
 *  - THIS component never logs coordinates, never puts them in the URL, and
 *    never sends them anywhere: it only mirrors them into the form's hidden
 *    inputs on submit. (The OPTIONAL reverse-geocoding of the address field
 *    lives in a SEPARATE `locationAddress` component, is gated by an explicit
 *    opt-in, and is the ONLY place a third party may ever see a coordinate.)
 *  - The map is an ENHANCEMENT: if Leaflet/tiles fail to load, the form still
 *    works with Kecamatan/Kelurahan/location_text.
 *
 * The initial view (`defaultCenter`) is ONLY the empty map's starting position —
 * it is never treated as a report or user location.
 */
function normalizeCoordinate(value, min, max) {
    if (value === null || value === undefined || value === '') return null;
    const n = Number(value);
    if (!Number.isFinite(n) || n < min || n > max) return null;
    return n;
}

Alpine.data('locationMap', (config = {}) => {
    const readonly = !!config.readonly;
    const defaultCenter = config.defaultCenter || { lat: -4.0135, lng: 119.6255 };
    const defaultZoom = Number(config.defaultZoom) || 13;
    const maxZoom = Number(config.maxZoom) || 19;
    const tileUrl = config.tileUrl || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
    const attribution = config.attribution || '&copy; OpenStreetMap contributors';

    // Accept initial coordinates ONLY as a fully-valid PAIR (old input / stored
    // complaint). A partial pair is ignored so no marker is shown for it.
    const initLat = normalizeCoordinate(config.lat, -90, 90);
    const initLng = normalizeCoordinate(config.lng, -180, 180);
    const hasInitialPoint = initLat !== null && initLng !== null;

    return {
        latitude: hasInitialPoint ? initLat.toFixed(7) : '',
        longitude: hasInitialPoint ? initLng.toFixed(7) : '',
        statusMessage: readonly
            ? ''
            : (hasInitialPoint ? 'Lokasi berhasil dipilih.' : 'Belum ada titik lokasi dipilih.'),
        statusKind: hasInitialPoint ? 'success' : 'idle',
        mapError: false,
        _map: null,
        _marker: null,

        get hasPoint() {
            return this.latitude !== '' && this.longitude !== '';
        },

        get coordinateLabel() {
            if (!this.hasPoint) return '';
            return `Latitude: ${this.latitude}, Longitude: ${this.longitude}`;
        },

        init() {
            if (this._map) return; // guard against double initialisation
            if (typeof L === 'undefined' || !this.$refs.mapEl) {
                this.mapError = true;
                return;
            }

            try {
                const center = this.hasPoint
                    ? [Number(this.latitude), Number(this.longitude)]
                    : [defaultCenter.lat, defaultCenter.lng];

                this._map = L.map(this.$refs.mapEl, {
                    zoomControl: true,
                    attributionControl: true,
                    scrollWheelZoom: false, // don't hijack page scroll
                }).setView(center, this.hasPoint ? 16 : defaultZoom);

                L.tileLayer(tileUrl, { maxZoom, attribution }).addTo(this._map);

                if (this.hasPoint) {
                    this._placeMarker(Number(this.latitude), Number(this.longitude));
                }

                if (!readonly) {
                    this._map.on('click', (e) => this._applyPoint(e.latlng.lat, e.latlng.lng));
                }
            } catch (error) {
                // Never let a map failure break the form.
                this.mapError = true;
            }
        },

        _placeMarker(lat, lng) {
            if (!this._map) return;
            if (this._marker) {
                this._marker.setLatLng([lat, lng]);
            } else {
                this._marker = L.marker([lat, lng]).addTo(this._map);
            }
        },

        _applyPoint(lat, lng) {
            const la = normalizeCoordinate(lat, -90, 90);
            const ln = normalizeCoordinate(lng, -180, 180);
            if (la === null || ln === null) return;

            this.latitude = la.toFixed(7);
            this.longitude = ln.toFixed(7);
            this._placeMarker(la, ln);
            if (this._map) {
                this._map.setView([la, ln], Math.max(this._map.getZoom(), 16));
            }
            this.statusMessage = 'Lokasi berhasil dipilih.';
            this.statusKind = 'success';

            // Notify any enclosing `locationPicker` so it can SUGGEST the
            // Kecamatan/Kelurahan for this point. Bubbles up the DOM; harmless
            // when nothing listens. The administrative pair stays REQUIRED and
            // is never cleared here.
            this.$dispatch('location-point', { lat: la, lng: ln });
        },

        detectLocation() {
            if (!('geolocation' in navigator)) {
                this.statusMessage = 'Perangkat ini tidak mendukung layanan lokasi. Silakan pilih titik secara manual pada peta.';
                this.statusKind = 'error';
                return;
            }

            this.statusMessage = 'Layanan lokasi sedang diproses...';
            this.statusKind = 'loading';

            // One-shot request only (never a continuous position watch).
            navigator.geolocation.getCurrentPosition(
                (position) => this._applyPoint(position.coords.latitude, position.coords.longitude),
                (error) => this._onGeoError(error),
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
            );
        },

        _onGeoError(error) {
            const code = error && error.code;
            if (code === 1) {
                this.statusMessage = 'Lokasi perangkat tidak dapat diakses. Anda tetap dapat memilih titik secara manual pada peta.';
            } else if (code === 3) {
                this.statusMessage = 'Permintaan lokasi melebihi batas waktu. Silakan pilih titik secara manual pada peta.';
            } else {
                this.statusMessage = 'Lokasi perangkat tidak tersedia. Silakan pilih titik secara manual pada peta.';
            }
            this.statusKind = 'error';
        },

        clearLocation() {
            this.latitude = '';
            this.longitude = '';
            if (this._marker && this._map) {
                this._map.removeLayer(this._marker);
                this._marker = null;
            }
            this.statusMessage = 'Belum ada titik lokasi dipilih.';
            this.statusKind = 'idle';
        },
    };
});

/**
 * OPTIONAL reverse-geocoding of the "Lokasi / Alamat Detail" free-text field.
 *
 * SCOPE / PRIVACY DEVIATION (owner-approved) — see config/map.php:
 *  - The PRD lists "geocoding" as OUT OF SCOPE and the map contract promised
 *    coordinates were "never sent anywhere except the form". The owner approved
 *    pre-filling the optional address from an EXTERNAL reverse-geocoding service.
 *  - Because this sends the citizen's coordinates to a THIRD PARTY (Nominatim),
 *    it is GATED by an EXPLICIT opt-in checkbox: no request is ever made unless
 *    the citizen ticks it. A one-shot request runs per chosen point only — never
 *    on page load, never a background/continuous loop.
 *  - "Fill only when empty": a manually typed address is NEVER overwritten, and
 *    the value is re-checked AFTER the async response so a mid-flight edit wins.
 *  - The result is a SUGGESTION: the field stays a normal editable input and the
 *    server keeps `location_text` nullable/free-text (no new validation rule).
 *  - Citizen forms only. Staff/read-only views never instantiate this component.
 *
 * Runs entirely in the browser against the public OSM Nominatim endpoint; the
 * app backend is never a proxy and stores nothing from this call.
 */
Alpine.data('locationAddress', (config = {}) => {
    const enabled = !!config.enabled;
    const url = typeof config.url === 'string' ? config.url : '';
    const email = typeof config.email === 'string' ? config.email : '';
    const locale = typeof config.locale === 'string' && config.locale !== '' ? config.locale : 'id';
    const attribution = typeof config.attribution === 'string' ? config.attribution : 'OpenStreetMap Nominatim';
    const maxLength = Number(config.maxLength) > 0 ? Number(config.maxLength) : 255;

    return {
        // Explicit consent — coordinates are only transmitted when true.
        optIn: false,
        // Mirrors the free-text field so detection can pre-fill it safely.
        text: typeof config.initialText === 'string' ? config.initialText : '',
        // '' | 'loading' | 'done' | 'empty' | 'error' | 'skipped'
        lookupState: '',
        lookupMessage: '',
        attribution,

        get canLookup() {
            return enabled && url !== '';
        },

        get hasText() {
            return this.text.trim() !== '';
        },

        /**
         * Entry point wired to `@location-point.window`. Detection of the
         * administrative pair is independent (and local); THIS handler is the
         * only place that may reach the network, and only when opted in.
         */
        handlePoint(lat, lng) {
            if (!this.canLookup) return;

            // "Fill only when empty" — a manual address always wins.
            if (this.hasText) {
                this.lookupState = 'skipped';
                this.lookupMessage = '';
                return;
            }

            if (!this.optIn) {
                this.lookupState = 'skipped';
                this.lookupMessage = 'Aktifkan "Isi alamat otomatis" untuk mengisi alamat dari titik lokasi.';
                return;
            }

            this.lookup(lat, lng);
        },

        async lookup(lat, lng) {
            const latitude = Number(lat);
            const longitude = Number(lng);
            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;

            this.lookupState = 'loading';
            this.lookupMessage = 'Mencari alamat dari titik lokasi...';

            try {
                const params = new URLSearchParams({
                    format: 'jsonv2',
                    lat: String(latitude),
                    lon: String(longitude),
                    'accept-language': locale,
                    zoom: '18',
                });
                if (email !== '') params.set('email', email);

                const response = await fetch(`${url}?${params.toString()}`, {
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);

                const data = await response.json();
                const address = this.pickAddress(data);

                if (address === '') {
                    this.lookupState = 'empty';
                    this.lookupMessage = 'Alamat tidak ditemukan untuk titik ini. Silakan isi manual.';
                    return;
                }

                // Re-check AFTER the await: a manual edit mid-flight must win.
                if (this.hasText) {
                    this.lookupState = 'skipped';
                    this.lookupMessage = '';
                    return;
                }

                this.text = address.slice(0, maxLength);
                this.lookupState = 'done';
                this.lookupMessage = 'Alamat terisi otomatis dari titik lokasi. Periksa kembali dan ubah bila perlu.';
            } catch (error) {
                // Never let a network/geocoder failure break the form.
                this.lookupState = 'error';
                this.lookupMessage = 'Gagal mengambil alamat otomatis. Silakan isi alamat secara manual.';
            }
        },

        /**
         * Prefer the human-readable `display_name`; otherwise assemble the most
         * useful address components. Never invents data — returns '' when the
         * provider has nothing usable.
         */
        pickAddress(data) {
            if (!data || typeof data !== 'object') return '';

            if (typeof data.display_name === 'string' && data.display_name.trim() !== '') {
                return data.display_name.trim();
            }

            const a = data.address && typeof data.address === 'object' ? data.address : {};
            return [
                a.road, a.pedestrian, a.neighbourhood, a.village, a.suburb,
                a.city_district, a.town, a.city, a.county, a.state,
            ].filter((part) => typeof part === 'string' && part.trim() !== '').join(', ');
        },
    };
});

/**
 * Geographic dashboard map (Prompt 5/5) — "Peta Wilayah Aduan".
 *
 * READ-ONLY analytics map for the Admin / Super Admin dashboards. Renders
 * complaint PIN markers (clustered) or a HEATMAP of report density, built ONLY
 * from complaints that carry a valid latitude+longitude pair. Administrative
 * counts (Kecamatan/Kelurahan) come from the server payload and are shown
 * alongside, but the map never invents coordinates.
 *
 * Modes (segmented control):
 *  - 'all'     : clustered pins (default) — clusters keep dense areas readable.
 *  - 'pin'     : same clustered pins, framed as the explicit "pin" view.
 *  - 'heatmap' : density heat layer (leaflet.heat).
 *
 * Contract / guards:
 *  - NO device-geolocation API is used here (no position request, no continuous
 *    position watch): the map center comes from config (config/map.php), never
 *    the admin's GPS.
 *  - NO AJAX: switching modes / zoom / pan / marker click never hits the server.
 *  - NO console logging of coordinates.
 *  - Defensive: if `L.markerClusterGroup` is unavailable the component falls
 *    back to a plain layer group; if `L.heatLayer` is unavailable the heatmap
 *    mode degrades with a note instead of crashing.
 *  - Popup content is built client-side from server data and HTML-escaped.
 */
Alpine.data('geographicMap', (config = {}) => {
    const points = Array.isArray(config.points) ? config.points : [];
    const defaultCenter = config.defaultCenter || { lat: -4.0135, lng: 119.6255 };
    const defaultZoom = Number(config.defaultZoom) || 13;
    const maxZoom = Number(config.maxZoom) || 19;
    const tileUrl = config.tileUrl || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
    const attribution = config.attribution || '&copy; OpenStreetMap contributors';

    // Escape any interpolated value before it reaches innerHTML.
    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    const hasPoints = points.length > 0;

    return {
        mode: 'all',
        hasPoints,
        mapError: false,
        heatUnavailable: false,
        _map: null,
        _markerLayer: null,
        _heatLayer: null,

        init() {
            if (this._map) return; // guard against double initialisation
            if (!this.hasPoints) return; // empty state renders no map
            if (typeof L === 'undefined' || !this.$refs.mapEl) {
                this.mapError = true;
                return;
            }

            try {
                this._map = L.map(this.$refs.mapEl, {
                    zoomControl: true,
                    attributionControl: true,
                    scrollWheelZoom: false, // don't hijack page scroll
                }).setView([defaultCenter.lat, defaultCenter.lng], defaultZoom);

                L.tileLayer(tileUrl, { maxZoom, attribution }).addTo(this._map);

                this._markerLayer = this._buildMarkerLayer();
                this._heatLayer = this._buildHeatLayer();

                // Fit to the real points with padding; a single point uses a
                // sensible zoom instead of collapsing to max zoom.
                const bounds = L.latLngBounds(points.map((p) => [p.latitude, p.longitude]));
                if (points.length === 1) {
                    this._map.setView([points[0].latitude, points[0].longitude], 15);
                } else {
                    this._map.fitBounds(bounds, { padding: [32, 32] });
                }

                this.applyMode();
            } catch (error) {
                // Never let a map failure break the dashboard.
                this.mapError = true;
            }
        },

        /**
         * Build the clustered pin layer ONCE. Falls back to a plain layer group
         * if the markercluster plugin failed to register.
         */
        _buildMarkerLayer() {
            const useCluster = typeof L.markerClusterGroup === 'function';
            const group = useCluster
                ? L.markerClusterGroup({
                    showCoverageOnHover: false,
                    maxClusterRadius: 60,
                    iconCreateFunction: (cluster) => L.divIcon({
                        html: '<div class="sb-cluster-marker">' + cluster.getChildCount() + '</div>',
                        className: 'sb-cluster-wrapper',
                        iconSize: L.point(40, 40),
                    }),
                })
                : L.layerGroup();

            points.forEach((p) => {
                const marker = L.marker([p.latitude, p.longitude]);
                marker.bindPopup(this._popupHtml(p));
                group.addLayer(marker);
            });

            return group;
        },

        /**
         * Build the heat layer ONCE. Returns null (and flags the mode as
         * unavailable) when leaflet.heat did not register.
         */
        _buildHeatLayer() {
            if (typeof L.heatLayer !== 'function') {
                this.heatUnavailable = true;
                return null;
            }

            return L.heatLayer(
                points.map((p) => [p.latitude, p.longitude, 0.6]),
                { radius: 28, blur: 20, maxZoom, max: 1.0, minOpacity: 0.25 },
            );
        },

        _popupHtml(p) {
            const rows = [
                ['Nomor Laporan', p.complaint_number],
                ['Kategori', p.category],
                ['Kecamatan', p.kecamatan || 'Belum Ditentukan'],
                ['Kelurahan', p.kelurahan || 'Belum Ditentukan'],
                ['Status', p.status_label],
            ];

            const body = rows.map(([label, value]) => (
                '<div class="sb-popup-row"><span class="sb-popup-label">' + escapeHtml(label)
                + '</span><span class="sb-popup-value">' + escapeHtml(value) + '</span></div>'
            )).join('');

            return '<div class="sb-popup">' + body + '</div>';
        },

        setMode(next) {
            this.mode = next;
            this.applyMode();
        },

        /**
         * Remove BOTH layers, then add only what the active mode needs. This
         * prevents stacked/duplicated layers when toggling modes.
         */
        applyMode() {
            if (!this._map) return;

            if (this._markerLayer && this._map.hasLayer(this._markerLayer)) {
                this._map.removeLayer(this._markerLayer);
            }
            if (this._heatLayer && this._map.hasLayer(this._heatLayer)) {
                this._map.removeLayer(this._heatLayer);
            }

            if (this.mode === 'heatmap') {
                if (this._heatLayer) {
                    this._map.addLayer(this._heatLayer);
                }
                return;
            }

            if (this._markerLayer) {
                this._map.addLayer(this._markerLayer);
            }
        },
    };
});

Alpine.start();

// Progressive GET navigation + in-memory page cache (Prompt 26).
// Enhancement only: if this fails, the app still works as a normal
// server-rendered Blade application.
initNavigation();
