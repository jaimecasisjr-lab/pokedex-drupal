<?php

namespace Drupal\pokemon_harvest\Drush\Commands;

use Drush\Commands\DrushCommands;
use Drupal\pokemon_harvest\PokemonImporter;

/**
 * Drush commands for Pokemon Harvest.
 */
class PokemonHarvestCommands extends DrushCommands {

  /**
   * @var \Drupal\pokemon_harvest\PokemonImporter
   */
  protected $importer;

  /**
   * Constructs the command object.
   */
  public function __construct(PokemonImporter $importer) {
    parent::__construct();
    $this->importer = $importer;
  }

  /**
   * Imports the original 151 Pokemon.
   *
   * @command pokemon:harvest
   * @usage ddev drush pokemon:harvest
   */
  public function harvest() {
    $this->output()->writeln('Gotta catch em all! Starting harvest...');
    $count = $this->importer->harvestGen1();
    $this->output()->writeln("Successfully imported " . $count . " Pokemon!");
  }
}