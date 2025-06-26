<?php

namespace Drupal\affiliate_widget\Install;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Service for provisioning and verifying Affiliate Widget dependencies.
 *
 * Handles checks and creation for content type, taxonomy, required modules.
 */
class InstallerService
{

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected  $entityTypeManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs the InstallerService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *  The entity type manager.
   * @param \Drupal\Core\Extension\M<ModuleHandlerInterface $moduleHandler
   *  The module handler
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ModuleHandlerInterface $moduleHandler)
  {
    $this->entityTypeManager = $entityTypeManager;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * Checks if the Key module is enabled.
   *
   * @return bool
   *  TRUE if Key Module is enabled, FALSE otherwise
   */
  public function isKeyModuleEnabled()
  {
    return $this->moduleHandler->moduleExists('key');
  }

  /**
   * Checks if the Affiliate Products content type exists.
   *
   * @return bool
   *  TRUE if the content type exists.
   */
  public function isAffiliateProductTypeExists()
  {
    $bundle = 'affiliate_item';
    /** @var \Drupal\node\NodeTypeInterface|null $type */
    $type = NodeType::load($bundle);
    return (bool) $type;
  }

  /**
   * Checks if Affiliate Product Tags vocabulary exists.
   *
   * @return bool
   *  TRUE if the vocabulary exists.
   */
  public function isAffiliateTagsVocabularyExists()
  {
    $vid = 'affiliate_tags';
    /** @var \Drupal\taxonomy\Entity\Vocabulary|null $vocab */
    $vocab = Vocabulary::load($vid);
    return (bool) $vocab;
  }

  /**
   * Attemps to provision ther Affiliate Product content type and fields.
   *
   * Creates content type and all required fields if missing.
   *
   * @return bool
   *  TRUE if created or already exists.
   */
  public function provisionAffiliateProductType()
  {
    $bundle = 'affiliate_item';
    if ($this->isAffiliateProductTypeExists()) {
      return TRUE;
    }
    // Create the content type
    $type = NodeType::create([
      'type' => $bundle,
      'name' => 'Affiliate Product',
      'description' => 'Product for affiliate widget system.',
    ]);
    $type->save();

    // Add fields (image, description, link, tags)
    $this->addField($bundle, 'field_affiliate_image', 'image', 'Affiliate Image');
    $this->addField($bundle, 'field_affiliate_description', 'text_long', 'Affiliate Description');
    $this->addField($bundle, 'field_affiliate_link', 'link', 'Affiliate Link');
    $this->addField($bundle, 'field_affiliate_tags', 'entity_reference', 'Affiliate Tags', [
      'target_type' => 'taxonomy_term',
      'handler' => 'default',
      'handler_settings' => [
        'target_bundles' => ['affiliate_tags' => 'affiliate_tags'],
      ],
    ]);
    return TRUE;
  }

  /**
   * Attempts to provision the Affiliate Product Tags vocabulary
   *
   * @return bool
   *  TRUE if created or already exists.
   */
  public function provisionAffiliateTagsVocabulary()
  {
    $vid = 'affiliate_tags';
    if ($this->isAffiliateTagsVocabularyExists()) {
      return TRUE;
    }
    $vocab = Vocabulary::create([
      'vid' => $vid,
      'description' => 'Tags for Affiliate Products',
      'name' => 'Affiliate Product Tags',
    ]);
    $vocab->save();
    return TRUE;
  }

  /**
   * Helper method to create a field for a content type.
   *
   * @param string $bundle
   *  The content type machine name
   * @param string $field_name
   *  The field machine name
   * @param string $type
   *  The field type machine name
   * @param string $label
   *  Thge field label
   * @param array $settings
   *  (optional) Additional field settings
   */
  protected function addField($bundle, string $field_name, string $type, string $label, $settings = [])
  {
    // Create field storage if not exists
    if (!FieldStorageConfig::loadByName('node', $field_name)) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => $type,
        'settings' => $settings,
      ])->save();
    }

    // Create field config if not exists
    if (!FieldConfig::loadByName('node', $bundle, $field_name)) {
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => $type,
        'bundle' => $bundle,
        'label' => $label,
        'settings' => $settings
      ])->save();
    }
  }
}
