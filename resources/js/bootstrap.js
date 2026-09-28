// Kept as the shared JS entrypoint hook. The app talks to the API with
// fetch() (see authFetch in app.js); axios was removed because nothing
// used it and it added ~30 kB to every page load.
