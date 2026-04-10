#!/bin/bash
set -e
DRUSH=vendor/drush/drush/drush

# cd /var/www/html/dev10

echo "=== ÉTAPE 1 : Désinstallation des modules ==="
$DRUSH -y pm:uninstall media_album_light_table_style -y 2>/dev/null || echo " media_album_light_table_style: OK ou non installé"
$DRUSH -y pm:uninstall media_album_av_common -y 2>/dev/null || echo "media_album_av_common: OK ou non installé"
$DRUSH -y pm:uninstall media_album_av -y 2>/dev/null || echo "media_album_av: OK ou non installé"
$DRUSH -y pm:uninstall media_drop -y 2>/dev/null || echo "media_drop: OK ou non installé"

echo "=== ÉTAPE 2 : Suppression des configurations ==="
$DRUSH sql-query "DELETE FROM drupal_config WHERE name LIKE 'media_drop.%';" 2>/dev/null && echo "✓ Config media_drop supprimées" || echo "✗ Pas de config media_drop"
$DRUSH sql-query "DELETE FROM drupal_config WHERE name LIKE 'media_album_%';" 2>/dev/null && echo "✓ Config media_album supprimées" || echo "✗ Pas de config media_album"
$DRUSH sql-query "DELETE FROM drupal_config WHERE name LIKE 'lightgallery_%';" 2>/dev/null && echo "✓ Config lightgallery supprimées" || echo "✗ Pas de config lightgallery"
$DRUSH sql-query "DELETE FROM drupal_config WHERE name LIKE 'field.field.media.media_album_av_%';" \
  && echo "✓ Config field.field.media supprimées"
$DRUSH sql-query "DELETE FROM drupal_config WHERE name LIKE 'field.field.node.media_album_av.%';" \
  && echo "✓ Config field.field.node supprimées"
$DRUSH sql-query "DELETE FROM drupal_config WHERE name LIKE 'field.storage.media.field_media_album_av_%';" \
  && echo "✓ Config field.storage.media supprimées"
$DRUSH sql-query "DELETE FROM drupal_config WHERE name LIKE 'field.storage.node.field_media_album_av_%';" \
  && echo "✓ Config field.storage.node supprimées"
$DRUSH sql-query "DELETE FROM drupal_config WHERE name LIKE 'core.entity_form_display.media.media_album_av_%';" \
  && echo "✓ Config form_display media supprimées"
$DRUSH sql-query "DELETE FROM drupal_config WHERE name LIKE 'core.entity_view_display.media.media_album_av_%';" \
  && echo "✓ Config view_display media supprimées"
$DRUSH sql-query "DELETE FROM drupal_config WHERE name LIKE 'core.entity_form_display.node.media_album_av%';" \
  && echo "✓ Config form_display node supprimées"
$DRUSH sql-query "DELETE FROM drupal_config WHERE name LIKE 'core.entity_view_display.node.media_album_av%';" \
  && echo "✓ Config view_display node supprimées"
$DRUSH sql-query "DELETE FROM drupal_config WHERE name LIKE 'media.type.media_album_av_%';" \
  && echo "✓ Config media types supprimées"
$DRUSH sql-query "DELETE FROM drupal_config WHERE name LIKE 'node.type.media_album_av%';" \
  && echo "✓ Config node type supprimé"
$DRUSH sql-query "DELETE FROM drupal_config WHERE name LIKE 'taxonomy.vocabulary.media_album_av_%';" \
  && echo "✓ Config taxonomies supprimées"

echo "=== ÉTAPE 3 : Suppression des vues personnalisées ==="
$DRUSH sql-query "DELETE FROM drupal_config WHERE name IN ('views.view.media_album_av', 'views.view.media_album_av_editor', 'views.view.media_drop_manage');" 2>/dev/null && echo "✓ Vues supprimées" || echo "✗ Pas de vues trouvées"

echo "=== ÉTAPE 3b : Déconfigurer Media Directories ==="
$DRUSH -y config:set media_directories.settings directory_taxonomy "" 2>/dev/null \
  && echo "✓ Media Directories découplé du vocabulary media_album_av_folders" \
  || echo "⚠ Impossible de modifier media_directories.settings"

echo "=== ÉTAPE 4 : Suppression des taxonomies ==="
# Suppression des termes
$DRUSH sql-query "DELETE FROM drupal_taxonomy_term_field_data WHERE vid IN ('media_album_av_folders', 'media_album_av_event');"
$DRUSH sql-query "DELETE FROM drupal_taxonomy_term_data WHERE vid IN ('media_album_av_folders', 'media_album_av_event');"
# Suppression des vocabulaires via l'API Drupal
$DRUSH eval "
\$vocabularies = ['media_album_av_authors', 'media_album_av_category', 'media_album_av_event', 'media_album_av_folders'];
foreach (\$vocabularies as \$vid) {
  \$vocab = Drupal\taxonomy\Entity\Vocabulary::load(\$vid);
  if (\$vocab) {
    \$vocab->delete();
    echo 'Vocabulaire ' . \$vid . ' supprimé' . PHP_EOL;
  }
}
" 2>/dev/null && echo "✓ Vocabulaires et termes supprimés" || echo "✗ Pas de vocabulaires trouvés"

echo "=== ÉTAPE 5 : Suppression des tables ==="
$DRUSH sql-query "DROP TABLE IF EXISTS drupal_media_drop_depots;" && echo "✓ Table drupal_media_drop_depots supprimée" || echo "✗ Table inexistante"
$DRUSH sql-query "DROP TABLE IF EXISTS drupal_media_drop_mime_mapping;" && echo "✓ Table drupal_media_drop_mime_mapping supprimée" || echo "✗ Table inexistante"
$DRUSH sql-query "DROP TABLE IF EXISTS drupal_media_drop_uploads;" && echo "✓ Table drupal_media_drop_uploads supprimée" || echo "✗ Table inexistante"

echo "=== ÉTAPE 6 : Vider les caches ==="
$DRUSH cache:rebuild && echo "✓ Caches vidés"

echo ""
echo "✅ DÉSINSTALLATION COMPLÈTE TERMINÉE!"
