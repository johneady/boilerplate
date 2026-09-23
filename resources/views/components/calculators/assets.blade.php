{{--
    Hands config/calculators.php to the browser and loads the script that does
    the arithmetic. @json escapes <, >, & and quotes, so nothing in the config
    can close this script element early.

    The config is embedded rather than fetched: the numbers are needed on the
    first paint, and a page with no request to make has nothing to fail.
--}}
<script type="application/json" id="calculator-config">
    @json(config('calculators'))
</script>
@vite('resources/js/calculators.js')
