/**
 * Leaflet global bootstrap (Prompt 5/5).
 *
 * WHY THIS MODULE EXISTS
 * ----------------------
 * The Leaflet plugins used by the Admin "Peta Wilayah Aduan" card
 * (`leaflet.markercluster`, `leaflet.heat`) are PLAIN/UMD scripts that expect a
 * GLOBAL `L` to already exist when they evaluate — they do not import Leaflet
 * themselves:
 *
 *   - leaflet.markercluster (UMD): `factory((global.Leaflet = global.Leaflet || {}, ...))`
 *     and then `L.MarkerClusterGroup = ...` against the global `L`.
 *   - leaflet.heat (IIFE):        `L.HeatLayer = (L.Layer ? ...)` against the global `L`.
 *
 * Because ES module imports are HOISTED, setting `window.L = L` in the same
 * module that also imports the plugins would run TOO LATE (the plugin bodies
 * evaluate first). So `window.L` is set here, in a dedicated module that is
 * imported BEFORE `leaflet-plugins.js` — guaranteeing the global exists by the
 * time the plugin scripts run.
 *
 * This also centralises the Leaflet CSS import and the default-marker icon fix
 * (Vite hashes asset URLs, which breaks Leaflet's runtime-relative icon paths).
 */
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

// Leaflet's default marker icon resolves its image URLs relative to the CSS at
// runtime, which breaks under a Vite production build (hashed asset paths).
// Pointing the icon at the Vite-resolved URLs fixes broken markers in prod.
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

// Expose Leaflet globally so the UMD/IIFE plugins below can attach to it. The
// app code itself keeps using the imported binding.
window.L = L;

export default L;
