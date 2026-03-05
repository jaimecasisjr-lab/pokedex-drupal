<?php

namespace Drupal\pokemon_search\Drush\Commands;

use Drush\Commands\DrushCommands;

class PokemonSearchCommands extends DrushCommands {

  /**
   * Search for a Pokemon by ID or Name.
   *
   * @command drush search
   * @aliases search
   */
  public function search($keyword) {
    // 1. Use the static \Drupal::entityQuery method directly
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'pokedex')
      ->accessCheck(FALSE);
    // 2. Add search logic
    if (is_numeric($keyword)) {
      $query->condition('field_id', $keyword);
    } else {
      $query->condition('title', $keyword);
    }

    $nids = $query->execute();

    if (empty($nids)) {
      $this->output()->writeln("<error>No Pokemon found for: $keyword</error>");
      return;
    }

    // still load the entitytypeManager
    $node = \Drupal::entityTypeManager()->getStorage('node')->load(reset($nids));
    
    $this->output()->writeln("<info>--- POKEMON DATA ---</info>");
    $this->output()->writeln("Name: " . $node->getTitle());
    $this->output()->writeln("ID: " . $node->get('field_id')->value);
    $this->output()->writeln("Height: " . $node->get('field_height')->value);
    
    $pokemon_types = [];
    foreach ($node->get('field_pokemon_type')->referencedEntities() as $term) {
      $pokemon_types[] = $term->getName();
    }
    $this->output()->writeln("Type: " . implode(', ', $pokemon_types));
    $this->output()->writeln("<info>--------------------</info>");
  }
}