<?php

namespace Drupal\generate_style_theme\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block\Entity\Block;
use Drupal\system\Entity\Menu;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Stephane888\Debug\Repositories\ConfigDrupal;
use Drupal\generate_style_theme\Entity\ConfigThemeEntity;
use Drupal\ovh_api_rest\Entity\DomainOvhEntity;

/**
 * Provides a form for deleting Config theme entity entities.
 *
 * @ingroup generate_style_theme
 */
class ConfigThemeEntityDeleteForm extends ContentEntityDeleteForm {
  /**
   *
   * @var ConfigThemeEntity
   */
  protected $entity;
  
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $conf = ConfigDrupal::config("generate_style_theme.settings");
    
    if (!empty($conf['tab1']['use_domain']) && \Drupal::moduleHandler()->moduleExists('domain')) {
      $domainId = $this->entity->getHostname();
      // Suppression de blocs personnaliser.
      $entity_type_id = 'block_content';
      $query = $this->entityTypeManager->getStorage($entity_type_id)->getQuery();
      // $query->condition('status', 1);
      $query->condition('field_domain_access', $domainId);
      $ids = $query->execute();
      $form['block_content'] = [
        '#type' => 'details',
        '#title' => 'Les blocs qui seront supprimés : ' . count($ids),
        '#open' => false
      ];
      if (!empty($ids)) {
        $entities = $this->entityTypeManager->getStorage($entity_type_id)->loadMultiple($ids);
        foreach ($entities as $entity) {
          /**
           *
           * @var BlockContent $entity
           */
          $form['block_content']['html'][] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $entity->bundle() . ' -> ' . $entity->label()
          ];
          $form['block_content']['id'][] = [
            '#type' => 'textfield',
            '#default_value' => $entity->id(),
            '#disabled' => true
          ];
        }
      }
      // Suppression de menus.
      $entity_type_id = 'menu';
      $query = $this->entityTypeManager->getStorage($entity_type_id)->getQuery();
      // $query->condition('status', 1);
      $query->condition('id', $domainId, 'CONTAINS');
      $ids = $query->execute();
      $form['menu'] = [
        '#type' => 'details',
        '#title' => 'Les menus qui seront supprimés : ' . count($ids),
        '#open' => false
      ];
      if ($ids) {
        $entities = $this->entityTypeManager->getStorage($entity_type_id)->loadMultiple($ids);
        foreach ($entities as $menu) {
          /**
           *
           * @var Menu $menu
           */
          $form['menu']['html'][] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $menu->label() . ' : ' . $menu->id()
          ];
          $form['menu']['id'][] = [
            '#type' => 'textfield',
            '#default_value' => $menu->id(),
            '#disabled' => true
          ];
        }
      }
      // Suppression des blocks.
      $entity_type_id = 'block';
      $query = $this->entityTypeManager->getStorage($entity_type_id)->getQuery();
      // $query->condition('status', 1);
      $query->condition('id', $domainId, 'CONTAINS');
      $ids = $query->execute();
      $form['block'] = [
        '#type' => 'details',
        '#title' => 'Les blocks qui seront supprimés : ' . count($ids),
        '#open' => false
      ];
      if ($ids) {
        $entities = $this->entityTypeManager->getStorage($entity_type_id)->loadMultiple($ids);
        foreach ($entities as $block) {
          /**
           *
           * @var Block $menu
           */
          $form['block']['html'][] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $block->label() . ' : ' . $block->id()
          ];
          $form['block']['id'][] = [
            '#type' => 'textfield',
            '#default_value' => $block->id(),
            '#disabled' => true
          ];
        }
      }
      // Suppression des nodes.
      $entity_type_id = 'node';
      $query = $this->entityTypeManager->getStorage($entity_type_id)->getQuery();
      // $query->condition('status', 1);
      $query->condition('field_domain_access', $domainId);
      $ids = $query->execute();
      $form['node'] = [
        '#type' => 'details',
        '#title' => 'Les nodes qui seront supprimés : ' . count($ids),
        '#open' => false
      ];
      if ($ids) {
        $entities = $this->entityTypeManager->getStorage($entity_type_id)->loadMultiple($ids);
        foreach ($entities as $node) {
          /**
           *
           * @var Node $node
           */
          $form['node']['html'][] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $node->bundle() . ' -> ' . $node->label()
          ];
          $form['node']['id'][] = [
            '#type' => 'textfield',
            '#default_value' => $node->id(),
            '#disabled' => true
          ];
        }
      }
      // Suppression des domain_ovh_entity. Sa suppresion doit entrainer la
      // suppression de "donnee_internet_entity";
      $entity_type_id = 'domain_ovh_entity';
      $query = $this->entityTypeManager->getStorage($entity_type_id)->getQuery();
      // $query->condition('status', 1);
      $query->condition('domain_id_drupal', $domainId);
      $ids = $query->execute();
      $form['domain_ovh_entity'] = [
        '#type' => 'details',
        '#title' => 'Les domain_ovh_entity qui seront supprimés : ' . count($ids),
        '#open' => false
      ];
      //
      if ($ids) {
        $entities = $this->entityTypeManager->getStorage($entity_type_id)->loadMultiple($ids);
        foreach ($entities as $DomainOvhEntity) {
          /**
           *
           * @var DomainOvhEntity $DomainOvhEntity
           */
          $form['domain_ovh_entity']['html'][] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $DomainOvhEntity->bundle() . ' -> ' . $DomainOvhEntity->label()
          ];
          $form['domain_ovh_entity']['id'][] = [
            '#type' => 'textfield',
            '#default_value' => $DomainOvhEntity->id(),
            '#disabled' => true
          ];
        }
      }
      // Suppression des site_type_datas.
      $entity_type_id = 'site_internet_entity';
      $field_access = \Drupal\domain_access\DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD;
      $query = $this->entityTypeManager->getStorage($entity_type_id)->getQuery();
      // $query->condition('status', 1);
      $query->condition($field_access, $domainId);
      $ids = $query->execute();
      $form['site_internet_entity'] = [
        '#type' => 'details',
        '#title' => ' Les site_internet_entity qui seront supprimés : ' . count($ids),
        '#open' => false
      ];
      //
      if ($ids) {
        $entities = $this->entityTypeManager->getStorage($entity_type_id)->loadMultiple($ids);
        foreach ($entities as $site_internet_entity) {
          /**
           *
           * @var DomainOvhEntity $DomainOvhEntity
           */
          $form['site_internet_entity']['html'][] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $site_internet_entity->bundle() . ' -> ' . $site_internet_entity->label()
          ];
          $form['site_internet_entity']['id'][] = [
            '#type' => 'textfield',
            '#default_value' => $site_internet_entity->id(),
            '#disabled' => true
          ];
        }
      }
    }
    return $form;
  }
  
}
