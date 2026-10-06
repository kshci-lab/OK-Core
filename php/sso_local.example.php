<?php

return array(
    'idp_url' => 'https://kshci-lab.net/software/hcimlab_auth',
    'client_id' => 'your-ok-core-client-id',
    'client_secret' => 'your-ok-core-client-secret',
    'base_url' => function_exists('hcimlab_sso_detect_base_url')
        ? hcimlab_sso_detect_base_url()
        : 'http://localhost:8888/OK-Core',
    'redirect_uri' => function_exists('hcimlab_sso_detect_base_url')
        ? rtrim(hcimlab_sso_detect_base_url(), '/') . '/auth/callback'
        : 'http://localhost:8888/OK-Core/auth/callback',
    'scope' => 'openid profile email lab',
    // Optional: override the auto-detected bundle at ../certs/cacert.pem
    // 'ca_bundle' => __DIR__ . '/../certs/cacert.pem',
);
