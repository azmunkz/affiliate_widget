<?php

namespace Drupal\affiliate_widget\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\affiliate_widget\Install\InstallerService;

/**
 * Class SettingsForm.
 *
 * Configuration form for Affiliate Widget module, including AI, display,
 * and fallback product settings. Displays admin warnings if required
 * dependencies or content model are missing.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The installer service for dependency and content model checks.
   *
   * @var \Drupal\affiliate_widget\Install\InstallerService
   */
  protected $installerService;

  /**
   * Messenger service for user feedback.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   * Constructor injects services for installer and messaging.
   */
  public function __construct($config_factory, InstallerService $installerService, MessengerInterface $messenger) {
    parent::__construct($config_factory);
    $this->installerService = $installerService;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   * Dependency injection for InstallerService and Messenger.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('affiliate_widget.installer_service'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   * Returns config name for editable settings.
   */
  protected function getEditableConfigNames() {
    return ['affiliate_widget.settings'];
  }

  /**
   * {@inheritdoc}
   * Build form fields and admin status checks.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('affiliate_widget.settings');

    // 1. Key module check (dependency).
    if (!$this->installerService->isKeyModuleEnabled()) {
      $this->messenger->addWarning($this->t('The <strong>Key module</strong> is not enabled. Please install and enable the Key module to securely manage your OpenAI API key. <a href=":url" target="_blank">Get Key module</a>.', [
        ':url' => 'https://www.drupal.org/project/key'
      ]));
    }

    // 2. Affiliate Product content type check.
    if (!$this->installerService->isAffiliateProductTypeExists()) {
      $this->messenger->addWarning($this->t('The content type <strong>Affiliate Product</strong> (<code>affiliate_item</code>) does not exist. Please run the Affiliate Widget installer or create it manually.'));
    }

    // 3. Affiliate Product Tags vocabulary check.
    if (!$this->installerService->isAffiliateTagsVocabularyExists()) {
      $this->messenger->addWarning($this->t('The taxonomy <strong>Affiliate Product Tags</strong> (<code>affiliate_tags</code>) does not exist. Please run the Affiliate Widget installer or create it manually.'));
    }

    // (Form fields follow – as before. Only shown if everything exists.)
    // Disable form if required entities/modules missing.
    $form_disabled = (
      !$this->installerService->isKeyModuleEnabled() ||
      !$this->installerService->isAffiliateProductTypeExists() ||
      !$this->installerService->isAffiliateTagsVocabularyExists()
    );

    $form['#disabled'] = $form_disabled;

    $form['model'] = [
      '#type' => 'select',
      '#title' => $this->t('OpenAI Model'),
      '#options' => [
        'gpt-4.1' => 'GPT-4.1',
        'gpt-4' => 'GPT-4',
      ],
      '#default_value' => $config->get('model') ?: 'gpt-4.1',
      '#description' => $this->t('Select which OpenAI model to use.'),
      '#required' => TRUE,
    ];

    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('AI Prompt'),
      '#default_value' => $config->get('prompt') ?: 'You are an expert in affiliate marketing. Analyze the text below and return a clean list of 5-7 relevant keywords that match shopping or affiliate product intent.',
      '#description' => $this->t('Edit the AI prompt used for keyword extraction.'),
      '#required' => TRUE,
    ];

    $form['max_tokens'] = [
      '#type' => 'number',
      '#title' => $this->t('Max Tokens'),
      '#default_value' => $config->get('max_tokens') ?: 256,
      '#min' => 1,
      '#max' => 4096,
      '#description' => $this->t('Maximum tokens for OpenAI response.'),
    ];

    $form['temperature'] = [
      '#type' => 'number',
      '#title' => $this->t('Temperature'),
      '#default_value' => $config->get('temperature') ?: 0.3,
      '#min' => 0,
      '#max' => 2,
      '#step' => 0.01,
      '#description' => $this->t('Sampling temperature (higher = more random). Recommended: 0.1 - 0.5.'),
    ];

    $form['frequency_penalty'] = [
      '#type' => 'number',
      '#title' => $this->t('Frequency Penalty'),
      '#default_value' => $config->get('frequency_penalty') ?: 0,
      '#min' => 0,
      '#max' => 2,
      '#step' => 0.01,
      '#description' => $this->t('Discourage repetition of same lines.'),
    ];

    $form['presence_penalty'] = [
      '#type' => 'number',
      '#title' => $this->t('Presence Penalty'),
      '#default_value' => $config->get('presence_penalty') ?: 0,
      '#min' => 0,
      '#max' => 2,
      '#step' => 0.01,
      '#description' => $this->t('Encourage/discourage new topics.'),
    ];

    $form['slider_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Slider Display Settings'),
      '#open' => TRUE,
    ];
    $form['slider_settings']['items_desktop'] = [
      '#type' => 'number',
      '#title' => $this->t('Items per view (Desktop)'),
      '#default_value' => $config->get('items_desktop') ?: 4,
      '#min' => 1,
      '#max' => 10,
      '#step' => 1,
    ];
    $form['slider_settings']['items_tablet'] = [
      '#type' => 'number',
      '#title' => $this->t('Items per view (Tablet)'),
      '#default_value' => $config->get('items_tablet') ?: 2,
      '#min' => 1,
      '#max' => 6,
      '#step' => 1,
    ];
    $form['slider_settings']['items_mobile'] = [
      '#type' => 'number',
      '#title' => $this->t('Items per view (Mobile)'),
      '#default_value' => $config->get('items_mobile') ?: 1,
      '#min' => 1,
      '#max' => 3,
      '#step' => 1,
    ];

    // Fallback Affiliate Products (entity autocomplete, max 5)
    $form['fallback_products'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Fallback Affiliate Products'),
      '#description' => $this->t('Select up to 5 affiliate products for fallback.'),
      '#target_type' => 'node',
      '#tags' => TRUE,
      '#selection_settings' => ['target_bundles' => ['affiliate_item']],
      '#default_value' => !empty($config->get('fallback_products'))
        ? Node::loadMultiple($config->get('fallback_products'))
        : [],
      '#maxlength' => 5,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   * Handles form submission and saves settings to config.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('affiliate_widget.settings')
      ->set('model', $form_state->getValue('model'))
      ->set('prompt', $form_state->getValue('prompt'))
      ->set('max_tokens', $form_state->getValue('max_tokens'))
      ->set('temperature', $form_state->getValue('temperature'))
      ->set('frequency_penalty', $form_state->getValue('frequency_penalty'))
      ->set('presence_penalty', $form_state->getValue('presence_penalty'))
      ->set('items_desktop', $form_state->getValue(['slider_settings', 'items_desktop']))
      ->set('items_tablet', $form_state->getValue(['slider_settings', 'items_tablet']))
      ->set('items_mobile', $form_state->getValue(['slider_settings', 'items_mobile']))
      ->set('fallback_products', array_slice(array_filter($form_state->getValue('fallback_products')), 0, 5))
      ->save();
  }
}
