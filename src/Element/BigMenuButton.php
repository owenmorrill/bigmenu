<?php

namespace Drupal\bigmenu\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElement;

/**
 * Provides an action big_menu_button form element.
 *
 * When the big_menu_button is pressed, the form will be submitted to Drupal,
 * where it is validated and rebuilt. The submit handler is not invoked.
 *
 * Properties:
 * - #limit_validation_errors: An array of form element keys that will block
 *   form submission when validation for these elements or any child elements
 *   fails. Specify an empty array to suppress all form validation errors.
 * - #value: The text to be shown on the big_menu_button.
 *
 *
 * Usage Example:
 * @code
 * $form['actions']['preview'] = array(
 *   '#type' => 'big_menu_button',
 *   '#value' => $this->t('Preview'),
 * );
 * @endcode
 *
 * @see \Drupal\Core\Render\Element\Submit
 *
 * @FormElement("big_menu_button")
 */
class BigMenuButton extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#name' => 'op',
      '#is_button' => TRUE,
      '#executes_submit_callback' => FALSE,
      '#limit_validation_errors' => FALSE,
      '#process' => [
        [$class, 'processBigMenuButton'],
        [$class, 'processAjaxForm'],
      ],
      '#pre_render' => [
        [$class, 'preRenderBigMenuButton'],
      ],
      '#theme_wrappers' => ['input__submit'],
    ];
  }

  /**
   * Processes a form big_menu_button element.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   *
   * @return array
   *   The processed element.
   */
  public static function processBigMenuButton(array &$element, FormStateInterface $form_state, array &$complete_form) {
    // If this is a big_menu_button intentionally allowing incomplete form
    // submission (e.g., a "Previous" or "Add another item" button), then also
    // skip client-side validation.
    if (isset($element['#limit_validation_errors']) && $element['#limit_validation_errors'] !== FALSE) {
      $element['#attributes']['formnovalidate'] = 'formnovalidate';
    }
    return $element;
  }

  /**
   * Prepares a #type 'big_menu_button' render element for input.html.twig.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   *   Properties used: #attributes, #button_type, #name, #value.
   *   The #button_type property accepts any value, though core themes have CSS
   *   that styles the following button_types appropriately 'primary', 'danger'.
   *
   * @return array
   *   The $element with prepared variables ready for input.html.twig.
   */
  public static function preRenderBigMenuButton(array $element) {
    $element['#attributes']['type'] = 'submit';
    Element::setAttributes($element, ['id', 'name', 'value']);

    $element['#attributes']['class'][] = 'button';
    if (!empty($element['#button_type'])) {
      $element['#attributes']['class'][] = 'button--' . $element['#button_type'];
    }
    $element['#attributes']['class'][] = 'js-form-submit';
    $element['#attributes']['class'][] = 'form-submit';
    $element['#attributes']['class'][] = 'bigmenu-childindicator';

    if (!empty($element['#attributes']['disabled'])) {
      $element['#attributes']['class'][] = 'is-disabled';
    }

    return $element;
  }

}
