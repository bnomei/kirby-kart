# Square Stale Customer ID Manual QA

1. Configure `bnomei.kart.providers.square.access_token`, `bnomei.kart.providers.square.api_version`, and `bnomei.kart.providers.square.location_id`.
2. Log in as a `customer` user and set provider user data to an invalid-but-formatted Square customer id:
   `kart()->provider()->setUserData(['customerId' => 'SQSTALECUSTOMERID0000000000000'], kirby()->user());`
3. Start checkout with a valid cart and visit the provider checkout flow.
4. Verify checkout URL is returned (no immediate checkout exception from stale customer linkage).
5. Verify the stale id was removed from provider user data after preflight:
   `kart()->provider()->userData('customerId')` should be `null`.
6. Re-run checkout and confirm no regression for users without a stored provider customer id.
