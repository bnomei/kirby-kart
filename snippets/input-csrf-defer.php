<input type="hidden" name="token" value="">
<script defer>
    (async () => {
        try {
            const res = await fetch('<?= \Bnomei\Kart\Router::csrf_token() ?>');
            if (!res.ok) throw new Error('Failed to fetch CSRF token');
            (document.currentScript.closest('form')?.querySelector('input[name="token"]') || {}).value = (await res.json()).token;
        } catch (e) { console.error('Error fetching CSRF token:', e); }
    })();
</script>
