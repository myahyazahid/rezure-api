{{--
    Runs before first paint so the stored theme is applied without a flash of
    the wrong palette. Light is the default when nothing is stored; only an
    explicit 'dark' choice adds the class. Kept inline (not in app.js) because
    Vite assets load too late to prevent the flash.
--}}
<script>
    (function () {
        try {
            document.documentElement.classList.toggle('dark', localStorage.getItem('rezure-theme') === 'dark');
        } catch (error) {
            // Storage blocked (private mode) — fall through to the light default.
        }
    })();
</script>
