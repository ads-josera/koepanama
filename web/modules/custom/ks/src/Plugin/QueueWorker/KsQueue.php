<?php

namespace Drupal\ks\Plugin\QueueWorker;

use Drupal\Core\Annotation\QueueWorker;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal;

/**
* Ks Queue Worker.
*
* @QueueWorker(
*   id = "ks_queue",
*   title = @Translation("Koe Queue"),
*   cron = {"time" = 30}
* )
*/
final class KsQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  
  /**
  * Main constructor.
  *
  * @param array $configuration
  *   Configuration array.
  * @param mixed $plugin_id
  *   The plugin id.
  * @param mixed $plugin_definition
  *   The plugin definition.
  * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
  *   The entity type manager.
  * @param \Drupal\Core\Database\Connection $database
  *   The connection to the database.
  */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }
  
  /**
  * Used to grab functionality from the container.
  *
  * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
  *   The container.
  * @param array $configuration
  *   Configuration array.
  * @param mixed $plugin_id
  *   The plugin id.
  * @param mixed $plugin_definition
  *   The plugin definition.
  *
  * @return static
  */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }
  
  /**
  * Processes an item in the queue.
  *
  * @param mixed $data
  *   The queue item data.
  *
  * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
  * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
  * @throws \Drupal\Core\Entity\EntityStorageException
  * @throws \Exception
  */
  public function processItem($item) {
    $path = $item->path;    
    @unlink($path);
  }

}