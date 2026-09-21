SET search_path TO forsa, public;

INSERT INTO forsa_shap_entities (code, name, short_name, sort_order, is_active) VALUES
('IP',    'PT PLN Indonesia Power',   'PLN IP',    1, TRUE),
('NP',    'PT PLN Nusantara Power',   'PLN NP',    2, TRUE),
('EPI',   'PT PLN Energi Primer Indonesia', 'PLN EPI', 3, TRUE),
('ICONP', 'PT PLN Icon Plus',         'PLN ICON+', 4, TRUE),
('ES',    'PT PLN Elektrik Sarana',   'PLN ES',    5, TRUE),
('ND',    'PT Prima Layanan Nasional Enjiniring', 'PLN ND', 6, TRUE),
('BATAM', 'PT PLN Batam',             'PLN BATAM', 7, TRUE),
('MCTN',  'PT PLN Muara Tawar',       'PLN MCTN',  8, TRUE),
('EMI',   'PT Energi Manajemen Indonesia', 'PLN EMI', 9, TRUE),
('ENJ',   'PT PLN Enjiniring',        'PLN ENJ',   10, TRUE)
ON CONFLICT (code) DO NOTHING;
