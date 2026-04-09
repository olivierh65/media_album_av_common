# 📚 Référence complète des fonctions et méthodes - Module media_album_av_common

**Date**: 9 avril 2026  
**Module**: media_album_av_common  
**Lieu**: `/web/modules/custom/media_album_av_common`  

> Ce document rassemble TOUTES les fonctions et méthodes publiques du module, avec leurs paramètres d'entrée, types de retour et descriptions.

---

## 📋 Table des matières

1. [Contrôleurs](#contrôleurs)
2. [Formulaires](#formulaires)
3. [Services](#services)
4. [Plugins Actions](#plugins-actions)
5. [Plugins Field Widgets](#plugins-field-widgets)
6. [Traits](#traits)

---

## Contrôleurs

### MediaOrderController
📄 Fichier: `src/Controller/MediaOrderController.php`

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `__construct()` | `$media_order_service` (MediaOrderService) | **void** | Constructeur - Injecte service gestion commandes média |
| 2 | `create()` | `$container` (ContainerInterface) | **MediaOrderController** | Factory method statique - Crée instance via DI |
| 3 | `saveMediaOrder()` | `$request` (Request) | **JsonResponse** | Sauvegarde ordre et groupement médias via POST JSON, retourne JSON avec statut |

---

### TaxonomyDirectoryController
📄 Fichier: `src/Controller/TaxonomyDirectoryController.php`

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `__construct()` | `$directory_service` (DirectoryService) | **void** | Constructeur - Injecte service répertoire |
| 2 | `create()` | `$container` (ContainerInterface) | **TaxonomyDirectoryController** | Factory method statique - Crée instance via DI |
| 3 | `createTerm()` | `$request` (Request) | **JsonResponse** | Crée nouveau terme taxonomique via AJAX, retourne term_id et nom |
| 4 | `deleteTerm()` | `$request` (Request) | **JsonResponse** | Supprime terme taxonomique via AJAX |
| 5 | `moveTerm()` | `$request` (Request) | **JsonResponse** | Déplace terme (change parent), met à jour poids si fournis |
| 6 | `updateTerm()` | `$request` (Request) | **JsonResponse** | Mets à jour propriétés terme taxonomique |

---

### GroupingController
📄 Fichier: `src/Controller/GroupingController.php`

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `__construct()` | `$mediaViewRenderer` (MediaViewRendererService) | **void** | Constructeur - Injecte service rendu vues média |
| 2 | `create()` | `$container` (ContainerInterface) | **GroupingController** | Factory method statique - Crée instance via DI |
| 3 | `applyGrouping()` | `$request` (Request) | **JsonResponse** | Applique groupement à vue média et retourne HTML rendu avec groupes |

---

### ActionModalController
📄 Fichier: `src/Controller/ActionModalController.php`

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `open()` | `$action_id` (string), `$album_grp` (string) | **AjaxResponse** | Ouvre fenêtre modale pour configurer action, utilise ActionConfigForm |

---

## Formulaires

### ActionConfigForm
📄 Fichier: `src/Form/ActionConfigForm.php`

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `__construct()` | `$entity_type_manager` (EntityTypeManagerInterface), `$action_plugin_manager` (ActionManager), `$taxonomy_service` (DirectoryService) | **void** | Constructeur - Injecte les 3 services pour gestion actions et répertoires |
| 2 | `create()` | `$container` (ContainerInterface) | **ActionConfigForm** | Factory method statique - Crée instance via DI |
| 3 | `getFormId()` | *(aucun)* | **string** | Retourne 'media_album_action_config_form' |
| 4 | `buildForm()` | `$form` (array), `$form_state` (FormStateInterface), `$action_id` (string = NULL), `$album_grp` (string = NULL), `$prepared_data` (array = []) | **array** | Construit formulaire multi-étapes pour configurer action sur médias |

---

## Services

### MediaOrderService
📄 Fichier: `src/Service/MediaOrderService.php`

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `__construct()` | `$logger_factory` (LoggerChannelFactoryInterface) | **void** | Constructeur - Injecte fabrique logger |
| 2 | `create()` | `$container` (ContainerInterface) | **MediaOrderService** | Factory method statique - Crée instance via DI |
| 3 | `saveMediaOrder()` | `$data` (array) | **array** | Sauvegarde ordre média et appelle hooks d'autres modules |
| 4 | `orderMediaItems()` | `$media_items` (array) | **array** | Trie médias par poids dans nœuds parents et met à jour taxonomies |

---

### AlbumGroupingFieldsService
📄 Fichier: `src/Service/AlbumGroupingFieldsService.php`

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `__construct()` | `$config_factory` (ConfigFactoryInterface), `$entity_field_manager` (EntityFieldManagerInterface) | **void** | Constructeur - Injecte config et gestionnaire champs |
| 2 | `getNodeFields()` | *(aucun)* | **array** | Retourne tous champs nœud disponibles pour groupement sauf spécifiques |
| 3 | `getAcceptedMediaBundles()` | `$entity_type` (string = 'node'), `$bundle` (string = 'media_album_av'), `$field_name` (string = 'field_media_album_av_media') | **array** | Extrait bundles média acceptés du champ référence |
| 4 | `getMediaFields()` | *(aucun)* | **array** | Retourne tous champs média disponibles pour groupement |

---

### AlbumGroupingConfigService
📄 Fichier: `src/Service/AlbumGroupingConfigService.php`

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `__construct()` | `$entity_type_manager` (EntityTypeManagerInterface), `$grouping_fields_service` (AlbumGroupingFieldsService) | **void** | Constructeur - Injecte gestionnaire entités et service champs groupement |
| 2 | `getAlbumGroupingFields()` | `$album_node` (NodeInterface) | **array** | Retourne champs groupement configurés pour album avec termes et rendu |
| 3 | `getAlbumGroupingFieldsConfig()` | `$album_node` (NodeInterface) | **array** | Retourne config complète champs groupement avec métadonnées |
| 4 | `convertGroupingFieldsFormat()` | `$grouping_fields` (array) | **array** | Convertit du format service au format renderGrouping |
| 5 | `parseFieldName()` | `$field_name` (string) | **array\|null** | Parse nom champ préfixé (node:field ou media:field) |
| 6 | `getRenderedTerms()` | `$terms` (array) | **array** | Affiche noms termes taxonomiques utilisés |

---

### DirectoryService
📄 Fichier: `src/Service/DirectoryService.php`

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `__construct()` | `$entity_type_manager` (EntityTypeManagerInterface), `$file_system` (FileSystemInterface), `$logger_factory` (LoggerChannelFactoryInterface) | **void** | Constructeur - Injecte les 3 services : entités, fichier et logger |
| 2 | `getLogger()` | *(aucun)* | **LoggerInterface** | Retourne channel logger du service |
| 3 | `createDirectoryTerm()` | `$vocabulary_id` (string), `$term_name` (string), `$parent_id` (int = 0) | **Term** | Crée nouveau terme dans taxonomie répertoire |
| 4 | `deleteDirectoryTerm()` | `$term_id` (int) | **void** | Supprime terme de répertoire |
| 5 | `moveDirectoryTerm()` | `$term_id` (int), `$parent_id` (int) | **void** | Déplace terme en changeant parent |
| 6 | `getDirectoryTreeData()` | `$vocabulary_id` (string), `$selected_tid` (int = NULL) | **array** | Retourne arborescence répertoire au format jstree |
| 7 | `buildTreeNode()` | `$term` (TermInterface), `$vocabulary_id` (string), `$selected_tid` (int = NULL) | **array** | Construit nœud jstree pour terme |

---

### ExifFieldManager
📄 Fichier: `src/Service/ExifFieldManager.php`

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `__construct()` | `$entity_type_manager` (EntityTypeManagerInterface), `$logger_factory` (LoggerChannelFactoryInterface), `$messenger` (MessengerInterface) | **void** | Constructeur - Injecte services pour gestion EXIF |
| 2 | `createExifFieldsForMediaType()` | `$media_type_id` (string), `$exif_keys` (array = []) | **int** | Crée champs EXIF pour type média spécifique |
| 3 | `createExifFieldsForAllMediaTypes()` | `$exif_keys` (array = []) | **int** | Crée champs EXIF pour tous types média |
| 4 | `deleteExifFields()` | `$media_type_id` (string), `$delete_storage` (bool = FALSE) | **int** | Supprime champs EXIF d'un type média |

---

### FieldWidgetFactory
📄 Fichier: `src/Service/FieldWidgetFactory.php`

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `__construct()` | `$entity_type_manager` (EntityTypeManagerInterface), `$string_translation` (TranslationInterface = NULL) | **void** | Constructeur - Injecte gestionnaire entités et traduction |
| 2 | `buildWidget()` | `$field_config` (object), `$default_value` (mixed = NULL), `$options` (array = []) | **array** | Crée widget formulaire approprié selon type champ |
| 3 | `getFieldType()` | `$field_config` (object) | **string** | Extrait type champ depuis config |
| 4 | `getFieldLabel()` | `$field_config` (object) | **string** | Extrait label champ |
| 5 | `createBaseWidget()` | `$field_type` (string), `$field_label` (string), `$field_config` (object), `$default_value` (mixed = NULL) | **array** | Crée widget base selon type : textfield, textarea, select, etc. |

---

### MediaViewRendererService
📄 Fichier: `src/Service/MediaViewRendererService.php`

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `__construct()` | 7 paramètres : EntityTypeManagerInterface, ConfigFactoryInterface, ModuleHandlerInterface, RendererInterface, FileUrlGeneratorInterface, EntityFieldManagerInterface, LoggerChannelFactoryInterface | **void** | Constructeur - Injecte 7 services pour rendu média |
| 2 | `renderEmbeddedMediaView()` | `$view_id` (string), `$display_id` (string), `$arguments` (array = []), `$libraries` (array = []), `$grouping_override` (array = []) | **array** | Rend vue média intégrée avec groupement optionnel |

---

## Plugins Actions

### BaseAlbumAction
📄 Fichier: `src/Plugin/Action/BaseAlbumAction.php`

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `__construct()` | `$configuration` (array), `$plugin_id` (string), `$plugin_definition` (array), `$entity_type_manager` (EntityTypeManagerInterface), `$taxonomy_service` (DirectoryService) | **void** | Constructeur - Initialise action avec services entité et répertoire |
| 2 | `create()` | `$container` (ContainerInterface), `$configuration` (array), `$plugin_id` (string), `$plugin_definition` (array) | **BaseAlbumAction** | Factory method statique - Crée instance via DI |

---

### AddToAlbumAction
📄 Fichier: `src/Plugin/Action/AddToAlbumAction.php`

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `__construct()` | `$configuration` (array), `$plugin_id` (string), `$plugin_definition` (array), `$entity_type_manager` (EntityTypeManagerInterface), `$taxonomy_service` (DirectoryService) | **void** | Constructeur - Initialise avec move=FALSE |
| 2 | `buildConfigurationForm()` | `$form` (array), `$form_state` (FormStateInterface) | **array** | Formulaire configuration (vide pour cette action) |
| 3 | `executeMultiple()` | `$entities` (array) | **void** | Exécute action pour plusieurs entités, respecte ordre si disponible |
| 4 | `execute()` | `$entity` (EntityInterface = NULL) | **void** | Ajoute média à album sans déplacer fichiers |

---

### MoveMediaToAlbumAction
📄 Fichier: `src/Plugin/Action/MoveMediaToAlbumAction.php`

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `__construct()` | `$configuration` (array), `$plugin_id` (string), `$plugin_definition` (array), `$entity_type_manager` (EntityTypeManagerInterface), `$taxonomy_service` (DirectoryService) | **void** | Constructeur - Initialise avec move=TRUE |
| 2 | `executeMultiple()` | `$data` (array) | **array** | Déplace plusieurs médias vers album, retourne statut et media_ids |

---

### BulkEditMediaAction
📄 Fichier: `src/Plugin/Action/BulkEditMediaAction.php`

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `__construct()` | `$configuration` (array), `$plugin_id` (string), `$plugin_definition` (array), `$entity_type_manager` (EntityTypeManagerInterface) | **void** | Constructeur - Initialise action édition en masse |
| 2 | `create()` | `$container` (ContainerInterface), `$configuration` (array), `$plugin_id` (string), `$plugin_definition` (array) | **BulkEditMediaAction** | Factory method statique - Crée instance via DI |
| 3 | `getEntityTypeManager()` | *(aucun)* | **EntityTypeManagerInterface** | Retourne gestionnaire entités |
| 4 | `defaultConfiguration()` | *(aucun)* | **array** | Retourne config par défaut : field_values=[] |
| 5 | `buildConfigurationForm()` | `$form` (array), `$form_state` (FormStateInterface) | **array** | Crée formulaire pour éditer champs groupés médias sélectionnés |

---

### ExtractExifDataAction
📄 Fichier: `src/Plugin/Action/ExtractExifDataAction.php`

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `__construct()` | `$configuration` (array), `$plugin_id` (string), `$plugin_definition` (array), `$entity_type_manager` (EntityTypeManagerInterface), `$taxonomy_service` (DirectoryService), `$exif_field_manager` (ExifFieldManager) | **void** | Constructeur - Initialise avec service EXIF |
| 2 | `create()` | `$container` (ContainerInterface), `$configuration` (array), `$plugin_id` (string), `$plugin_definition` (array) | **ExtractExifDataAction** | Factory method statique - Crée instance via DI |
| 3 | `defaultConfiguration()` | *(aucun)* | **array** | Retourne config par défaut avec auto_create_fields et exif_keys |
| 4 | `buildConfigurationForm()` | `$form` (array), `$form_state` (FormStateInterface) | **array** | Crée formulaire pour sélection champs EXIF à extraire |

---

### SortMediaAction
📄 Fichier: `src/Plugin/Action/SortMediaAction.php`

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `defaultConfiguration()` | *(aucun)* | **array** | Retourne sort_by='title', sort_order='ASC' |
| 2 | `buildConfigurationForm()` | `$form` (array), `$form_state` (FormStateInterface) | **array** | Crée formulaire pour choisir critère et orientation du tri |
| 3 | `submitConfigurationForm()` | `&$form` (array), `$form_state` (FormStateInterface) | **void** | Sauvegarde choix tri |
| 4 | `executeMultiple()` | `$data` (array) | **array** | Trie médias selon critères et retourne statut |

---

### DeleteMediaWithFiles
📄 Fichier: `src/Plugin/Action/DeleteMediaWithFiles.php`

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `execute()` | `$entity` (EntityInterface = NULL) | **void** | Supprime média et tous ses fichiers associés |
| 2 | `access()` | `$object` (EntityInterface), `$account` (AccountInterface = NULL), `$return_as_object` (bool = FALSE) | **bool\|AccessResult** | Vérifie si utilisateur peut supprimer média |

---

### MoveMediaToDirectory
📄 Fichier: `src/Plugin/Action/MoveMediaToDirectory.php`

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `__construct()` | `$configuration` (array), `$plugin_id` (string), `$plugin_definition` (array), `$entity_type_manager` (EntityTypeManagerInterface), `$taxonomy_service` (DirectoryService) | **void** | Constructeur - Initialise action déplacement vers répertoire |
| 2 | `create()` | `$container` (ContainerInterface), `$configuration` (array), `$plugin_id` (string), `$plugin_definition` (array) | **MoveMediaToDirectory** | Factory method statique - Crée instance via DI |
| 3 | `defaultConfiguration()` | *(aucun)* | **array** | Retourne directory_tid=NULL |
| 4 | `buildConfigurationForm()` | `$form` (array), `$form_state` (FormStateInterface) | **array** | Crée formulaire pour sélectionner répertoire destination |

---

### AddMediaToNodeAction
📄 Fichier: `src/Plugin/Action/AddMediaToNodeAction.php`

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `__construct()` | `$configuration` (array), `$plugin_id` (string), `$plugin_definition` (array), `$entity_type_manager` (EntityTypeManagerInterface) | **void** | Constructeur - Initialise action |
| 2 | `create()` | `$container` (ContainerInterface), `$configuration` (array), `$plugin_id` (string), `$plugin_definition` (array) | **AddMediaToNodeAction** | Factory method statique - Crée instance via DI |
| 3 | `defaultConfiguration()` | *(aucun)* | **array** | Retourne node_id=NULL, directory_tid=NULL, field_values=[] |
| 4 | `buildConfigurationForm()` | `$form` (array), `$form_state` (FormStateInterface) | **array** | Formulaire multi-étapes pour sélectionner nœud et valeurs champs |
| 5 | `buildStepOne()` | `$form` (array), `$form_state` (FormStateInterface) | **array** | Étape 1 : sélection nœud et répertoire |
| 6 | `buildStepTwo()` | `$form` (array), `$form_state` (FormStateInterface) | **array** | Étape 2 : configuration valeurs champs |

---

## Plugins Field Widgets

### GroupingFieldsWidget
📄 Fichier: `src/Plugin/Field/FieldWidget/GroupingFieldsWidget.php`

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `__construct()` | `$plugin_id` (string), `$plugin_definition` (array), `$field_definition` (FieldDefinitionInterface), `$settings` (array), `$third_party_settings` (array), `$grouping_fields_service` (AlbumGroupingFieldsService) | **void** | Constructeur - Injecte service champs groupement |
| 2 | `create()` | `$container` (ContainerInterface), `$configuration` (array), `$plugin_id` (string), `$plugin_definition` (array) | **GroupingFieldsWidget** | Factory method statique - Crée instance via DI |
| 3 | `formElement()` | `$items` (FieldItemListInterface), `$delta` (int), `$element` (array), `&$form` (array), `$form_state` (FormStateInterface) | **array** | Crée widget tableau drag & drop pour ordonner niveaux groupement |
| 4 | `getFieldOptions()` | *(aucun)* | **array** | Retourne tous champs disponibles pour groupement (nœud et média) |
| 5 | `massageFormValues()` | `$values` (array), `$form` (array), `$form_state` (FormStateInterface) | **array** | Traite valeurs widget en triant par poids |

---

## Traits

| # | Trait | Fichier | Description |
|---|-------|---------|-------------|
| 1 | ExifFieldDefinitionsTrait | `src/Traits/ExifFieldDefinitionsTrait.php` | Définitions champs EXIF standards |
| 2 | CustomFieldsTrait | `src/Traits/CustomFieldsTrait.php` | Logique champs personnalisés |
| 3 | TaxonomyTrait | `src/Traits/TaxonomyTrait.php` | Gestion taxonomies |
| 4 | AlbumTrait | `src/Traits/AlbumTrait.php` | Logique albums |
| 5 | WidgetUpdateTrait | `src/Traits/WidgetUpdateTrait.php` | Mise à jour widgets |
| 6 | FieldWidgetBuilderTrait | `src/Traits/FieldWidgetBuilderTrait.php` | Construction widgets champs |
| 7 | ExifFieldDefinitionsTraitMediaAlbum | `src/Traits/ExifFieldDefinitionsTraitMediaAlbum.php` | Définitions EXIF album |
| 8 | MediaTrait | `src/Traits/MediaTrait.php` | Logique média |

*(Contiennent des méthodes protégées/utilitaires pour les classes les utilisant)*

---

## 📊 Résumé statistique

| Catégorie | Total |
|-----------|-------|
| **Contrôleurs** | 4 classes × 3-6 méthodes |
| **Formulaires** | 1 classe × 4 méthodes |
| **Services** | 6 classes × 2-7 méthodes |
| **Action Plugins** | 8 classes × 1-6 méthodes |
| **Field Widgets** | 1 classe × 5 méthodes |
| **Traits** | 8 (méthodes protégées) |
| **Total fichiers PHP** | 30 |
| **Total fonctions/méthodes publiques** | **80+** |

---

## 🎯 Architecture du module

Ce module fournit les **services et actions partagées** pour gestion des albums médias avec :

1. **Gestion de l'ordre des médias** (MediaOrderController, MediaOrderService) - Sauvegarde ordre et groupement
2. **Gestion des répertoires taxonomiques** (TaxonomyDirectoryController, DirectoryService) - CRUD termes
3. **Groupement dynamique** (GroupingController, AlbumGroupingConfigService) - Application regroupements Views
4. **Extraction EXIF** (ExifFieldManager, ExtractExifDataAction) - Création automatique champs EXIF
5. **Actions groupées** (8 plugins Action) - Ajouter/déplacer/supprimer/trier/éditer en masse
6. **Widgets spécialisés** (GroupingFieldsWidget) - Interface drag-drop pour configurer groupement

---

## 🔧 Comment utiliser ce document

1. **Sauvegarder ordre média**: Consultez MediaOrderController::saveMediaOrder()
2. **Gérer répertoires**: Consultez TaxonomyDirectoryController et DirectoryService
3. **Appliquer groupement**: Consultez GroupingController et AlbumGroupingConfigService
4. **Actions sur médias**: Consultez les 8 plugins Action (Add, Move, Delete, Sort, Extract EXIF, etc.)
5. **Configurer groupement**: Consultez GroupingFieldsWidget et AlbumGroupingFieldsService

---

**Dernière mise à jour**: 9 avril 2026
