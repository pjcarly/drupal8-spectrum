<?php

namespace Drupal\spectrum\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Core\Command\ContainerAwareCommand;
use Drupal\Console\Annotations\DrupalCommand;
use Drupal\spectrum\Model\ModelServiceInterface;
use Symfony\Component\Console\Input\InputArgument;
use Drupal\Core\Database\Connection;

/**
 * Class GenerateEntitySQLViewCommand.
 *
 * @DrupalCommand (
 *     extension="spectrum",
 *     extensionType="module"
 * )
 */
class GenerateEntitySQLViewCommand extends ContainerAwareCommand
{
  /**
   * @var ModelServiceInterface
   */
  protected $modelService;

  /**
   * @var Connection
   */
  protected $db;

  public function __construct(ModelServiceInterface $modelService, Connection $db)
  {
    parent::__construct();
    $this->modelService = $modelService;
    $this->db = $db;
  }

  /**
   * {@inheritdoc}
   */
  protected function configure()
  {
    $this
      ->setName('spectrum:sql-view:generate')
      ->setAliases(['sp:vsql'])
      ->setDescription('Generates SQL views for all bundles of passed in entity')
      ->addArgument('entity', InputArgument::REQUIRED, 'Entity');
  }

  /**
   * {@inheritdoc}
   */
  protected function interact(InputInterface $input, OutputInterface $output)
  {
    if (empty($input->getArgument('entity'))) {

      // Lets get all the different entities
      $entities = [];
      $modelClasses = $this->modelService->getRegisteredModelClasses();
      foreach ($modelClasses as $modelClass) {
        $entities[] = $modelClass::entityType();
      }

      $entities = array_unique($entities);
      asort($entities);
      $entities = array_values($entities);

      $io = $this->getIo();
      $input->setArgument('entity', $io->choice('Which Entity type would you like to generate views for?', $entities));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $entityType = $input->getArgument('entity');

    /** @var Model[] $modelClasses */
    $modelClasses = $this->modelService->getRegisteredModelClasses();
    foreach ($modelClasses as $modelClass) {
      if ($modelClass::entityType() === $entityType) {
        $query = $modelClass::getViewSelectQuery();

        if (!empty($query)) {
          $viewName = empty($modelClass::bundle()) ? $modelClass::entityType() : $modelClass::bundle();
          $this->db->query('DROP VIEW IF EXISTS `view_' . $viewName . '`');
          $this->db->query("CREATE VIEW `view_" . $viewName . "` AS " . $query);
        }
      }
    }

    $this->getIo()->success('Views succesfully generated');
  }
}
