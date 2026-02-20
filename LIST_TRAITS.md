Services disponibles dans media_album_av_common:
media_album_av_common.field_widget_factory
media_album_av_common.media_view_renderer
media_album_av_common.directory_service
media_album_av_common.media_order_service
media_album_av_common.grouping_fields
media_album_av_common.album_grouping_config

Services disponibles dans media_taxonomy_service:
media_taxonomy_service.directory_service (classe: DirectoryService)

Traits disponibles dans media_album_av_common:
📌 FieldWidgetBuilderTrait
buildFieldWidget($field_config, $default_value = NULL, $additional_options = []) - Construit un widget de formulaire pour un champ
getFieldType($field_config) - Récupère le type d'un champ
getFieldLabel($field_config) - Récupère le libellé d'un champ
createBaseWidget($field_type, $field_label, $default_value = NULL) - Crée la structure de base du widget selon le type
buildEntityReferenceWidget(...) - Widget spécialisé pour les références d'entités
📌 MediaTrait
getMediaThumbnail(EntityInterface $media, $style_name = 'medium') - Récupère la vignette et les métadonnées d'un média
getThumbnailSize($style_name = 'medium') - Récupère la taille de la vignette
getMediaEntity(ResultRow $row) - Récupère l'entité média d'une ligne de vue
getReferencedMediaEntity(ResultRow $row) - Récupère l'entité média référencée
getMediaReferenceField($entity) - Trouve le champ qui référence les médias
📌 CustomFieldsTrait
getCustomFields(EntityInterface $entity) - Récupère les champs custom d'une entité (sans les champs média principaux)
getCustomFieldValues(EntityInterface $entity) - Récupère les valeurs des champs custom
📌 ExifFieldDefinitionsTrait
getExifFieldKeys() - Liste des clés EXIF supportées
getExifFieldTypeMap() - Mapping des types de champs EXIF
getExifFieldLabelMap() - Labels humains des champs EXIF
📌 WidgetUpdateTrait
updateFieldAndUserInput(FormStateInterface $form_state, $field_name, $new_values, $operation_type, $storage_data = []) - Met à jour un champ d'entité et les valeurs utilisateur avec mise à jour AJAX

Méthodes principales de DirectoryService (media_taxonomy_service):
createDirectoryTerm($vocabulary_id, $term_name, $parent_id = 0) - Crée un terme de taxonomie
deleteDirectoryTerm($term_id) - Supprime un terme
moveDirectoryTerm($term_id, $parent_id) - Déplace un terme sous un parent
getDirectoryTreeData($vocabulary_id, $selected_tid = NULL) - Récupère l'arborescence pour jstree
buildTermPath($term) - Construit le chemin de fichier à partir du breadcrumb du terme
buildDirectoryPathFromTerm($term_id = NULL) - 🎯 Récupère le chemin répertoire d'un terme par son ID
getOrCreateTerm($vocabulary_id, $term_name, $parent_tid = 0) - Récupère ou crée un terme
getMediaDirectoriesVocabulary() - Récupère le vocabulaire des répertoires média