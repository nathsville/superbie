/**
 * Leaflet plugin registration (Prompt 5/5).
 *
 * Loads the two ALLOWED, explicitly-recorded Leaflet plugins used by the Admin
 * "Peta Wilayah Aduan" card:
 *
 *   - leaflet.markercluster ^1.5.3 — marker clustering (`L.markerClusterGroup`)
 *   - leaflet.heat ^0.2.0          — heatmap layer (`L.heatLayer`)
 *
 * Import ORDER matters: `./leaflet-global.js` MUST be evaluated first so a
 * global `L` exists before the UMD/IIFE plugin scripts run. ES module imports
 * are hoisted, so the global is set in its own module (see leaflet-global.js).
 *
 * markercluster also ships CSS for its cluster bubbles; we import BOTH the base
 * and default styles, then override the look with a SuperBie-branded
 * `iconCreateFunction` in the `geographicMap` Alpine component.
 *
 * Importing this module is enough — it is imported for its side effects (the
 * plugins mutate the global `L`). No symbols are re-exported.
 */
import './leaflet-global.js';
import 'leaflet.markercluster';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';
import 'leaflet.heat';
