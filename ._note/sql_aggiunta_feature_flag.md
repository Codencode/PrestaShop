// TODO <cnc> ===== Front admin bar ===== sql_aggiunta_feature_flag.md
```sql
INSERT INTO `ps_feature_flag` (
  `name`,
  `type`,
  `state`,
  `label_wording`,
  `label_domain`,
  `description_wording`,
  `description_domain`,
  `stability`
)
SELECT
  'front_office_admin_bar',
  'env,dotenv,db',
  0,
  'Front office admin bar',
  'Admin.Advparameters.Feature',
  'Enable / Disable the experimental admin bar in the front office.',
  'Admin.Advparameters.Help',
  'beta'
WHERE NOT EXISTS (
  SELECT 1
  FROM `ps_feature_flag`
  WHERE `name` = 'front_office_admin_bar'
);
```