---
paths:
  - config/geoip.php
---

# Config

## geoip cache_tags must stay empty
Keep 'cache_tags' => [] in config/geoip.php. torann/geoip 3.0.10's GeoIP constructor builds its internal Cache wrapper unconditionally and calls Cache::tags() whenever cache_tags is non-empty — it ignores 'cache' => 'none'. With the app's database cache store that throws "This cache store does not support tagging" on every lookup, before any IP is resolved, so country_code silently lands as null on every event and the dashboard Geography page stays empty. GeolocationResolver already caches per-IP itself; the package's cache is not needed.
