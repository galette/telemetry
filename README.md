# Telemetry

PHP application that aims to store some Telemetry information sent from your instances and display charts based on it.
It is also able to store references :)

## Dark theme

Dark theme stylesheet (`public/css/dark.css`) is generated with [DarkReader](https://darkreader.org/) from a running instance, and must be regenerated when the theme changes:

```bash
TELEMETRY_URL=http://telemetry.localhost/ npx gulp dark_css
```

A Chromium binary is required; set `CHROMIUM_PATH` if it is not found automatically.
