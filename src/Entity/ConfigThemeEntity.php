<?php

namespace Drupal\generate_style_theme\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\file\Entity\File;
use Drupal\Component\Serialization\Json;

/**
 * Defines the Config theme entity entity.
 *
 * @ingroup generate_style_theme
 *
 * @ContentEntityType(
 *   id = "config_theme_entity",
 *   label = @Translation("Config theme entity"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\generate_style_theme\ConfigThemeEntityListBuilder",
 *     "views_data" = "Drupal\generate_style_theme\Entity\ConfigThemeEntityViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\generate_style_theme\Form\ConfigThemeEntityForm",
 *       "add" = "Drupal\generate_style_theme\Form\ConfigThemeEntityForm",
 *       "edit" = "Drupal\generate_style_theme\Form\ConfigThemeEntityForm",
 *       "delete" = "Drupal\generate_style_theme\Form\ConfigThemeEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\generate_style_theme\ConfigThemeEntityHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\generate_style_theme\ConfigThemeEntityAccessControlHandler",
 *   },
 *   base_table = "config_theme_entity",
 *   translatable = FALSE,
 *   admin_permission = "administer config theme entity entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "hostname",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "published" = "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/config_theme_entity/{config_theme_entity}",
 *     "add-form" = "/admin/structure/config_theme_entity/add",
 *     "edit-form" = "/admin/structure/config_theme_entity/{config_theme_entity}/edit",
 *     "delete-form" = "/admin/structure/config_theme_entity/{config_theme_entity}/delete",
 *     "collection" = "/admin/structure/config_theme_entity",
 *   },
 *   field_ui_base_route = "config_theme_entity.settings",
 *   constraints = {
 *     "ValidateThemeCreate" = {}
 *   }
 * )
 */
class ConfigThemeEntity extends ContentEntityBase implements ConfigThemeEntityInterface {
  use EntityChangedTrait;
  use EntityPublishedTrait;
  
  /**
   *
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
      'settheme_as_defaut' => TRUE
    ];
  }
  
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);
    /**
     *
     * @var \Drupal\generate_style_theme\Entity\ConfigThemeEntity $entity
     */
    $entity = reset($entities);
    // Array entity to delete.
    $entitiesIdDelete = [
      'block_content',
      'node',
      'site_internet_entity',
      'menu',
      'block',
      'domain_ovh_entity',
      'domain'
    ];
    /**
     * On supprime le contenu en relation avec ce theme.
     */
    if ($entity && $entity->id()) {
      $domainId = $entity->getHostname();
      $entityTypeManager = \Drupal::entityTypeManager();
      
      /**
       * On retire les enregistrements sur le serveurs ( vhost ).
       *
       * @var \Drupal\ovh_api_rest\Services\ManageRegisterDomain $ManageRegisterDomain
       */
      $ManageRegisterDomain = \Drupal::service('ovh_api_rest.manage');
      $ManageRegisterDomain->removeDomain($domainId);
      //
      foreach ($entitiesIdDelete as $entity_type_id) {
        switch ($entity_type_id) {
          case 'block_content':
          case 'node':
          case 'site_internet_entity':
            $query = $entityTypeManager->getStorage($entity_type_id)->getQuery();
            $query->condition('field_domain_access', $domainId);
            $ids = $query->execute();
            if (!empty($ids)) {
              $entitiesDelete = $entityTypeManager->getStorage($entity_type_id)->loadMultiple($ids);
              foreach ($entitiesDelete as $entityDelete) {
                $entityDelete->delete();
              }
            }
            break;
          case 'menu':
          case 'block':
            $query = $entityTypeManager->getStorage($entity_type_id)->getQuery();
            $query->condition('id', $domainId, 'CONTAINS');
            $ids = $query->execute();
            if (!empty($ids)) {
              $entitiesDelete = $entityTypeManager->getStorage($entity_type_id)->loadMultiple($ids);
              foreach ($entitiesDelete as $entityDelete) {
                $entityDelete->delete();
              }
            }
          case 'domain_ovh_entity':
            $query = $entityTypeManager->getStorage($entity_type_id)->getQuery();
            $query->condition('domain_id_drupal', $domainId);
            $ids = $query->execute();
            if (!empty($ids)) {
              $entitiesDelete = $entityTypeManager->getStorage($entity_type_id)->loadMultiple($ids);
              foreach ($entitiesDelete as $entityDelete) {
                $entityDelete->delete();
              }
            }
            break;
          case 'domain':
            $query = $entityTypeManager->getStorage($entity_type_id)->getQuery();
            $query->condition('id', $domainId, '=');
            $ids = $query->execute();
            if (!empty($ids)) {
              $entitiesDelete = $entityTypeManager->getStorage($entity_type_id)->loadMultiple($ids);
              foreach ($entitiesDelete as $entityDelete) {
                $entityDelete->delete();
              }
            }
            break;
          default:
            ;
            break;
        }
      }
      /**
       * On desinstalle le theme.
       *
       * @var \Drupal\Core\Extension\ThemeInstaller $ThemeInstaller
       */
      try {
        $ThemeInstaller = \Drupal::service('theme_installer');
        $theme_list = [
          $domainId => $domainId
        ];
        $ThemeInstaller->uninstall($theme_list);
      }
      catch (\Exception $e) {
        \Drupal::messenger()->addWarning(" Le theme n'a pas pu etre desintall?? : " . $domainId);
      }
    /**
     * Suppresion du dossier du theme.
     */
    }
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function getHostname() {
    return $this->get('hostname')->value;
  }
  
  /**
   * -
   */
  public function getLogo() {
    $fid = $this->get('logo')->target_id;
    if (!empty($fid)) {
      $file = File::load($fid);
      if ($file) {
        // permet de generer le fichier image, car on remarque que le fichier ne
        // se genere via theme_get_setting('logo.url');
        $img2 = ImageStyle::load('medium')->buildUrl($file->getFileUri());
        file_get_contents($img2);
        // return path to save in theme.settings.logo.url
        return ImageStyle::load('medium')->buildUri($file->getFileUri());
      }
    }
    //
    return null;
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function setHostname($name) {
    $this->set('hostname', $name);
    return $this;
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }
  
  /**
   * Retourne la premiere ocurence trouv??.
   *
   * @return array ["name" => "bleu"
   *         "color" => "#7AE864"]
   */
  public function getColorPrimary() {
    if ($this->get('color_primary')->first())
      return $this->get('color_primary')->first()->getValue();
  }
  
  public function getColorSecondaire() {
    if ($this->get('color_secondaire')->first())
      return $this->get('color_secondaire')->first()->getValue();
  }
  
  public function getColorLinkHover() {
    if ($this->get('color_link_hover')->first())
      return $this->get('color_link_hover')->first()->getValue();
  }
  
  /**
   *
   * @return mixed
   */
  public function getColorBackground() {
    if ($this->get('wbubackground')->first())
      return $this->get('wbubackground')->first()->getValue();
  }
  
  /**
   *
   * @remove to 2x
   * @deprecated
   * @return mixed
   */
  public function getLirairy() {
    return $this->get('lirairy')->value;
  }
  
  /**
   * --
   */
  public function getwbu_titre_suppra() {
    return $this->get('wbu_titre_suppra')->value;
  }
  
  /**
   * --
   */
  public function getwbu_titre_biggest() {
    return $this->get('wbu_titre_biggest')->value;
  }
  
  /**
   * --
   */
  public function getwbu_titre_big() {
    return $this->get('wbu_titre_big')->value;
  }
  
  /**
   * --
   */
  public function getH1FontSize() {
    if ($this->get('h1_font_size')->first())
      return $this->get('h1_font_size')->first()->getValue();
  }
  
  /**
   * --
   */
  public function getH2FontSize() {
    if ($this->get('h2_font_size')->first())
      return $this->get('h2_font_size')->first()->getValue();
  }
  
  /**
   * --
   */
  public function getH3FontSize() {
    return $this->get('h3_font_size')->value;
  }
  
  /**
   * --
   */
  public function getH4FontSize() {
    return $this->get('h4_font_size')->value;
  }
  
  /**
   * --
   */
  public function getH5FontSize() {
    return $this->get('h5_font_size')->value;
  }
  
  /**
   * --
   */
  public function getH6FontSize() {
    return $this->get('h6_font_size')->value;
  }
  
  /**
   *
   * @return mixed
   */
  public function gettext_font_size() {
    if ($this->get('text_font_size')->first())
      return $this->get('text_font_size')->first()->getValue();
  }
  
  /**
   *
   * @return mixed
   */
  public function getspace_bottom() {
    if ($this->get('space_bottom')->first())
      return $this->get('space_bottom')->first()->getValue();
  }
  
  /**
   *
   * @return mixed
   */
  public function getspace_top() {
    if ($this->get('space_top')->first())
      return $this->get('space_top')->first()->getValue();
  }
  
  /**
   *
   * @return mixed
   */
  public function getspace_inner_top() {
    if ($this->get('space_inner_top')->first())
      return $this->get('space_inner_top')->first()->getValue();
  }
  
  public function postSave($storage, $update = TRUE) {
    // \Drupal::messenger()->addStatus('postSave');
    parent::postSave($storage, $update);
  }
  
  public function preSave($storage) {
    // \Drupal::messenger()->addStatus('preSave');
    // On doit nettoyer le nom d'hote, car il est utilis?? comme nom du theme.
    $themeName = str_replace([
      ' ',
      '-',
      '.'
    ], '_', strtolower($this->getHostname()));
    $this->set('hostname', preg_replace('/[^a-z0-9\_]/', "", $themeName));
    parent::preSave($storage);
  }
  
  /**
   * NB: application de ses informations se fait apres la creation du theme.
   *
   * @see \Drupal\generate_style_theme\Services\GenerateStyleTheme::setConfigTheme()
   *
   * @return Json
   */
  public function getsite_config() {
    return $this->get('site_config')->value;
  }
  
  public function SetThemeAsDefaut() {
    return $this->get('settheme_as_defaut')->value;
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    
    // Add the published field.
    $fields += static::publishedBaseFieldDefinitions($entity_type);
    //
    // $fields['user_id'] =
    // BaseFieldDefinition::create('entity_reference')->setLabel(t('Authored
    // by'))->setDescription(t('The user ID of author of the Domain buy
    // entity.'))->setRevisionable(TRUE)->setSetting('target_type',
    // 'user')->setSetting('handler', 'default')->setDisplayOptions('view', [
    // 'label' => 'hidden',
    // 'type' => 'author',
    // 'weight' => 0
    // ])->setDisplayOptions('form', [
    // 'type' => 'entity_reference_autocomplete',
    // 'weight' => 5,
    // 'settings' => [
    // 'match_operator' => 'CONTAINS',
    // 'size' => '60',
    // 'autocomplete_type' => 'tags',
    // 'placeholder' => ''
    // ]
    // ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view',
    // TRUE);
    // $fields['user_id'] = BaseFieldDefinition::create('string')->setLabel("
    // Espace interne 2 ")->setDisplayOptions('form', [
    // 'type' => 'number'
    // ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view',
    // TRUE)->setDefaultValue(0);
    //
    $fields['hostname'] = BaseFieldDefinition::create('wbumenudomaineditlink')->setLabel(t(' Hostname ou nom de domaine '))->setRequired(TRUE)->setDisplayOptions('form', [
      'type' => 'wbumenudomainhost',
      'settings' => [],
      'weight' => -3
    ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE)->addConstraint('UniqueField');
    /**
     *
     * @delete before 2x
     */
    $fields['lirairy'] = BaseFieldDefinition::create('list_string')->setLabel(t(' Selectionn?? un style pour ce domaine '))->setRequired(False)->setDescription(t(' Selectionner le nom de domaine ( ?? supprimer plus tard ) '))->setSetting('allowed_values_function', [
      '\Drupal\generate_style_theme\GenerateStyleTheme',
      'getLibrairiesCurrentTheme'
    ])->setDisplayOptions('view', [
      'label' => 'above'
    ])->setDisplayOptions('form', [
      'type' => 'options_select',
      'settings' => [],
      'weight' => -3
    ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);
    
    $fields['logo'] = BaseFieldDefinition::create('image')->setLabel(' Logo .. ')->setSetting('preview_image_style', 'medium')->setDisplayOptions('form', [
      'type' => 'image_image',
      'settings' => [
        'preview_image_style' => 'medium'
      ]
    ])->setDisplayConfigurable('form', true)->setDisplayConfigurable('view', TRUE)->setSetting("min_resolution", "150x120");
    
    $fields['color_primary'] = BaseFieldDefinition::create('color_theme_field_type')->setLabel(' Couleur primaire ')->setRequired(TRUE)->setDisplayOptions('form', [
      'type' => 'colorapi_color_display'
    ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE)->setDefaultValue([
      'color' => '#CE3B3B',
      'name' => ''
    ]);
    
    $fields['color_secondaire'] = BaseFieldDefinition::create('color_theme_field_type')->setLabel(" Couleur secondaire  ")->setRequired(TRUE)->setDisplayOptions('form', [
      'type' => 'colorapi_color_display'
    ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE)->setDefaultValue([
      'color' => '#DD731D',
      'name' => ''
    ]);
    
    $fields['color_link_hover'] = BaseFieldDefinition::create('color_theme_field_type')->setLabel(" Couleur des liens ")->setRequired(TRUE)->setDisplayOptions('form', [
      'type' => 'colorapi_color_display'
    ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE)->setDefaultValue([
      'color' => '#F88C12',
      'name' => ''
    ]);
    
    $fields['wbubackground'] = BaseFieldDefinition::create('color_theme_field_type')->setLabel(" Couleur d'arri??re plan ")->setRequired(TRUE)->setDisplayOptions('form', [
      'type' => 'colorapi_color_display'
    ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE)->setDefaultValue([
      'color' => '#0F103E',
      'name' => ''
    ]);
    $fields['wbu_titre_suppra'] = BaseFieldDefinition::create('string')->setLabel(" Taille de la police de titre (wbu-titre-suppra) ")->setDisplayOptions('form', [
      'type' => 'string_textfield'
    ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE)->setDefaultValue('6.4rem');
    
    $fields['wbu_titre_biggest'] = BaseFieldDefinition::create('string')->setLabel(" Taille de la police de titre (wbu-titre-biggest) ")->setDisplayOptions('form', [
      'type' => 'string_textfield'
    ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE)->setDefaultValue('5.4rem');
    
    $fields['wbu_titre_big'] = BaseFieldDefinition::create('string')->setLabel(" Taille de la police de titre (wbu-titre-big) ")->setDisplayOptions('form', [
      'type' => 'string_textfield'
    ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE)->setDefaultValue('4.4rem');
    
    $fields['h1_font_size'] = BaseFieldDefinition::create('string')->setLabel(" Taille de la police de titre (h1) ")->setDisplayOptions('form', [
      'type' => 'string_textfield'
    ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE)->setDefaultValue('3.4rem');
    
    $fields['h2_font_size'] = BaseFieldDefinition::create('string')->setLabel(" Taille de la police de sous titre (h2) ")->setDisplayOptions('form', [
      'type' => 'string_textfield'
    ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE)->setDefaultValue('2.4rem');
    
    $fields['h3_font_size'] = BaseFieldDefinition::create('string')->setLabel(" Taille de la police de sous titre (h3) ")->setDisplayOptions('form', [
      'type' => 'string_textfield'
    ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE)->setDefaultValue('1.8rem');
    
    $fields['h4_font_size'] = BaseFieldDefinition::create('string')->setLabel(" Taille de la police de sous titre (h4) ")->setDisplayOptions('form', [
      'type' => 'string_textfield'
    ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE)->setDefaultValue('1.6rem');
    
    $fields['h5_font_size'] = BaseFieldDefinition::create('string')->setLabel(" Taille de la police de sous titre (h5) ")->setDisplayOptions('form', [
      'type' => 'string_textfield'
    ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE)->setDefaultValue('1.6rem');
    
    $fields['h6_font_size'] = BaseFieldDefinition::create('string')->setLabel(" Taille de la police de sous titre (h6) ")->setDisplayOptions('form', [
      'type' => 'string_textfield'
    ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE)->setDefaultValue('1.4rem');
    
    $fields['text_font_size'] = BaseFieldDefinition::create('string')->setLabel(" Taille de la police par defaut ")->setDisplayOptions('form', [
      'type' => 'string_textfield'
    ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE)->setDefaultValue('1.4rem');
    
    $fields['space_bottom'] = BaseFieldDefinition::create('string')->setLabel(" Espace du bas entre les blocs ")->setDisplayOptions('form', [
      'type' => 'number'
    ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE)->setDefaultValue(5);
    
    $fields['space_top'] = BaseFieldDefinition::create('string')->setLabel(" Espace du haut entre les blocs ")->setDisplayOptions('form', [
      'type' => 'number'
    ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE)->setDefaultValue(4);
    
    $fields['space_inner_top'] = BaseFieldDefinition::create('string')->setLabel(" Espace interne ")->setDisplayOptions('form', [
      'type' => 'number'
    ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE)->setDefaultValue(0.5);
    
    $fields['status']->setDescription(t(' A boolean indicating whether the Config theme entity is published. '))->setDisplayOptions('form', [
      'type' => 'boolean_checkbox',
      'weight' => -3
    ]);
    $fields['settheme_as_defaut'] = BaseFieldDefinition::create('boolean')->setLabel(" Definir ce theme comme theme par defaut ")->setDisplayOptions('form', [
      'type' => 'boolean_checkbox',
      'weight' => -3
    ])->setDisplayOptions('view', [])->setDisplayConfigurable('view', TRUE)->setDisplayConfigurable('form', true)->setDefaultValue(true);
    
    $fields['run_npm'] = BaseFieldDefinition::create('boolean')->setLabel(" Generate files style ? ")->setDisplayOptions('form', [
      'type' => 'boolean_checkbox',
      'weight' => -3
    ])->setDisplayOptions('view', [])->setDisplayConfigurable('view', TRUE)->setDisplayConfigurable('form', true)->setDefaultValue(true);
    // NB: application de ses informations se fait apres la creation du theme.
    // @see
    // Drupal\generate_style_theme\Services\GenerateStyleTheme::setConfigTheme()
    $fields['site_config'] = BaseFieldDefinition::create('wbumenudomaineditlink')->setLabel(t(' Information de configuration du domaine '))->setRequired(false)->setDisplayOptions('form', [
      'type' => 'wbumenudomainsiteconfig',
      'settings' => [],
      'weight' => -3
    ])->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE)->setConstraints([
      'UniqueField' => []
    ]);
    
    $fields['created'] = BaseFieldDefinition::create('created')->setLabel(t('Created'))->setDescription(t('The time that the entity was created.'));
    
    $fields['changed'] = BaseFieldDefinition::create('changed')->setLabel(t('Changed'))->setDescription(t('The time that the entity was last edited.'));
    
    return $fields;
  }
  
}