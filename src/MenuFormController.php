<?php

namespace Drupal\bigmenu;

use Drupal\Core\Url;
use Drupal\menu_ui\MenuForm as DefaultMenuFormController;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Render\Element;

/**
 * Defines class for MenuFormController.
 */
class MenuFormController extends DefaultMenuFormController {

  /**
   * The menu tree.
   *
   * @var array
   */
  protected $tree = array();

  /**
   * Overrides Drupal\menu_ui\MenuForm::buildOverviewForm() to limit the depth.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   The form.
   */
  protected function buildOverviewForm(array &$form, FormStateInterface $form_state) {
    return $this->buildOverviewFormWithDepth($form, $form_state, 1, NULL);
  }

  /**
   * Build a shallow version of the overview form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param int $depth
   *   The depth.
   * @param string $menu_link
   *   The menu link.
   *
   * @return array
   *   The form.
   */
  protected function buildOverviewFormWithDepth(array &$form, FormStateInterface $form_state, $depth = 1, $menu_link = NULL) {
    // Ensure that menu_overview_form_submit() knows the parents of this form
    // section.
    if (!$form_state->has('menu_overview_form_parents')) {
      $form_state->set('menu_overview_form_parents', []);
    }

    // Use Menu UI adminforms.
    $form['#attached']['library'][] = 'menu_ui/drupal.menu_ui.adminforms';

    $form['links'] = array(
      '#type' => 'table',
      '#theme' => 'table__menu_overview',
      '#header' => array(
        $this->t('Menu link'),
        array(
          'data' => $this->t('Enabled'),
          'class' => array('checkbox'),
        ),
        $this->t('Weight'),
        array(
          'data' => $this->t('Operations'),
          'colspan' => 3,
        ),
      ),
      '#attributes' => array(
        'id' => 'menu-overview',
      ),
      '#tabledrag' => array(
        array(
          'action' => 'match',
          'relationship' => 'parent',
          'group' => 'menu-parent',
          'subgroup' => 'menu-parent',
          'source' => 'menu-id',
          'hidden' => TRUE,
          'limit' => \Drupal::menuTree()->maxDepth() - 1,
        ),
        array(
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'menu-weight',
        ),
      ),
    );

    // No Links available (Empty menu)
    $form['links']['#empty'] = $this->t('There are no menu links yet. <a href=":url">Add link</a>.', [
      ':url' => Url::fromRoute(
        'entity.menu.add_link_form',
        ['menu' => $this->entity->id()],
        ['query' => ['destination' => $this->entity->toUrl('edit-form')->toString()]]
      ),
    ]);

    // Get the menu tree if it's not in our property.
    if (empty($this->tree)) {
      $this->tree = $this->getTree($depth);
    }

    // Determine the delta; the number of weights to be made available.
    $count = function (array $tree) {
      $sum = function ($carry, MenuLinkTreeElement $item) {
        return $carry + $item->count();
      };
      return array_reduce($tree, $sum);
    };

    // Tree maximum or 50.
    $delta = max($count($this->tree), 50);

    $links = $this->buildOverviewTreeForm($this->tree, $delta);

    $this->processLinks($form, $links, $menu_link);

    return $form;
  }

  /**
   * Format the links appropriately so draggable views will work.
   *
   * @param array $form
   *   The form array.
   * @param array $links
   *   An array of links.
   * @param string $menu_link
   *   A menu link plugin id.
   */
  public function processLinks(&$form, &$links, $menu_link) {
    foreach (Element::children($links) as $id) {
      if (isset($links[$id]['#item'])) {
        $element = $links[$id];

        $form['links'][$id]['#item'] = $element['#item'];

        // TableDrag: Mark the table row as draggable.
        $form['links'][$id]['#attributes'] = $element['#attributes'];
        $form['links'][$id]['#attributes']['class'][] = 'draggable';

        // TableDrag: Sort the table row according to its existing/configured
        // weight.
        $form['links'][$id]['#weight'] = $element['#item']->link->getWeight();

        // Add special classes to be used for tabledrag.js.
        $element['parent']['#attributes']['class'] = array('menu-parent');
        $element['weight']['#attributes']['class'] = array('menu-weight');
        $element['id']['#attributes']['class'] = array('menu-id');

        $form['links'][$id]['title'] = array(
          array(
            '#theme' => 'indentation',
            '#size' => $element['#item']->depth - 1,
          ),
          $element['title'],
        );

        $form['links'][$id]['root'][] = array();

        if ($form['links'][$id]['#item']->hasChildren) {
          if (is_null($menu_link) || (isset($menu_link) && $menu_link != $element['#item']->link->getPluginId())) {
            $uri = Url::fromRoute('bigmenu.menu_link', array(
              'menu' => $this->entity->id(),
              'menu_link' => $element['#item']->link->getPluginId(),
            ));

            $form['links'][$id]['root'][] = array(
              '#type' => 'link',
              '#title' => t('Edit child items'),
              '#url' => $uri,
            );
          }
        }

        $form['links'][$id]['enabled'] = $element['enabled'];
        $form['links'][$id]['enabled']['#wrapper_attributes']['class'] = array('checkbox', 'menu-enabled');

        $form['links'][$id]['weight'] = $element['weight'];

        // Operations (dropbutton) column.
        $form['links'][$id]['operations'] = $element['operations'];

        $form['links'][$id]['id'] = $element['id'];
        $form['links'][$id]['parent'] = $element['parent'];
      }
    }
  }

  /**
   * Gets the menu tree.
   *
   * @param int $depth
   *   The depth.
   * @param string $root
   *   An optional root menu link plugin id.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   An array of menu link tree elements.
   */
  protected function getTree($depth, $root = NULL) {
    $tree_params = new MenuTreeParameters();
    $tree_params->setMaxDepth($depth);

    if ($root) {
      $tree_params->setRoot($root);
    }

    $tree = $this->menuTree->load($this->entity->id(), $tree_params);

    // We indicate that a menu administrator is running the menu access check.
    $this->getRequest()->attributes->set('_menu_admin', TRUE);
    $manipulators = array(
      array('callable' => 'menu.default_tree_manipulators:checkAccess'),
      array('callable' => 'menu.default_tree_manipulators:generateIndexAndSort'),
    );
    $tree = $this->menuTree->transform($tree, $manipulators);
    $this->getRequest()->attributes->set('_menu_admin', FALSE);

    return $tree;
  }

  /**
   * Append a child's subtree to the parent tree.
   *
   * @param int $depth
   *   The depth.
   * @param string $root
   *   An optional root menu link plugin id.
   */
  protected function appendSubtree($depth, $root) {
    // Clear out the overview tree form which has the old tree info in it.
    $this->overviewTreeForm = array('#tree' => TRUE);

    // Get the slice of the subtree that we're looking for.
    $slice_tree = $this->getTree($depth, $root);

    // Add it to the larger tree we're rendering.
    $root_key = key($slice_tree);
    $this->tree[$root_key]->subtree = &$slice_tree[$root_key]->subtree;
  }

}
