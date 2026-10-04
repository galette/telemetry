// Leaflet is already loaded by leaflet.bundle.min.js; hand the bundler that
// instance instead of a second copy, or the binding would extend a Leaflet the
// page never sees.
module.exports = window.L;
