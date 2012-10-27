INSERT INTO tentc_user (
    entity,
    server_url,
    app_id,
    app_mac_key,
    app_mac_secret,
    app_mac_algorithm,
    mac_key,
    mac_secret,
    mac_algorithm,
    token_type,
    post_types,
    profile_types,
    notification_url)
SELECT
    u.entity_url,
    a.server_url,
    a.application_id,
    a.mac_key_id,
    a.mac_key,
    a.mac_algorithm,
    u.access_token,
    u.mac_key,
    u.mac_algorithm,
    u.token_type,
    '["http://www.beberlei.de/tent/bookmark/v0.0.1"]',
    '[]',
    null
FROM tentc_user_authorization u, tentc_application_config a
WHERE u.application_id = a.application_id;

