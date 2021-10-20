<?php

namespace Drupal\islandora_advanced_search\Form;


use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use \Drupal\Core\Url;
use Drupal\islandora_advanced_search\Plugin\Block\AdvancedSearchBlock;

class SearchForm  extends FormBase
{
  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $block = \Drupal\block\Entity\Block::load("search");

    if ($block) {
      $settings = $block->get('settings');

      $form['targeted_advanced_search_id'] = array(
        '#type' => 'hidden',
        '#value' => $settings['search_view_machine_name'],
      );
    }

    $form['search-textfield'] = array(
      '#type' => 'textfield',
      '#title' => (!empty($settings['search_textfield_label']) ? $settings['search_textfield_label'] : ''),
      '#attributes' => ['placeholder' => $settings['search_placeholder']]
    );

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => (!empty($settings['search_submit_label']) ? $settings['search_submit_label'] : 'Search'),
      '#button_type' => 'primary',
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    // original params
    $params = [
      'a[0][f]' => 'all',
      'a[0][i]' => 'IS',
      'a[0][v]' => $form_state->getValues()['search-textfield'],
    ];

    if (!preg_match("/ /", $form_state->getValues()['search-textfield'])) {
      $block_machine_name = $this->getBlockDef($form_state->getValues()['targeted_advanced_search_id']);
      $blocks = (array)\Drupal::entityTypeManager()->getStorage('block')
        ->loadByProperties(['plugin' => $block_machine_name]);
      $foundBlockids = array_keys($blocks);
      $search_fields = [];
      foreach($foundBlockids as $bid) {
        $block = \Drupal\block\Entity\Block::load($bid);
        if (isset($block)) {
          $settigns = $block->get("settings");
          $search_fields = $settigns[AdvancedSearchBlock::SETTING_FIELDS];
          break;
        }
      }
      $i = 1;
      foreach ($search_fields as $sf) {
        if  (strpos($form_state->getValues()['search-textfield'], "*") !== false
          || strpos($form_state->getValues()['search-textfield'], "?") !== false) {
          $params['a['.$i.'][c]'] = 'OR';
          $params['a['.$i.'][f]'] = $sf;
          $params['a['.$i.'][i]'] = "IS";
          $params['a['.$i.'][v]'] = $form_state->getValues()['search-textfield'];
        }
        else {
          $params['a['.$i.'][c]'] = 'OR';
          $params['a['.$i.'][f]'] = $sf;
          $params['a['.$i.'][i]'] = "IS";
          $params['a['.$i.'][v]'] = "*". $form_state->getValues()['search-textfield']. "*";
        }
        $i++;
      }

    }

    $block = \Drupal\block\Entity\Block::load("search");
    if ($block) {
      $settings = $block->get('settings');
      $view_machine_name = $settings['search_view_machine_name'];
    }
    $url = Url::fromRoute($view_machine_name, $params);
    $form_state->setRedirectUrl($url);
  }

  /**
   *
   */
  public function getBlockDef($targeted_view) {
    // convert targeted advanced search view machine name
    $plugin_id = str_replace("view.", "",$targeted_view);
    $plugin_id = str_replace(".", "__",$plugin_id);

    // through associate context, search Advanced Search Form block
    $blockManager = \Drupal::service('plugin.manager.block');
    $contextRepository = \Drupal::service('context.repository');
    $contexts = $blockManager->getDefinitionsForContexts($contextRepository->getAvailableContexts());
    $plugins = array_keys($contexts);
    $found = "";
    foreach ($plugins as $plugin) {
      if (strpos($plugin, $plugin_id) !== false) {
        $found = $plugin;
        break;
      }
    }
    return $found;
  }

}
