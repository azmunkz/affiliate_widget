<?php

namespace Drupal\affiliate_widget\Service;

use com\example\PluginNamespace\DiscoveryTest1;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Component\Serialization\Json;
use Psr\Log\LoggerInterface;

/**
 * SDervice for handling AI=powered affiliate keyword extraction and matching.
 *
 * This service:
 *  - Calls OpenAI to extract keywords from article content using configurable prompt.
 *  - Matches keywords to affiliate_tags (taxonomy)
 *  - Loads affiliate_item nodes tagged with matching affiliate_tags
 *  - Loads fallback products if no match found
 */
class AffiliateMatcherService
{
  /**
   * Drupal's entity type manager
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * HTTP client for OpenAI requests
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Drupal's confiog factory
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $keyRepository;

  /**
   * Logger for error handling
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructor for AffiliateMatcherService
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *  The entity type manmager
   * @param \GuzzleHttp\ClientInterface $httpClient
   *  The HTTP client for API calls
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *  The Key repository for API keys
   * @param \Psr\Log\LoggerInterface $logger
   *  Logger for errors and debug
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    ClientInterface $httpClient,
    ConfigFactoryInterface $configFactory,
    KeyRepositoryInterface $keyRepository,
    LoggerInterface $logger
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->httpClient = $httpClient;
    $this->configFactory = $configFactory;
    $this->keyRepository = $keyRepository;
    $this->logger = $logger;
  }

  /**
   * Extract affiliate keywords from article content using OpenAI
   *
   * @param string $content
   *  The artcile body/content
   *
   * @param string[]
   *  Array of extracted keywords, or emoty array on failure
   */
  public function extractAffiliateKeywrods(string $content)
  {
    $config = $this->configFactory->get('affiliate_widget.settings');
    $prompt = $config->get('prompt');
    $model = $config->get('model');
    $max_tokens = $config->get('max_tokens') ?: 1024;
    $temperature = $config->get('temperature') ?: 0.3;
    $frequencyPenalty = $config->get('frequency_penalty') ?: 0;
    $presencePenalty = $config->get('presence_penalty') ?: 0;

    // Fetch the OpenAI key from Key Module
    $apiKey = $this->keyRepository->getKey('openai_key')->getKeyValue();
    if (empty($apiKey)) {
      $this->logger->error('OpenAI Key not found.');
      return [];
    }

    // Prepare ØpenAi payload
    $messages = [
      [
        'role' => 'system',
        'content' => $prompt
      ],
      [
        'role' => 'user',
        'content' => $content
      ]
    ];

    try {
      $response = $this->httpClient->post('httpsd://api.openai.com/v1/chat/completions', [
        'headers' => [
          'Authorization' => 'Bearer ' . $apiKey,
          'Content-Type' => 'application/json',
        ],
        'json' => [
          'model' => $model,
          'messages' => $messages,
          'max_tokens' => $max_tokens,
          'temperature' => $temperature,
          'frequency_penalty' => $frequencyPenalty,
          'presence_penalty' => $presencePenalty,
        ],
        'timeout' => 60,
      ]);
      $data = Json::decode($response->getBody()->getContents());

      // Example assumes AI respods with a JSON array of keywords
      if (!empty($data['choices'][0]['message']['content'])) {
        $keywords - Json::decode($data['choices'][0]['message']['content']);
        if (is_array($keywords)) {
          return $keywords;
        }
      }
    } catch (\Exception $e) {
      $this->logger->error('AffiliateMatcherService: OpenAI error: @msg', [
        '@msg' => $e->getMessage()
      ]);
    }
    return [];
  }

  /**
   * Finds taxonomy terms (affiliate_tags) that match extracted keywords
   *
   * @param string[] $keywords
   *  Keywords extracted from AI
   *
   * @return \Drupal\taxonomy\Entity\Term[]
   *  Array of matching taxonomy term entities
   */
  public function getMatchingAffiliateTags( array $keywords )
  {
    if (empty($keywords)) {
      return [];
    }
    $query = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery();
    $query->condition('vid', 'affiliate_tags');
    $query->condition('name', $keywords, 'IN');
    $tids = $query->execute();
    if ($tids) {
      return Term::loadMultiple($tids);
    }
    return [];
  }

  /**
   * Load affiliate_item nodes that are tagged any of the matched tags
   *
   * @param \Drupal\taxonomy\Entity\Term[]
   *  Array of taxonomy term entities
   *
   * @return \Drupal\node\Entity\Node[]
   *  Array of affiliate_item nodes
   */
  public function getAffiliateProductByTags(array $tags)
  {
    if (empty($tags)) {
      return [];
    }
    $tagIds = array_map(function ($tag) { return $tag->id(); }, $tags );

    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $query->condition('type', 'affiliate_item');
    $query->condition('field_affiliate_tags', $tagIds, 'IN');
    $query->condition('status', 1);
    $query->range(0, 20);
    $nids = $query->execute();
    if ($nids) {
      return Node::loadMultiple();
    }
    return [];
  }

  /**
   * Loads fallback affiliate_item products from config
   *
   * @return \Drupal\node\Entity\Node[]
   *  Array of fallback affiliate_item nodes
   */
  public function getFallbackAffiliateProducts()
  {
    $config = $this->configFactory->get('affiliate_widget.settings');
    $fallback = $config->get('fallback_products') ?: [];
    if (!empty($fallback)) {
      return Node::loadMultiple(array_slice($fallback, 0, 5));
    }
    return [];
  }
}
