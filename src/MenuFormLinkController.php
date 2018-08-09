<?php

namespace Drupal\bigmenu;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Defines class for MenuFormLinkController.
 */
class MenuFormLinkController extends MenuFormController {

  /**
   * {@inheritdoc}
   */
  protected function buildOverviewFormWithDepth(array &$form, FormStateInterface $form_state, $depth = 1, $menu_link = NULL) {
    $form = array();

    // Create a link to add a menu item.
    $uri = Url::fromRoute('entity.menu.add_link_form', array(
      'menu' => $this->getEntity()->id(),
      'destination' => $this->getEntity()->toUrl('edit-form')->toString(),
    ));

    $form['addlink'] = array(
      '#type' => 'link',
      '#title' => t('Add link'),
      '#url' => $uri,
    );

    array_merge($form, parent::buildOverviewFormWithDepth($form, $form_state, $depth, $menu_link));

    $form['links']['#header'] = array(
      $this->t('Menu link'),
      $this->t('Edit children'),
      array(
        'data' => $this->t('Enabled'),
        'class' => array('checkbox'),
      ),
      $this->t('Weight'),
      array(
        'data' => $this->t('Operations'),
        'colspan' => 3,
      ),
    );

    return $form;
  }

}
