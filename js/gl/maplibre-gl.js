import * as maplibregl from 'maplibre-gl';
// Registers L.maplibreGL on the Leaflet the page already loaded (see the
// `leaflet` alias in gulpfile.js).
import '@maplibre/maplibre-gl-leaflet';

// The worker is shipped next to this bundle; derive its URL from our own
// <script> so no template has to know where assets are served from. Keep its
// version query string, both files are built together.
const bundle_url = document.currentScript && document.currentScript.src;
if (bundle_url) {
  const worker_url = new URL('maplibre-gl.worker.min.js', bundle_url);
  worker_url.search = new URL(bundle_url).search;
  maplibregl.setWorkerUrl(worker_url.href);
}

export * from 'maplibre-gl';
