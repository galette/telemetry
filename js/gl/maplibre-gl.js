import * as maplibregl from 'maplibre-gl';
// Registers L.maplibreGL on the Leaflet the page already loaded (see the
// `leaflet` alias in gulpfile.js).
import '@maplibre/maplibre-gl-leaflet';

// The worker is shipped next to this bundle; derive its URL from our own
// <script> so no template has to know where assets are served from.
const bundle_url = document.currentScript && document.currentScript.src;
if (bundle_url) {
  maplibregl.setWorkerUrl(new URL('maplibre-gl.worker.min.js', bundle_url).href);
}

export * from 'maplibre-gl';
